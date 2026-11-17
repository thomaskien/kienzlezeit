"""Installer HTTPS tests: temporary certificates/config only, no root or system changes.

Run: python3 -m unittest discover -s tests -p 'test_installer_https.py' -v
Requires bash, PHP CLI, OpenSSL and curl. Local Apache integration runs if available.
"""
import os
from pathlib import Path
import shlex
import shutil
import socket
import subprocess
import tempfile
import time
import unittest

ROOT = Path(__file__).resolve().parents[1]
PREFIX = (ROOT / 'installer.sh').read_text().split('[[ "${EUID}" -eq 0 ]]', 1)[0]

MOCKS = r'''
install() {
  local args=()
  while (($#)); do
    case "$1" in -o|-g) shift 2 ;; *) args+=("$1"); shift ;; esac
  done
  /usr/bin/install "${args[@]}"
}
a2query() { [[ -e "$MOCK_MODULE" ]]; }
a2enmod() { touch "$MOCK_MODULE"; }
a2dismod() { rm -f "$MOCK_MODULE"; }
a2ensite() { [[ -L "$TLS_ENABLED" ]] || ln -s "$TLS_SITE" "$TLS_ENABLED"; }
a2dissite() { rm -f "$TLS_ENABLED"; }
apache2ctl() {
  if [[ -e "$MOCK_FAIL_CONFIG" ]]; then rm -f "$MOCK_FAIL_CONFIG"; return 1; fi
  return 0
}
systemctl() { printf '%s\n' "$*" >> "$MOCK_ACTIONS"; }
curl() {
  printf '%s\n' "$@" > "$MOCK_CURL_ARGS"
  [[ ! -e "$MOCK_FAIL_CURL" ]]
}
'''


class HttpsInstallerTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='kz-https-test-')
        self.addCleanup(self.tmp.cleanup)
        self.base = Path(self.tmp.name)
        self.prefix = self.base / 'helpers.sh'
        self.prefix.write_text(PREFIX)
        self.source = self.base / 'source'
        self.source.mkdir()
        self.tls = self.base / 'tls'
        self.site = self.base / 'sites-available' / 'kienzlezeit-ssl.conf'
        self.enabled = self.base / 'sites-enabled' / self.site.name
        self.site.parent.mkdir()
        self.enabled.parent.mkdir()
        self.web = self.base / 'web'
        self.web.mkdir()
        self.module = self.base / 'ssl-enabled'
        self.fail_config = self.base / 'fail-config'
        self.fail_curl = self.base / 'fail-curl'
        self.env = {**os.environ,
                    'MOCK_MODULE': str(self.module), 'MOCK_FAIL_CONFIG': str(self.fail_config),
                    'MOCK_FAIL_CURL': str(self.fail_curl), 'MOCK_ACTIONS': str(self.base / 'actions'),
                    'MOCK_CURL_ARGS': str(self.base / 'curl-args')}

    def bash(self, code, stdin='', mocked=False, check=True):
        self.source.mkdir(exist_ok=True)
        values = {'SOURCE_DIR': self.source, 'TLS_DIR': self.tls, 'TLS_SITE': self.site,
                  'TLS_ENABLED': self.enabled, 'WEBROOT': self.web}
        script = 'source ' + shlex.quote(str(self.prefix)) + '\n'
        script += ''.join(k + '=' + shlex.quote(str(v)) + '\n' for k, v in values.items())
        if mocked:
            script += MOCKS + '\ntrap cleanup EXIT\n'
        script += code
        result = subprocess.run(['bash', '-c', script], input=stdin, text=True, capture_output=True,
                                env=self.env, timeout=40)
        if check:
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        return result

    def certificate(self, names='localhost 127.0.0.1 ::1', label='cert'):
        directory = self.base / label
        directory.mkdir(mode=0o700)
        self.bash('names="$(tls_addresses)"\ntls_create_certificate ' + shlex.quote(str(directory)) + ' "$names"', stdin=names)
        return directory

    def active(self):
        return self.tls / (self.tls / 'active').read_text().strip()

    def test_canonical_dns_ipv4_ipv6_and_duplicates(self):
        output = self.bash('tls_addresses', stdin='Kienzlebox.LOCAL., 192.168.1.20 [2001:0db8::1] kienzlebox.local').stdout
        self.assertEqual(output, 'kienzlebox.local\n192.168.1.20\n2001:db8::1\n')

    def test_rejects_urls_paths_ports_invalid_ips_and_injection(self):
        for value in ('', 'https://kienzlebox', 'box/path', 'box:443', '999.999.1.1', '*.local',
                      'box;touch', '$(touch /tmp/invalid)', 'box_name', '-box', 'box..local',
                      '[req]', '::1%eth0', 'a' * 64):
            with self.subTest(value=value):
                self.assertNotEqual(self.bash('tls_addresses', stdin=value, check=False).returncode, 0)

    def test_url_brackets_only_ipv6(self):
        self.assertEqual(self.bash('tls_url_host 2001:db8::1').stdout, '[2001:db8::1]')
        self.assertEqual(self.bash('tls_url_host 192.168.1.20').stdout, '192.168.1.20')

    def test_certificate_san_key_permissions_and_validity(self):
        directory = self.certificate()
        self.bash('tls_check_pair ' + shlex.quote(str(directory)))
        self.assertEqual((directory / 'server.key').stat().st_mode & 0o777, 0o600)
        text = subprocess.check_output(['openssl', 'x509', '-in', str(directory / 'server.crt'), '-text', '-noout'], text=True)
        self.assertIn('DNS:localhost', text)
        self.assertIn('IP Address:127.0.0.1', text)
        self.assertIn('CA:FALSE', text)
        self.assertIn('TLS Web Server Authentication', text)
        self.assertEqual(subprocess.run(['openssl', 'x509', '-in', str(directory / 'server.crt'), '-checkend', str(364*86400), '-noout'], capture_output=True).returncode, 0)
        self.assertNotEqual(subprocess.run(['openssl', 'x509', '-in', str(directory / 'server.crt'), '-checkend', str(366*86400), '-noout'], capture_output=True).returncode, 0)

    def test_wrong_key_or_altered_address_rejected(self):
        first = self.certificate(label='first')
        second = self.certificate(label='second')
        (first / 'addresses').write_text('wrong.example\n')
        self.assertNotEqual(self.bash('tls_check_pair ' + shlex.quote(str(first)), check=False).returncode, 0)
        (first / 'addresses').write_text('localhost\n127.0.0.1\n::1\n')
        shutil.copyfile(second / 'server.key', first / 'server.key')
        self.assertNotEqual(self.bash('tls_check_pair ' + shlex.quote(str(first)), check=False).returncode, 0)

    def test_vhost_contains_tls_only_no_http_redirect(self):
        directory = self.certificate()
        text = self.bash('tls_write_vhost ' + shlex.quote(str(directory))).stdout
        self.assertIn('<VirtualHost *:443>', text)
        self.assertIn('SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1', text)
        self.assertIn('ServerName localhost', text)
        self.assertIn('ServerAlias 127.0.0.1', text)
        self.assertNotIn('Redirect', text)
        self.assertNotIn('Strict-Transport-Security', text)
        self.assertNotIn('Listen ', text)

    def test_full_setup_uses_certificate_verified_probe(self):
        other = self.site.parent / '000-default.conf'
        other.write_text('existing HTTP site\n')
        self.bash('configure_https\nprintf "URL=%s\\n" "$HTTPS_URL"', '\n127.0.0.1 localhost\n', mocked=True)
        self.assertTrue(self.module.exists())
        self.assertTrue(self.enabled.is_symlink())
        self.assertTrue((self.active() / 'server.crt').exists())
        self.assertEqual(other.read_text(), 'existing HTTP site\n')
        args = (self.base / 'curl-args').read_text().splitlines()
        self.assertIn('--cacert', args)
        self.assertIn('--connect-to', args)
        self.assertIn('127.0.0.1:443:127.0.0.1:443', args)
        self.assertIn('https://127.0.0.1/kienzlezeit.php', args)
        self.assertNotIn('--insecure', args)
        self.assertNotIn('-k', args)

    def test_ipv6_primary_is_not_used_as_apache_servername(self):
        directory = self.certificate('::1 localhost')
        text = self.bash('tls_write_vhost ' + shlex.quote(str(directory))).stdout
        self.assertIn('ServerName localhost', text)
        self.assertIn('ServerAlias [::1]', text)
        (directory / 'addresses').write_text('::1\n')
        text = self.bash('tls_write_vhost ' + shlex.quote(str(directory))).stdout
        self.assertIn('ServerName kienzlezeit.invalid', text)
        self.assertIn('ServerAlias [::1]', text)
        self.assertNotIn('ServerName [', text)

    def test_rerun_keeps_certificate_and_generation(self):
        self.bash('configure_https', '\nlocalhost\n', mocked=True)
        generation = self.active()
        cert = (generation / 'server.crt').read_bytes()
        self.bash('configure_https', '\n\n', mocked=True)
        self.assertEqual(self.active(), generation)
        self.assertEqual((generation / 'server.crt').read_bytes(), cert)
        self.assertEqual(len(list(self.tls.glob('cert-*'))), 1)

    def test_explicit_renewal_keeps_old_certificate(self):
        self.bash('configure_https', '\nlocalhost\n', mocked=True)
        old = self.active()
        cert = (old / 'server.crt').read_bytes()
        self.bash('configure_https', '\nn\nnew.example 192.168.1.30\n', mocked=True)
        self.assertNotEqual(self.active(), old)
        self.assertEqual((old / 'server.crt').read_bytes(), cert)
        self.assertEqual((self.active() / 'addresses').read_text(), 'new.example\n192.168.1.30\n')

    def test_foreign_site_is_not_overwritten(self):
        self.site.write_text('User-managed HTTPS\n')
        result = self.bash('configure_https', '\n', mocked=True, check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.site.read_text(), 'User-managed HTTPS\n')
        self.assertFalse(self.module.exists())

    def test_failed_new_config_reverts_site_and_module(self):
        self.fail_config.touch()
        result = self.bash('configure_https', '\nlocalhost\n', mocked=True, check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(self.site.exists())
        self.assertFalse(self.enabled.exists())
        self.assertFalse(self.module.exists())
        self.assertFalse((self.tls / 'active').exists())

    def test_failed_probe_restores_previous_site_and_active_certificate(self):
        self.bash('configure_https', '\nlocalhost\n', mocked=True)
        old_site, old_active = self.site.read_bytes(), self.active()
        self.fail_curl.touch()
        result = self.bash('configure_https', '\nn\nnew.example\n', mocked=True, check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.site.read_bytes(), old_site)
        self.assertEqual(self.active(), old_active)
        self.assertTrue(self.enabled.exists())
        self.assertTrue(self.module.exists())

    def test_declining_setup_changes_nothing(self):
        self.bash('configure_https', 'n\n', mocked=True)
        self.assertFalse(self.tls.exists())
        self.assertFalse(self.site.exists())
        self.assertFalse(self.module.exists())

    def test_invalid_input_never_activates_https(self):
        result = self.bash('configure_https', '\nhttp://example\n', mocked=True, check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(self.module.exists())
        self.assertFalse(self.site.exists())

    def test_real_apache_https_and_http(self):
        httpd = next((p for p in ('/usr/sbin/httpd', '/usr/sbin/apache2') if Path(p).exists()), None)
        modules = next((Path(p) for p in ('/usr/libexec/apache2', '/usr/lib/apache2/modules') if Path(p).is_dir()), None)
        if not httpd or not modules:
            self.skipTest('Local Apache modules are not available')
        directory = self.certificate('::1 localhost 127.0.0.1')
        # A tiny static fixture is sufficient to verify the vhost, handshake,
        # SAN/hostname validation and continued availability of plain HTTP.
        (self.web / 'kienzlezeit.php').write_text('kienzlezeit TLS test')
        def free_port():
            with socket.socket() as sock:
                sock.bind(('127.0.0.1', 0))
                return sock.getsockname()[1]
        http_port, tls_port = free_port(), free_port()
        while tls_port == http_port:
            tls_port = free_port()
        compiled = subprocess.check_output([httpd, '-l'], text=True)
        required = [('mpm_prefork', 'prefork.c'), ('unixd', 'unixd.c'), ('authz_core', 'mod_authz_core.c'),
                    ('authz_host', 'mod_authz_host.c'), ('log_config', 'mod_log_config.c'), ('ssl', 'mod_ssl.c')]
        config = [f'ServerRoot "{self.base}"', f'DefaultRuntimeDir "{self.base}"',
                  f'Mutex "file:{self.base}" default', f'PidFile "{self.base}/httpd.pid"', 'ServerName localhost',
                  f'Listen 127.0.0.1:{http_port}', f'Listen 127.0.0.1:{tls_port}',
                  f'User #{os.getuid()}', f'Group #{os.getgid()}', f'ErrorLog "{self.base}/error.log"']
        for name, static_name in required:
            if static_name not in compiled:
                config.insert(0, f'LoadModule {name}_module "{modules}/mod_{name}.so"')
        # Load unixd/authz/log before using their configuration directives.
        loads = [line for line in config if line.startswith('LoadModule')]
        config = loads + [line for line in config if not line.startswith('LoadModule')]
        config += [f'DocumentRoot "{self.web}"', f'<Directory "{self.web}">', 'Require all granted', '</Directory>']
        vhost = self.bash('tls_write_vhost ' + shlex.quote(str(directory))).stdout.replace('*:443', f'*:{tls_port}')
        path = self.base / 'httpd.conf'
        path.write_text('\n'.join(config) + '\n' + vhost)
        env = {**os.environ, 'APACHE_LOG_DIR': str(self.base)}
        checked = subprocess.run([httpd, '-t', '-f', str(path)], text=True, capture_output=True, env=env)
        self.assertEqual(checked.returncode, 0, checked.stderr)
        # Also exercise Apache's canonical-name fallback with an IPv6-only
        # address list (the certificate still covers the other test names).
        (directory / 'addresses').write_text('::1\n')
        vhost = self.bash('tls_write_vhost ' + shlex.quote(str(directory))).stdout.replace('*:443', f'*:{tls_port}')
        path.write_text('\n'.join(config) + '\n' + vhost)
        checked = subprocess.run([httpd, '-t', '-f', str(path)], text=True, capture_output=True, env=env)
        self.assertEqual(checked.returncode, 0, checked.stderr)
        with tempfile.TemporaryFile() as log:
            server = subprocess.Popen([httpd, '-X', '-f', str(path)], stdout=log, stderr=log, env=env)
            try:
                for attempt in range(60):
                    if server.poll() is not None:
                        log.seek(0)
                        self.fail(log.read().decode())
                    try:
                        with socket.create_connection(('127.0.0.1', tls_port), timeout=.2):
                            break
                    except OSError:
                        time.sleep(.1)
                args = ['curl', '--silent', '--show-error', '--fail', '--noproxy', '*', '--max-time', '5']
                for url, extra in ((f'https://localhost:{tls_port}/kienzlezeit.php', ['--cacert', str(directory / 'server.crt')]),
                                   (f'https://127.0.0.1:{tls_port}/kienzlezeit.php', ['--cacert', str(directory / 'server.crt')]),
                                   (f'https://[::1]:{tls_port}/kienzlezeit.php', ['--cacert', str(directory / 'server.crt'), '--connect-to', f'[::1]:{tls_port}:127.0.0.1:{tls_port}']),
                                   (f'http://127.0.0.1:{http_port}/kienzlezeit.php', [])):
                    response = subprocess.run(args + extra + [url], text=True, capture_output=True)
                    self.assertEqual(response.returncode, 0, response.stderr)
                    self.assertEqual(response.stdout, 'kienzlezeit TLS test')
                wrong = subprocess.run(args + ['--cacert', str(directory / 'server.crt'), '--resolve', f'wrong.example:{tls_port}:127.0.0.1', f'https://wrong.example:{tls_port}/kienzlezeit.php'], capture_output=True)
                self.assertNotEqual(wrong.returncode, 0)
            finally:
                server.terminate()
                try:
                    server.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait()


if __name__ == '__main__':
    unittest.main()
