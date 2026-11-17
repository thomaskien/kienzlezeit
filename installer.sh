#!/usr/bin/env bash
#
# kienzlezeit – Installer fuer Apache/SQLite
#
# Installer-Version: 1.6.9
# Anwendungs-Version: 1.6.7
# Author: Dr. Thomas Kienzle
# Stand: 2026-11-17
#
# Changelog (komplett):
# - Installer 1.6.9 (2026-11-17):
#   - Richtet optional (Standard: Ja) Apache-HTTPS mit selbstsigniertem Zertifikat ein.
#   - Fragt IP-Adressen/Hostnamen fuer SAN ab; erhaelt Zertifikate bei Wiederholung.
#   - Prueft TLS und Apache; nimmt eigene HTTPS-Aenderungen bei Fehlern zurueck.
#   - HTTP bleibt fuer bestehende Terminals ohne Umleitung erreichbar.
#
# - Installer 1.6.8 (2026-07-18):
#   - Installiert kienzlezeit 1.6.7 und den RFID-Endpunkt 1.3.
#   - Neue Buchungen werden trotz erkannter fehlender Ausbuchung gespeichert und gewarnt.
#
# - Installer 1.6.7 (2026-07-08):
#   - Installiert kienzlezeit 1.6.6 mit neu geordnetem Admin-Menue.
#   - Installiert die stichtagsbezogene Monatsuebersicht aller aktiven Mitarbeitenden.
#
# - Installer 1.6.6 (2026-07-06):
#   - Kann als einzelne Datei gestartet werden und laedt die benoetigten
#     Anwendungsdateien direkt aus dem GitHub-Repository nach.
#   - Der Quell-Ref kann fuer Tests oder Releases mit KIENZLEZEIT_REF gesetzt werden.
#
# - 1.6.5 (2026-07-06):
#   - Installiert kienzlezeit 1.6.5 und den RFID-Endpunkt 1.2.
#   - Administrativ abgeschlossene Altbuchungen blockieren das Terminal nicht mehr.
#
# - 1.6.4 (2026-07-06):
#   - Installiert kienzlezeit 1.6.4 mit frei editierbarer Arbeitszeit in der Buchungspruefung.
#   - Vorhandene Sollzeit wird vorbelegt; ein Sollplan ist fuer die Korrektur nicht erforderlich.
#
# - 1.6.3 (2026-07-06):
#   - Installiert kienzlezeit 1.6.3 und die Auslagenverwaltung 1.1.3.
#   - Beleg-vorhanden wird ausschliesslich im Adminbereich gepflegt.
#   - Admin-Uebersicht zeigt den buchhalterischen Belegstatus je Vorgang.
#
# - 1.6.2 (2026-07-05):
#   - Installiert kienzlezeit 1.6.2 und die Auslagenverwaltung 1.1.2.
#   - Belegschalter serverseitig sichtbar, Mitarbeiteransicht ohne QR-Code.
#   - Vollstaendige Namen von Admin-Konten sind editierbar.
#
# - 1.6.1 (2026-07-05):
#   - Installiert kienzlezeit 1.6.1 und die Auslagenverwaltung 1.1.1.
#   - Migriert Auslagenposten additiv um den Beleg-vorhanden-Status.
#   - Die Web-Detailansicht zeigt den lokal erzeugten EPC-QR-Code.
#
# - 1.6 (2026-07-05):
#   - Installiert kienzlezeit 1.6 und die integrierte Auslagenverwaltung 1.1.
#   - Installiert qrencode fuer lokale EPC-QR-Codes.
#   - Uebernimmt eine vorhandene Auslagen-Datenbank nur nach Rueckfrage,
#     sichert Bestandsdaten und migriert sie additiv ausserhalb des Webroots.
#   - Setzt getrennte, restriktive Rechte fuer beide SQLite-Datenbanken.
#
# - 1.5 (2026-07-05):
#   - Installiert die Serveroberflaeche 1.5 mit globaler Wochenendanzeige,
#     verdichteter Wochenplanung, neuen Mitarbeiter-Kennzahlen und Anwesenheitskacheln.
#   - Keine strukturelle Datenbankmigration erforderlich; neue Anzeigeoptionen
#     werden additiv in der vorhandenen Einstellungstabelle angelegt.
#
# - 1.4 (2026-07-05):
#   - Installiert die Serveroberflaeche 1.4 und migriert SQLite additiv auf Schema 3.
#   - Sichert vorhandene Datenbanken vor dem Update und erhaelt Rohbuchungen vollstaendig.
#   - Richtet Mitarbeiterkorrekturen, Buchungspruefung, Salden, Oeffnungszeiten,
#     zusaetzliche Anwesenheiten, Bundeslaender-Feiertage und weitere Admin-Konten ein.
#
# - 1.3 (2026-07-05):
#   - Installiert die Serveroberflaeche 1.3 mit loeschbaren Testkarten.
#   - Keine strukturelle Datenbankmigration erforderlich.
#
# - 1.2 (2026-07-05):
#   - Installiert die Serveroberflaeche 1.2 mit frei waehlbarer Secret-Laenge.
#   - Keine Datenbankmigration erforderlich.
#
# - 1.1 (2026-07-04):
#   - Fuehrt die additive Datenbankmigration auf Schema 2 aus.
#   - Erzeugt die lokale Schluesseldatei fuer verschluesselte Terminal-Secrets.
#   - Prueft zusaetzlich die fuer die Verschluesselung benoetigte OpenSSL-Erweiterung.
#   - Fragt keine vorhandenen Terminal-Secrets ab und uebernimmt sie nicht.
#
# - 1.0 (2026-07-02):
#   - Erste funktionsfaehige Installation fuer Debian/Ubuntu und Apache.
#   - Installiert fehlende PHP-/SQLite-Module nach Rueckfrage.
#   - Richtet Webdateien, SQLite-Verzeichnis, Rechte, Admin und Terminal ein.
#   - Wiederholbarer Lauf ohne ungefragtes Ueberschreiben von Daten oder Schluesseln.
#   - Interaktive, protokollierte Notfallruecksetzung des Admin-Passworts.

set -Eeuo pipefail

INSTALLER_VERSION="1.6.9"
APP_VERSION="1.6.7"
APP_AUTHOR="Dr. Thomas Kienzle"
GITHUB_REPOSITORY="thomaskien/kienzlezeit"
SOURCE_REF="${KIENZLEZEIT_REF:-main}"
RAW_BASE_URL="${KIENZLEZEIT_RAW_BASE:-https://raw.githubusercontent.com/${GITHUB_REPOSITORY}/${SOURCE_REF}}"
WEBROOT="/var/www/html"
DATA_DIR="/var/lib/kienzlezeit"
BACKUP_DIR="${DATA_DIR}/backups"
DB_FILE="${DATA_DIR}/kienzlezeit.sqlite"
EXPENSE_DB_FILE="${DATA_DIR}/auslagen.sqlite"
SECRET_KEY_FILE="${DATA_DIR}/terminal-secret.key"
WEB_USER="www-data"
WEB_GROUP="www-data"
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR=""
TLS_DIR="/etc/kienzlezeit/tls"
TLS_SITE="/etc/apache2/sites-available/kienzlezeit-ssl.conf"
TLS_ENABLED="/etc/apache2/sites-enabled/kienzlezeit-ssl.conf"
HTTPS_URL=""
HTTPS_PENDING=0
TLS_OLD_SITE=0
TLS_OLD_ENABLED=0
TLS_OLD_MODULE=0
TLS_OLD_ACTIVE=0

say() { printf '%s\n' "$*"; }
die() { printf 'FEHLER: %s\n' "$*" >&2; exit 1; }
confirm() {
  local prompt="$1" answer
  read -r -p "${prompt} [j/N] " answer
  [[ "${answer,,}" == "j" || "${answer,,}" == "ja" ]]
}
random_hex() {
  local bytes="$1"
  od -An -N"${bytes}" -tx1 /dev/urandom | tr -d ' \n'
}
json_install() {
  local username="$1" password="$2" terminal_key="$3"
  printf '{"admin_username":"%s","admin_display_name":"Dr. Thomas Kienzle","admin_password":"%s","terminal_code":"eingang-1","terminal_label":"Eingang","terminal_key":"%s"}' \
    "${username}" "${password}" "${terminal_key}" | php "${WEBROOT}/kienzlezeit.php" --install
}
json_reset() {
  local username="$1" password="$2"
  printf '{"admin_username":"%s","admin_password":"%s"}' "${username}" "${password}" | php "${WEBROOT}/kienzlezeit.php" --reset-admin
}
cleanup() {
  if [[ "${HTTPS_PENDING}" == 1 ]]; then
    rollback_https
  fi
  if [[ -n "${SOURCE_DIR}" && -d "${SOURCE_DIR}" ]]; then
    rm -rf -- "${SOURCE_DIR}"
  fi
}
download_source() {
  local source_name="$1" target part_file url
  target="${SOURCE_DIR}/${source_name}"
  part_file="${target}.part"
  url="${RAW_BASE_URL}/${source_name}"
  install -d -m 0755 "$(dirname -- "${target}")"
  say "Lade ${source_name} aus GitHub ..."
  curl --fail --location --silent --show-error \
    --retry 3 --retry-delay 2 --connect-timeout 15 --max-time 180 \
    --proto '=https' --tlsv1.2 \
    --output "${part_file}" "${url}" \
    || die "Download fehlgeschlagen: ${url}"
  [[ -s "${part_file}" ]] || die "Leere Quelldatei empfangen: ${url}"
  mv -- "${part_file}" "${target}"
}

# BEGIN HTTPS HELPERS
confirm_default_yes() {
  local answer
  read -r -p "$1 [J/n] " answer || return 1
  case "${answer}" in ''|j|J|ja|Ja|JA) return 0 ;; *) return 1 ;; esac
}

# Canonical lines: one DNS name or IP address per line. Never evaluate this text
# as shell code. PHP is already an installer dependency, including filter/openssl.
tls_addresses() {
  php -r '
    $input=trim(stream_get_contents(STDIN));
    $out=[];
    foreach(preg_split("/[\\s,]+/",$input,-1,PREG_SPLIT_NO_EMPTY) as $name) {
      if(str_starts_with($name,"[") && str_ends_with($name,"]")) {
        $name=substr($name,1,-1);
        if(filter_var($name,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)===false) {
          fwrite(STDERR,"Eckige Klammern sind nur fuer IPv6-Adressen erlaubt.\n");exit(1);
        }
      }
      if(filter_var($name,FILTER_VALIDATE_IP)!==false) {
        $name=inet_ntop(inet_pton($name));
      } else {
        $name=strtolower(rtrim($name,"."));
        if(strlen($name)>253 || preg_match("/^[0-9.]+$/D",$name)
          || !preg_match("/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/D",$name)) {
          fwrite(STDERR,"Ungueltige Adresse. Nur IP/Hostname ohne http(s)://, Port, Pfad oder Wildcard eingeben.\n"); exit(1);
        }
      }
      if(!in_array($name,$out,true)) $out[]=$name;
    }
    if(count($out)<1 || count($out)>32) {fwrite(STDERR,"Bitte 1 bis 32 Adressen angeben.\n");exit(1);}
    echo implode("\n",$out)."\n";
  '
}

tls_url_host() {
  case "$1" in *:*) printf '[%s]' "$1" ;; *) printf '%s' "$1" ;; esac
}

tls_create_certificate() {
  local directory="$1" addresses="$2" address type index=0
  [[ -d "${directory}" && ! -L "${directory}" ]] || return 1
  [[ ! -e "${directory}/server.key" && ! -e "${directory}/server.crt" ]] || return 1
  printf '%s\n' "${addresses}" > "${directory}/addresses"
  {
    printf '%s\n' '[req]' 'prompt = no' 'distinguished_name = dn' 'x509_extensions = server' \
      '[dn]' 'CN = kienzlezeit' '[server]' 'basicConstraints = critical,CA:FALSE' \
      'keyUsage = critical,digitalSignature,keyEncipherment' 'extendedKeyUsage = serverAuth' \
      'subjectAltName = @names' '[names]'
    while IFS= read -r address; do
      [[ -n "${address}" ]] || continue
      index=$((index + 1))
      if [[ "${address}" == *:* || "${address}" =~ ^[0-9.]+$ ]]; then type=IP; else type=DNS; fi
      printf '%s.%s = %s\n' "${type}" "${index}" "${address}"
    done <<< "${addresses}"
  } > "${directory}/openssl.cnf"
  (umask 077; openssl req -x509 -newkey rsa:3072 -nodes -sha256 -days 365 \
    -config "${directory}/openssl.cnf" -keyout "${directory}/server.key" \
    -out "${directory}/server.crt" >/dev/null 2>&1) || return 1
  chmod 0600 "${directory}/server.key"
  chmod 0644 "${directory}/server.crt"
}

tls_check_pair() {
  local directory="$1" cert_public key_public address
  [[ -f "${directory}/server.crt" && ! -L "${directory}/server.crt" \
    && -f "${directory}/server.key" && ! -L "${directory}/server.key" \
    && -f "${directory}/addresses" && ! -L "${directory}/addresses" ]] || return 1
  cert_public="$(openssl x509 -in "${directory}/server.crt" -pubkey -noout)" || return 1
  key_public="$(openssl pkey -in "${directory}/server.key" -passin pass: -pubout 2>/dev/null)" || return 1
  [[ "${cert_public}" == "${key_public}" ]] || return 1
  # Refuse unsafe metadata before it can reach Apache's configuration.
  tls_addresses < "${directory}/addresses" > "${SOURCE_DIR}/tls-validated-addresses" || return 1
  cmp -s "${directory}/addresses" "${SOURCE_DIR}/tls-validated-addresses" || return 1
  while IFS= read -r address; do
    if [[ "${address}" == *:* || "${address}" =~ ^[0-9.]+$ ]]; then
      openssl x509 -in "${directory}/server.crt" -noout -checkip "${address}" >/dev/null || return 1
    else
      openssl x509 -in "${directory}/server.crt" -noout -checkhost "${address}" >/dev/null || return 1
    fi
  done < "${directory}/addresses"
}

tls_write_vhost() {
  local directory="$1" primary='' address
  # Apache does not accept IPv6 literals in ServerName. Keep IPv6 in SANs
  # and aliases; select a DNS/IPv4 name, or a local-only canonical fallback.
  while IFS= read -r address; do
    if [[ "${address}" != *:* ]]; then primary="${address}"; break; fi
  done < "${directory}/addresses"
  primary="${primary:-kienzlezeit.invalid}"
  printf '%s\n' '# Managed by kienzlezeit installer (HTTPS)' '<VirtualHost *:443>'
  printf '    ServerName %s\n' "${primary}"
  while IFS= read -r address; do
    if [[ "${address}" == "${primary}" ]]; then continue; fi
    printf '    ServerAlias %s\n' "$(tls_url_host "${address}")"
  done < "${directory}/addresses"
  printf '    DocumentRoot "%s"\n' "${WEBROOT}"
  printf '%s\n' '    SSLEngine on' '    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1' \
    '    SSLCipherSuite HIGH:!aNULL:!MD5:!3DES' '    SSLSessionTickets off'
  printf '    SSLCertificateFile "%s/server.crt"\n' "${directory}"
  printf '    SSLCertificateKeyFile "%s/server.key"\n' "${directory}"
  printf '    <Directory "%s">\n' "${WEBROOT}"
  printf '%s\n' '        Options -Indexes' '        Require all granted' '    </Directory>' \
    '    ErrorLog ${APACHE_LOG_DIR}/kienzlezeit-ssl-error.log' \
    '    CustomLog ${APACHE_LOG_DIR}/kienzlezeit-ssl-access.log combined' '</VirtualHost>'
}

rollback_https() {
  # Only this installer's site/module changes are reverted. Other sites/certs
  # and immutable certificate generations are never removed.
  HTTPS_PENDING=0
  say 'HTTPS-Einrichtung nicht abgeschlossen; vorherige Apache-Konfiguration wird wiederhergestellt.'
  if [[ "${TLS_OLD_ENABLED}" == 0 ]]; then a2dissite kienzlezeit-ssl.conf >/dev/null 2>&1 || true; fi
  if [[ "${TLS_OLD_SITE}" == 1 ]]; then
    cp -p "${SOURCE_DIR}/tls-site.previous" "${TLS_SITE}.restore" && mv -f "${TLS_SITE}.restore" "${TLS_SITE}" || return 1
  else
    rm -f -- "${TLS_SITE}"
  fi
  if [[ "${TLS_OLD_ACTIVE}" == 1 ]]; then
    cp -p "${SOURCE_DIR}/tls-active.previous" "${TLS_DIR}/active.restore" && mv -f "${TLS_DIR}/active.restore" "${TLS_DIR}/active" || return 1
  else
    rm -f -- "${TLS_DIR}/active"
  fi
  if [[ "${TLS_OLD_MODULE}" == 0 ]]; then a2dismod ssl >/dev/null 2>&1 || true; fi
  if apache2ctl configtest && systemctl is-active --quiet apache2; then
    systemctl reload apache2 || say 'WARNUNG: Apache konnte nach Ruecknahme nicht neu geladen werden.'
  fi
}

configure_https() {
  local generation='' directory='' addresses='' entered='' primary='' answer='' existing=0 tool
  say ''
  say 'HTTPS fuer die Weboberflaeche (selbstsigniertes Zertifikat)'
  say 'HTTP bleibt ohne Umleitung fuer bestehende Terminals erreichbar.'
  if ! confirm_default_yes 'HTTPS jetzt einrichten bzw. vorhandene Einrichtung pruefen?'; then
    say 'HTTPS-Konfiguration unveraendert. Keine neue HTTPS-Einrichtung vorgenommen.'
    return
  fi
  for tool in openssl a2enmod a2dismod a2query a2ensite a2dissite apache2ctl systemctl; do
    command -v "${tool}" >/dev/null || die "Fuer HTTPS fehlt ${tool}. Debian/Ubuntu mit Apache erforderlich."
  done
  [[ ! -L "${TLS_DIR}" ]] || die 'TLS-Verzeichnis darf kein Symlink sein.'
  if [[ -e "${TLS_SITE}" || -L "${TLS_SITE}" ]]; then
    [[ -f "${TLS_SITE}" && ! -L "${TLS_SITE}" ]] || die 'Vorhandene HTTPS-Site ist keine regulaere Datei; unveraendert abgebrochen.'
    IFS= read -r answer < "${TLS_SITE}"
    [[ "${answer}" == '# Managed by kienzlezeit installer (HTTPS)' ]] || die 'Vorhandene kienzlezeit-ssl.conf wird nicht vom Installer verwaltet; bitte bestehende Konfiguration pruefen.'
    TLS_OLD_SITE=1
  fi
  if [[ -e "${TLS_ENABLED}" || -L "${TLS_ENABLED}" ]]; then
    [[ -L "${TLS_ENABLED}" && "${TLS_ENABLED}" -ef "${TLS_SITE}" ]] || die 'Aktivierte HTTPS-Site verweist auf eine fremde Konfiguration; unveraendert abgebrochen.'
    TLS_OLD_ENABLED=1
  fi
  a2query -m ssl >/dev/null 2>&1 && TLS_OLD_MODULE=1
  if [[ -e "${TLS_DIR}/active" || -L "${TLS_DIR}/active" ]]; then
    [[ -f "${TLS_DIR}/active" && ! -L "${TLS_DIR}/active" ]] || die 'Ungueltige TLS-Metadaten; nichts ueberschrieben.'
    IFS= read -r generation < "${TLS_DIR}/active"
    [[ "${generation}" =~ ^cert-[0-9]{8}-[0-9]{6}-[0-9a-f]{8}$ ]] || die 'Ungueltige Zertifikatskennung.'
    directory="${TLS_DIR}/${generation}"
    [[ ! -L "${directory}" ]] && tls_check_pair "${directory}" || die 'Vorhandenes Zertifikat/Schluessel oder Adressliste ist ungueltig. Keine automatische Ersetzung.'
    cp -p "${TLS_DIR}/active" "${SOURCE_DIR}/tls-active.previous"
    TLS_OLD_ACTIVE=1
    say 'Vorhandenes Zertifikat fuer:'
    sed 's/^/  /' "${directory}/addresses"
    openssl x509 -in "${directory}/server.crt" -noout -dates -fingerprint -sha256
    if openssl x509 -in "${directory}/server.crt" -checkend 0 -noout >/dev/null; then
      openssl x509 -in "${directory}/server.crt" -checkend 2592000 -noout >/dev/null || say 'WARNUNG: Zertifikat laeuft innerhalb von 30 Tagen ab; Erneuerung empfohlen.'
      if confirm_default_yes 'Vorhandenes Zertifikat und Adressen beibehalten?'; then existing=1; fi
    else
      confirm_default_yes 'Zertifikat ist abgelaufen. Neues Zertifikat erstellen?' || die 'Abgebrochen; abgelaufenes Zertifikat wurde nicht ersetzt.'
    fi
  fi
  if [[ "${existing}" == 0 ]]; then
    say 'Alle verwendeten IP-Adressen/Hostnamen angeben, getrennt durch Leerzeichen oder Komma.'
    say 'Die erste Adresse wird als URL ausgegeben. Beispiel: 192.168.1.20 kienzlebox kienzlebox.local'
    read -r -p 'Adresse(n), ohne http(s):// und ohne Pfad: ' entered || die 'Keine Adresse eingegeben.'
    addresses="$(printf '%s' "${entered}" | tls_addresses)" || die 'Adressliste ungueltig; HTTPS nicht veraendert.'
    install -d -o root -g root -m 0700 "${TLS_DIR}"
    generation="cert-$(date '+%Y%m%d-%H%M%S')-$(random_hex 4)"
    directory="${TLS_DIR}/${generation}"
    [[ ! -e "${directory}" ]] || die 'Zertifikatsverzeichnis existiert bereits.'
    install -d -o root -g root -m 0700 "${directory}"
    tls_create_certificate "${directory}" "${addresses}" && tls_check_pair "${directory}" || die 'Zertifikat konnte nicht erzeugt/geprueft werden; Apache unveraendert.'
    say 'Neues selbstsigniertes Zertifikat erstellt (365 Tage gueltig).'
  fi
  if [[ "${TLS_OLD_SITE}" == 1 ]]; then cp -p "${TLS_SITE}" "${SOURCE_DIR}/tls-site.previous"; fi
  tls_write_vhost "${directory}" > "${SOURCE_DIR}/kienzlezeit-ssl.conf"
  HTTPS_PENDING=1
  a2enmod ssl >/dev/null || die 'Apache-SSL-Modul konnte nicht aktiviert werden.'
  install -o root -g root -m 0644 "${SOURCE_DIR}/kienzlezeit-ssl.conf" "${TLS_SITE}.new"
  mv -f "${TLS_SITE}.new" "${TLS_SITE}"
  a2ensite kienzlezeit-ssl.conf >/dev/null || die 'HTTPS-Site konnte nicht aktiviert werden.'
  apache2ctl configtest || die 'HTTPS-Konfiguration fehlerhaft; vorherige Konfiguration wird wiederhergestellt.'
  systemctl reload apache2 || die 'Apache konnte HTTPS nicht laden; vorherige Konfiguration wird wiederhergestellt.'
  IFS= read -r primary < "${directory}/addresses"
  HTTPS_URL="https://$(tls_url_host "${primary}")"
  # Use the generated certificate as trust anchor, not --insecure. This detects
  # SAN mismatch, wrong/default vhost, missing listener and a failed TLS handshake.
  curl --fail --silent --show-error --noproxy '*' --cacert "${directory}/server.crt" \
    --connect-to "$(tls_url_host "${primary}"):443:127.0.0.1:443" --connect-timeout 5 --max-time 20 \
    --retry 2 --retry-connrefused --retry-delay 1 --output /dev/null \
    "${HTTPS_URL}/kienzlezeit.php" || die 'Lokaler HTTPS-Test fehlgeschlagen. Port 443, vorhandene HTTPS-VHosts und Adresszuordnung pruefen.'
  printf '%s\n' "${generation}" > "${TLS_DIR}/active.new"
  chmod 0600 "${TLS_DIR}/active.new"
  mv -f "${TLS_DIR}/active.new" "${TLS_DIR}/active"
  HTTPS_PENDING=0
  say "HTTPS erfolgreich geprueft: ${HTTPS_URL}/kienzlezeit.php"
  openssl x509 -in "${directory}/server.crt" -noout -dates -fingerprint -sha256
  say 'Firefox meldet wegen der Selbstsignierung zunaechst einen unbekannten Aussteller.'
  say 'Adresse und SHA-256-Fingerabdruck pruefen, dann die Ausnahme fuer diesen Server bestaetigen.'
  say 'Bei spaeterer Erneuerung muss dem neuen Zertifikat erneut vertraut werden.'
}
# END HTTPS HELPERS

[[ "${EUID}" -eq 0 ]] || die "Bitte mit sudo oder als root ausfuehren."
command -v apt-get >/dev/null 2>&1 || die "Dieser Installer erwartet Debian/Ubuntu mit apt."

say "kienzlezeit v${APP_VERSION} von ${APP_AUTHOR}"
say "Installer v${INSTALLER_VERSION}; Quelle: GitHub ${GITHUB_REPOSITORY}@${SOURCE_REF}"
say "Ziel: Apache unter ${WEBROOT}, Datenbank ${DB_FILE}"
say ""

required_packages=(
  apache2
  ca-certificates
  curl
  openssl
  libapache2-mod-php
  php-cli
  php-sqlite3
  php-mbstring
  php-xml
  php-gd
  sqlite3
  qrencode
)
missing_packages=()
for package in "${required_packages[@]}"; do
  if ! dpkg-query -W -f='${Status}' "${package}" 2>/dev/null | grep -q 'ok installed'; then
    missing_packages+=("${package}")
  fi
done

if ((${#missing_packages[@]})); then
  say "Folgende benoetigte Pakete fehlen:"
  printf '  - %s\n' "${missing_packages[@]}"
  if confirm "Fehlende Pakete jetzt mit apt installieren?"; then
    apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "${missing_packages[@]}"
  else
    die "Installation wurde ohne Veraenderung der Pakete beendet."
  fi
else
  say "Alle benoetigten Apache-, PHP- und SQLite-Pakete sind vorhanden."
fi

command -v curl >/dev/null 2>&1 || die "curl ist fuer den Download aus GitHub erforderlich."
SOURCE_DIR="$(mktemp -d -t kienzlezeit-installer.XXXXXXXX)"
trap cleanup EXIT
for source_file in kienzlezeit.php rfid-scan.php kienzlezeit.png auslagen/auslagen.php; do
  download_source "${source_file}"
done

php -r 'exit(extension_loaded("pdo_sqlite") && extension_loaded("mbstring") && extension_loaded("openssl") ? 0 : 1);' \
  || die "PHP-Erweiterungen pdo_sqlite, mbstring oder openssl sind nicht aktiv."
php -l "${SOURCE_DIR}/kienzlezeit.php" >/dev/null \
  || die "Die geladene Datei kienzlezeit.php ist syntaktisch ungueltig."
php -l "${SOURCE_DIR}/rfid-scan.php" >/dev/null \
  || die "Die geladene Datei rfid-scan.php ist syntaktisch ungueltig."
php -l "${SOURCE_DIR}/auslagen/auslagen.php" >/dev/null \
  || die "Die geladene Datei auslagen/auslagen.php ist syntaktisch ungueltig."

install -d -o "${WEB_USER}" -g "${WEB_GROUP}" -m 0750 "${DATA_DIR}"
install -d -o "${WEB_USER}" -g "${WEB_GROUP}" -m 0750 "${BACKUP_DIR}"
install -d -o root -g root -m 0755 "${WEBROOT}"

if [[ ! -f "${SECRET_KEY_FILE}" ]]; then
  secret_master_key="$(random_hex 32)"
  printf '%s\n' "${secret_master_key}" > "${SECRET_KEY_FILE}"
  say "Schluesseldatei fuer Terminal-Secrets erzeugt: ${SECRET_KEY_FILE}"
fi
grep -Eq '^[0-9a-fA-F]{64}$' "${SECRET_KEY_FILE}" \
  || die "Die Schluesseldatei ${SECRET_KEY_FILE} ist ungueltig."
chown root:"${WEB_GROUP}" "${SECRET_KEY_FILE}"
chmod 0640 "${SECRET_KEY_FILE}"

deploy_file() {
  local source_name="$1" target_name="$2" mode="$3" owner="$4" group="$5"
  local source="${SOURCE_DIR}/${source_name}" target="${WEBROOT}/${target_name}"
  if [[ -e "${target}" ]] && cmp -s "${source}" "${target}"; then
    say "Unveraendert: ${target}"
    return
  fi
  if [[ -e "${target}" ]] && ! confirm "Vorhandene Datei ${target} durch v${APP_VERSION} ersetzen?"; then
    die "Abbruch: ${target} wurde nicht veraendert."
  fi
  install -o "${owner}" -g "${group}" -m "${mode}" "${source}" "${target}"
  say "Installiert: ${target}"
}

deploy_file "kienzlezeit.php" "kienzlezeit.php" 0640 root "${WEB_GROUP}"
deploy_file "rfid-scan.php" "rfid-scan.php" 0640 root "${WEB_GROUP}"
deploy_file "kienzlezeit.png" "kienzlezeit.png" 0644 root root
deploy_file "auslagen/auslagen.php" "auslagen.php" 0640 root "${WEB_GROUP}"

admin_count=0
terminal_count=0
if [[ -f "${DB_FILE}" ]]; then
  backup_file="${BACKUP_DIR}/kienzlezeit-vor-installation-$(date '+%Y%m%d-%H%M%S').sqlite"
  sqlite3 "${DB_FILE}" ".backup '${backup_file}'" \
    || die "Sicherheitskopie der vorhandenen Datenbank ist fehlgeschlagen."
  chown "${WEB_USER}:${WEB_GROUP}" "${backup_file}"
  chmod 0640 "${backup_file}"
  say "Sicherheitskopie erstellt: ${backup_file}"
  admin_count="$(sqlite3 "${DB_FILE}" 'SELECT COUNT(*) FROM admin_users;' 2>/dev/null || printf '0')"
  terminal_count="$(sqlite3 "${DB_FILE}" 'SELECT COUNT(*) FROM terminals;' 2>/dev/null || printf '0')"
fi

if [[ -f "${EXPENSE_DB_FILE}" ]]; then
  expense_backup_file="${BACKUP_DIR}/auslagen-vor-installation-$(date '+%Y%m%d-%H%M%S').sqlite"
  sqlite3 "${EXPENSE_DB_FILE}" ".backup '${expense_backup_file}'" \
    || die "Sicherheitskopie der vorhandenen Auslagen-Datenbank ist fehlgeschlagen."
  chown "${WEB_USER}:${WEB_GROUP}" "${expense_backup_file}"
  chmod 0640 "${expense_backup_file}"
  say "Sicherheitskopie erstellt: ${expense_backup_file}"
elif [[ -f "${SCRIPT_DIR}/auslagen/auslagen.sqlite" ]] && confirm "Mitgelieferte Auslagen-Datenbank als Altbestand uebernehmen?"; then
  install -o "${WEB_USER}" -g "${WEB_GROUP}" -m 0640 "${SCRIPT_DIR}/auslagen/auslagen.sqlite" "${EXPENSE_DB_FILE}"
  say "Auslagen-Altbestand uebernommen: ${EXPENSE_DB_FILE}"
fi

initial_admin_password="$(random_hex 9)"
initial_terminal_key="$(random_hex 24)"
admin_username="admin"

if ((admin_count == 0)); then
  read -r -p "Benutzername fuer den ersten Admin [admin]: " entered_username
  if [[ -n "${entered_username}" ]]; then
    [[ "${entered_username}" =~ ^[A-Za-z0-9_.-]{3,64}$ ]] || die "Ungueltiger Admin-Benutzername."
    admin_username="${entered_username}"
  fi
fi

install_result="$(json_install "${admin_username}" "${initial_admin_password}" "${initial_terminal_key}")" \
  || die "Die SQLite-Datenbank konnte nicht initialisiert werden."
say "${install_result}"

AUSLAGEN_DB_PATH="${EXPENSE_DB_FILE}" KZ_DB_PATH="${DB_FILE}" php "${WEBROOT}/auslagen.php" --migrate \
  || die "Die Auslagen-Datenbank konnte nicht initialisiert oder migriert werden."

chown "${WEB_USER}:${WEB_GROUP}" "${DB_FILE}"
chmod 0640 "${DB_FILE}"
chown "${WEB_USER}:${WEB_GROUP}" "${EXPENSE_DB_FILE}"
chmod 0640 "${EXPENSE_DB_FILE}"
find "${DATA_DIR}" -maxdepth 1 -type f \( -name 'kienzlezeit.sqlite-wal' -o -name 'kienzlezeit.sqlite-shm' \) \
  -exec chown "${WEB_USER}:${WEB_GROUP}" {} + -exec chmod 0640 {} + 2>/dev/null || true
find "${DATA_DIR}" -maxdepth 1 -type f \( -name 'auslagen.sqlite-wal' -o -name 'auslagen.sqlite-shm' \) \
  -exec chown "${WEB_USER}:${WEB_GROUP}" {} + -exec chmod 0640 {} + 2>/dev/null || true
chmod 0750 "${DATA_DIR}" "${BACKUP_DIR}"

if ((admin_count == 0)); then
  say ""
  say "ERSTER ADMIN"
  say "  Benutzername: ${admin_username}"
  say "  Uebergangspasswort: ${initial_admin_password}"
  say "  Beim ersten Login muss das Passwort geaendert werden."
fi
if ((terminal_count == 0)); then
  say ""
  say "ERSTES TERMINAL"
  say "  Terminal-ID: eingang-1"
  say "  Terminal-Key: ${initial_terminal_key}"
  say "  Diesen Key exakt als TERMINAL_KEY in die M5Dial-Firmware eintragen."
fi

if ((admin_count > 0)); then
  say ""
  if confirm "Vorhandenes Admin-Passwort als Notfallmassnahme zuruecksetzen?"; then
    read -r -p "Admin-Benutzername [admin]: " reset_username
    reset_username="${reset_username:-admin}"
    say "ACHTUNG: Alle bestehenden Sitzungen dieses Kontos werden ungueltig."
    if confirm "Passwort fuer ${reset_username} wirklich zuruecksetzen?"; then
      reset_password="$(random_hex 9)"
      json_reset "${reset_username}" "${reset_password}" || die "Passwortruecksetzung fehlgeschlagen."
      chown "${WEB_USER}:${WEB_GROUP}" "${DB_FILE}"
      chmod 0640 "${DB_FILE}"
      say "  Neues Uebergangspasswort: ${reset_password}"
      say "  Beim naechsten Login muss es geaendert werden."
    else
      say "Passwortruecksetzung abgebrochen."
    fi
  fi
fi

if command -v apache2ctl >/dev/null 2>&1; then
  apache2ctl configtest || die "Apache-Konfiguration ist fehlerhaft; Dienst wurde nicht neu geladen."
fi
if command -v systemctl >/dev/null 2>&1; then
  systemctl enable --now apache2
  systemctl reload apache2
fi

configure_https

say ""
say "Installation abgeschlossen."
say "Weboberflaeche: ${HTTPS_URL:-http://SERVER-IP}/kienzlezeit.php"
if [[ -n "${HTTPS_URL}" ]]; then say "RFID per HTTPS: ${HTTPS_URL}/rfid-scan.php"; fi
say "RFID-Endpunkt:  http://SERVER-IP/rfid-scan.php"
say "Auslagen:       ${HTTPS_URL:-http://SERVER-IP}/auslagen.php (in den Optionen aktivierbar)"
say "Datenbank:      ${DB_FILE}"
say "Auslagen-DB:    ${EXPENSE_DB_FILE}"
say "Secret-Key:     ${SECRET_KEY_FILE}"
say ""
say "WICHTIG: Regelmaessige Backups der beiden SQLite-Datenbanken und der"
say "Schluesseldatei auf einem zweiten Datentraeger sind dringend empfohlen."
say ""
say "Hinweis: Fuer Admin-Passwoerter und Personaldaten die HTTPS-Adresse verwenden."
