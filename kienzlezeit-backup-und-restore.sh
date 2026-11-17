#!/usr/bin/env bash
# kienzlezeit – Backup und Wiederherstellung
# Version: 1.0.1
# Autor: Dr. Thomas Kienzle
# Stand: 2026-11-17
# Changelog (komplett):
# - 1.0.1 (2026-11-17): Expliziter SMB-Gastzugriff ohne Passwortabfrage;
#   bestehende Benutzeranmeldungen und ZIP-Passwortschutz bleiben unveraendert.
# - 1.0 (2026-11-16): Eigenstaendiger Installer, SSH/SMB, AES-256-ZIP,
#   Cron, 30 Tage taeglich / woechentlich unbegrenzt und gepruefter Restore.
# Eine einzelne verteilbare Datei; Python-Standardbibliothek und PHP/libzip.
set -Eeuo pipefail
export PATH=/usr/sbin:/usr/bin:/sbin:/bin
if [[ "${1:-}" == "--version" ]]; then
  printf '%s\n' 'kienzlezeit Backup und Wiederherstellung 1.0.1'
  exit 0
fi
if [[ "${1:-}" == "--help" ]]; then
  printf '%s\n' 'sudo bash kienzlezeit-backup-und-restore.sh [--backup | --status | --cron]' \
    'Ohne Argument: interaktives Menue. Zielsystem: Debian/Ubuntu mit Apache.'
  exit 0
fi
[[ ${EUID} -eq 0 ]] || { echo 'Bitte mit sudo oder als root ausfuehren.' >&2; exit 1; }
command -v apt-get >/dev/null || { echo 'Debian/Ubuntu mit apt erforderlich.' >&2; exit 1; }
if ! command -v python3 >/dev/null; then
  [[ "${1:-}" != "--cron" ]] || { echo 'Python fehlt; Installer erneut starten.' >&2; exit 1; }
  apt-get update
  DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends python3
fi
exec python3 - "$0" "$@" <<'PYTHON'
import contextlib
import datetime as dt
import fcntl
import getpass
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import pwd
import re
import shutil
import signal
import sqlite3
import stat
import subprocess
import sys
import tempfile
import time
import uuid
import zipfile
from zoneinfo import ZoneInfo

VERSION = '1.0.1'
SOURCE = Path(sys.argv[1]).resolve()
CONFIG_DIR = Path('/etc/kienzlezeit-backup')
CONFIG = CONFIG_DIR / 'config.json'
STATE = Path('/var/lib/kienzlezeit-backup')
DATA = Path('/var/lib/kienzlezeit')
WEB = Path('/var/www/html')
INSTALLED = Path('/usr/local/sbin/kienzlezeit-backup-und-restore.sh')
CRON = Path('/etc/cron.d/kienzlezeit-backup')
MAINTENANCE = Path('/etc/apache2/conf-enabled/kienzlezeit-restore.conf')
SESSION_CONF = Path('/etc/apache2/conf-enabled/kienzlezeit-restored-sessions.conf')
DB_NAMES = ('kienzlezeit.sqlite', 'auslagen.sqlite')
KEY_NAME = 'terminal-secret.key'
MEMBERS = set(DB_NAMES) | {KEY_NAME, 'manifest.json'}
MAX_BYTES = 8 * 1024**3
ARCHIVE_RE = re.compile(r'^kienzlezeit_[A-Za-z0-9-]+_(\d{8}T\d{6}Z)_v(\d+(?:\.\d+){1,3})_([a-f0-9]{16})_([a-f0-9]{12})\.zip$')
MAINTENANCE_TEXT = '# kienzlezeit Restore-Schutz\n<LocationMatch "(?i)/(kienzlezeit|rfid-scan|auslagen)\\.php(?:/|$)">\nRequire all denied\n</LocationMatch>\n'


class BackupError(Exception):
    pass


def now():
    return dt.datetime.now(dt.timezone.utc)


def berlin(value=None):
    return (value or now()).astimezone(ZoneInfo('Europe/Berlin'))


def say(message):
    print(message, flush=True)


def run(args, *, text_input=None, timeout=120, check=True):
    try:
        result = subprocess.run([str(x) for x in args], input=text_input,
                                text=True, capture_output=True, timeout=timeout,
                                env={**os.environ, 'LC_ALL': 'C'})
    except subprocess.TimeoutExpired:
        raise BackupError(f'{Path(args[0]).name}: Zeitlimit ueberschritten.') from None
    if check and result.returncode:
        # Never report command arguments/stdin: these may contain private paths/data.
        raise BackupError(f'{Path(args[0]).name} fehlgeschlagen (Status {result.returncode}).')
    return result


def atomic_write(path, content, mode=0o600):
    path = Path(path)
    fd, tmp = tempfile.mkstemp(prefix='.kz-', dir=path.parent)
    try:
        with os.fdopen(fd, 'wb') as stream:
            os.fchmod(stream.fileno(), mode)
            stream.write(content.encode('utf-8') if isinstance(content, str) else content)
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(tmp, path)
        sync_dir(path.parent)
    finally:
        if os.path.exists(tmp):
            os.unlink(tmp)


def atomic_copy(source, target, mode=0o640):
    fd, tmp = tempfile.mkstemp(prefix='.kz-', dir=Path(target).parent)
    try:
        with open(source, 'rb') as src, os.fdopen(fd, 'wb') as dst:
            os.fchmod(dst.fileno(), mode)
            shutil.copyfileobj(src, dst, 1024 * 1024)
            dst.flush()
            os.fsync(dst.fileno())
        os.replace(tmp, target)
        sync_dir(Path(target).parent)
    finally:
        if os.path.exists(tmp):
            os.unlink(tmp)


def sync_dir(path):
    fd = os.open(path, os.O_RDONLY)
    try:
        os.fsync(fd)
    finally:
        os.close(fd)


def write_json(path, value):
    atomic_write(path, json.dumps(value, ensure_ascii=False, indent=2) + '\n')


def read_json(path, default=None):
    return json.loads(Path(path).read_text()) if Path(path).exists() else default


def private_dir(path):
    path = Path(path)
    if path.is_symlink():
        raise BackupError(f'Verzeichnis darf kein Symlink sein: {path}')
    path.mkdir(mode=0o700, parents=True, exist_ok=True)
    os.chmod(path, 0o700)


def regular_file(path):
    if not stat.S_ISREG(Path(path).lstat().st_mode):
        raise BackupError(f'Keine regulaere Datei: {path}')


def sha256(path):
    h = hashlib.sha256()
    with open(path, 'rb') as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b''):
            h.update(chunk)
    return h.hexdigest()


def ask(prompt, default=None):
    suffix = f' [{default}]' if default is not None else ''
    answer = input(prompt + suffix + ': ').strip()
    return answer or (default if default is not None else '')


def confirm(prompt, default=False):
    answer = ask(prompt, 'J/n' if default else 'j/N').lower()
    return default if answer in ('j/n', '') else answer in ('j', 'ja')


def secret(prompt):
    value = getpass.getpass(prompt + ': ')
    if not value or any(c in value for c in '\r\n\x00'):
        raise BackupError('Passwort darf nicht leer sein und keine Zeilenumbrueche enthalten.')
    return value


def apt_install(packages):
    missing = [p for p in packages if run(['dpkg-query', '-W', '-f=${Status}', p], check=False).stdout.strip() != 'install ok installed']
    if missing:
        say('Installiere fehlende Pakete: ' + ', '.join(missing))
        for args in (['apt-get', 'update'], ['apt-get', 'install', '-y', '--no-install-recommends', *missing]):
            result = subprocess.run(args, env={**os.environ, 'DEBIAN_FRONTEND': 'noninteractive'})
            if result.returncode:
                raise BackupError('Paketinstallation fehlgeschlagen. Es wird kein Cronjob eingerichtet.')


def prerequisites(interactive):
    if interactive:
        apt_install(['python3', 'sqlite3', 'php-cli', 'php-zip', 'php-sqlite3', 'cron', 'tzdata', 'ca-certificates'])
    for command in ('php', 'sqlite3', 'systemctl', 'apache2ctl'):
        if not shutil.which(command):
            raise BackupError(f'{command} fehlt. Zuerst installer.sh bzw. dieses Programm interaktiv ausfuehren.')
    run(['php', '-r', 'exit(class_exists("ZipArchive") && extension_loaded("pdo_sqlite") && extension_loaded("openssl") && ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256, true) && ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256, false) ? 0 : 1);'])
    ZoneInfo('Europe/Berlin')


def load_config():
    if CONFIG.exists():
        regular_file(CONFIG)
        if CONFIG.stat().st_mode & 0o077:
            raise BackupError('Konfiguration muss ausschliesslich fuer root lesbar sein (chmod 600).')
    return read_json(CONFIG, {})


def save_config(config):
    write_json(CONFIG, config)


def app_info():
    text = (WEB / 'kienzlezeit.php').read_text()
    v = re.search(r"const KZ_APP_VERSION = '(\d+(?:\.\d+){1,3})';", text)
    s = re.search(r'const KZ_SCHEMA_VERSION = (\d+);', text)
    if not v or not s:
        raise BackupError('Installierte kienzlezeit-Version konnte nicht erkannt werden.')
    return v.group(1), int(s.group(1))


def db_schema(path, name):
    regular_file(path)
    with contextlib.closing(sqlite3.connect(Path(path).resolve().as_uri() + '?mode=ro', uri=True)) as conn:
        if conn.execute('PRAGMA integrity_check').fetchall() != [('ok',)]:
            raise BackupError(f'{name}: SQLite-Integritaetspruefung fehlgeschlagen.')
        if conn.execute('PRAGMA foreign_key_check').fetchone():
            raise BackupError(f'{name}: Ungueltige Datenbankverknuepfungen.')
        if name == 'kienzlezeit.sqlite':
            value = conn.execute("SELECT setting_value FROM settings WHERE setting_key='schema_version'").fetchone()
            for table in ('employees', 'admin_users', 'terminals', 'time_events', 'event_requests', 'corrections'):
                conn.execute('SELECT 1 FROM ' + table + ' LIMIT 1')
        else:
            value = conn.execute("SELECT value FROM settings WHERE key='schema_version'").fetchone()
            for table in ('reimbursements', 'items', 'reimbursement_accounts'):
                conn.execute('SELECT 1 FROM ' + table + ' LIMIT 1')
        if not value:
            raise BackupError('Datenbank-Schemaversion fehlt.')
        return int(value[0])


def snapshot(directory, config):
    """Reserve both writers; readers/backup connections can still read in WAL mode."""
    names = [name for name in DB_NAMES if (DATA / name).exists()]
    if 'kienzlezeit.sqlite' not in names:
        raise BackupError('Zeiterfassungs-Datenbank fehlt. Zuerst installer.sh ausfuehren.')
    if DATA.is_symlink():
        raise BackupError('Das Datenverzeichnis darf kein Symlink sein.')
    locks = []
    deadline = time.monotonic() + 60
    def progress(*unused):
        if time.monotonic() > deadline:
            raise BackupError('Datenbanksicherung dauerte zu lange; unveraendert abgebrochen.')
    try:
        for name in names:
            regular_file(DATA / name)
            lock = sqlite3.connect((DATA / name).resolve().as_uri() + '?mode=rw', uri=True, timeout=15)
            locks.append(lock)
            lock.execute('BEGIN IMMEDIATE')
        for name in names:
            with contextlib.closing(sqlite3.connect((DATA / name).resolve().as_uri() + '?mode=ro', uri=True)) as src:
                with contextlib.closing(sqlite3.connect(directory / name)) as dst:
                    src.backup(dst, pages=256, progress=progress)
        regular_file(DATA / KEY_NAME)
        shutil.copyfile(DATA / KEY_NAME, directory / KEY_NAME)
    finally:
        for lock in reversed(locks):
            lock.rollback()
            lock.close()
    version, unused = app_info()
    manifest = {'format': 1, 'application': 'kienzlezeit', 'backup_version': VERSION,
                'app_version': version, 'created_at': now().isoformat(),
                'installation_id': config['installation_id'], 'schemas': {}, 'files': {}}
    for name in names:
        manifest['schemas'][name] = db_schema(directory / name, name)
    if not re.fullmatch(r'[0-9a-fA-F]{64}\s*', (directory / KEY_NAME).read_text()):
        raise BackupError('Terminal-Schluesseldatei ist ungueltig.')
    for name in names + [KEY_NAME]:
        manifest['files'][name] = {'sha256': sha256(directory / name), 'size': (directory / name).stat().st_size}
    write_json(directory / 'manifest.json', manifest)
    return manifest


# Secrets travel through stdin, never process arguments, environment or log output.
ZIP_PHP = r'''
$p=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$z=new ZipArchive();
try {
  if ($p['action']==='pack') {
    if ($z->open($p['archive'],ZipArchive::CREATE|ZipArchive::EXCL)!==true) throw new Exception();
    foreach($p['names'] as $n) {
      if(!$z->addFile($p['directory'].'/'.$n,$n)) throw new Exception();
      if($p['password']!==null && !$z->setEncryptionName($n,ZipArchive::EM_AES_256,$p['password'])) throw new Exception();
    }
    if(!$z->close()) throw new Exception();
  } else {
    if($z->open($p['archive'],ZipArchive::RDONLY)!==true) throw new Exception();
    if($p['password']!==null) $z->setPassword($p['password']);
    foreach($p['names'] as $n) {
      $s=$z->getStream($n); if($s===false) throw new Exception();
      $out=fopen($p['directory'].'/'.$n,'xb'); if($out===false) throw new Exception();
      $size=stream_copy_to_stream($s,$out); fclose($out); fclose($s);
      if($size===false || $size!==$z->statName($n)['size']) throw new Exception();
    }
    if(!$z->close()) throw new Exception();
  }
} catch(Throwable $e) { fwrite(STDERR,"ZIP-Pruefung fehlgeschlagen (Archiv oder Passwort).\n"); exit(1); }
'''


def zip_call(action, archive, directory, names, password):
    run(['php', '-d', 'display_errors=0', '-d', 'log_errors=0', '-r', ZIP_PHP],
        text_input=json.dumps({'action': action, 'archive': str(archive), 'directory': str(directory),
                               'names': sorted(names), 'password': password}), timeout=600)


def archive_members(archive):
    regular_file(archive)
    if archive.stat().st_size > MAX_BYTES:
        raise BackupError('Archiv ist groesser als das Sicherheitslimit von 8 GiB.')
    with zipfile.ZipFile(archive) as z:
        infos = z.infolist()
        names = [i.filename for i in infos]
        if len(names) != len(set(names)) or not set(names) <= MEMBERS or not {'manifest.json', KEY_NAME, DB_NAMES[0]} <= set(names):
            raise BackupError('Archiv enthaelt unerwartete, doppelte oder fehlende Dateien.')
        if sum(i.file_size for i in infos) > MAX_BYTES:
            raise BackupError('Entpackter Inhalt ueberschreitet das Sicherheitslimit von 8 GiB.')
        for info in infos:
            mode = info.external_attr >> 16
            if stat.S_ISLNK(mode) or info.is_dir():
                raise BackupError('Verknuepfungen und Verzeichnisse sind im Archiv nicht erlaubt.')
            if (info.filename == 'manifest.json' and info.file_size > 1024**2) or (info.filename == KEY_NAME and info.file_size > 128):
                raise BackupError('Manifest oder Schluesseldatei ist unerwartet gross.')
        encrypted = [bool(i.flag_bits & 1) for i in infos]
        if any(encrypted) and not all(encrypted):
            raise BackupError('Uneinheitlicher Passwortschutz im Archiv.')
        return names, any(encrypted), sum(i.file_size for i in infos)


def unpack_checked(archive, directory, password, compatible=True):
    names, encrypted, size = archive_members(archive)
    if encrypted and not password:
        raise BackupError('Dieses Archiv benoetigt ein Passwort.')
    if shutil.disk_usage(directory).free < size + 64 * 1024**2:
        raise BackupError('Zu wenig freier Speicher fuer die Archivpruefung.')
    zip_call('unpack', archive, directory, names, password)
    manifest = read_json(directory / 'manifest.json')
    if manifest.get('format') != 1 or manifest.get('application') != 'kienzlezeit':
        raise BackupError('Kein unterstuetztes kienzlezeit-Backup.')
    if set(manifest.get('files', {})) != set(names) - {'manifest.json'}:
        raise BackupError('Dateiliste stimmt nicht mit dem Manifest ueberein.')
    if not re.fullmatch(r'[a-f0-9]{16}', manifest.get('installation_id', '')):
        raise BackupError('Ungueltige Installationskennung.')
    dt.datetime.fromisoformat(manifest['created_at'])
    for name, info in manifest['files'].items():
        if sha256(directory / name) != info['sha256'] or (directory / name).stat().st_size != info['size']:
            raise BackupError('Pruefsummenfehler: ' + name)
    if not re.fullmatch(r'[0-9a-fA-F]{64}\s*', (directory / KEY_NAME).read_text()):
        raise BackupError('Terminal-Schluesseldatei ist ungueltig.')
    for name in DB_NAMES:
        if name in names and db_schema(directory / name, name) != manifest['schemas'].get(name):
            raise BackupError('Schemaversion stimmt nicht mit dem Manifest ueberein.')
    # Hashes alone cannot tell whether a valid key belongs to this database.
    run(['php', '-d', 'display_errors=0', '-d', 'log_errors=0', '-r', r'''
$p=json_decode(stream_get_contents(STDIN),true);
try {
 $db=new PDO('sqlite:'.$p['db']); $key=hex2bin(trim(file_get_contents($p['key'])));
 foreach($db->query('SELECT key_ciphertext,key_nonce,key_tag FROM terminals WHERE key_ciphertext IS NOT NULL AND key_ciphertext != ""') as $r) {
  if(openssl_decrypt(base64_decode($r['key_ciphertext']),'aes-256-gcm',$key,OPENSSL_RAW_DATA,base64_decode($r['key_nonce']),base64_decode($r['key_tag']))===false) exit(2);
 }
} catch(Throwable $e) { exit(1); }
'''], text_input=json.dumps({'db': str(directory / DB_NAMES[0]), 'key': str(directory / KEY_NAME)}))
    if compatible:
        current, schema = app_info()
        def ver(value):
            if not re.fullmatch(r'\d+(?:\.\d+){1,3}', value):
                raise BackupError('Ungueltige Anwendungs-Version.')
            return tuple(int(v) for v in value.split('.')) + (0,) * (4-len(value.split('.')))
        if ver(manifest['app_version']) > ver(current) or manifest['schemas'][DB_NAMES[0]] != schema:
            raise BackupError('Backup passt nicht zur installierten Anwendung. Passenden Anwendungsstand installieren.')
        if DB_NAMES[1] in names:
            # The normal installer creates/migrates the expense DB before restore.
            if not (DATA / DB_NAMES[1]).exists() or db_schema(DATA / DB_NAMES[1], DB_NAMES[1]) != manifest['schemas'][DB_NAMES[1]]:
                raise BackupError('Auslagen-Schema passt nicht. Zuerst den passenden installer.sh ausfuehren.')
    return manifest


def archive_name(manifest):
    host = re.sub('[^A-Za-z0-9-]', '-', os.uname().nodename.split('.')[0])[:40] or 'server'
    stamp = dt.datetime.fromisoformat(manifest['created_at']).astimezone(dt.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    return f"kienzlezeit_{host}_{stamp}_v{manifest['app_version']}_{manifest['installation_id']}_{uuid.uuid4().hex[:12]}.zip"


def safe_remote_path(value, absolute=False):
    if not value and not absolute:
        return ''
    if not re.fullmatch(r'[A-Za-z0-9_./ -]+', value) or any(x == '..' for x in value.split('/')):
        raise BackupError('Zielpfad: nur Buchstaben, Zahlen, Leerzeichen, /, _, - und Punkt; kein .. verwenden.')
    if absolute and not value.startswith('/'):
        raise BackupError('SSH-Zielverzeichnis muss ein absoluter Pfad sein.')
    if not absolute and value.startswith('/'):
        raise BackupError('SMB-Unterverzeichnis muss relativ zur Freigabe sein.')
    return value.rstrip('/') or ('/' if absolute else '')


def quote(value):
    value = str(value)
    if any(c in value for c in '\n\r\x00"\\;*?[]'):
        raise BackupError('Nicht unterstuetztes Sonderzeichen im Uebertragungspfad.')
    return '"' + value + '"'


class Remote:
    """Only SFTP/SMB file operations; no remote shell and no network mounts."""
    def __init__(self, config):
        self.c = config
        if config.get('transport') not in ('ssh', 'smb'):
            raise BackupError('Bitte zuerst den Speicherort konfigurieren.')
        if config.get('transport') == 'smb' and config.get('smb_auth', 'password') not in ('password', 'guest'):
            raise BackupError('Ungueltige SMB-Anmeldeart. Speicherort bitte erneut konfigurieren.')

    def command(self, command):
        c = self.c
        path = quote(c['directory'])
        prefix = f'cd {path}\n' if c['directory'] else ''
        if c['transport'] == 'ssh':
            args = ['sftp', '-F', '/dev/null', '-q', '-b', '-', '-P', str(c['port']),
                    '-i', c['identity'], '-o', 'BatchMode=yes', '-o', 'IdentitiesOnly=yes',
                    '-o', 'StrictHostKeyChecking=yes', '-o', 'GlobalKnownHostsFile=/dev/null',
                    '-o', f"UserKnownHostsFile={c['known_hosts']}", '-o', 'ConnectTimeout=15',
                    '-o', 'ServerAliveInterval=15', '-o', 'ServerAliveCountMax=3',
                    c['username'] + '@' + c['host']]
            result = run(args, text_input=prefix + command + '\n', timeout=600, check=False)
        else:
            args = ['smbclient', '//' + c['host'] + '/' + c['share'],
                    '--option=client min protocol=SMB2', '-m', 'SMB3', '-t', '60',
                    '-c', (prefix + command).strip().replace('\n', ';')]
            if c.get('smb_auth', 'password') == 'guest':
                # Explicit empty password, no credentials file, prompt or cached
                # Kerberos identity. Guest access must be allowed by the server.
                args += ['-U', 'guest%', '-N', '--use-kerberos=off']
                result = run(args, timeout=600, check=False)
            else:
                with tempfile.TemporaryDirectory(prefix='smb-', dir=STATE) as td:
                    auth = Path(td) / 'auth'
                    for key in ('username', 'password', 'domain'):
                        if any(x in c.get(key, '') for x in '\r\n\x00'):
                            raise BackupError('Ungueltige SMB-Zugangsdaten.')
                    atomic_write(auth, f"username = {c['username']}\npassword = {c['password']}\ndomain = {c.get('domain', '')}\n")
                    args += ['-A', str(auth)]
                    result = run(args, timeout=600, check=False)
        if result.returncode or 'NT_STATUS_' in result.stdout + result.stderr:
            raise BackupError('Uebertragung fehlgeschlagen. Zielpfad, Rechte, Netzwerk und SSH-Fingerabdruck pruefen.')
        return result.stdout

    def list(self, area=''):
        output = self.command(('cd ' + quote(area) + '\n' if area else '') + ('ls -1' if self.c['transport'] == 'ssh' else 'ls'))
        # Ignore client prompts/headers; accept only our strict file-name grammar.
        names = set()
        for line in output.splitlines():
            if self.c['transport'] == 'ssh':
                item = line.strip().rsplit('/', 1)[-1]
                if item in ('daily', 'weekly') or ARCHIVE_RE.fullmatch(item.removesuffix('.sha256')):
                    names.add(item)
            else:
                match = re.match(r'^\s*(\S+)\s+[DAHRSN]+\s+\d+\s+', line)
                if match:
                    item = match.group(1)
                    if item in ('daily', 'weekly') or ARCHIVE_RE.fullmatch(item.removesuffix('.sha256')):
                        names.add(item)
        return sorted(names)

    def ensure_dirs(self):
        existing = self.list()
        for area in ('daily', 'weekly'):
            if area not in existing:
                self.command('mkdir ' + quote(area))

    def get(self, remote, local):
        self.command('get ' + quote(remote) + ' ' + quote(local))

    def put(self, local, remote):
        self.command('put ' + quote(local) + ' ' + quote(remote))

    def rename(self, old, new):
        self.command('rename ' + quote(old) + ' ' + quote(new))

    def delete(self, remote):
        self.command(('rm ' if self.c['transport'] == 'ssh' else 'del ') + quote(remote))


def target_id(config):
    fields = {k: config.get(k) for k in ('transport', 'host', 'port', 'share', 'directory', 'username')}
    # Preserve existing 1.0 outbox IDs for password logins. Guest access is an
    # explicit new target identity, never an implicit fallback for queued data.
    if config.get('transport') == 'smb' and config.get('smb_auth') == 'guest':
        fields['smb_auth'] = 'guest'
    return hashlib.sha256(json.dumps(fields, sort_keys=True).encode()).hexdigest()[:16]


def check_connection(remote):
    remote.ensure_dirs()
    name = '.kz-probe-' + uuid.uuid4().hex
    with tempfile.TemporaryDirectory(dir=STATE) as td:
        src, dst = Path(td) / 'probe', Path(td) / 'check'
        atomic_write(src, uuid.uuid4().hex)
        remote.put(src, name)
        try:
            remote.get(name, dst)
            if sha256(src) != sha256(dst):
                raise BackupError('Verbindungspruefung: Pruefsummen stimmen nicht ueberein.')
        finally:
            remote.delete(name)


def verified_download(remote, area, name, destination):
    if not ARCHIVE_RE.fullmatch(name) or area not in ('daily', 'weekly'):
        raise BackupError('Ungueltiger Archivname.')
    remote.get(area + '/' + name + '.sha256', destination.with_suffix('.sha256'))
    digest = destination.with_suffix('.sha256').read_text().strip()
    if not re.fullmatch('[a-f0-9]{64}', digest):
        raise BackupError('Ungueltige externe Pruefsumme.')
    remote.get(area + '/' + name, destination)
    if sha256(destination) != digest:
        raise BackupError('Uebertragene Datei stimmt nicht mit der Pruefsumme ueberein.')


def upload_pending(remote, config):
    outbox = STATE / 'outbox' / target_id(config)
    if not outbox.exists():
        return
    remote.ensure_dirs()
    for archive in sorted(outbox.glob('*.zip')):
        digest = sha256(archive)
        marker = archive.with_suffix('.zip.sha256')
        if marker.read_text().strip() != digest:
            raise BackupError('Lokale Sicherung wurde veraendert; Uebertragung abgebrochen.')
        name = archive.name
        existing = remote.list('daily')
        with tempfile.TemporaryDirectory(dir=STATE) as td:
            readback = Path(td) / 'readback.zip'
            if name in existing:
                remote.get('daily/' + name, readback)
                if sha256(readback) != digest:
                    raise BackupError('Gleichnamiges Zielarchiv hat anderen Inhalt; kein Ueberschreiben.')
            else:
                part = 'daily/' + name + '.part'
                remote.put(archive, part)
                remote.get(part, readback)
                if sha256(readback) != digest:
                    raise BackupError('Uebertragungspruefung fehlgeschlagen. Lokale Sicherung bleibt erhalten.')
                remote.rename(part, 'daily/' + name)
            if name + '.sha256' in existing:
                check = Path(td) / 'existing.sha256'
                remote.get('daily/' + name + '.sha256', check)
                if check.read_text().strip() != digest:
                    raise BackupError('Vorhandene Ziel-Pruefsumme weicht ab; kein Ueberschreiben.')
            else:
                remote.put(marker, 'daily/' + name + '.sha256.part')
                remote.rename('daily/' + name + '.sha256.part', 'daily/' + name + '.sha256')
        archive.unlink()
        marker.unlink()
        say('Uebertragen und geprueft: ' + name)


def archive_date(name):
    match = ARCHIVE_RE.fullmatch(name)
    if not match:
        raise BackupError('Ungueltiger Archivname.')
    return dt.datetime.strptime(match.group(1), '%Y%m%dT%H%M%SZ').replace(tzinfo=dt.timezone.utc)


def retention_plan(daily, weekly, installation_id, at=None):
    at = at or now()
    def ours(name):
        m = ARCHIVE_RE.fullmatch(name)
        return m is not None and m.group(3) == installation_id
    def week(name):
        return berlin(archive_date(name)).date().isocalendar()[:2]
    current_week = berlin(at).date().isocalendar()[:2]
    kept_weeks = {week(n) for n in weekly if ours(n)}
    groups = {}
    for name in daily:
        if ours(name) and week(name) < current_week:
            groups.setdefault(week(name), []).append(name)
    promote = []
    for w, names in groups.items():
        if w not in kept_weeks:
            promote.append(max(names, key=lambda n: (archive_date(n), n)))
            kept_weeks.add(w)
    cutoff = berlin(at).date() - dt.timedelta(days=30)
    remove = [n for n in daily if ours(n) and n not in promote and week(n) in kept_weeks
              and berlin(archive_date(n)).date() <= cutoff]
    return sorted(promote), sorted(remove)


def retain(remote, config):
    daily_all, weekly_all = remote.list('daily'), remote.list('weekly')
    # Recover interruption between the two renames of a weekly promotion.
    for name in weekly_all:
        match = ARCHIVE_RE.fullmatch(name)
        if not match or match.group(3) != config['installation_id']:
            continue
        if name + '.sha256' not in weekly_all and name + '.sha256' in daily_all:
            with tempfile.TemporaryDirectory(dir=STATE) as td:
                digest, archive = Path(td) / 'checksum', Path(td) / 'archive'
                remote.get('daily/' + name + '.sha256', digest)
                remote.get('weekly/' + name, archive)
                if sha256(archive) != digest.read_text().strip():
                    raise BackupError('Unterbrochene Wochensicherung konnte nicht verifiziert werden.')
            remote.rename('daily/' + name + '.sha256', 'weekly/' + name + '.sha256')
            weekly_all.append(name + '.sha256')
    daily = [n for n in daily_all if n + '.sha256' in daily_all]
    weekly = [n for n in weekly_all if n + '.sha256' in weekly_all]
    promote, remove = retention_plan(daily, weekly, config['installation_id'])
    # Verify every permanent recovery point used to justify deleting daily copies.
    needed_weeks = {berlin(archive_date(n)).date().isocalendar()[:2] for n in remove}
    for area, names in (('daily', promote), ('weekly', weekly)):
        for name in names:
            if area == 'weekly' and berlin(archive_date(name)).date().isocalendar()[:2] not in needed_weeks:
                continue
            with tempfile.TemporaryDirectory(dir=STATE) as td:
                verified_download(remote, area, name, Path(td) / 'check.zip')
            if area == 'daily':
                remote.rename('daily/' + name, 'weekly/' + name)
                remote.rename('daily/' + name + '.sha256', 'weekly/' + name + '.sha256')
    for name in remove:
        remote.delete('daily/' + name)
        remote.delete('daily/' + name + '.sha256')
    # There is deliberately no deletion operation for weekly/.


def create_archive(config, directory):
    with tempfile.TemporaryDirectory(prefix='snapshot-', dir=STATE) as td:
        source = Path(td)
        manifest = snapshot(source, config)
        archive = directory / archive_name(manifest)
        temporary = archive.with_suffix('.zip.part')
        zip_call('pack', temporary, source, set(manifest['files']) | {'manifest.json'}, config.get('archive_password'))
        with tempfile.TemporaryDirectory(prefix='verify-', dir=STATE) as verify:
            unpack_checked(temporary, Path(verify), config.get('archive_password'), compatible=False)
        os.replace(temporary, archive)
        atomic_write(archive.with_suffix('.zip.sha256'), sha256(archive) + '\n')
        sync_dir(directory)
        return archive


def backup(config, scheduled=False):
    if 'archive_password' not in config:
        raise BackupError('Passwortschutz ist noch nicht festgelegt. Zuerst Menuepunkt 2 ausfuehren.')
    status = read_json(STATE / 'status.json', {})
    outbox = STATE / 'outbox' / target_id(config)
    private_dir(outbox)
    status['last_attempt'] = now().isoformat()
    write_json(STATE / 'status.json', status)
    try:
        remote = Remote(config)  # Reject missing configuration before making an archive.
        daily_due = status.get('snapshot_day') != berlin().date().isoformat() and berlin().strftime('%H:%M') >= config['time']
        if not scheduled or daily_due or not status.get('snapshot_day'):
            required = sum((DATA / (n + suffix)).stat().st_size for n in DB_NAMES for suffix in ('', '-wal') if (DATA / (n + suffix)).exists()) * 4 + 128 * 1024**2
            if shutil.disk_usage(STATE).free < required:
                # A recovered remote may free a full outbox without discarding it.
                upload_pending(remote, config)
            if shutil.disk_usage(STATE).free < required:
                raise BackupError('Zu wenig lokaler Speicher. Es werden keine Wochenbackups geloescht.')
            archive = create_archive(config, outbox)
            status['snapshot_day'] = berlin().date().isoformat()
            status['last_archive'] = archive.name
            write_json(STATE / 'status.json', status)
            say('Lokale Sicherung geprueft: ' + archive.name)
        upload_pending(remote, config)
        if config.get('enabled'):
            retain(remote, config)
        status.update(last_success=now().isoformat(), last_error=None)
        write_json(STATE / 'status.json', status)
        say('Sicherung erfolgreich. Wochenbackups bleiben unbegrenzt erhalten.')
    except Exception:
        status['last_error'] = 'Sicherung/Uebertragung nicht abgeschlossen. Lokale Archive bleiben in der Outbox.'
        write_json(STATE / 'status.json', status)
        raise


def cron_due(config, status, at=None):
    at = at or now()
    if not config.get('enabled'):
        return False
    local = berlin(at)
    if local.strftime('%H:%M') < config['time'] and not status.get('last_error'):
        return False
    if status.get('last_success') and status.get('snapshot_day') == local.date().isoformat() and berlin(dt.datetime.fromisoformat(status['last_success'])).date() == local.date() and not status.get('last_error'):
        return False
    if status.get('last_attempt') and (at - dt.datetime.fromisoformat(status['last_attempt'])).total_seconds() < 3600:
        return False
    return True


def disable_cron(config):
    config['enabled'] = False
    save_config(config)
    CRON.unlink(missing_ok=True)


def enable_cron(config):
    atomic_write(INSTALLED, SOURCE.read_bytes(), 0o750)
    run(['systemctl', 'enable', '--now', 'cron'])
    # Minute dispatcher implements Europe/Berlin independently of host timezone,
    # one successful job/day, missed-time catch-up and hourly retry after failure.
    atomic_write(CRON, f'# kienzlezeit Backup {VERSION}\nSHELL=/bin/sh\nPATH=/usr/sbin:/usr/bin:/sbin:/bin\n* * * * * root /usr/local/sbin/kienzlezeit-backup-und-restore.sh --cron\n', 0o644)
    config['enabled'] = True
    save_config(config)


def configure_target(config):
    if config.get('enabled') and not confirm('Zielwechsel deaktiviert zuerst den bisherigen Cronjob. Fortfahren?'):
        return
    c = dict(config)
    say('\nSpeicherort einrichten\n  1  SSH\n  2  SMB / Netzwerkfreigabe')
    choice = ask('Auswahl')
    if choice not in ('1', '2'):
        raise BackupError('Bitte 1 oder 2 waehlen.')
    c['transport'] = 'ssh' if choice == '1' else 'smb'
    c['host'] = ask('Servername oder IPv4-Adresse')
    if not re.fullmatch('[A-Za-z0-9][A-Za-z0-9.-]*', c['host']):
        raise BackupError('Ungueltiger Servername (DNS-Name oder IPv4 verwenden).')
    if choice == '2':
        say('\nSMB-Anmeldung:\n  1  Benutzername und Passwort\n  2  Gastzugriff ohne Passwort')
        auth_choice = ask('Auswahl', '1')
        if auth_choice not in ('1', '2'):
            raise BackupError('Bitte Anmeldeart 1 oder 2 waehlen.')
        c['smb_auth'] = 'guest' if auth_choice == '2' else 'password'
    else:
        c.pop('smb_auth', None)
    if c.get('smb_auth') == 'guest':
        c.update(username='guest', password='', domain='')
        say('Gastzugriff: keine Benutzer- oder Passwortabfrage.\nDas NAS muss Gastzugriff mit Schreibrechten erlauben.\nZIP-Verschluesselung bleibt separat waehlbar; sie schuetzt nicht vor dem Loeschen durch andere Gaeste.')
    else:
        c['username'] = ask('Benutzername')
        if not re.fullmatch('[A-Za-z0-9_][A-Za-z0-9_.@-]*', c['username']):
            raise BackupError('Ungueltiger Benutzername.')
    if choice == '1':
        apt_install(['openssh-client'])
        c['port'] = int(ask('SSH-Port', '22'))
        if not 1 <= c['port'] <= 65535:
            raise BackupError('Ungueltiger Port.')
        c['directory'] = safe_remote_path(ask('Vorhandenes Zielverzeichnis (absolut)'), absolute=True)
        key = CONFIG_DIR / ('ssh-' + uuid.uuid4().hex[:12])
        if ask('SSH-Schluessel: 1 erzeugen / 2 vorhandenen kopieren', '1') == '2':
            original = Path(ask('Pfad zum privaten Schluessel (ohne Passphrase)')).expanduser()
            regular_file(original)
            atomic_write(key, original.read_bytes())
        else:
            run(['ssh-keygen', '-t', 'ed25519', '-N', '', '-f', key, '-C', 'kienzlezeit-backup'])
            say('Oeffentlichen Schluessel auf dem Zielserver hinterlegen:\n' + Path(str(key) + '.pub').read_text())
            ask('Nach dem Hinterlegen Enter druecken')
        c['identity'] = str(key)
        scanned = run(['ssh-keyscan', '-T', '10', '-p', str(c['port']), c['host']]).stdout
        known = CONFIG_DIR / ('known-hosts-' + uuid.uuid4().hex[:12])
        atomic_write(known, scanned)
        say('Server-Fingerabdruck / Fingerabdruecke:\n' + run(['ssh-keygen', '-lf', known]).stdout)
        if not confirm('Unabhaengig mit dem Zielserver verglichen und korrekt?'):
            raise BackupError('Fingerabdruck nicht bestaetigt. Konfiguration nicht aktiviert.')
        c['known_hosts'] = str(known)
        c.pop('password', None)
    else:
        apt_install(['smbclient'])
        c['share'] = ask('Freigabename')
        if not re.fullmatch('[A-Za-z0-9_][A-Za-z0-9_. -]*', c['share']):
            raise BackupError('Ungueltiger Freigabename.')
        c['directory'] = safe_remote_path(ask('Vorhandenes Unterverzeichnis (leer = Freigabewurzel)'))
        if c['smb_auth'] == 'password':
            c['domain'] = ask('Domaene / Arbeitsgruppe (leer, falls nicht benoetigt)')
            c['password'] = secret('SMB-Passwort')
    c.setdefault('installation_id', uuid.uuid4().hex[:16])
    c['enabled'] = False
    c.setdefault('time', '02:00')
    say('Pruefe Verbindung und Schreibzugriff ...')
    check_connection(Remote(c))
    disable_cron(config)
    config.clear()
    config.update(c)
    save_config(config)
    # Do not interpret success against the old target as success against the new one.
    (STATE / 'status.json').unlink(missing_ok=True)
    say('Speicherort gespeichert. Automatische Sicherung ist NICHT aktiviert.\nVorhandene Sicherungen wurden nicht veraendert.')


def configure_schedule(config):
    Remote(config)
    clock = ask('Taegliche Sicherungszeit (Europe/Berlin)', config.get('time', '02:00'))
    if not re.fullmatch(r'(?:[01]\d|2[0-3]):[0-5]\d', clock):
        raise BackupError('Uhrzeit bitte als HH:MM eingeben.')
    say('Aufbewahrung: 30 Tage taeglich, danach eine Sicherung je Kalenderwoche unbegrenzt.\nWochenbackups werden niemals automatisch geloescht.')
    password = None
    if confirm('ZIP-Dateien mit AES-256 verschluesseln?', True):
        if config.get('archive_password') and confirm('Vorhandenes Backup-Passwort beibehalten?', True):
            password = config['archive_password']
        else:
            password = secret('Neues Backup-Passwort')
            if password != secret('Backup-Passwort wiederholen'):
                raise BackupError('Passwoerter stimmen nicht ueberein.')
        say('Passwort wird root-geschuetzt gespeichert. Zusaetzlich separat aufbewahren!\nAeltere Archive behalten ihr bisheriges Passwort.')
    else:
        if not confirm('Ohne Verschluesselung sind Personal-, Konto- und Schluesseldaten im Archiv lesbar. Fortfahren?'):
            return
    config.update(time=clock, archive_password=password)
    save_config(config)
    if confirm('Jetzt eine Testsicherung erstellen und uebertragen?', True):
        backup(config)
    if confirm('Cronjob jetzt aktivieren?', True):
        enable_cron(config)
        say('Automatische Sicherung aktiviert: taeglich ' + clock + ' Uhr Europe/Berlin.\nBei Fehlern erneuter Versuch nach einer Stunde; ausgefallene Startzeit wird nachgeholt.')


def session_config():
    # Isolate restored app sessions from pre-restore browser sessions without
    # deleting sessions of other applications sharing Apache's session storage.
    directory = DATA / ('sessions-restore-' + uuid.uuid4().hex[:16])
    directory.mkdir(mode=0o700)
    account = pwd.getpwnam('www-data')
    os.chown(directory, account.pw_uid, account.pw_gid)
    text = '# kienzlezeit: nach Restore neue, isolierte PHP-Sitzungen\n<LocationMatch "(?i)/(kienzlezeit|rfid-scan|auslagen)\\.php(?:/|$)">\nphp_value session.save_path "' + str(directory) + '"\n</LocationMatch>\n'
    atomic_write(SESSION_CONF, text, 0o644)


def restore_journal():
    return STATE / 'restore-pending.json'


def rollback_restore(journal):
    if journal['phase'] in ('committed', 'rolled_back'):
        finish_restore(journal)
        return
    recovery = Path(journal['recovery'])
    run(['systemctl', 'stop', 'apache2'])
    if journal['phase'] != 'prepared':
        for name in journal['managed']:
            target = DATA / name
            target.unlink(missing_ok=True)
            if (recovery / name).exists():
                shutil.copy2(recovery / name, target)
                os.chown(target, journal['owners'][name][0], journal['owners'][name][1])
        sync_dir(DATA)
    if journal.get('old_session_conf') is None:
        SESSION_CONF.unlink(missing_ok=True)
    else:
        atomic_write(SESSION_CONF, journal['old_session_conf'], 0o644)
    journal['phase'] = 'rolled_back'
    write_json(restore_journal(), journal)
    finish_restore(journal)
    say('Bisheriger Datenstand wiederhergestellt. Rueckfallsicherung: ' + str(recovery))


def finish_restore(journal):
    MAINTENANCE.unlink(missing_ok=True)
    try:
        run(['apache2ctl', 'configtest'])
        if journal['apache_active']:
            run(['systemctl', 'start', 'apache2'])
    except BaseException:
        atomic_write(MAINTENANCE, MAINTENANCE_TEXT, 0o644)
        raise
    restore_journal().unlink()
    sync_dir(STATE)


def apply_restore(directory, manifest, config):
    if MAINTENANCE.exists() or restore_journal().exists():
        raise BackupError('Ein Restore-Schutz ist bereits vorhanden. Zuerst unterbrochenen Restore klaeren.')
    if DATA.is_symlink():
        raise BackupError('Datenverzeichnis darf kein Symlink sein.')
    account = pwd.getpwnam('www-data')
    recovery = STATE / 'recovery' / (now().strftime('%Y%m%dT%H%M%SZ') + '-' + uuid.uuid4().hex[:8])
    private_dir(recovery)
    managed = [KEY_NAME] + [n + suffix for n in DB_NAMES for suffix in ('', '-wal', '-shm')]
    journal = {'phase': 'prepared', 'recovery': str(recovery), 'managed': managed, 'owners': {},
               'apache_active': run(['systemctl', 'is-active', '--quiet', 'apache2'], check=False).returncode == 0,
               'old_session_conf': SESSION_CONF.read_text() if SESSION_CONF.exists() else None}
    # Persistent access denial survives a machine crash/reboot mid-replacement.
    write_json(restore_journal(), journal)
    try:
        atomic_write(MAINTENANCE, MAINTENANCE_TEXT, 0o644)
        run(['apache2ctl', 'configtest'])
        run(['systemctl', 'stop', 'apache2'])
        if run(['systemctl', 'is-active', '--quiet', 'apache2'], check=False).returncode == 0:
            raise BackupError('Apache konnte nicht angehalten werden.')
        for name in managed:
            path = DATA / name
            if path.exists() or path.is_symlink():
                regular_file(path)
                st = path.stat()
                journal['owners'][name] = [st.st_uid, st.st_gid]
                shutil.copy2(path, recovery / name)
                with open(recovery / name, 'rb') as stream:
                    os.fsync(stream.fileno())
        sync_dir(recovery)
        journal['phase'] = 'replacing'
        write_json(restore_journal(), journal)
        for name in managed:
            (DATA / name).unlink(missing_ok=True)
        for name in manifest['files']:
            atomic_copy(directory / name, DATA / name)
            os.chown(DATA / name, 0 if name == KEY_NAME else account.pw_uid, account.pw_gid)
        for name in DB_NAMES:
            if name in manifest['files']:
                db_schema(DATA / name, name)
        # A backup from a deployment without expenses must not retain new-install
        # expenses. The app's migration will create an empty expense DB as needed.
        session_config()
        run(['apache2ctl', 'configtest'])
        config['installation_id'] = manifest['installation_id']
        config['enabled'] = False
        save_config(config)
        (STATE / 'status.json').unlink(missing_ok=True)
        write_json(recovery / 'restore-info.json', {'restored_at': now().isoformat(), 'archive': manifest})
        # Commit before admitting requests. After this point recovery only finishes
        # activation; it must never roll back new bookings received after restart.
        journal['phase'] = 'committed'
        write_json(restore_journal(), journal)
        finish_restore(journal)
        say('Wiederherstellung erfolgreich.\nLokale Rueckfallsicherung: ' + str(recovery))
    except BaseException:
        try:
            rollback_restore(read_json(restore_journal()))
        except BaseException:
            say('ACHTUNG: Ruecksetzen nicht abgeschlossen. Restore-Schutz nicht entfernen!\nProgramm erneut starten; Rueckfallsicherung: ' + str(recovery))
        raise


def restore(config):
    say('\nSicherung wiederherstellen\n  1  Konfigurierter SSH-/SMB-Speicherort\n  2  Lokale ZIP-Datei\n  0  Abbrechen')
    choice = ask('Auswahl')
    if choice == '0':
        return
    with tempfile.TemporaryDirectory(prefix='restore-', dir=STATE) as td:
        stage = Path(td)
        archive = stage / 'backup.zip'
        if choice == '1':
            remote = Remote(config)
            entries = []
            for area in ('daily', 'weekly'):
                names = remote.list(area)
                entries += [(area, n) for n in names if ARCHIVE_RE.fullmatch(n) and n + '.sha256' in names]
            entries.sort(key=lambda x: archive_date(x[1]), reverse=True)
            if not entries:
                raise BackupError('Keine vollstaendigen Sicherungen gefunden.')
            for i, (area, name) in enumerate(entries, 1):
                match = ARCHIVE_RE.fullmatch(name)
                say(f"{i:3}  {berlin(archive_date(name)):%d.%m.%Y %H:%M}  v{match.group(2)}  {'Woechentlich' if area == 'weekly' else 'Taeglich'}  [{match.group(3)}]")
            selected = int(ask('Sicherung auswaehlen (0 = Abbrechen)'))
            if selected == 0:
                return
            if not 1 <= selected <= len(entries):
                raise BackupError('Ungueltige Auswahl.')
            area, name = entries[selected-1]
            verified_download(remote, area, name, archive)
        elif choice == '2':
            original = Path(ask('Pfad zur lokalen ZIP-Datei')).expanduser()
            regular_file(original)
            if original.stat().st_size > MAX_BYTES:
                raise BackupError('Archiv ist groesser als 8 GiB.')
            shutil.copyfile(original, archive)
        else:
            raise BackupError('Ungueltige Auswahl.')
        unused, encrypted, size = archive_members(archive)
        password = secret('Passwort DIESER Sicherung') if encrypted else None
        extracted = stage / 'checked'
        extracted.mkdir(mode=0o700)
        manifest = unpack_checked(archive, extracted, password)
        current_size = sum((DATA / n).stat().st_size for n in DB_NAMES if (DATA / n).exists())
        if shutil.disk_usage(DATA).free < size + current_size + 128 * 1024**2:
            raise BackupError('Zu wenig Speicher fuer Wiederherstellung und Rueckfallsicherung.')
        say(f"\nGepruefte Sicherung: {manifest['created_at']}\nAnwendung: {manifest['app_version']}\nDateien: {', '.join(manifest['files'])}")
        say('\nACHTUNG: Der aktuelle Datenstand wird vollstaendig ersetzt.\nSpaetere Buchungen werden NICHT uebernommen. Es gelten wieder die\nBenutzer, Admin-Zugaenge und Terminal-Secrets aus der Sicherung.\nApache wird kurz gestoppt (auch andere dort gehostete Seiten).\nBestehende kienzlezeit-Browsersitzungen werden ungueltig.\nBackupverbindung bleibt erhalten; Cron bleibt anschliessend deaktiviert.')
        if ask('Zum Fortfahren WIEDERHERSTELLEN eingeben') != 'WIEDERHERSTELLEN':
            say('Abgebrochen. Keine Anwendungsdaten veraendert.')
            return
        disable_cron(config)
        apply_restore(extracted, manifest, config)
    if confirm('Automatische Sicherungen jetzt einrichten?', True):
        configure_schedule(config)


def status(config, test=False):
    s = read_json(STATE / 'status.json', {})
    say('Cronjob: ' + ('aktiv' if config.get('enabled') and CRON.exists() else 'deaktiviert'))
    say('Letzte erfolgreiche Sicherung: ' + s.get('last_success', 'noch keine'))
    if s.get('last_error'):
        say('WARNUNG: ' + s['last_error'])
    pending = list((STATE / 'outbox').glob('*/*.zip')) if (STATE / 'outbox').exists() else []
    say(f'Noch lokal ausstehende Uebertragungen (alle Ziele): {len(pending)}')
    if s.get('last_success') and now() - dt.datetime.fromisoformat(s['last_success']) > dt.timedelta(hours=36):
        say('WARNUNG: Letzte erfolgreiche Sicherung ist aelter als 36 Stunden!')
    if test:
        check_connection(Remote(config))
        say('Verbindung und Schreibzugriff: OK')


def main():
    os.umask(0o077)
    args = sys.argv[2:]
    if args not in ([], ['--cron'], ['--backup'], ['--status']):
        raise BackupError('Unbekanntes Argument. --help zeigt die Aufrufe.')
    interactive = not args
    private_dir(CONFIG_DIR)
    private_dir(STATE)
    with open(STATE / 'operation.lock', 'a') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            if args == ['--cron']:
                return
            raise BackupError('Eine weitere Backup-/Restore-Sitzung ist bereits aktiv.')
        config = load_config()
        if args == ['--cron'] and not restore_journal().exists() and not cron_due(config, read_json(STATE / 'status.json', {})):
            return
        prerequisites(interactive)
        if interactive:
            if config.get('transport') in ('ssh', 'smb'):
                apt_install(['openssh-client'] if config['transport'] == 'ssh' else ['smbclient'])
            sys.stdin = open('/dev/tty')
        if restore_journal().exists():
            if not interactive:
                raise BackupError('Unterbrochener Restore. Programm interaktiv starten; Apache-Schutz bleibt aktiv.')
            say('Unterbrochener Restore erkannt. Automatische Sicherung bleibt gesperrt.')
            journal = read_json(restore_journal())
            prompt = 'Bereits fertig eingespielten Datenstand freigeben?' if journal['phase'] in ('committed', 'rolled_back') else 'Den vorherigen lokalen Datenstand aus der Rueckfallsicherung wiederherstellen?'
            if confirm(prompt):
                rollback_restore(journal)
            else:
                return
        if args == ['--cron'] or args == ['--backup']:
            backup(config, scheduled=args == ['--cron'])
            return
        if args == ['--status']:
            status(config)
            return
        say(f'\nkienzlezeit – Backup und Wiederherstellung v{VERSION}\nvon Dr. Thomas Kienzle\n\nVoraussetzungen geprueft. Fehlende Pakete wurden installiert.')
        while True:
            say('\n  1  Speicherort einrichten oder aendern\n  2  Automatische Sicherung einrichten\n  3  Jetzt sichern\n  4  Sicherung wiederherstellen\n  5  Verbindung und Sicherungsstatus pruefen\n  6  Automatische Sicherung deaktivieren\n  0  Beenden')
            try:
                choice = ask('Auswahl')
                if choice == '0':
                    break
                elif choice == '1':
                    configure_target(config)
                elif choice == '2':
                    configure_schedule(config)
                elif choice == '3':
                    if 'archive_password' not in config:
                        say('Bitte zuerst unter 2 den Passwortschutz festlegen; Cron muss dabei nicht aktiviert werden.')
                    else:
                        backup(config)
                elif choice == '4':
                    restore(config)
                elif choice == '5':
                    status(config, test=True)
                elif choice == '6':
                    disable_cron(config)
                    say('Automatische Sicherung deaktiviert. Vorhandene Backups bleiben erhalten.')
                else:
                    say('Bitte einen Menuepunkt auswaehlen.')
            except (BackupError, OSError, ValueError, sqlite3.Error, zipfile.BadZipFile, RuntimeError) as exc:
                say('FEHLER: ' + str(exc))
                if restore_journal().exists():
                    raise BackupError('Restore muss zuerst wiederhergestellt werden. Programm erneut starten.')


if __name__ == '__main__':
    def interrupted(signum, frame):
        raise KeyboardInterrupt()
    signal.signal(signal.SIGTERM, interrupted)
    try:
        main()
    except (KeyboardInterrupt, EOFError):
        say('\nAbgebrochen. Vorhandene Sicherungen bleiben erhalten.')
        sys.exit(130)
    except Exception as exc:
        say('FEHLER: ' + str(exc))
        sys.exit(1)
PYTHON
