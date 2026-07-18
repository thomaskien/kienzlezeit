#!/usr/bin/env bash
#
# kienzlezeit – Installer fuer Apache/SQLite
#
# Installer-Version: 1.6.8
# Anwendungs-Version: 1.6.7
# Author: Dr. Thomas Kienzle
# Stand: 2026-07-18
#
# Changelog (komplett):
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

INSTALLER_VERSION="1.6.8"
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

say ""
say "Installation abgeschlossen."
say "Weboberflaeche: http://SERVER-IP/kienzlezeit.php"
say "RFID-Endpunkt:  http://SERVER-IP/rfid-scan.php"
say "Auslagen:       http://SERVER-IP/auslagen.php (in den Optionen aktivierbar)"
say "Datenbank:      ${DB_FILE}"
say "Auslagen-DB:    ${EXPENSE_DB_FILE}"
say "Secret-Key:     ${SECRET_KEY_FILE}"
say ""
say "WICHTIG: Regelmaessige Backups der beiden SQLite-Datenbanken und der"
say "Schluesseldatei auf einem zweiten Datentraeger sind dringend empfohlen."
say ""
say "Hinweis: Fuer den Adminbetrieb sollte Apache spaeter per HTTPS erreichbar sein."
