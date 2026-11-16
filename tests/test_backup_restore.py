"""Backup 1.0 regression tests; only temporary data, never production services.

Run: python3 -m unittest discover -s tests -p 'test_backup_restore.py' -v
Requires PHP CLI with ZipArchive, PDO SQLite and OpenSSL.
"""
import contextlib
import datetime as dt
import io
import json
import os
from pathlib import Path
import shutil
import sqlite3
import subprocess
import sys
import tempfile
import types
import unittest
from unittest.mock import patch
import warnings
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / 'kienzlezeit-backup-und-restore.sh'
CODE = SCRIPT.read_text().split("<<'PYTHON'\n", 1)[1].rsplit('\nPYTHON', 1)[0]


def module():
    m = types.ModuleType('kz_backup_tests')
    with patch.object(sys, 'argv', ['python', str(SCRIPT)]):
        exec(compile(CODE, str(SCRIPT), 'exec'), m.__dict__)
    return m


class LocalRemote:
    """File-operation adapter; deliberately rejects rename-overwrite."""
    def __init__(self, root):
        self.root = root
        self.deleted = []

    def ensure_dirs(self):
        for area in ('daily', 'weekly'):
            (self.root / area).mkdir(parents=True, exist_ok=True)

    def list(self, area=''):
        return sorted(p.name for p in (self.root / area).iterdir())

    def get(self, remote, local):
        shutil.copyfile(self.root / remote, local)

    def put(self, local, remote):
        shutil.copyfile(local, self.root / remote)

    def rename(self, old, new):
        if (self.root / new).exists():
            raise RuntimeError('Destination already exists')
        (self.root / old).rename(self.root / new)

    def delete(self, remote):
        self.deleted.append(remote)
        (self.root / remote).unlink()


class BackupTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.fixture = tempfile.TemporaryDirectory(prefix='kz-fixture-')
        folder = Path(cls.fixture.name)
        (folder / 'terminal-secret.key').write_text('ab' * 32 + '\n')
        env = {**os.environ, 'KZ_DB_PATH': str(folder / 'kienzlezeit.sqlite'),
               'KZ_SECRET_KEY_PATH': str(folder / 'terminal-secret.key'),
               'AUSLAGEN_DB_PATH': str(folder / 'auslagen.sqlite')}
        payload = {'admin_username': 'testadmin', 'admin_password': 'not-real-test-only-password',
                   'terminal_key': 'test-only-key', 'terminal_code': 'test-terminal'}
        subprocess.run(['php', str(ROOT / 'kienzlezeit.php'), '--install'], input=json.dumps(payload),
                       text=True, capture_output=True, env=env, check=True)
        subprocess.run(['php', str(ROOT / 'auslagen/auslagen.php'), '--migrate'],
                       text=True, capture_output=True, env=env, check=True)

    @classmethod
    def tearDownClass(cls):
        cls.fixture.cleanup()

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='kz-backup-test-')
        self.addCleanup(self.tmp.cleanup)
        self.base = Path(self.tmp.name)
        self.m = module()
        for key, leaf in (('DATA', 'data'), ('STATE', 'state'), ('WEB', 'web'), ('CONFIG_DIR', 'config')):
            setattr(self.m, key, self.base / leaf)
            getattr(self.m, key).mkdir()
        self.m.CONFIG = self.m.CONFIG_DIR / 'config.json'
        self.m.CRON = self.base / 'cron'
        self.m.INSTALLED = self.base / 'installed.sh'
        self.m.MAINTENANCE = self.base / 'maintenance.conf'
        self.m.SESSION_CONF = self.base / 'session.conf'
        for file in Path(self.fixture.name).iterdir():
            shutil.copyfile(file, self.m.DATA / file.name)
        shutil.copyfile(ROOT / 'kienzlezeit.php', self.m.WEB / 'kienzlezeit.php')
        self.config = {'installation_id': 'a' * 16, 'archive_password': None, 'transport': 'ssh',
                       'host': 'backup.invalid', 'username': 'test', 'directory': '/backup', 'port': 22,
                       'identity': '/dummy/key', 'known_hosts': '/dummy/known', 'enabled': False, 'time': '02:00'}
        self.out = self.base / 'output'
        self.out.mkdir()
        self.remote = LocalRemote(self.base / 'remote')
        self.remote.ensure_dirs()

    def archive(self, password=None):
        return self.m.create_archive({**self.config, 'archive_password': password}, self.out)

    def unpack(self, path, password=None, compatible=True):
        folder = Path(tempfile.mkdtemp(dir=self.base))
        result = self.m.unpack_checked(path, folder, password, compatible)
        return folder, result

    def name(self, day, instance='a' * 16, suffix='0' * 12):
        return f'kienzlezeit_test_{day}T010000Z_v1.6.7_{instance}_{suffix}.zip'

    def put_fake(self, area, name):
        path = self.remote.root / area / name
        path.write_bytes(b'test archive ' + name.encode())
        path.with_suffix('.zip.sha256').write_text(self.m.sha256(path) + '\n')

    def mutate(self, path, updater):
        other = self.out / ('changed-' + str(len(list(self.out.iterdir()))) + '.zip')
        with zipfile.ZipFile(path) as source, zipfile.ZipFile(other, 'w') as target:
            contents = {n: source.read(n) for n in source.namelist()}
            updater(contents)
            for name, content in contents.items():
                target.writestr(name, content)
        return other

    def test_plain_roundtrip(self):
        archive = self.archive()
        folder, manifest = self.unpack(archive)
        self.assertEqual(manifest['format'], 1)
        self.assertEqual(manifest['installation_id'], self.config['installation_id'])
        self.assertEqual((folder / self.m.KEY_NAME).read_bytes(), (self.m.DATA / self.m.KEY_NAME).read_bytes())
        self.assertEqual(set(manifest['files']), set(self.m.DB_NAMES) | {self.m.KEY_NAME})

    def test_encrypted_roundtrip_and_wrong_password(self):
        password = 'Ein langes Testpasswort mit ! $ ü und Leerzeichen'
        archive = self.archive(password)
        self.assertTrue(self.m.archive_members(archive)[1])
        with zipfile.ZipFile(archive) as z:
            self.assertTrue(all(i.flag_bits & 1 for i in z.infolist()))
            self.assertTrue(all(b'\x01\x99' in i.extra for i in z.infolist()))
        self.unpack(archive, password)
        with self.assertRaises(self.m.BackupError):
            self.unpack(archive, 'wrong')
        with self.assertRaises(self.m.BackupError):
            self.unpack(archive)

    def test_password_not_in_process_arguments(self):
        password = 'secret-that-must-not-appear-in-argv'
        original = self.m.run
        calls = []
        def spy(args, **kwargs):
            calls.append(args)
            return original(args, **kwargs)
        with patch.object(self.m, 'run', side_effect=spy):
            self.archive(password)
        self.assertTrue(calls)
        self.assertTrue(all(password not in str(args) for args in calls))

    def test_missing_expenses_is_optional(self):
        (self.m.DATA / 'auslagen.sqlite').unlink()
        archive = self.archive()
        folder, manifest = self.unpack(archive)
        self.assertNotIn('auslagen.sqlite', manifest['files'])

    def test_missing_main_database_rejected(self):
        (self.m.DATA / 'kienzlezeit.sqlite').unlink()
        with self.assertRaises(self.m.BackupError):
            self.archive()

    def test_wal_contains_latest_committed_row(self):
        with contextlib.closing(sqlite3.connect(self.m.DATA / 'kienzlezeit.sqlite')) as conn:
            conn.execute('PRAGMA journal_mode=WAL')
            conn.execute('PRAGMA wal_autocheckpoint=0')
            conn.execute("UPDATE admin_users SET display_name='Latest WAL commit' WHERE id=1")
            conn.commit()
            self.assertTrue((self.m.DATA / 'kienzlezeit.sqlite-wal').exists())
            folder, unused = self.unpack(self.archive())
            with contextlib.closing(sqlite3.connect(folder / 'kienzlezeit.sqlite')) as restored:
                self.assertEqual(restored.execute('SELECT display_name FROM admin_users WHERE id=1').fetchone()[0], 'Latest WAL commit')

    def test_rejects_bad_hash(self):
        archive = self.mutate(self.archive(), lambda contents: contents.update({'terminal-secret.key': b'cd' * 32}))
        with self.assertRaisesRegex(self.m.BackupError, 'Pruefsummenfehler'):
            self.unpack(archive)

    def test_rejects_wrong_key_even_with_valid_hash(self):
        def update(contents):
            import hashlib
            contents['terminal-secret.key'] = b'cd' * 32 + b'\n'
            manifest = json.loads(contents['manifest.json'])
            manifest['files']['terminal-secret.key']['sha256'] = hashlib.sha256(contents['terminal-secret.key']).hexdigest()
            contents['manifest.json'] = json.dumps(manifest).encode()
        with self.assertRaises(self.m.BackupError):
            self.unpack(self.mutate(self.archive(), update))

    def test_rejects_future_version(self):
        def update(contents):
            manifest = json.loads(contents['manifest.json'])
            manifest['app_version'] = '99.0'
            contents['manifest.json'] = json.dumps(manifest).encode()
        with self.assertRaisesRegex(self.m.BackupError, 'passt nicht'):
            self.unpack(self.mutate(self.archive(), update))

    def test_rejects_wrong_manifest_file_list(self):
        def update(contents):
            manifest = json.loads(contents['manifest.json'])
            del manifest['files']['terminal-secret.key']
            contents['manifest.json'] = json.dumps(manifest).encode()
        with self.assertRaises(self.m.BackupError):
            self.unpack(self.mutate(self.archive(), update))

    def test_rejects_traversal_extra_and_duplicate(self):
        original = self.archive()
        for name in ('../escape', '/tmp/escape', 'extra.txt'):
            archive = self.mutate(original, lambda c: c.update({name: b'bad'}))
            with self.assertRaises(self.m.BackupError):
                self.m.archive_members(archive)
        duplicate = self.out / 'duplicate.zip'
        shutil.copyfile(original, duplicate)
        with warnings.catch_warnings():
            warnings.simplefilter('ignore')
            with zipfile.ZipFile(duplicate, 'a') as z:
                z.writestr('manifest.json', '{}')
        with self.assertRaises(self.m.BackupError):
            self.m.archive_members(duplicate)

    def test_rejects_symlink(self):
        archive = self.out / 'symlink.zip'
        with zipfile.ZipFile(archive, 'w') as z:
            for name in ('manifest.json', 'kienzlezeit.sqlite'):
                z.writestr(name, '{}')
            info = zipfile.ZipInfo('terminal-secret.key')
            info.create_system = 3
            info.external_attr = (0o120777 << 16)
            z.writestr(info, '/etc/passwd')
        with self.assertRaises(self.m.BackupError):
            self.m.archive_members(archive)

    def test_retention_keeps_30_days_and_permanent_weeks(self):
        at = dt.datetime(2026, 11, 16, 12, tzinfo=dt.timezone.utc)
        names = [self.name((at.date() - dt.timedelta(days=i)).strftime('%Y%m%d')) for i in range(75)]
        promote, remove = self.m.retention_plan(names, [], 'a' * 16, at)
        self.assertFalse(set(promote) & set(remove))
        for n in names:
            age = (at.date() - self.m.berlin(self.m.archive_date(n)).date()).days
            if age < 30:
                self.assertNotIn(n, remove)
        self.assertTrue(any((at.date() - self.m.archive_date(n).date()).days == 30 for n in remove))
        self.assertEqual(len(promote), len({self.m.berlin(self.m.archive_date(n)).date().isocalendar()[:2] for n in promote}))
        for n in promote:
            self.assertNotIn(n, remove)

    def test_retention_ignores_other_installations(self):
        foreign = self.name('20200101', 'b' * 16)
        promote, remove = self.m.retention_plan([foreign], [], 'a' * 16)
        self.assertEqual((promote, remove), ([], []))

    def test_retention_preserves_existing_week_across_year(self):
        old = self.name('20251229')
        later_same_week = self.name('20260104')
        promote, remove = self.m.retention_plan([later_same_week], [old], 'a' * 16, dt.datetime(2026, 3, 1, tzinfo=dt.timezone.utc))
        self.assertEqual(promote, [])
        self.assertEqual(remove, [later_same_week])

    def test_retention_no_weekly_deletions_and_validates_remote(self):
        old, latest = self.name('20260105'), self.name('20260111')
        self.put_fake('daily', old)
        self.put_fake('daily', latest)
        self.m.retain(self.remote, self.config)
        self.assertTrue((self.remote.root / 'weekly' / latest).exists())
        self.assertFalse((self.remote.root / 'daily' / old).exists())
        self.assertTrue(all(n.startswith('daily/') for n in self.remote.deleted))
        self.put_fake('daily', old)
        (self.remote.root / 'weekly' / latest).write_bytes(b'corrupted')
        with self.assertRaises(self.m.BackupError):
            self.m.retain(self.remote, self.config)
        self.assertTrue((self.remote.root / 'daily' / old).exists())

    def test_recovers_interrupted_weekly_promotion(self):
        name = self.name('20260111')
        self.put_fake('daily', name)
        self.remote.rename('daily/' + name, 'weekly/' + name)
        self.m.retain(self.remote, self.config)
        self.assertTrue((self.remote.root / 'weekly' / (name + '.sha256')).exists())

    def test_upload_roundtrip_and_interrupted_retry(self):
        outbox = self.m.STATE / 'outbox' / self.m.target_id(self.config)
        outbox.mkdir(parents=True)
        archive = self.m.create_archive(self.config, outbox)
        marker = archive.with_suffix('.zip.sha256')
        # Simulate full remote publication followed by interrupted local cleanup.
        self.remote.put(archive, 'daily/' + archive.name)
        self.remote.put(marker, 'daily/' + marker.name)
        with contextlib.redirect_stdout(io.StringIO()):
            self.m.upload_pending(self.remote, self.config)
        self.assertFalse(archive.exists())
        self.assertTrue((self.remote.root / 'daily' / archive.name).exists())

    def test_failed_upload_keeps_local_copy(self):
        outbox = self.m.STATE / 'outbox' / self.m.target_id(self.config)
        outbox.mkdir(parents=True)
        archive = self.m.create_archive(self.config, outbox)
        with patch.object(self.remote, 'put', side_effect=self.m.BackupError('offline')):
            with self.assertRaises(self.m.BackupError):
                self.m.upload_pending(self.remote, self.config)
        self.assertTrue(archive.exists())

    def test_incomplete_and_foreign_remote_files_not_deleted(self):
        orphan = self.name('20260101')
        (self.remote.root / 'daily' / orphan).write_bytes(b'uncommitted')
        foreign = self.name('20260102', 'b' * 16)
        self.put_fake('daily', foreign)
        self.m.retain(self.remote, self.config)
        self.assertTrue((self.remote.root / 'daily' / orphan).exists())
        self.assertTrue((self.remote.root / 'daily' / foreign).exists())

    def test_cron_before_time_success_and_hourly_retry(self):
        config = {**self.config, 'enabled': True}
        at = dt.datetime(2026, 11, 16, 1, 0, tzinfo=dt.timezone.utc)  # 02:00 Berlin
        self.assertTrue(self.m.cron_due(config, {}, at))
        self.assertFalse(self.m.cron_due(config, {}, at - dt.timedelta(minutes=1)))
        self.assertFalse(self.m.cron_due(config, {'last_attempt': at.isoformat()}, at + dt.timedelta(minutes=59)))
        self.assertTrue(self.m.cron_due(config, {'last_attempt': at.isoformat()}, at + dt.timedelta(hours=1)))
        self.assertFalse(self.m.cron_due(config, {'last_success': at.isoformat(), 'snapshot_day': '2026-11-16'}, at + dt.timedelta(hours=3)))
        self.assertFalse(self.m.cron_due(self.config, {}, at))

    def test_cron_dst_fallback_runs_once(self):
        config = {**self.config, 'enabled': True}
        first = dt.datetime(2026, 10, 25, 0, 30, tzinfo=dt.timezone.utc)
        second = first + dt.timedelta(hours=1)
        self.assertTrue(self.m.cron_due(config, {}, first))
        self.assertFalse(self.m.cron_due(config, {'last_success': first.isoformat(), 'snapshot_day': '2026-10-25'}, second))

    def test_cron_spring_missing_hour_catches_up(self):
        at = dt.datetime(2026, 3, 29, 1, 0, tzinfo=dt.timezone.utc)
        self.assertTrue(self.m.cron_due({**self.config, 'enabled': True, 'time': '02:30'}, {}, at))

    def test_failed_previous_day_retries_before_daily_time(self):
        at = dt.datetime(2026, 11, 16, 0, 0, tzinfo=dt.timezone.utc)
        config = {**self.config, 'enabled': True}
        old = {'snapshot_day': '2026-11-15', 'last_attempt': (at - dt.timedelta(hours=1)).isoformat(), 'last_error': 'offline'}
        self.assertTrue(self.m.cron_due(config, old, at))
        # Uploading yesterday's archive today must not suppress today's 02:00 snapshot.
        old.update(last_error=None, last_success=at.isoformat())
        self.assertFalse(self.m.cron_due(config, old, at))
        self.assertTrue(self.m.cron_due(config, old, at + dt.timedelta(hours=1)))

    def test_remote_paths_reject_injection(self):
        for value in ('/a;rm x', '/a/../b', '/a\nb', '/a*', '/a"b'):
            with self.assertRaises(self.m.BackupError):
                self.m.safe_remote_path(value, absolute=True)
        self.assertEqual(self.m.safe_remote_path('/Backup Folder/kienzlezeit', True), '/Backup Folder/kienzlezeit')

    def test_smb_and_sftp_list_contents(self):
        name = self.name('20261116')
        remote = self.m.Remote({**self.config, 'transport': 'smb'})
        with patch.object(remote, 'command', return_value=f'  {name} A 123 Mon Nov 16 02:00:00 2026\n') as command:
            self.assertEqual(remote.list('daily'), [name])
            self.assertEqual(command.call_args.args[0], 'cd "daily"\nls')
        remote = self.m.Remote(self.config)
        with patch.object(remote, 'command', return_value=f'sftp> ls -1\n{name}\n{name}.sha256\n'):
            self.assertEqual(remote.list('daily'), [name, name + '.sha256'])

    def test_smb_password_only_in_protected_auth_file(self):
        config = {**self.config, 'transport': 'smb', 'share': 'Backups', 'directory': '',
                  'password': 'smb-secret', 'domain': ''}
        def fake_run(args, **kwargs):
            self.assertNotIn(config['password'], str(args))
            auth = Path(args[args.index('-A') + 1])
            self.assertEqual(auth.stat().st_mode & 0o777, 0o600)
            self.assertIn(config['password'], auth.read_text())
            return types.SimpleNamespace(returncode=0, stdout='', stderr='')
        with patch.object(self.m, 'run', side_effect=fake_run):
            self.m.Remote(config).command('ls')

    def test_real_sftp_file_protocol(self):
        server = next((p for p in ('/usr/libexec/sftp-server', '/usr/lib/openssh/sftp-server', '/usr/lib/ssh/sftp-server') if Path(p).exists()), None)
        if not server or not shutil.which('sftp'):
            self.skipTest('Local OpenSSH sftp-server not installed')
        # Real OpenSSH client/server file protocol over stdin/stdout; no port,
        # remote accounts, authentication changes or real network destination.
        root = self.base / 'real-sftp'
        root.mkdir()
        config = {**self.config, 'directory': str(root)}
        remote = self.m.Remote(config)
        original = self.m.run
        def direct_sftp(args, **kwargs):
            if args[0] == 'sftp':
                self.assertIn('StrictHostKeyChecking=yes', args)
                self.assertIn('BatchMode=yes', args)
                args = ['sftp', '-q', '-D', server, '-b', '-']
            return original(args, **kwargs)
        with patch.object(self.m, 'run', side_effect=direct_sftp):
            self.m.check_connection(remote)
            self.assertEqual(remote.list(), ['daily', 'weekly'])
            outbox = self.m.STATE / 'outbox' / self.m.target_id(config)
            outbox.mkdir(parents=True)
            archive = self.m.create_archive(config, outbox)
            with contextlib.redirect_stdout(io.StringIO()):
                self.m.upload_pending(remote, config)
            self.assertIn(archive.name, remote.list('daily'))
            restored = self.base / 'downloaded.zip'
            self.m.verified_download(remote, 'daily', archive.name, restored)
            self.unpack(restored)

    def test_marker_is_missing_after_interrupted_upload(self):
        outbox = self.m.STATE / 'outbox' / self.m.target_id(self.config)
        outbox.mkdir(parents=True)
        archive = self.m.create_archive(self.config, outbox)
        self.remote.put(archive, 'daily/' + archive.name)
        with contextlib.redirect_stdout(io.StringIO()):
            self.m.upload_pending(self.remote, self.config)
        self.assertTrue((self.remote.root / 'daily' / (archive.name + '.sha256')).exists())

    def test_failure_stores_error_and_success_clears_it(self):
        with patch.object(self.m, 'Remote', return_value=self.remote), patch.object(self.remote, 'put', side_effect=self.m.BackupError('offline')), contextlib.redirect_stdout(io.StringIO()):
            with self.assertRaises(self.m.BackupError):
                self.m.backup(self.config, scheduled=True)
        status = self.m.read_json(self.m.STATE / 'status.json')
        self.assertTrue(status['last_error'])
        self.assertNotIn('last_success', status)
        outbox = self.m.STATE / 'outbox' / self.m.target_id(self.config)
        old_archives = list(outbox.glob('*.zip'))
        self.assertEqual(len(old_archives), 1)
        with patch.object(self.m, 'Remote', return_value=self.remote), contextlib.redirect_stdout(io.StringIO()):
            self.m.backup(self.config, scheduled=True)
        status = self.m.read_json(self.m.STATE / 'status.json')
        self.assertIsNone(status['last_error'])
        self.assertIn('last_success', status)
        self.assertEqual(list(outbox.glob('*.zip')), [])
        self.assertTrue((self.remote.root / 'daily' / old_archives[0].name).exists())

    def test_future_schema_is_rejected(self):
        path = self.m.DATA / 'kienzlezeit.sqlite'
        with contextlib.closing(sqlite3.connect(path)) as db:
            db.execute("UPDATE settings SET setting_value='999' WHERE setting_key='schema_version'")
            db.commit()
        archive = self.archive()
        with self.assertRaisesRegex(self.m.BackupError, 'passt nicht'):
            self.unpack(archive)

    def test_oversized_manifest_is_rejected_before_extract(self):
        archive = self.mutate(self.archive(), lambda c: c.update({'manifest.json': b' ' * (1024**2 + 1)}))
        with self.assertRaisesRegex(self.m.BackupError, 'unerwartet gross'):
            self.m.archive_members(archive)

    def mock_services(self):
        self.apache = True
        self.commands = []
        def fake_run(args, **kwargs):
            self.commands.append(args)
            if args[:2] == ['systemctl', 'stop']:
                self.apache = False
            elif args[:2] == ['systemctl', 'start']:
                self.apache = True
            code = 0 if args[:2] != ['systemctl', 'is-active'] or self.apache else 3
            return types.SimpleNamespace(returncode=code, stdout='', stderr='')
        return fake_run

    def test_restore_success_keeps_target_config_and_restores_data(self):
        folder, manifest = self.unpack(self.archive())
        with contextlib.closing(sqlite3.connect(self.m.DATA / 'kienzlezeit.sqlite')) as db:
            db.execute("UPDATE admin_users SET display_name='After backup'")
            db.commit()
        account = types.SimpleNamespace(pw_uid=os.getuid(), pw_gid=os.getgid())
        with patch.object(self.m, 'run', side_effect=self.mock_services()), patch.object(self.m.pwd, 'getpwnam', return_value=account), patch.object(self.m.os, 'chown'), contextlib.redirect_stdout(io.StringIO()):
            self.m.apply_restore(folder, manifest, self.config)
        self.assertTrue(self.apache)
        self.assertFalse(self.m.restore_journal().exists())
        self.assertFalse(self.m.MAINTENANCE.exists())
        self.assertTrue(self.m.SESSION_CONF.exists())
        saved = self.m.read_json(self.m.CONFIG)
        self.assertEqual(saved['host'], 'backup.invalid')
        self.assertFalse(saved['enabled'])
        with contextlib.closing(sqlite3.connect(self.m.DATA / 'kienzlezeit.sqlite')) as db:
            self.assertNotEqual(db.execute('SELECT display_name FROM admin_users').fetchone()[0], 'After backup')

    def test_restore_failure_rolls_back_original_data(self):
        folder, manifest = self.unpack(self.archive())
        original = {p.name: p.read_bytes() for p in self.m.DATA.iterdir() if p.is_file()}
        account = types.SimpleNamespace(pw_uid=os.getuid(), pw_gid=os.getgid())
        with patch.object(self.m, 'run', side_effect=self.mock_services()), patch.object(self.m.pwd, 'getpwnam', return_value=account), patch.object(self.m.os, 'chown'), patch.object(self.m, 'session_config', side_effect=self.m.BackupError('simulated')), contextlib.redirect_stdout(io.StringIO()):
            with self.assertRaisesRegex(self.m.BackupError, 'simulated'):
                self.m.apply_restore(folder, manifest, self.config)
        for name, content in original.items():
            self.assertEqual((self.m.DATA / name).read_bytes(), content)
        self.assertTrue(self.apache)
        self.assertFalse(self.m.restore_journal().exists())

    def test_committed_journal_finishes_without_rollback(self):
        journal = {'phase': 'committed', 'apache_active': True, 'recovery': '/nonexistent'}
        self.m.write_json(self.m.restore_journal(), journal)
        self.m.MAINTENANCE.write_text(self.m.MAINTENANCE_TEXT)
        with patch.object(self.m, 'run', side_effect=self.mock_services()):
            self.m.rollback_restore(journal)
        self.assertFalse(self.m.restore_journal().exists())
        self.assertTrue(self.apache)

    def test_backup_requires_explicit_encryption_choice(self):
        config = dict(self.config)
        del config['archive_password']
        with self.assertRaises(self.m.BackupError):
            self.m.backup(config)

    def test_no_retention_before_cron_activation(self):
        with patch.object(self.m, 'Remote', return_value=self.remote), patch.object(self.m, 'retain') as retain, contextlib.redirect_stdout(io.StringIO()):
            self.m.backup(self.config)
            retain.assert_not_called()
            self.m.backup({**self.config, 'enabled': True})
            retain.assert_called_once()

    def test_cron_configuration_is_single_file_and_contains_no_password(self):
        self.config['archive_password'] = 'archive-secret'
        with patch.object(self.m, 'run', side_effect=self.mock_services()):
            self.m.enable_cron(self.config)
            self.m.enable_cron(self.config)
        text = self.m.CRON.read_text()
        self.assertEqual(text.count('--cron'), 1)
        self.assertNotIn('archive-secret', text)
        self.assertEqual(self.m.CONFIG.stat().st_mode & 0o777, 0o600)
        self.m.disable_cron(self.config)
        self.assertFalse(self.m.CRON.exists())
        self.assertFalse(self.config['enabled'])


if __name__ == '__main__':
    unittest.main()
