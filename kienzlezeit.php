<?php
/**
 * kienzlezeit.php
 * Lokale RFID-Zeiterfassung, Mitarbeiter- und Adminoberflaeche.
 *
 * Version: 1.6.6
 * Author: Dr. Thomas Kienzle
 * Stand: 2026-07-08
 *
 * Changelog (komplett):
 * - 1.6.6 (2026-07-08):
 *   - Admin-Menue logisch neu geordnet und Auswertung einheitlich benannt.
 *   - Feiertagsverwaltung von Auswertung nach Planung verschoben.
 *   - Neue stichtagsbezogene Monatsuebersicht aller aktiven Mitarbeitenden in Web, PDF und CSV.
 *   - Gesamt- und Monatssalden, Urlaub sowie Jahres-Abwesenheitstage werden gemeinsam ausgewiesen.
 *
 * - 1.6.5 (2026-07-06):
 *   - Administrativ gesetzte Tagesarbeitszeiten schliessen offene Altbuchungen am RFID-Terminal ab.
 *   - Nach dem Abschluss wird die naechste Kartenbuchung korrekt wieder als Kommen behandelt.
 *
 * - 1.6.4 (2026-07-06):
 *   - Zu pruefende Buchungen erhalten je Tag ein editierbares Arbeitszeitfeld.
 *   - Vorhandene Sollzeiten werden vorbelegt; ohne Sollplan kann die Zeit frei eingetragen werden.
 *   - Zeitangaben werden als Stunden:Minuten oder Dezimalstunden akzeptiert und protokolliert.
 *
 * - 1.6.3 (2026-07-06):
 *   - Beleg-vorhanden ist ausschliesslich ein administrativer Buchhaltungsstatus.
 *   - Admin-Vorgangsliste zeigt offene, fehlende oder vollstaendige Beleglagen.
 *   - Mitarbeiterantrag und Mitarbeiterdetails enthalten keinen Belegstatus.
 *
 * - 1.6.2 (2026-07-05):
 *   - Der Beleg-vorhanden-Schalter wird bereits serverseitig sichtbar ausgegeben.
 *   - Mitarbeiter-Detailansicht ohne QR-Code und mit breiterer Belegvorschau.
 *   - Vollstaendige Namen von Admin-Konten koennen nachvollziehbar bearbeitet werden.
 *
 * - 1.6.1 (2026-07-05):
 *   - Auslagenposten erhalten einen dokumentierten Ja/Nein-Schalter fuer vorhandene Belege.
 *   - EPC-QR-Codes werden in der Web-Detailansicht einer Auslage angezeigt.
 *
 * - 1.6 (2026-07-05):
 *   - Auslagenverwaltung kann global aktiviert oder deaktiviert werden.
 *   - Aktivierte Auslagenverwaltung wird in der Admin- und Mitarbeiternavigation verlinkt.
 *   - Die gemeinsame kienzlezeit-Anmeldung gilt auch fuer die getrennte Auslagenanwendung.
 *
 * - 1.5 (2026-07-05):
 *   - Globale Option zum Ein- oder Ausblenden von Samstag und Sonntag in Wochenplanungen.
 *   - Leere Tagesabschnitte werden in Wochenplanungen nicht mehr als eigene Kaestchen dargestellt.
 *   - Die redundanten Kennzeichnungen VM und NM entfallen in den Planungskarten.
 *   - Persoenliche Uebersicht mit Ist/Monatssoll, tagesaktuellem Monatssaldo,
 *     Resturlaub und Gesamt-Stunden-Saldo.
 *   - Aktuell anwesende Personen werden im Adminbereich und optional oeffentlich als Kacheln angezeigt.
 *   - Der Seitenfuss bleibt bei kurzen Seiten verlaesslich am unteren Fensterrand.
 *
 * - 1.4 (2026-07-05):
 *   - Neue gruppierte Navigation und dauerhaft sichtbarer Mitarbeiter-Logout.
 *   - Admin-Dashboard mit Anwesenheit, Antragszahlen und Buchungspruefung.
 *   - Oeffentliche Anwesenheitsanzeige und zukuenftige Wochenplanung ohne Login.
 *   - Mitarbeiterliste mit Stundenkonto, Urlaub, Krankheit und getrenntem Vollnamen.
 *   - Krankheitstage werden direkt aus genehmigten Krankmeldungen und dem historischen Sollplan berechnet.
 *   - Historisierte Startsalden und revisionssichere Saldoanpassungen.
 *   - Mitarbeiter-Korrekturantraege mit Admin-Genehmigung.
 *   - Buchungspruefung fuer unvollstaendige, doppelte und ueberlange Buchungen.
 *   - Oeffnungszeiten und Uebernahme in persoenliche Sollplaene.
 *   - Zusaetzliche Anwesenheit/Vertretung ohne Aenderung des Sollplans.
 *   - Feiertagskalender fuer alle Bundeslaender je Kalenderjahr.
 *   - PDF-Ausgaben mit echten Tabellen, Summen, Urlaub, Gesamtsaldo und Seitenfuss.
 *   - Admin-Konten koennen angelegt und revisionssicher deaktiviert werden.
 *   - Datenbank: Automatische, additive Migration von Schema 2 auf Schema 3.
 *
 * - 1.3 (2026-07-05):
 *   - Karten: Testkarten koennen im Adminbereich gezielt geloescht werden.
 *   - Karten: Mitgelieferte Testkarten werden nur einmal initial angelegt und nach einer Loeschung nicht erneut erzeugt.
 *   - Audit: Das Loeschen einer Testkarte wird mit ihrem vorherigen Datenstand protokolliert.
 *
 * - 1.2 (2026-07-05):
 *   - Terminals: Secrets koennen ohne vorgegebene Mindestlaenge gespeichert werden.
 *   - UI: Das Secret-Feld erklaert eindeutig, dass ein leeres Feld keine Aenderung ausloest.
 *
 * - 1.1 (2026-07-04):
 *   - UI: Logout ist im gesamten Adminbereich dauerhaft in der Kopfzeile erreichbar.
 *   - Feiertage: NRW-Feiertage werden in Planung und Auswertungen sichtbar dargestellt.
 *   - Feiertage: Manuelle Ergaenzungen, Uebersteuerungen und Deaktivierungen mit Auditlog.
 *   - Feiertage: Eigene Kalenderansicht und CSV-/PDF-Export je Jahr.
 *   - Terminals: Secret verschluesselt speicherbar, im Adminbereich anzeigbar und editierbar.
 *   - Terminals: Revisionssicheres Archivieren entfernt Terminals aus allen Auswahllisten.
 *   - Datenbank: Automatische, additive Migration von Schema 1 auf Schema 2.
 *
 * - 1.0 (2026-07-02):
 *   - Erste funktionsfaehige Serverversion mit SQLite.
 *   - Mitarbeiter- und Kartenverwaltung, Testkarten und Terminals.
 *   - Idempotente RFID-Buchungen mit Kommen/Gehen und Doppelauflege-Schutz.
 *   - Passwortloser Mitarbeiterlogin ueber einen ausgewaehlten M5Dial.
 *   - Historisierte Sollplaene fuer Vormittag und Nachmittag.
 *   - Anwesenheits-, Urlaubs- und allgemeine Abwesenheitsverwaltung.
 *   - Revisionssichere Korrekturen, Monatsabschluss und Auditprotokoll.
 *   - CSV- und PDF-Exporte inklusive reduziertem Jahresabschluss-Export.
 *   - NRW-Feiertage, responsive Oberflaeche, Logo und einheitlicher Footer.
 */

declare(strict_types=1);

const KZ_APP_TITLE = 'kienzlezeit';
const KZ_APP_VERSION = '1.6.6';
const KZ_APP_AUTHOR = 'Dr. Thomas Kienzle';
const KZ_SCHEMA_VERSION = 3;
const KZ_DEFAULT_DB = '/var/lib/kienzlezeit/kienzlezeit.sqlite';
const KZ_DEFAULT_SECRET_KEY = '/var/lib/kienzlezeit/terminal-secret.key';

date_default_timezone_set('Europe/Berlin');

function kz_db_path(): string
{
    $override = getenv('KZ_DB_PATH');
    return is_string($override) && $override !== '' ? $override : KZ_DEFAULT_DB;
}

function kz_secret_key_path(): string
{
    $override = getenv('KZ_SECRET_KEY_PATH');
    return is_string($override) && $override !== '' ? $override : KZ_DEFAULT_SECRET_KEY;
}

function kz_secret_key(): string
{
    $path = kz_secret_key_path();
    $raw = @file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Schluesseldatei fuer Terminal-Secrets fehlt. Installer erneut ausfuehren.');
    }
    $raw = trim($raw);
    $key = ctype_xdigit($raw) && strlen($raw) === 64 ? hex2bin($raw) : $raw;
    if (!is_string($key) || strlen($key) !== 32) {
        throw new RuntimeException('Schluesseldatei fuer Terminal-Secrets ist ungueltig.');
    }
    return $key;
}

function kz_encrypt_terminal_secret(string $secret): array
{
    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($secret, 'aes-256-gcm', kz_secret_key(), OPENSSL_RAW_DATA, $nonce, $tag);
    if ($ciphertext === false || strlen($tag) !== 16) {
        throw new RuntimeException('Terminal-Secret konnte nicht verschluesselt werden.');
    }
    return [
        'ciphertext' => base64_encode($ciphertext),
        'nonce' => base64_encode($nonce),
        'tag' => base64_encode($tag),
    ];
}

function kz_decrypt_terminal_secret(array $terminal): string
{
    if (empty($terminal['key_ciphertext']) || empty($terminal['key_nonce']) || empty($terminal['key_tag'])) {
        throw new RuntimeException('Fuer dieses Terminal ist noch kein anzeigbares Secret hinterlegt. Bitte ein neues Secret speichern.');
    }
    $plaintext = openssl_decrypt(
        base64_decode((string) $terminal['key_ciphertext'], true) ?: '',
        'aes-256-gcm',
        kz_secret_key(),
        OPENSSL_RAW_DATA,
        base64_decode((string) $terminal['key_nonce'], true) ?: '',
        base64_decode((string) $terminal['key_tag'], true) ?: ''
    );
    if ($plaintext === false) {
        throw new RuntimeException('Terminal-Secret konnte nicht entschluesselt werden.');
    }
    return $plaintext;
}

function kz_now_utc(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function kz_today(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
}

function kz_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function kz_json(array $value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('JSON konnte nicht erzeugt werden.');
    }
    return $json;
}

function kz_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = kz_db_path();
    $directory = dirname($path);
    if (!is_dir($directory)) {
        throw new RuntimeException('Datenverzeichnis fehlt: ' . $directory);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 1000');
    kz_ensure_schema($pdo);
    return $pdo;
}

function kz_ensure_schema(PDO $db): void
{
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE COLLATE NOCASE,
    display_name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
    must_change_password INTEGER NOT NULL DEFAULT 1 CHECK(must_change_password IN (0,1)),
    session_version INTEGER NOT NULL DEFAULT 1,
    failed_attempts INTEGER NOT NULL DEFAULT 0,
    locked_until TEXT,
    created_at TEXT NOT NULL,
    last_login_at TEXT
);

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_user_id INTEGER REFERENCES admin_users(id),
    actor_label TEXT NOT NULL,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT,
    before_data TEXT,
    after_data TEXT,
    reason TEXT,
    ip_address TEXT,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    personnel_number TEXT NOT NULL UNIQUE COLLATE NOCASE,
    name TEXT NOT NULL,
    active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS rfid_cards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid_canonical TEXT NOT NULL UNIQUE COLLATE NOCASE,
    card_type TEXT NOT NULL CHECK(card_type IN ('EMPLOYEE','TEST')),
    active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
    label TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS rfid_card_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    card_id INTEGER NOT NULL REFERENCES rfid_cards(id),
    employee_id INTEGER NOT NULL REFERENCES employees(id),
    valid_from TEXT NOT NULL,
    valid_until TEXT,
    reason TEXT NOT NULL,
    created_by_admin_id INTEGER REFERENCES admin_users(id),
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_card_assignments_current
ON rfid_card_assignments(card_id, valid_from, valid_until);

CREATE TABLE IF NOT EXISTS test_card_profiles (
    card_id INTEGER PRIMARY KEY REFERENCES rfid_cards(id) ON DELETE CASCADE,
    response_ok INTEGER NOT NULL DEFAULT 1 CHECK(response_ok IN (0,1)),
    title TEXT NOT NULL,
    line1 TEXT NOT NULL,
    line2 TEXT NOT NULL,
    internal_note TEXT NOT NULL DEFAULT '',
    updated_by_admin_id INTEGER REFERENCES admin_users(id),
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS terminals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    terminal_code TEXT NOT NULL UNIQUE COLLATE NOCASE,
    label TEXT NOT NULL,
    key_hash TEXT NOT NULL,
    key_ciphertext TEXT,
    key_nonce TEXT,
    key_tag TEXT,
    active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
    last_seen_at TEXT,
    archived_at TEXT,
    archived_by INTEGER REFERENCES admin_users(id),
    archive_reason TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS login_challenges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    challenge_token TEXT NOT NULL UNIQUE,
    browser_session_id TEXT NOT NULL,
    terminal_id INTEGER NOT NULL REFERENCES terminals(id),
    status TEXT NOT NULL CHECK(status IN ('PENDING','APPROVED','EXPIRED','CANCELLED')),
    employee_id INTEGER REFERENCES employees(id),
    created_at TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    approved_at TEXT
);

CREATE TABLE IF NOT EXISTS terminal_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    terminal_id INTEGER NOT NULL REFERENCES terminals(id),
    action_type TEXT NOT NULL CHECK(action_type IN ('REGISTER_CARD','WEB_LOGIN')),
    status TEXT NOT NULL CHECK(status IN ('PENDING','CONSUMED','EXPIRED','CANCELLED')),
    employee_id INTEGER REFERENCES employees(id),
    login_challenge_id INTEGER REFERENCES login_challenges(id),
    created_by_admin_id INTEGER REFERENCES admin_users(id),
    created_at TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    consumed_at TEXT
);

CREATE INDEX IF NOT EXISTS idx_terminal_actions_pending
ON terminal_actions(terminal_id, status, expires_at);

CREATE TABLE IF NOT EXISTS event_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    terminal_id INTEGER NOT NULL REFERENCES terminals(id),
    event_id TEXT NOT NULL,
    uid_received TEXT NOT NULL,
    request_body TEXT NOT NULL,
    request_hash TEXT NOT NULL,
    received_at TEXT NOT NULL,
    http_status INTEGER NOT NULL,
    response_body TEXT NOT NULL,
    action_type TEXT NOT NULL,
    time_event_id INTEGER,
    UNIQUE(terminal_id, event_id)
);

CREATE TABLE IF NOT EXISTS time_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id),
    card_id INTEGER NOT NULL REFERENCES rfid_cards(id),
    terminal_id INTEGER NOT NULL REFERENCES terminals(id),
    event_request_id INTEGER UNIQUE REFERENCES event_requests(id),
    event_type TEXT NOT NULL CHECK(event_type IN ('COME','LEAVE')),
    occurred_at TEXT NOT NULL,
    uid_snapshot TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_time_events_employee_time
ON time_events(employee_id, occurred_at);

CREATE TABLE IF NOT EXISTS corrections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id),
    target_time_event_id INTEGER REFERENCES time_events(id),
    correction_type TEXT NOT NULL CHECK(correction_type IN ('ADD','VOID','REPLACE')),
    corrected_event_type TEXT CHECK(corrected_event_type IN ('COME','LEAVE')),
    corrected_time TEXT,
    reason TEXT NOT NULL,
    performed_by INTEGER NOT NULL REFERENCES admin_users(id),
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_corrections_employee
ON corrections(employee_id, created_at);

CREATE TABLE IF NOT EXISTS work_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id),
    valid_from TEXT NOT NULL,
    valid_until TEXT,
    weekly_target_minutes INTEGER NOT NULL DEFAULT 0,
    created_by INTEGER NOT NULL REFERENCES admin_users(id),
    created_at TEXT NOT NULL,
    UNIQUE(employee_id, valid_from)
);

CREATE TABLE IF NOT EXISTS work_schedule_day_parts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    schedule_id INTEGER NOT NULL REFERENCES work_schedules(id) ON DELETE CASCADE,
    weekday INTEGER NOT NULL CHECK(weekday BETWEEN 1 AND 7),
    day_part TEXT NOT NULL CHECK(day_part IN ('MORNING','AFTERNOON')),
    target_minutes INTEGER NOT NULL DEFAULT 0 CHECK(target_minutes >= 0),
    planned_start TEXT,
    planned_end TEXT,
    UNIQUE(schedule_id, weekday, day_part)
);

CREATE INDEX IF NOT EXISTS idx_schedules_employee_validity
ON work_schedules(employee_id, valid_from, valid_until);

CREATE TABLE IF NOT EXISTS absence_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type_code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    time_credit_rule TEXT NOT NULL CHECK(time_credit_rule IN ('PLANNED_TIME','NO_CREDIT','MANUAL')),
    consumes_vacation INTEGER NOT NULL DEFAULT 0 CHECK(consumes_vacation IN (0,1)),
    employee_requestable INTEGER NOT NULL DEFAULT 1 CHECK(employee_requestable IN (0,1)),
    active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
    color TEXT NOT NULL DEFAULT '#64748b'
);

CREATE TABLE IF NOT EXISTS absence_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id),
    absence_type_id INTEGER NOT NULL REFERENCES absence_types(id),
    start_date TEXT NOT NULL,
    end_date TEXT NOT NULL,
    start_part TEXT NOT NULL CHECK(start_part IN ('FULL_DAY','MORNING','AFTERNOON')),
    end_part TEXT NOT NULL CHECK(end_part IN ('FULL_DAY','MORNING','AFTERNOON')),
    status TEXT NOT NULL CHECK(status IN ('PENDING','APPROVED','REJECTED','CANCELLED')),
    employee_note TEXT NOT NULL DEFAULT '',
    admin_note TEXT NOT NULL DEFAULT '',
    submitted_at TEXT NOT NULL,
    decided_at TEXT,
    decided_by INTEGER REFERENCES admin_users(id),
    created_by_role TEXT NOT NULL CHECK(created_by_role IN ('EMPLOYEE','ADMIN'))
);

CREATE TABLE IF NOT EXISTS absence_days (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL REFERENCES absence_requests(id),
    employee_id INTEGER NOT NULL REFERENCES employees(id),
    work_date TEXT NOT NULL,
    day_part TEXT NOT NULL CHECK(day_part IN ('FULL_DAY','MORNING','AFTERNOON')),
    target_minutes INTEGER NOT NULL,
    credited_minutes INTEGER NOT NULL,
    vacation_days_used REAL NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_absence_days_employee_date
ON absence_days(employee_id, work_date);

CREATE TABLE IF NOT EXISTS absence_request_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL REFERENCES absence_requests(id),
    previous_status TEXT,
    new_status TEXT NOT NULL,
    changed_by INTEGER REFERENCES admin_users(id),
    actor_label TEXT NOT NULL,
    reason TEXT,
    changed_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS vacation_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id),
    year INTEGER NOT NULL,
    entitlement_days REAL NOT NULL DEFAULT 0,
    carried_days REAL NOT NULL DEFAULT 0,
    adjustment_days REAL NOT NULL DEFAULT 0,
    adjustment_reason TEXT NOT NULL DEFAULT '',
    updated_by INTEGER NOT NULL REFERENCES admin_users(id),
    updated_at TEXT NOT NULL,
    UNIQUE(employee_id, year)
);

CREATE TABLE IF NOT EXISTS month_closures (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id),
    year INTEGER NOT NULL,
    month INTEGER NOT NULL CHECK(month BETWEEN 1 AND 12),
    status TEXT NOT NULL CHECK(status IN ('CLOSED','REOPENED')),
    snapshot_json TEXT NOT NULL,
    closed_at TEXT NOT NULL,
    closed_by INTEGER NOT NULL REFERENCES admin_users(id),
    reopened_at TEXT,
    reopened_by INTEGER REFERENCES admin_users(id),
    reopen_reason TEXT,
    UNIQUE(employee_id, year, month)
);

CREATE TABLE IF NOT EXISTS export_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_user_id INTEGER NOT NULL REFERENCES admin_users(id),
    export_type TEXT NOT NULL,
    export_format TEXT NOT NULL,
    period_label TEXT NOT NULL,
    employee_scope TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS holiday_overrides (
    holiday_date TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    override_mode TEXT NOT NULL CHECK(override_mode IN ('ADD_OR_REPLACE','DISABLE')),
    credit_rule TEXT NOT NULL CHECK(credit_rule IN ('PLANNED_TIME','NO_CREDIT')),
    reason TEXT NOT NULL,
    updated_by INTEGER NOT NULL REFERENCES admin_users(id),
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS holiday_calendars (
    year INTEGER PRIMARY KEY,
    state_code TEXT NOT NULL,
    updated_by INTEGER REFERENCES admin_users(id),
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS opening_hours (
    weekday INTEGER NOT NULL CHECK(weekday BETWEEN 1 AND 7),
    day_part TEXT NOT NULL CHECK(day_part IN ('MORNING','AFTERNOON')),
    start_time TEXT,
    end_time TEXT,
    updated_by INTEGER REFERENCES admin_users(id),
    updated_at TEXT NOT NULL,
    PRIMARY KEY(weekday,day_part)
);

CREATE TABLE IF NOT EXISTS balance_adjustments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id),
    effective_date TEXT NOT NULL,
    adjustment_minutes INTEGER NOT NULL,
    old_balance_minutes INTEGER NOT NULL,
    new_balance_minutes INTEGER NOT NULL,
    adjustment_type TEXT NOT NULL CHECK(adjustment_type IN ('OPENING','SET_BALANCE')),
    reason TEXT NOT NULL,
    created_by INTEGER NOT NULL REFERENCES admin_users(id),
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS correction_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id),
    request_type TEXT NOT NULL CHECK(request_type IN ('ADD','REPLACE','VOID')),
    target_time_event_id INTEGER REFERENCES time_events(id),
    requested_event_type TEXT CHECK(requested_event_type IN ('COME','LEAVE')),
    requested_time TEXT,
    reason TEXT NOT NULL,
    status TEXT NOT NULL CHECK(status IN ('PENDING','APPROVED','REJECTED','CANCELLED')),
    submitted_at TEXT NOT NULL,
    decided_at TEXT,
    decided_by INTEGER REFERENCES admin_users(id),
    admin_note TEXT NOT NULL DEFAULT '',
    correction_id INTEGER REFERENCES corrections(id)
);

CREATE TABLE IF NOT EXISTS presence_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id),
    work_date TEXT NOT NULL,
    day_part TEXT NOT NULL CHECK(day_part IN ('FULL_DAY','MORNING','AFTERNOON')),
    note TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL CHECK(status IN ('PENDING','APPROVED','REJECTED','CANCELLED')),
    submitted_at TEXT NOT NULL,
    decided_at TEXT,
    decided_by INTEGER REFERENCES admin_users(id),
    admin_note TEXT NOT NULL DEFAULT '',
    created_by_role TEXT NOT NULL CHECK(created_by_role IN ('EMPLOYEE','ADMIN'))
);

CREATE TABLE IF NOT EXISTS day_time_overrides (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id),
    work_date TEXT NOT NULL,
    worked_minutes INTEGER NOT NULL,
    reason TEXT NOT NULL,
    created_by INTEGER NOT NULL REFERENCES admin_users(id),
    created_at TEXT NOT NULL,
    UNIQUE(employee_id,work_date)
);

CREATE TABLE IF NOT EXISTS booking_issue_resolutions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    issue_fingerprint TEXT NOT NULL UNIQUE,
    employee_id INTEGER NOT NULL REFERENCES employees(id),
    work_date TEXT NOT NULL,
    action_type TEXT NOT NULL CHECK(action_type IN ('REVIEWED','SET_TARGET')),
    issue_data TEXT NOT NULL,
    note TEXT NOT NULL DEFAULT '',
    resolved_by INTEGER NOT NULL REFERENCES admin_users(id),
    resolved_at TEXT NOT NULL
);
SQL);

    kz_add_column_if_missing($db, 'terminals', 'key_ciphertext', 'TEXT');
    kz_add_column_if_missing($db, 'terminals', 'key_nonce', 'TEXT');
    kz_add_column_if_missing($db, 'terminals', 'key_tag', 'TEXT');
    kz_add_column_if_missing($db, 'terminals', 'archived_at', 'TEXT');
    kz_add_column_if_missing($db, 'terminals', 'archived_by', 'INTEGER REFERENCES admin_users(id)');
    kz_add_column_if_missing($db, 'terminals', 'archive_reason', 'TEXT');
    kz_add_column_if_missing($db, 'employees', 'full_name', 'TEXT');
    $db->exec("UPDATE employees SET full_name=name WHERE full_name IS NULL OR trim(full_name)=''");

    $now = kz_now_utc();
    $settings = [
        'schema_version' => (string) KZ_SCHEMA_VERSION,
        'day_part_boundary' => '13:00',
        'duplicate_guard_seconds' => '30',
        'federal_state' => 'NW',
        'public_presence_enabled' => '0',
        'show_weekends' => '1',
        'expenses_enabled' => '0',
    ];
    $statement = $db->prepare(
        'INSERT OR IGNORE INTO settings(setting_key, setting_value, updated_at) VALUES(?,?,?)'
    );
    foreach ($settings as $key => $value) {
        $statement->execute([$key, $value, $now]);
    }
    $statement = $db->prepare('UPDATE settings SET setting_value=?,updated_at=? WHERE setting_key=\'schema_version\'');
    $statement->execute([(string) KZ_SCHEMA_VERSION, $now]);

    $absenceTypes = [
        ['VACATION', 'Urlaub', 'PLANNED_TIME', 1, 1, '#16a34a'],
        ['TRAINING', 'Fortbildung', 'PLANNED_TIME', 0, 1, '#2563eb'],
        ['ILLNESS', 'Krankheit', 'PLANNED_TIME', 0, 0, '#dc2626'],
        ['OTHER', 'Sonstige Abwesenheit', 'PLANNED_TIME', 0, 1, '#7c3aed'],
        ['UNPAID', 'Unbezahlt frei', 'NO_CREDIT', 0, 1, '#64748b'],
    ];
    $statement = $db->prepare(
        'INSERT OR IGNORE INTO absence_types(type_code,name,time_credit_rule,consumes_vacation,employee_requestable,active,color) VALUES(?,?,?,?,?,1,?)'
    );
    foreach ($absenceTypes as $type) {
        $statement->execute($type);
    }

    $statement = $db->prepare('SELECT setting_value FROM settings WHERE setting_key=\'test_cards_seeded\'');
    $statement->execute();
    if ($statement->fetchColumn() === false) {
        kz_seed_test_cards($db);
        $db->prepare('INSERT INTO settings(setting_key,setting_value,updated_at) VALUES(\'test_cards_seeded\',\'1\',?)')->execute([$now]);
    }
}

function kz_add_column_if_missing(PDO $db, string $table, string $column, string $definition): void
{
    $columns = $db->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    foreach ($columns as $existing) {
        if ((string) $existing['name'] === $column) {
            return;
        }
    }
    $db->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function kz_seed_test_cards(PDO $db): void
{
    $cards = [
        ['E9-4A-2C-83', 'Testkarte 1'],
        ['15-38-E4-3D', 'Testkarte 2'],
        ['04-3F-32-6A-EC-6B-80', 'Testkarte 3'],
    ];
    $now = kz_now_utc();
    foreach ($cards as [$uid, $label]) {
        $find = $db->prepare('SELECT id,card_type FROM rfid_cards WHERE uid_canonical=?');
        $find->execute([$uid]);
        $existing = $find->fetch();
        if ($existing === false) {
            $insert = $db->prepare('INSERT INTO rfid_cards(uid_canonical,card_type,active,label,created_at,updated_at) VALUES(?,\'TEST\',1,?,?,?)');
            $insert->execute([$uid, $label, $now, $now]);
            $cardId = (int) $db->lastInsertId();
        } elseif ($existing['card_type'] === 'TEST') {
            $cardId = (int) $existing['id'];
        } else {
            continue;
        }
        $profile = $db->prepare('INSERT OR IGNORE INTO test_card_profiles(card_id,response_ok,title,line1,line2,internal_note,updated_at) VALUES(?,1,?,?,\'Erfolgreich erkannt\',\'Aus Testendpunkt v0.2 uebernommen\',?)');
        $profile->execute([(int) $cardId, $label, $uid, $now]);
    }
}

function kz_setting(string $key, ?string $default = null): ?string
{
    $statement = kz_db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
    $statement->execute([$key]);
    $value = $statement->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function kz_set_setting(string $key, string $value): void
{
    $statement = kz_db()->prepare('INSERT INTO settings(setting_key,setting_value,updated_at) VALUES(?,?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value,updated_at=excluded.updated_at');
    $statement->execute([$key, $value, kz_now_utc()]);
}

function kz_show_weekends(): bool
{
    return kz_setting('show_weekends', '1') === '1';
}

function kz_expenses_enabled(): bool
{
    return kz_setting('expenses_enabled', '0') === '1';
}

function kz_expenses_url(bool $admin = false): string
{
    $path = is_file(__DIR__ . '/auslagen.php') ? 'auslagen.php' : 'auslagen/auslagen.php';
    return $path . ($admin ? '?admin=1' : '');
}

function kz_canonical_uid(string $uid): string
{
    $compact = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $uid));
    if ($compact === '' || strlen($compact) % 2 !== 0 || strlen($compact) < 8 || strlen($compact) > 20) {
        throw new InvalidArgumentException('Die RFID-UID ist ungueltig.');
    }
    return implode('-', str_split($compact, 2));
}

function kz_audit(string $action, string $entityType, string|int|null $entityId = null, mixed $before = null, mixed $after = null, ?string $reason = null, ?int $adminId = null, ?string $actorLabel = null): void
{
    if ($adminId === null && isset($_SESSION['admin_id'])) {
        $adminId = (int) $_SESSION['admin_id'];
    }
    if ($actorLabel === null) {
        $actorLabel = isset($_SESSION['admin_name']) ? (string) $_SESSION['admin_name'] : 'System';
    }
    $statement = kz_db()->prepare('INSERT INTO admin_audit_log(admin_user_id,actor_label,action,entity_type,entity_id,before_data,after_data,reason,ip_address,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)');
    $statement->execute([
        $adminId,
        $actorLabel,
        $action,
        $entityType,
        $entityId === null ? null : (string) $entityId,
        $before === null ? null : kz_json((array) $before),
        $after === null ? null : kz_json((array) $after),
        $reason,
        $_SERVER['REMOTE_ADDR'] ?? null,
        kz_now_utc(),
    ]);
}

function kz_federal_states(): array
{
    return ['BW'=>'Baden-Württemberg','BY'=>'Bayern','BE'=>'Berlin','BB'=>'Brandenburg','HB'=>'Bremen','HH'=>'Hamburg','HE'=>'Hessen','MV'=>'Mecklenburg-Vorpommern','NI'=>'Niedersachsen','NW'=>'Nordrhein-Westfalen','RP'=>'Rheinland-Pfalz','SL'=>'Saarland','SN'=>'Sachsen','ST'=>'Sachsen-Anhalt','SH'=>'Schleswig-Holstein','TH'=>'Thüringen'];
}

function kz_holiday_state_for_year(int $year): string
{
    $statement = kz_db()->prepare('SELECT state_code FROM holiday_calendars WHERE year=?');
    $statement->execute([$year]);
    $state = $statement->fetchColumn();
    return is_string($state) && isset(kz_federal_states()[$state]) ? $state : (string) kz_setting('federal_state', 'NW');
}

function kz_state_holidays(int $year, ?string $state = null): array
{
    $state ??= kz_holiday_state_for_year($year);
    $a = $year % 19;
    $b = intdiv($year, 100);
    $c = $year % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31);
    $day = (($h + $l - 7 * $m + 114) % 31) + 1;
    $easter = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new DateTimeZone('Europe/Berlin'));
    $holidays = [
        sprintf('%04d-01-01', $year) => 'Neujahr',
        $easter->modify('-2 days')->format('Y-m-d') => 'Karfreitag',
        $easter->modify('+1 day')->format('Y-m-d') => 'Ostermontag',
        sprintf('%04d-05-01', $year) => 'Tag der Arbeit',
        $easter->modify('+39 days')->format('Y-m-d') => 'Christi Himmelfahrt',
        $easter->modify('+50 days')->format('Y-m-d') => 'Pfingstmontag',
        sprintf('%04d-10-03', $year) => 'Tag der Deutschen Einheit',
        sprintf('%04d-12-25', $year) => '1. Weihnachtstag',
        sprintf('%04d-12-26', $year) => '2. Weihnachtstag',
    ];
    if (in_array($state, ['BW','BY','ST'], true)) $holidays[sprintf('%04d-01-06',$year)]='Heilige Drei Könige';
    if (in_array($state, ['BE','MV'], true)) $holidays[sprintf('%04d-03-08',$year)]='Internationaler Frauentag';
    if ($state === 'BB') {
        $holidays[$easter->format('Y-m-d')]='Ostersonntag';
        $holidays[$easter->modify('+49 days')->format('Y-m-d')]='Pfingstsonntag';
    }
    if (in_array($state, ['BW','BY','HE','NW','RP','SL'], true)) $holidays[$easter->modify('+60 days')->format('Y-m-d')]='Fronleichnam';
    if (in_array($state, ['SL'], true)) $holidays[sprintf('%04d-08-15',$year)]='Mariä Himmelfahrt';
    if ($state === 'TH') $holidays[sprintf('%04d-09-20',$year)]='Weltkindertag';
    if (in_array($state, ['BB','HB','HH','MV','NI','SN','ST','SH','TH'], true)) $holidays[sprintf('%04d-10-31',$year)]='Reformationstag';
    if (in_array($state, ['BW','BY','NW','RP','SL'], true)) $holidays[sprintf('%04d-11-01',$year)]='Allerheiligen';
    if ($state === 'SN') {
        $nov23 = new DateTimeImmutable(sprintf('%04d-11-23',$year), new DateTimeZone('Europe/Berlin'));
        $daysBack = ((int)$nov23->format('N') - 3 + 7) % 7;
        if ($daysBack === 0) $daysBack = 7;
        $holidays[$nov23->modify('-'.$daysBack.' days')->format('Y-m-d')]='Buß- und Bettag';
    }
    ksort($holidays);
    return $holidays;
}

function kz_holidays(int $year): array
{
    $holidays = [];
    $base = kz_state_holidays($year);
    foreach ($base as $date => $name) {
        $holidays[$date] = [
            'date' => $date,
            'name' => $name,
            'source' => 'STATE_AUTO',
            'credit_rule' => 'PLANNED_TIME',
            'overridden' => false,
        ];
    }
    $statement = kz_db()->prepare('SELECT * FROM holiday_overrides WHERE holiday_date>=? AND holiday_date<? ORDER BY holiday_date');
    $statement->execute([sprintf('%04d-01-01', $year), sprintf('%04d-01-01', $year + 1)]);
    foreach ($statement as $override) {
        $date = (string) $override['holiday_date'];
        if ($override['override_mode'] === 'DISABLE') {
            unset($holidays[$date]);
            continue;
        }
        $holidays[$date] = [
            'date' => $date,
            'name' => (string) $override['name'],
            'source' => isset($base[$date]) ? 'MANUAL_OVERRIDE' : 'MANUAL',
            'credit_rule' => (string) $override['credit_rule'],
            'overridden' => true,
        ];
    }
    ksort($holidays);
    return $holidays;
}

function kz_holiday_admin_rows(int $year): array
{
    $base = kz_state_holidays($year);
    $effective = kz_holidays($year);
    $rows = $effective;
    $statement = kz_db()->prepare("SELECT * FROM holiday_overrides WHERE holiday_date>=? AND holiday_date<? AND override_mode='DISABLE' ORDER BY holiday_date");
    $statement->execute([sprintf('%04d-01-01', $year), sprintf('%04d-01-01', $year + 1)]);
    foreach ($statement as $override) {
        $date = (string) $override['holiday_date'];
        $rows[$date] = [
            'date' => $date,
            'name' => (string) ($base[$date] ?? $override['name']),
            'source' => 'DISABLED',
            'credit_rule' => (string) $override['credit_rule'],
            'overridden' => true,
        ];
    }
    ksort($rows);
    return $rows;
}

function kz_schedule_for_date(int $employeeId, string $date): ?array
{
    $statement = kz_db()->prepare('SELECT * FROM work_schedules WHERE employee_id=? AND valid_from<=? AND (valid_until IS NULL OR valid_until>=?) ORDER BY valid_from DESC LIMIT 1');
    $statement->execute([$employeeId, $date, $date]);
    $schedule = $statement->fetch();
    return $schedule ?: null;
}

function kz_target_parts(int $employeeId, string $date): array
{
    $schedule = kz_schedule_for_date($employeeId, $date);
    $result = ['MORNING' => 0, 'AFTERNOON' => 0];
    if ($schedule === null) {
        return $result;
    }
    $weekday = (int) (new DateTimeImmutable($date, new DateTimeZone('Europe/Berlin')))->format('N');
    $statement = kz_db()->prepare('SELECT day_part,target_minutes FROM work_schedule_day_parts WHERE schedule_id=? AND weekday=?');
    $statement->execute([(int) $schedule['id'], $weekday]);
    foreach ($statement as $row) {
        $result[(string) $row['day_part']] = (int) $row['target_minutes'];
    }
    return $result;
}

function kz_is_month_closed(int $employeeId, string $date): bool
{
    $dt = new DateTimeImmutable($date, new DateTimeZone('Europe/Berlin'));
    $statement = kz_db()->prepare('SELECT 1 FROM month_closures WHERE employee_id=? AND year=? AND month=? AND status=\'CLOSED\'');
    $statement->execute([$employeeId, (int) $dt->format('Y'), (int) $dt->format('n')]);
    return (bool) $statement->fetchColumn();
}

function kz_assert_holiday_month_open(string $date): void
{
    $dt = new DateTimeImmutable($date, new DateTimeZone('Europe/Berlin'));
    $statement = kz_db()->prepare("SELECT COUNT(*) FROM month_closures WHERE year=? AND month=? AND status='CLOSED'");
    $statement->execute([(int) $dt->format('Y'), (int) $dt->format('n')]);
    if ((int) $statement->fetchColumn() > 0) {
        throw new RuntimeException('Mindestens ein Mitarbeiter-Monat fuer diesen Zeitraum ist abgeschlossen. Feiertag erst nach dokumentierter Wiedereroeffnung aendern.');
    }
}

function kz_effective_events(int $employeeId, ?string $from = null, ?string $to = null): array
{
    $params = [$employeeId];
    $where = 'te.employee_id=?';
    if ($from !== null) {
        $where .= ' AND te.occurred_at>=?';
        $params[] = $from;
    }
    if ($to !== null) {
        $where .= ' AND te.occurred_at<?';
        $params[] = $to;
    }
    $statement = kz_db()->prepare("SELECT te.id AS source_id,te.event_type,te.occurred_at,'RAW' AS source FROM time_events te WHERE $where AND NOT EXISTS(SELECT 1 FROM corrections c WHERE c.target_time_event_id=te.id AND c.correction_type IN ('VOID','REPLACE'))");
    $statement->execute($params);
    $events = $statement->fetchAll();

    $params = [$employeeId];
    $where = 'employee_id=? AND correction_type IN (\'ADD\',\'REPLACE\')';
    if ($from !== null) {
        $where .= ' AND corrected_time>=?';
        $params[] = $from;
    }
    if ($to !== null) {
        $where .= ' AND corrected_time<?';
        $params[] = $to;
    }
    $statement = kz_db()->prepare("SELECT id AS source_id,corrected_event_type AS event_type,corrected_time AS occurred_at,'CORRECTION' AS source FROM corrections WHERE $where");
    $statement->execute($params);
    $events = array_merge($events, $statement->fetchAll());
    usort($events, static fn(array $a, array $b): int => strcmp((string) $a['occurred_at'], (string) $b['occurred_at']));
    return $events;
}

function kz_local_datetime(string $utc): DateTimeImmutable
{
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Berlin'));
}

function kz_latest_effective_event(int $employeeId): ?array
{
    $events = kz_effective_events($employeeId);
    return $events === [] ? null : $events[array_key_last($events)];
}

function kz_minutes_label(int $minutes, bool $signed = false): string
{
    $prefix = '';
    if ($signed) {
        $prefix = $minutes > 0 ? '+' : ($minutes < 0 ? '-' : '');
    } elseif ($minutes < 0) {
        $prefix = '-';
    }
    $absolute = abs($minutes);
    return sprintf('%s%d:%02d', $prefix, intdiv($absolute, 60), $absolute % 60);
}

function kz_parse_duration(string $value): int
{
    $value = trim($value);
    if (!preg_match('/^(\d{1,2}):([0-5]\d)$/', $value, $matches)) {
        throw new InvalidArgumentException('Eine Sollzeit ist ungueltig. Bitte Stunden:Minuten eingeben.');
    }
    $minutes = (int) $matches[1] * 60 + (int) $matches[2];
    if ($minutes > 720) {
        throw new InvalidArgumentException('Ein Tagesabschnitt darf hoechstens 12 Stunden umfassen.');
    }
    return $minutes;
}

function kz_parse_signed_duration(string $value): int
{
    $value = trim($value);
    if (!preg_match('/^([+-]?)(\d{1,5}):([0-5]\d)$/', $value, $matches)) {
        throw new InvalidArgumentException('Der Saldo muss als +Stunden:Minuten oder -Stunden:Minuten angegeben werden.');
    }
    $minutes = (int) $matches[2] * 60 + (int) $matches[3];
    return $matches[1] === '-' ? -$minutes : $minutes;
}

function kz_duration_input(int $minutes): string
{
    return sprintf('%02d:%02d', intdiv(max(0, $minutes), 60), max(0, $minutes) % 60);
}

function kz_parse_worked_duration(string $value): int
{
    $value = trim($value);
    if (preg_match('/^(\d{1,2}):([0-5]\d)$/', $value, $matches)) {
        $minutes = (int) $matches[1] * 60 + (int) $matches[2];
    } else {
        $decimal = str_replace(',', '.', $value);
        if (!preg_match('/^\d{1,2}(?:\.\d{1,2})?$/', $decimal)) {
            throw new InvalidArgumentException('Die Arbeitszeit bitte beispielsweise als 8:30 oder 8,5 eingeben.');
        }
        $minutes = (int) round((float) $decimal * 60);
    }
    if ($minutes > 1440) {
        throw new InvalidArgumentException('Die Arbeitszeit eines Tages darf höchstens 24 Stunden betragen.');
    }
    return $minutes;
}

function kz_month_report(int $employeeId, int $year, int $month): array
{
    $startLocal = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), new DateTimeZone('Europe/Berlin'));
    $endLocal = $startLocal->modify('first day of next month');
    $fromUtc = $startLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    $toUtc = $endLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    $events = kz_effective_events($employeeId, $fromUtc, $toUtc);
    $byDate = [];
    foreach ($events as $event) {
        $local = kz_local_datetime((string) $event['occurred_at']);
        $byDate[$local->format('Y-m-d')][] = $event + ['local' => $local];
    }
    $statement = kz_db()->prepare('SELECT ad.*,at.name AS absence_name,at.type_code FROM absence_days ad JOIN absence_requests ar ON ar.id=ad.request_id JOIN absence_types at ON at.id=ar.absence_type_id WHERE ad.employee_id=? AND ad.work_date>=? AND ad.work_date<? AND ar.status=\'APPROVED\'');
    $statement->execute([$employeeId, $startLocal->format('Y-m-d'), $endLocal->format('Y-m-d')]);
    $absences = [];
    foreach ($statement as $row) {
        $absences[(string) $row['work_date']][] = $row;
    }
    $statement = kz_db()->prepare('SELECT * FROM day_time_overrides WHERE employee_id=? AND work_date>=? AND work_date<?');
    $statement->execute([$employeeId, $startLocal->format('Y-m-d'), $endLocal->format('Y-m-d')]);
    $dayOverrides = [];
    foreach ($statement as $row) $dayOverrides[(string)$row['work_date']] = $row;

    $holidays = kz_holidays($year);
    $days = [];
    $totals = ['target' => 0, 'worked' => 0, 'credited' => 0, 'balance' => 0];
    for ($date = $startLocal; $date < $endLocal; $date = $date->modify('+1 day')) {
        $dateKey = $date->format('Y-m-d');
        $parts = kz_target_parts($employeeId, $dateKey);
        $target = array_sum($parts);
        $worked = 0;
        $intervals = [];
        $open = null;
        $incomplete = false;
        foreach ($byDate[$dateKey] ?? [] as $event) {
            if ($event['event_type'] === 'COME') {
                if ($open !== null) {
                    $incomplete = true;
                }
                $open = $event['local'];
            } elseif ($open instanceof DateTimeImmutable) {
                $leave = $event['local'];
                if ($leave > $open) {
                    $minutes = (int) floor(($leave->getTimestamp() - $open->getTimestamp()) / 60);
                    $worked += $minutes;
                    $intervals[] = $open->format('H:i') . '–' . $leave->format('H:i');
                }
                $open = null;
            } else {
                $incomplete = true;
            }
        }
        if ($open !== null) {
            $incomplete = true;
            $intervals[] = $open->format('H:i') . '–offen';
        }

        if (isset($dayOverrides[$dateKey])) {
            $worked = (int) $dayOverrides[$dateKey]['worked_minutes'];
            $incomplete = false;
        }

        $credited = 0;
        $labels = [];
        if (isset($holidays[$dateKey])) {
            $credited = $holidays[$dateKey]['credit_rule'] === 'PLANNED_TIME' ? $target : 0;
            $labels[] = $holidays[$dateKey]['name'];
        } else {
            foreach ($absences[$dateKey] ?? [] as $absence) {
                $credited += (int) $absence['credited_minutes'];
                $labels[] = (string) $absence['absence_name'];
            }
            $credited = min($credited, $target);
        }
        if (isset($dayOverrides[$dateKey])) $labels[] = 'Auf Sollzeit gesetzt';
        if ((int) $date->format('N') >= 6 && $target === 0 && $worked === 0 && $credited === 0 && $labels === []) $labels[] = 'Wochenende';
        $balance = $worked + $credited - $target;
        $days[] = [
            'date' => $dateKey,
            'weekday' => ['So','Mo','Di','Mi','Do','Fr','Sa'][(int) $date->format('w')],
            'target' => $target,
            'worked' => $worked,
            'credited' => $credited,
            'balance' => $balance,
            'intervals' => $intervals,
            'note' => implode(', ', array_unique($labels)),
            'incomplete' => $incomplete,
        ];
        $totals['target'] += $target;
        $totals['worked'] += $worked;
        $totals['credited'] += $credited;
        $totals['balance'] += $balance;
    }
    return ['days' => $days, 'totals' => $totals];
}

function kz_date_range(string $start, string $end): array
{
    $from = new DateTimeImmutable($start, new DateTimeZone('Europe/Berlin'));
    $to = new DateTimeImmutable($end, new DateTimeZone('Europe/Berlin'));
    if ($to < $from || $to->diff($from)->days > 370) {
        throw new InvalidArgumentException('Der Datumsbereich ist ungueltig oder zu lang.');
    }
    $dates = [];
    for ($date = $from; $date <= $to; $date = $date->modify('+1 day')) {
        $dates[] = $date->format('Y-m-d');
    }
    return $dates;
}

function kz_request_part_for_date(array $request, string $date): string
{
    if ($request['start_date'] === $request['end_date']) {
        return (string) $request['start_part'];
    }
    if ($date === $request['start_date']) {
        return $request['start_part'] === 'AFTERNOON' ? 'AFTERNOON' : 'FULL_DAY';
    }
    if ($date === $request['end_date']) {
        return $request['end_part'] === 'MORNING' ? 'MORNING' : 'FULL_DAY';
    }
    return 'FULL_DAY';
}

function kz_vacation_balance(int $employeeId, int $year): array
{
    $statement = kz_db()->prepare('SELECT * FROM vacation_accounts WHERE employee_id=? AND year=?');
    $statement->execute([$employeeId, $year]);
    $account = $statement->fetch() ?: ['entitlement_days' => 0, 'carried_days' => 0, 'adjustment_days' => 0];
    $statement = kz_db()->prepare("SELECT COALESCE(SUM(ad.vacation_days_used),0) FROM absence_days ad JOIN absence_requests ar ON ar.id=ad.request_id JOIN absence_types at ON at.id=ar.absence_type_id WHERE ad.employee_id=? AND substr(ad.work_date,1,4)=? AND ar.status='APPROVED' AND at.consumes_vacation=1");
    $statement->execute([$employeeId, sprintf('%04d', $year)]);
    $used = (float) $statement->fetchColumn();
    $total = (float) $account['entitlement_days'] + (float) $account['carried_days'] + (float) $account['adjustment_days'];
    return ['total' => $total, 'used' => $used, 'remaining' => $total - $used];
}

function kz_vacation_summary(int $employeeId, int $year): array
{
    $balance = kz_vacation_balance($employeeId, $year);
    $statement = kz_db()->prepare("SELECT COALESCE(SUM(CASE WHEN ad.work_date<? THEN ad.vacation_days_used ELSE 0 END),0) taken,COALESCE(SUM(CASE WHEN ad.work_date>=? THEN ad.vacation_days_used ELSE 0 END),0) planned FROM absence_days ad JOIN absence_requests ar ON ar.id=ad.request_id JOIN absence_types at ON at.id=ar.absence_type_id WHERE ad.employee_id=? AND substr(ad.work_date,1,4)=? AND ar.status='APPROVED' AND at.consumes_vacation=1");
    $statement->execute([kz_today(),kz_today(),$employeeId,sprintf('%04d',$year)]);
    $row=$statement->fetch()?:['taken'=>0,'planned'=>0];
    return $balance+['taken'=>(float)$row['taken'],'planned'=>(float)$row['planned']];
}

function kz_sickness_days(int $employeeId, int $year): float
{
    $statement = kz_db()->prepare("SELECT ar.* FROM absence_requests ar JOIN absence_types at ON at.id=ar.absence_type_id WHERE ar.employee_id=? AND ar.status='APPROVED' AND at.type_code='ILLNESS' AND ar.start_date<=? AND ar.end_date>=? ORDER BY ar.start_date");
    $statement->execute([$employeeId, sprintf('%04d-12-31', $year), sprintf('%04d-01-01', $year)]);
    $holidays = kz_holidays($year);
    $counted = [];
    foreach ($statement as $request) {
        $start = max((string) $request['start_date'], sprintf('%04d-01-01', $year));
        $end = min((string) $request['end_date'], sprintf('%04d-12-31', $year));
        foreach (kz_date_range($start, $end) as $date) {
            if (isset($holidays[$date])) continue;
            $part = kz_request_part_for_date($request, $date);
            $targets = kz_target_parts($employeeId, $date);
            if ($part === 'MORNING' && $targets['MORNING'] > 0) $counted[$date]['MORNING'] = true;
            elseif ($part === 'AFTERNOON' && $targets['AFTERNOON'] > 0) $counted[$date]['AFTERNOON'] = true;
            elseif ($part === 'FULL_DAY' && $targets['MORNING'] + $targets['AFTERNOON'] > 0) $counted[$date]['FULL_DAY'] = true;
        }
    }
    $days = 0.0;
    foreach ($counted as $parts) $days += isset($parts['FULL_DAY']) ? 1.0 : 0.5 * count($parts);
    return $days;
}

function kz_absence_days_by_type_until(int $employeeId, int $year, string $throughExclusive): array
{
    $result = ['ILLNESS'=>0.0,'TRAINING'=>0.0,'OTHER'=>0.0,'UNPAID'=>0.0];
    $statement = kz_db()->prepare("SELECT at.type_code,COALESCE(SUM(CASE WHEN ad.day_part='FULL_DAY' THEN 1.0 ELSE 0.5 END),0) AS days FROM absence_days ad JOIN absence_requests ar ON ar.id=ad.request_id JOIN absence_types at ON at.id=ar.absence_type_id WHERE ad.employee_id=? AND ad.work_date>=? AND ad.work_date<? AND ar.status='APPROVED' AND at.type_code IN ('ILLNESS','TRAINING','OTHER','UNPAID') GROUP BY at.type_code");
    $statement->execute([$employeeId,sprintf('%04d-01-01',$year),$throughExclusive]);
    foreach($statement as $row)$result[(string)$row['type_code']]=(float)$row['days'];
    return $result;
}

function kz_team_month_overview(int $year, int $month): array
{
    $year=max(2000,min(2100,$year));$month=max(1,min(12,$month));
    $timezone=new DateTimeZone('Europe/Berlin');
    $monthStart=new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month),$timezone);
    $currentMonth=(new DateTimeImmutable(kz_today(),$timezone))->modify('first day of this month');
    if($monthStart>$currentMonth){$monthStart=$currentMonth;$year=(int)$monthStart->format('Y');$month=(int)$monthStart->format('n');}
    $monthEnd=$monthStart->modify('first day of next month');$today=new DateTimeImmutable(kz_today(),$timezone);
    $throughExclusive=$monthEnd<$today?$monthEnd:$today;
    $cutoffLabel=$throughExclusive>$monthStart?$throughExclusive->modify('-1 day')->format('d.m.Y'):'noch kein abgeschlossener Tag';
    $employees=kz_db()->query('SELECT * FROM employees WHERE active=1 ORDER BY full_name,name')->fetchAll();$rows=[];
    foreach($employees as $employee){
        $employeeId=(int)$employee['id'];$report=kz_month_report($employeeId,$year,$month);$monthBalance=0;
        foreach($report['days'] as $day)if($day['date']<$throughExclusive->format('Y-m-d'))$monthBalance+=(int)$day['balance'];
        $vacation=kz_vacation_balance($employeeId,$year);$absence=kz_absence_days_by_type_until($employeeId,$year,$throughExclusive->format('Y-m-d'));
        $rows[]=['employee'=>$employee,'total_balance'=>kz_total_balance($employeeId,$throughExclusive->format('Y-m-d')),'month_balance'=>$monthBalance,'vacation_total'=>(float)$vacation['total'],'vacation_used'=>(float)$vacation['used'],'vacation_remaining'=>(float)$vacation['remaining'],'illness'=>$absence['ILLNESS'],'training'=>$absence['TRAINING'],'other'=>$absence['OTHER'],'unpaid'=>$absence['UNPAID']];
    }
    return ['year'=>$year,'month'=>$month,'month_start'=>$monthStart->format('Y-m-d'),'through_exclusive'=>$throughExclusive->format('Y-m-d'),'cutoff_label'=>$cutoffLabel,'rows'=>$rows];
}

function kz_days_label(float $days): string
{
    return number_format($days,1,',','.');
}

function kz_total_balance(int $employeeId, ?string $throughExclusive=null): int
{
    $throughExclusive ??= kz_today();
    $statement=kz_db()->prepare("SELECT MIN(d) FROM (SELECT MIN(substr(occurred_at,1,10)) d FROM time_events WHERE employee_id=? UNION ALL SELECT MIN(valid_from) FROM work_schedules WHERE employee_id=? UNION ALL SELECT MIN(effective_date) FROM balance_adjustments WHERE employee_id=?)");
    $statement->execute([$employeeId,$employeeId,$employeeId]);
    $earliest=$statement->fetchColumn();
    $start=new DateTimeImmutable(is_string($earliest)&&$earliest!==''?$earliest:$throughExclusive,new DateTimeZone('Europe/Berlin'));
    $end=new DateTimeImmutable($throughExclusive,new DateTimeZone('Europe/Berlin'));
    $cursor=$start->modify('first day of this month');
    $balance=0;
    while($cursor<$end){
        $report=kz_month_report($employeeId,(int)$cursor->format('Y'),(int)$cursor->format('n'));
        foreach($report['days'] as $day) if($day['date']<$throughExclusive) $balance+=(int)$day['balance'];
        $cursor=$cursor->modify('first day of next month');
    }
    $statement=kz_db()->prepare('SELECT COALESCE(SUM(adjustment_minutes),0) FROM balance_adjustments WHERE employee_id=? AND effective_date<=?');
    $statement->execute([$employeeId,$throughExclusive]);
    return $balance+(int)$statement->fetchColumn();
}

function kz_current_presence(): array
{
    $employees=kz_db()->query('SELECT * FROM employees WHERE active=1 ORDER BY name')->fetchAll();
    $present=[];
    foreach($employees as $employee){
        $latest=kz_latest_effective_event((int)$employee['id']);
        if(!$latest||$latest['event_type']!=='COME') continue;
        $local=kz_local_datetime((string)$latest['occurred_at']);
        if($local->format('Y-m-d')!==kz_today()) continue;
        $check=kz_db()->prepare('SELECT 1 FROM day_time_overrides WHERE employee_id=? AND work_date=?');$check->execute([(int)$employee['id'],kz_today()]);
        if($check->fetchColumn()) continue;
        $terminal=kz_db()->prepare('SELECT t.label FROM time_events te JOIN terminals t ON t.id=te.terminal_id WHERE te.employee_id=? ORDER BY te.occurred_at DESC LIMIT 1');
        $terminal->execute([(int)$employee['id']]);
        $present[]=['employee'=>$employee,'since'=>$local,'terminal'=>(string)($terminal->fetchColumn()?:'Korrektur'),'latest'=>$latest];
    }
    return $present;
}

function kz_opening_hours(): array
{
    $rows=kz_db()->query('SELECT * FROM opening_hours ORDER BY weekday,day_part')->fetchAll();$result=[];
    foreach($rows as $row)$result[(int)$row['weekday']][(string)$row['day_part']]=$row;
    return $result;
}

function kz_booking_issues(): array
{
    $issues=[];$now=time();$today=kz_today();
    foreach(kz_db()->query('SELECT id,name,full_name FROM employees WHERE active=1 ORDER BY name') as $employee){
        $events=kz_effective_events((int)$employee['id']);$days=[];
        foreach($events as $event){$local=kz_local_datetime((string)$event['occurred_at']);$days[$local->format('Y-m-d')][]=$event+['local'=>$local];}
        foreach($days as $date=>$dayEvents){
            $open=null;$types=[];$details=[];
            foreach($dayEvents as $event){
                if($event['event_type']==='COME'){
                    if($open!==null){$types[]='Doppeltes Kommen';$details[]='Kommen trotz offener Anwesenheit';}
                    $open=$event['local'];
                }else{
                    if(!($open instanceof DateTimeImmutable)){$types[]='Gehen ohne Kommen';$details[]='Gehen ohne vorheriges Kommen';}
                    else{$minutes=(int)(($event['local']->getTimestamp()-$open->getTimestamp())/60);if($minutes>600){$types[]='Mehr als 10 Stunden';$details[]=kz_minutes_label($minutes).' Anwesenheit';}$open=null;}
                }
            }
            if($open instanceof DateTimeImmutable){$age=(int)(($now-$open->getTimestamp())/60);if($date<$today){$types[]='Kommen ohne Gehen';$details[]='Offene Buchung';}elseif($age>600){$types[]='Mehr als 10 Stunden';$details[]='Seit '.kz_minutes_label($age).' offen';}}
            if($types===[])continue;
            $types=array_values(array_unique($types));$fingerprint=hash('sha256',$employee['id'].'|'.$date.'|'.implode('|',$types).'|'.implode(',',array_column($dayEvents,'source_id')));
            $s=kz_db()->prepare('SELECT 1 FROM booking_issue_resolutions WHERE issue_fingerprint=?');$s->execute([$fingerprint]);if($s->fetchColumn())continue;
            $issues[]=['fingerprint'=>$fingerprint,'employee'=>$employee,'date'=>$date,'types'=>$types,'details'=>$details,'events'=>$dayEvents,'target'=>array_sum(kz_target_parts((int)$employee['id'],$date))];
        }
    }
    usort($issues,static fn($a,$b)=>strcmp($b['date'],$a['date']));
    return $issues;
}

function kz_pdf_text(string $text): string
{
    $encoded=iconv('UTF-8','Windows-1252//TRANSLIT',$text);if($encoded===false)$encoded=$text;
    return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$encoded);
}

function kz_generate_table_pdf(string $title,array $headers,array $rows,array $widths,array $summary=[]): string
{
    $pageWidth=842;$pageHeight=595;$margin=30;$usable=$pageWidth-2*$margin;$sum=array_sum($widths);
    $widths=array_map(static fn($w)=>$usable*$w/$sum,$widths);
    $firstRows=max(8,26-count($summary));$chunks=[];
    if($rows===[])$chunks=[[]];else{$chunks[]=array_splice($rows,0,$firstRows);while($rows)$chunks[]=array_splice($rows,0,28);}
    $objects = [];
    $pageIds = [];
    $contentIds = [];
    $nextId = 4;
    foreach ($chunks as $_) {
        $pageIds[] = $nextId++;
        $contentIds[] = $nextId++;
    }
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $kids = implode(' ', array_map(static fn(int $id): string => "$id 0 R", $pageIds));
    $objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageIds) . ' >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $created=(new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('d.m.Y H:i');$pageCount=count($chunks);
    foreach ($chunks as $index => $pageRows) {
        $content="BT /F1 16 Tf {$margin} 557 Td (".kz_pdf_text($title).") Tj ET\n";
        $content.="BT /F1 8 Tf ".($pageWidth-$margin-220)." 559 Td (Erstellt: ".kz_pdf_text($created)." Uhr) Tj ET\n";
        $y=535;
        if($index===0){foreach($summary as $line){$content.="BT /F1 9 Tf {$margin} {$y} Td (".kz_pdf_text((string)$line).") Tj ET\n";$y-=13;}$y-=5;}
        $headerHeight=18;$rowHeight=16;$x=$margin;
        $content.="0.90 0.94 0.90 rg {$margin} ".($y-$headerHeight)." {$usable} {$headerHeight} re f 0 G 0 g\n";
        foreach($headers as $i=>$header){$w=$widths[$i]??50;$content.="$x ".($y-$headerHeight)." $w $headerHeight re S\n";$label=mb_strimwidth((string)$header,0,max(2,(int)($w/5.2)-1),'…','UTF-8');$content.="BT /F1 8 Tf ".($x+3)." ".($y-12)." Td (".kz_pdf_text($label).") Tj ET\n";$x+=$w;}
        $y-=$headerHeight;
        foreach($pageRows as $row){$x=$margin;foreach($headers as $i=>$_){$w=$widths[$i]??50;$content.="$x ".($y-$rowHeight)." $w $rowHeight re S\n";$value=(string)($row[$i]??'');$value=mb_strimwidth($value,0,max(2,(int)($w/5.0)-1),'…','UTF-8');$content.="BT /F1 7.5 Tf ".($x+3)." ".($y-11)." Td (".kz_pdf_text($value).") Tj ET\n";$x+=$w;}$y-=$rowHeight;}
        $footer=KZ_APP_TITLE.' · v'.KZ_APP_VERSION.' · '.KZ_APP_AUTHOR.' · Erstellt '.$created.' Uhr · Seite '.($index+1).'/'.$pageCount;
        $content.="BT /F1 8 Tf {$margin} 18 Td (".kz_pdf_text($footer).") Tj ET";
        $pageId = $pageIds[$index];
        $contentId = $contentIds[$index];
        $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pageWidth $pageHeight] /Resources << /Font << /F1 3 0 R >> >> /Contents $contentId 0 R >>";
        $objects[$contentId] = '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "\nendstream";
    }
    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $id => $object) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "$id 0 obj\n$object\nendobj\n";
    }
    $xref = strlen($pdf);
    $max = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($id = 1; $id <= $max; $id++) {
        $pdf .= sprintf('%010d 00000 n ', $offsets[$id]) . "\n";
    }
    $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    return $pdf;
}

function kz_cli_install(array $input): array
{
    $db = kz_db();
    $username = trim((string) ($input['admin_username'] ?? 'admin'));
    $displayName = trim((string) ($input['admin_display_name'] ?? KZ_APP_AUTHOR));
    $password = (string) ($input['admin_password'] ?? '');
    $terminalCode = trim((string) ($input['terminal_code'] ?? 'eingang-1'));
    $terminalLabel = trim((string) ($input['terminal_label'] ?? 'Eingang'));
    $terminalKey = (string) ($input['terminal_key'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username) || strlen($password) < 12) {
        throw new InvalidArgumentException('Admin-Benutzer oder Passwort ist ungueltig.');
    }
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $terminalCode) || $terminalKey === '') {
        throw new InvalidArgumentException('Terminal-ID oder Terminal-Key ist ungueltig.');
    }
    $now = kz_now_utc();
    $createdAdmin = false;
    $createdTerminal = false;
    $encryptedTerminalKey = kz_encrypt_terminal_secret($terminalKey);
    $db->exec('BEGIN IMMEDIATE');
    try {
        $statement = $db->prepare('SELECT id FROM admin_users WHERE username=?');
        $statement->execute([$username]);
        if ($statement->fetchColumn() === false) {
            $statement = $db->prepare('INSERT INTO admin_users(username,display_name,password_hash,active,must_change_password,created_at) VALUES(?,?,?,1,1,?)');
            $statement->execute([$username, $displayName, password_hash($password, PASSWORD_DEFAULT), $now]);
            $adminId = (int) $db->lastInsertId();
            $createdAdmin = true;
            $statement = $db->prepare('INSERT INTO admin_audit_log(admin_user_id,actor_label,action,entity_type,entity_id,after_data,created_at) VALUES(?,?,?,?,?,?,?)');
            $statement->execute([$adminId, 'Installer', 'CREATE', 'admin_user', (string) $adminId, kz_json(['username' => $username]), $now]);
        }
        $statement = $db->prepare('SELECT id FROM terminals WHERE terminal_code=?');
        $statement->execute([$terminalCode]);
        if ($statement->fetchColumn() === false) {
            $statement = $db->prepare('INSERT INTO terminals(terminal_code,label,key_hash,key_ciphertext,key_nonce,key_tag,active,created_at,updated_at) VALUES(?,?,?,?,?,?,1,?,?)');
            $statement->execute([$terminalCode, $terminalLabel, hash('sha256', $terminalKey), $encryptedTerminalKey['ciphertext'], $encryptedTerminalKey['nonce'], $encryptedTerminalKey['tag'], $now, $now]);
            $createdTerminal = true;
        }
        $db->exec('COMMIT');
    } catch (Throwable $exception) {
        $db->exec('ROLLBACK');
        throw $exception;
    }
    return ['ok' => true, 'message' => 'Datenbank initialisiert.', 'created_admin' => $createdAdmin, 'created_terminal' => $createdTerminal];
}

function kz_cli_reset_admin(array $input): array
{
    $username = trim((string) ($input['admin_username'] ?? 'admin'));
    $password = (string) ($input['admin_password'] ?? '');
    if (strlen($password) < 12) {
        throw new InvalidArgumentException('Das neue Passwort ist zu kurz.');
    }
    $db = kz_db();
    $statement = $db->prepare('SELECT * FROM admin_users WHERE username=?');
    $statement->execute([$username]);
    $admin = $statement->fetch();
    if (!$admin) {
        throw new RuntimeException('Admin-Konto wurde nicht gefunden.');
    }
    $statement = $db->prepare('UPDATE admin_users SET password_hash=?,must_change_password=1,session_version=session_version+1,failed_attempts=0,locked_until=NULL WHERE id=?');
    $statement->execute([password_hash($password, PASSWORD_DEFAULT), (int) $admin['id']]);
    kz_audit('PASSWORD_RESET', 'admin_user', (int) $admin['id'], null, ['username' => $username], 'Notfallruecksetzung durch Installer', (int) $admin['id'], 'Installer');
    return ['ok' => true, 'message' => 'Admin-Passwort wurde zurueckgesetzt.'];
}

function kz_cli_dispatch(array $argv): never
{
    $command = $argv[1] ?? '';
    $raw = stream_get_contents(STDIN);
    $input = json_decode($raw ?: '{}', true);
    if (!is_array($input)) {
        fwrite(STDERR, "Ungueltige Installationsdaten.\n");
        exit(2);
    }
    try {
        $result = match ($command) {
            '--install' => kz_cli_install($input),
            '--reset-admin' => kz_cli_reset_admin($input),
            default => throw new InvalidArgumentException('Unbekannter CLI-Befehl.'),
        };
        fwrite(STDOUT, kz_json($result) . PHP_EOL);
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

if (PHP_SAPI === 'cli' && isset($argv[1]) && str_starts_with((string) $argv[1], '--')) {
    kz_cli_dispatch($argv);
}

if (defined('KZ_LIBRARY_ONLY')) {
    return;
}

function kz_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('kienzlezeit_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    if (!isset($_SESSION['created_at'])) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }
    if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > 1800) {
        session_unset();
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}

function kz_csrf(): string
{
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['csrf'];
}

function kz_require_csrf(): void
{
    $provided = (string) ($_POST['csrf'] ?? '');
    if ($provided === '' || !hash_equals(kz_csrf(), $provided)) {
        throw new RuntimeException('Die Sitzung ist abgelaufen. Bitte erneut versuchen.');
    }
}

function kz_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function kz_take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flashes) ? $flashes : [];
}

function kz_redirect(string $target): never
{
    header('Location: ' . $target, true, 303);
    exit;
}

function kz_is_admin(): bool
{
    if (!isset($_SESSION['admin_id'], $_SESSION['admin_session_version'])) {
        return false;
    }
    $statement = kz_db()->prepare('SELECT active,session_version FROM admin_users WHERE id=?');
    $statement->execute([(int) $_SESSION['admin_id']]);
    $admin = $statement->fetch();
    if (!$admin || !(bool) $admin['active'] || (int) $admin['session_version'] !== (int) $_SESSION['admin_session_version']) {
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_session_version']);
        return false;
    }
    return true;
}

function kz_require_admin(): void
{
    if (!kz_is_admin()) {
        throw new RuntimeException('Admin-Anmeldung erforderlich.');
    }
}

function kz_employee_id(): ?int
{
    return isset($_SESSION['employee_id']) ? (int) $_SESSION['employee_id'] : null;
}

function kz_admin_login(string $username, string $password): bool
{
    $db = kz_db();
    $statement = $db->prepare('SELECT * FROM admin_users WHERE username=? AND active=1');
    $statement->execute([trim($username)]);
    $admin = $statement->fetch();
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if ($admin && $admin['locked_until'] && new DateTimeImmutable((string) $admin['locked_until']) > $now) {
        return false;
    }
    if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
        if ($admin) {
            $attempts = (int) $admin['failed_attempts'] + 1;
            $lockedUntil = $attempts >= 5 ? $now->modify('+5 minutes')->format('Y-m-d\TH:i:s\Z') : null;
            $statement = $db->prepare('UPDATE admin_users SET failed_attempts=?,locked_until=? WHERE id=?');
            $statement->execute([$attempts, $lockedUntil, (int) $admin['id']]);
        }
        return false;
    }
    $statement = $db->prepare('UPDATE admin_users SET failed_attempts=0,locked_until=NULL,last_login_at=? WHERE id=?');
    $statement->execute([kz_now_utc(), (int) $admin['id']]);
    session_regenerate_id(true);
    unset($_SESSION['employee_id']);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_name'] = (string) $admin['display_name'];
    $_SESSION['admin_session_version'] = (int) $admin['session_version'];
    $_SESSION['must_change_password'] = (bool) $admin['must_change_password'];
    kz_audit('LOGIN', 'admin_user', (int) $admin['id']);
    return true;
}

function kz_expire_terminal_actions(PDO $db): void
{
    $now = kz_now_utc();
    $db->prepare("UPDATE terminal_actions SET status='EXPIRED' WHERE status='PENDING' AND expires_at<?")->execute([$now]);
    $db->prepare("UPDATE login_challenges SET status='EXPIRED' WHERE status='PENDING' AND expires_at<?")->execute([$now]);
}

function kz_create_terminal_action(int $terminalId, string $type, ?int $employeeId = null, ?int $challengeId = null): int
{
    $db = kz_db();
    kz_expire_terminal_actions($db);
    $statement = $db->prepare("SELECT 1 FROM terminal_actions WHERE terminal_id=? AND status='PENDING' AND expires_at>=?");
    $statement->execute([$terminalId, kz_now_utc()]);
    if ($statement->fetchColumn()) {
        throw new RuntimeException('Dieses Terminal ist gerade belegt.');
    }
    $expires = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+60 seconds')->format('Y-m-d\TH:i:s\Z');
    $statement = $db->prepare("INSERT INTO terminal_actions(terminal_id,action_type,status,employee_id,login_challenge_id,created_by_admin_id,created_at,expires_at) VALUES(?,?,'PENDING',?,?,?,?,?)");
    $statement->execute([$terminalId, $type, $employeeId, $challengeId, $_SESSION['admin_id'] ?? null, kz_now_utc(), $expires]);
    return (int) $db->lastInsertId();
}

function kz_create_absence_days(array $request): array
{
    $db = kz_db();
    $statement = $db->prepare('SELECT * FROM absence_types WHERE id=?');
    $statement->execute([(int) $request['absence_type_id']]);
    $type = $statement->fetch();
    if (!$type) {
        throw new RuntimeException('Abwesenheitsart fehlt.');
    }
    $rows = [];
    foreach (kz_date_range((string) $request['start_date'], (string) $request['end_date']) as $date) {
        if (kz_is_month_closed((int) $request['employee_id'], $date)) {
            throw new RuntimeException('Der Zeitraum enthaelt einen abgeschlossenen Monat.');
        }
        $parts = kz_target_parts((int) $request['employee_id'], $date);
        $part = kz_request_part_for_date($request, $date);
        $target = match ($part) {
            'MORNING' => $parts['MORNING'],
            'AFTERNOON' => $parts['AFTERNOON'],
            default => $parts['MORNING'] + $parts['AFTERNOON'],
        };
        if ($target <= 0 || isset(kz_holidays((int) substr($date, 0, 4))[$date])) {
            continue;
        }
        $overlap = $db->prepare("SELECT 1 FROM absence_days ad JOIN absence_requests ar ON ar.id=ad.request_id WHERE ad.employee_id=? AND ad.work_date=? AND ar.status='APPROVED' AND (ad.day_part='FULL_DAY' OR ?='FULL_DAY' OR ad.day_part=?)");
        $overlap->execute([(int) $request['employee_id'], $date, $part, $part]);
        if ($overlap->fetchColumn()) {
            throw new RuntimeException('Am ' . $date . ' besteht bereits eine genehmigte Abwesenheit.');
        }
        $credited = $type['time_credit_rule'] === 'NO_CREDIT' ? 0 : $target;
        $vacation = (bool) $type['consumes_vacation'] ? ($part === 'FULL_DAY' ? 1.0 : 0.5) : 0.0;
        $rows[] = ['date' => $date, 'part' => $part, 'target' => $target, 'credited' => $credited, 'vacation' => $vacation];
    }
    if ((bool) $type['consumes_vacation']) {
        $byYear = [];
        foreach ($rows as $row) {
            $byYear[(int) substr($row['date'], 0, 4)] = ($byYear[(int) substr($row['date'], 0, 4)] ?? 0) + $row['vacation'];
        }
        foreach ($byYear as $year => $needed) {
            if (kz_vacation_balance((int) $request['employee_id'], $year)['remaining'] + 0.0001 < $needed) {
                throw new RuntimeException('Der Urlaubsanspruch fuer ' . $year . ' reicht nicht aus.');
            }
        }
    }
    return $rows;
}

kz_start_session();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'");

function kz_valid_date(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Berlin'));
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Ungueltiges Datum.');
    }
    return $date;
}

function kz_admin_absence_decide(int $requestId, string $decision, string $note): void
{
    kz_require_admin();
    $db = kz_db();
    $statement = $db->prepare('SELECT * FROM absence_requests WHERE id=?');
    $statement->execute([$requestId]);
    $request = $statement->fetch();
    if (!$request || $request['status'] !== 'PENDING') {
        throw new RuntimeException('Der Antrag ist nicht mehr offen.');
    }
    if (!in_array($decision, ['APPROVED', 'REJECTED'], true)) {
        throw new InvalidArgumentException('Ungueltige Entscheidung.');
    }
    $rows = $decision === 'APPROVED' ? kz_create_absence_days($request) : [];
    $db->exec('BEGIN IMMEDIATE');
    try {
        if ($decision === 'APPROVED') {
            $insert = $db->prepare('INSERT INTO absence_days(request_id,employee_id,work_date,day_part,target_minutes,credited_minutes,vacation_days_used) VALUES(?,?,?,?,?,?,?)');
            foreach ($rows as $row) {
                $insert->execute([$requestId, (int) $request['employee_id'], $row['date'], $row['part'], $row['target'], $row['credited'], $row['vacation']]);
            }
        }
        $statement = $db->prepare('UPDATE absence_requests SET status=?,admin_note=?,decided_at=?,decided_by=? WHERE id=?');
        $statement->execute([$decision, $note, kz_now_utc(), (int) $_SESSION['admin_id'], $requestId]);
        $statement = $db->prepare('INSERT INTO absence_request_history(request_id,previous_status,new_status,changed_by,actor_label,reason,changed_at) VALUES(?,?,?,?,?,?,?)');
        $statement->execute([$requestId, 'PENDING', $decision, (int) $_SESSION['admin_id'], (string) $_SESSION['admin_name'], $note, kz_now_utc()]);
        $db->exec('COMMIT');
    } catch (Throwable $exception) {
        $db->exec('ROLLBACK');
        throw $exception;
    }
    kz_audit($decision === 'APPROVED' ? 'APPROVE' : 'REJECT', 'absence_request', $requestId, $request, ['status' => $decision], $note);
}

function kz_handle_export(string $type): never
{
    $db = kz_db();
    $format = strtolower((string) ($_GET['format'] ?? 'csv'));
    if (!in_array($format, ['csv', 'pdf'], true)) {
        throw new InvalidArgumentException('Ungueltiges Exportformat.');
    }
    $employeeId = (int) ($_GET['employee_id'] ?? 0);
    $year = max(2000, min(2100, (int) ($_GET['year'] ?? date('Y'))));
    $month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));
    $rows = [];
    $filename = 'kienzlezeit-export';
    $pdfTitle='kienzlezeit Export';$pdfSummary=[];$pdfWidths=[];

    if ($type === 'holidays') {
        kz_require_admin();
        $rows[] = ['Datum','Bezeichnung','Quelle','Zeitgutschrift'];
        $state=kz_holiday_state_for_year($year);$stateName=kz_federal_states()[$state];$pdfTitle='Feiertagskalender '.$stateName.' '.$year;
        foreach (kz_holidays($year) as $holiday) {
            $source = match ($holiday['source']) {
                'STATE_AUTO' => $stateName.' automatisch',
                'MANUAL_OVERRIDE' => 'manuell geaendert',
                default => 'manuell',
            };
            $credit = $holiday['credit_rule'] === 'PLANNED_TIME' ? 'volle Sollzeit' : 'keine';
            $rows[] = [$holiday['date'], $holiday['name'], $source, $credit];
        }
        $pdfWidths=[1,2.5,2,1.4];
        $filename = 'kienzlezeit-feiertage-' . $year;
        $db->prepare('INSERT INTO export_log(admin_user_id,export_type,export_format,period_label,employee_scope,created_at) VALUES(?,?,?,?,?,?)')->execute([(int) $_SESSION['admin_id'], 'HOLIDAYS', strtoupper($format), (string) $year, 'ALL', kz_now_utc()]);
    } elseif ($type === 'own_month') {
        $employeeId = kz_employee_id() ?? 0;
        if ($employeeId <= 0) {
            throw new RuntimeException('Mitarbeiter-Anmeldung erforderlich.');
        }
        $statement = $db->prepare('SELECT * FROM employees WHERE id=?');
        $statement->execute([$employeeId]);
        $employee = $statement->fetch();
        $report = kz_month_report($employeeId, $year, $month);
        $rows[] = ['Datum','Tag','Intervalle','Soll','Arbeit','Gutschrift','Saldo','Hinweis'];
        foreach ($report['days'] as $day) {
            $rows[] = [$day['date'],$day['weekday'],implode(' / ', $day['intervals']),kz_minutes_label($day['target']),kz_minutes_label($day['worked']),kz_minutes_label($day['credited']),kz_minutes_label($day['balance'], true),$day['note']];
        }
        $fullName=(string)($employee['full_name']?:$employee['name']);$vac=kz_vacation_summary($employeeId,$year);$through=sprintf('%04d-%02d-01',$year,$month);$through=(new DateTimeImmutable($through))->modify('first day of next month')->format('Y-m-d');if($through>kz_today())$through=kz_today();
        $pdfTitle='Stundenzettel '.$fullName.' · '.sprintf('%02d/%04d',$month,$year);
        $pdfSummary=['Gesamtsoll: '.kz_minutes_label($report['totals']['target']).' · Arbeitszeit: '.kz_minutes_label($report['totals']['worked']).' · Gutschriften: '.kz_minutes_label($report['totals']['credited']).' · Monatssaldo: '.kz_minutes_label($report['totals']['balance'],true),'Gesamtstundenkonto: '.kz_minutes_label(kz_total_balance($employeeId,$through),true),'Urlaub '.$year.': Anspruch '.number_format($vac['total'],1,',','.').' · genommen '.number_format($vac['taken'],1,',','.').' · geplant '.number_format($vac['planned'],1,',','.').' · Rest '.number_format($vac['remaining'],1,',','.')];
        $pdfWidths=[1.1,.6,2.2,.8,.8,.9,.8,1.5];
        $filename = 'kienzlezeit-' . $employee['personnel_number'] . '-' . sprintf('%04d-%02d', $year, $month);
    } elseif ($type === 'admin_month') {
        kz_require_admin();
        $statement = $db->prepare('SELECT * FROM employees WHERE id=?');
        $statement->execute([$employeeId]);
        $employee = $statement->fetch();
        if (!$employee) {
            throw new RuntimeException('Mitarbeiter wurde nicht gefunden.');
        }
        $report = kz_month_report($employeeId, $year, $month);
        $rows[] = ['Personalnummer','Name','Datum','Tag','Intervalle','Soll','Arbeit','Gutschrift','Saldo','Hinweis'];
        foreach ($report['days'] as $day) {
            $rows[] = [$employee['personnel_number'],($employee['full_name']?:$employee['name']),$day['date'],$day['weekday'],implode(' / ', $day['intervals']),kz_minutes_label($day['target']),kz_minutes_label($day['worked']),kz_minutes_label($day['credited']),kz_minutes_label($day['balance'], true),$day['note']];
        }
        $fullName=(string)($employee['full_name']?:$employee['name']);$vac=kz_vacation_summary($employeeId,$year);$through=(new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month)))->modify('first day of next month')->format('Y-m-d');if($through>kz_today())$through=kz_today();
        $pdfTitle='Monatsnachweis '.$fullName.' · '.sprintf('%02d/%04d',$month,$year);
        $pdfSummary=['Gesamtsoll: '.kz_minutes_label($report['totals']['target']).' · Arbeitszeit: '.kz_minutes_label($report['totals']['worked']).' · Gutschriften: '.kz_minutes_label($report['totals']['credited']).' · Monatssaldo: '.kz_minutes_label($report['totals']['balance'],true),'Gesamtstundenkonto: '.kz_minutes_label(kz_total_balance($employeeId,$through),true),'Urlaub '.$year.': Anspruch '.number_format($vac['total'],1,',','.').' · genommen '.number_format($vac['taken'],1,',','.').' · geplant '.number_format($vac['planned'],1,',','.').' · Rest '.number_format($vac['remaining'],1,',','.')];
        $pdfWidths=[.8,1.2,.9,.5,1.7,.7,.7,.7,.7,1.2];
        $filename = 'kienzlezeit-monat-' . $employee['personnel_number'] . '-' . sprintf('%04d-%02d', $year, $month);
        $db->prepare('INSERT INTO export_log(admin_user_id,export_type,export_format,period_label,employee_scope,created_at) VALUES(?,?,?,?,?,?)')->execute([(int) $_SESSION['admin_id'], 'MONTH', strtoupper($format), sprintf('%04d-%02d', $year, $month), (string) $employeeId, kz_now_utc()]);
    } elseif ($type === 'team_month_summary') {
        kz_require_admin();$overview=kz_team_month_overview($year,$month);$year=(int)$overview['year'];$month=(int)$overview['month'];
        $rows[]=['Mitarbeiter','Gesamtsaldo','Monatssaldo','Urlaub gesamt','genommen/geplant','Resturlaub','Krankheit','Fortbildung','Sonstige','Unbezahlt frei'];
        foreach($overview['rows'] as $row){$employee=$row['employee'];$name=(string)($employee['full_name']?:$employee['name']);if(trim((string)$employee['personnel_number'])!=='')$name.=' · '.$employee['personnel_number'];$rows[]=[$name,kz_minutes_label((int)$row['total_balance'],true),kz_minutes_label((int)$row['month_balance'],true),kz_days_label((float)$row['vacation_total']),kz_days_label((float)$row['vacation_used']),kz_days_label((float)$row['vacation_remaining']),kz_days_label((float)$row['illness']),kz_days_label((float)$row['training']),kz_days_label((float)$row['other']),kz_days_label((float)$row['unpaid'])];}
        $pdfTitle='Mitarbeiterübersicht · '.sprintf('%02d/%04d',$month,$year);$pdfSummary=['Berechnungsstand: '.$overview['cutoff_label'].' · Zeiten nur aus abgeschlossenen Tagen.','Urlaub genommen/geplant enthält alle genehmigten Urlaubstage des Jahres. Abwesenheiten: '.$year.' bis zum Berechnungsstand.'];$pdfWidths=[1.8,.95,.95,1,1.25,.75,.75,.9,.7,1.1];
        $filename='kienzlezeit-mitarbeiteruebersicht-'.sprintf('%04d-%02d',$year,$month);
        $db->prepare('INSERT INTO export_log(admin_user_id,export_type,export_format,period_label,employee_scope,created_at) VALUES(?,?,?,?,?,?)')->execute([(int)$_SESSION['admin_id'],'TEAM_MONTH_SUMMARY',strtoupper($format),sprintf('%04d-%02d',$year,$month),'ACTIVE',kz_now_utc()]);
    } elseif ($type === 'annual_targets') {
        kz_require_admin();
        $employees = [];
        if ($employeeId > 0) {
            $statement = $db->prepare('SELECT * FROM employees WHERE id=?');
            $statement->execute([$employeeId]);
            $employee = $statement->fetch();
            if ($employee) {
                $employees[] = $employee;
            }
        } else {
            $employees = $db->query('SELECT * FROM employees ORDER BY name')->fetchAll();
        }
        $rows[] = ['Personalnummer','Name','Aktiv','Jahr','Monat','Monatssoll','Regelmaessige Wochenstunden'];
        foreach ($employees as $employee) {
            for ($m = 1; $m <= 12; $m++) {
                $report = kz_month_report((int) $employee['id'], $year, $m);
                $schedule = kz_schedule_for_date((int) $employee['id'], sprintf('%04d-%02d-15', $year, $m));
                $weekly = $schedule ? kz_minutes_label((int) $schedule['weekly_target_minutes']) : '0:00';
                $rows[] = [$employee['personnel_number'],($employee['full_name']?:$employee['name']),(bool) $employee['active'] ? 'ja' : 'nein',$year,sprintf('%02d', $m),kz_minutes_label($report['totals']['target']),$weekly];
            }
        }
        $pdfTitle='Personendaten und Sollzeiten · Jahresabschluss '.$year;$pdfSummary=['Keine Buchungen, Abwesenheitsgründe, RFID-Daten oder Zugangsdaten enthalten.'];$pdfWidths=[1.1,2,0.6,.7,.7,1.2,1.4];
        $filename = 'kienzlezeit-personendaten-sollzeiten-' . $year;
        $scope = $employeeId > 0 ? (string) $employeeId : 'ALL';
        $db->prepare('INSERT INTO export_log(admin_user_id,export_type,export_format,period_label,employee_scope,created_at) VALUES(?,?,?,?,?,?)')->execute([(int) $_SESSION['admin_id'], 'ANNUAL_TARGETS', strtoupper($format), (string) $year, $scope, kz_now_utc()]);
    } else {
        throw new InvalidArgumentException('Unbekannter Export.');
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        echo "\xEF\xBB\xBF";
        $handle = fopen('php://output', 'wb');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', "\\");
        }
        fclose($handle);
        exit;
    }
    $pdfHeaders=array_shift($rows)?:[];if($pdfWidths===[])$pdfWidths=array_fill(0,count($pdfHeaders),1);
    $pdf = kz_generate_table_pdf($pdfTitle,$pdfHeaders,$rows,$pdfWidths,$pdfSummary);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

function kz_process_action(string $action): void
{
    $db = kz_db();
    if (str_starts_with($action, 'export_')) {
        kz_handle_export(substr($action, 7));
    }
    if ($action === 'terminal_secret') {
        kz_require_admin();
        $provided = (string) ($_GET['csrf'] ?? '');
        if ($provided === '' || !hash_equals(kz_csrf(), $provided)) {
            throw new RuntimeException('Die Sitzung ist abgelaufen.');
        }
        $terminalId = (int) ($_GET['terminal_id'] ?? 0);
        $statement = $db->prepare('SELECT * FROM terminals WHERE id=? AND archived_at IS NULL');
        $statement->execute([$terminalId]);
        $terminal = $statement->fetch();
        if (!$terminal) {
            throw new RuntimeException('Terminal wurde nicht gefunden.');
        }
        $secret = kz_decrypt_terminal_secret($terminal);
        kz_audit('VIEW_SECRET', 'terminal', $terminalId);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo kz_json(['ok' => true, 'secret' => $secret]);
        exit;
    }
    if ($action === 'login_status') {
        header('Content-Type: application/json; charset=utf-8');
        $token = (string) ($_GET['token'] ?? '');
        kz_expire_terminal_actions($db);
        $statement = $db->prepare('SELECT lc.*,e.name FROM login_challenges lc LEFT JOIN employees e ON e.id=lc.employee_id WHERE lc.challenge_token=? AND lc.browser_session_id=?');
        $statement->execute([$token, session_id()]);
        $challenge = $statement->fetch();
        if (!$challenge) {
            echo kz_json(['status' => 'INVALID']);
            exit;
        }
        if ($challenge['status'] === 'APPROVED' && $challenge['employee_id']) {
            session_regenerate_id(true);
            $_SESSION['employee_id'] = (int) $challenge['employee_id'];
            $_SESSION['employee_name'] = (string) $challenge['name'];
        }
        echo kz_json(['status' => $challenge['status'], 'name' => $challenge['name'] ?? null]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    kz_require_csrf();

    if ($action === 'admin_login') {
        if (!kz_admin_login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            throw new RuntimeException('Anmeldung fehlgeschlagen oder Konto voruebergehend gesperrt.');
        }
        kz_redirect('?page=admin-dashboard');
    }
    if ($action === 'logout') {
        session_unset();
        session_regenerate_id(true);
        kz_redirect('?');
    }
    if ($action === 'start_card_login') {
        $terminalId = (int) ($_POST['terminal_id'] ?? 0);
        $statement = $db->prepare('SELECT id FROM terminals WHERE id=? AND active=1 AND archived_at IS NULL');
        $statement->execute([$terminalId]);
        if (!$statement->fetchColumn()) {
            throw new RuntimeException('Das Terminal ist nicht verfuegbar.');
        }
        $token = bin2hex(random_bytes(24));
        $expires = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+60 seconds')->format('Y-m-d\TH:i:s\Z');
        $statement = $db->prepare("INSERT INTO login_challenges(challenge_token,browser_session_id,terminal_id,status,created_at,expires_at) VALUES(?,?,?,'PENDING',?,?)");
        $statement->execute([$token, session_id(), $terminalId, kz_now_utc(), $expires]);
        $challengeId = (int) $db->lastInsertId();
        try {
            kz_create_terminal_action($terminalId, 'WEB_LOGIN', null, $challengeId);
        } catch (Throwable $exception) {
            $db->prepare("UPDATE login_challenges SET status='CANCELLED' WHERE id=?")->execute([$challengeId]);
            throw $exception;
        }
        kz_redirect('?page=card-wait&token=' . urlencode($token));
    }
    if ($action === 'employee_absence_submit') {
        $employeeId = kz_employee_id();
        if ($employeeId === null) {
            throw new RuntimeException('Mitarbeiter-Anmeldung erforderlich.');
        }
        $typeId = (int) ($_POST['absence_type_id'] ?? 0);
        $statement = $db->prepare('SELECT * FROM absence_types WHERE id=? AND active=1 AND employee_requestable=1');
        $statement->execute([$typeId]);
        if (!$statement->fetch()) {
            throw new RuntimeException('Diese Abwesenheitsart kann nicht beantragt werden.');
        }
        $start = kz_valid_date((string) ($_POST['start_date'] ?? ''));
        $end = kz_valid_date((string) ($_POST['end_date'] ?? ''));
        kz_date_range($start, $end);
        $startPart = (string) ($_POST['start_part'] ?? 'FULL_DAY');
        $endPart = (string) ($_POST['end_part'] ?? $startPart);
        if (!in_array($startPart, ['FULL_DAY','MORNING','AFTERNOON'], true) || !in_array($endPart, ['FULL_DAY','MORNING','AFTERNOON'], true)) {
            throw new RuntimeException('Tagesabschnitt ist ungueltig.');
        }
        $note = trim((string) ($_POST['employee_note'] ?? ''));
        $statement = $db->prepare("INSERT INTO absence_requests(employee_id,absence_type_id,start_date,end_date,start_part,end_part,status,employee_note,submitted_at,created_by_role) VALUES(?,?,?,?,?,?,'PENDING',?,?,'EMPLOYEE')");
        $statement->execute([$employeeId, $typeId, $start, $end, $startPart, $endPart, $note, kz_now_utc()]);
        $requestId = (int) $db->lastInsertId();
        $db->prepare("INSERT INTO absence_request_history(request_id,previous_status,new_status,actor_label,reason,changed_at) VALUES(?,NULL,'PENDING',?,?,?)")->execute([$requestId, (string) ($_SESSION['employee_name'] ?? 'Mitarbeiter'), $note, kz_now_utc()]);
        kz_flash('success', 'Abwesenheitsantrag wurde eingereicht.');
        kz_redirect('?page=employee-absences');
    }
    if ($action === 'employee_absence_cancel') {
        $employeeId = kz_employee_id();
        $requestId = (int) ($_POST['request_id'] ?? 0);
        if ($employeeId === null) {
            throw new RuntimeException('Mitarbeiter-Anmeldung erforderlich.');
        }
        $statement = $db->prepare("UPDATE absence_requests SET status='CANCELLED' WHERE id=? AND employee_id=? AND status='PENDING'");
        $statement->execute([$requestId, $employeeId]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Nur offene eigene Antraege koennen zurueckgezogen werden.');
        }
        $db->prepare("INSERT INTO absence_request_history(request_id,previous_status,new_status,actor_label,reason,changed_at) VALUES(?,'PENDING','CANCELLED',?,'Vom Mitarbeiter zurueckgezogen',?)")->execute([$requestId, (string) ($_SESSION['employee_name'] ?? 'Mitarbeiter'), kz_now_utc()]);
        kz_flash('success', 'Antrag wurde zurueckgezogen.');
        kz_redirect('?page=employee-absences');
    }
    if ($action === 'employee_correction_submit') {
        $employeeId=kz_employee_id();if($employeeId===null)throw new RuntimeException('Mitarbeiter-Anmeldung erforderlich.');
        $type=(string)($_POST['request_type']??'');$targetId=(int)($_POST['target_time_event_id']??0);$eventType=(string)($_POST['requested_event_type']??'');$localTime=trim((string)($_POST['requested_time']??''));$reason=trim((string)($_POST['reason']??''));
        if(!in_array($type,['ADD','REPLACE','VOID'],true)||$reason==='')throw new RuntimeException('Korrekturart und Begründung sind erforderlich.');
        $target=null;$dates=[];
        if($type!=='ADD'){$s=$db->prepare('SELECT * FROM time_events WHERE id=? AND employee_id=?');$s->execute([$targetId,$employeeId]);$target=$s->fetch();if(!$target)throw new RuntimeException('Die eigene Rohbuchung wurde nicht gefunden.');$dates[]=kz_local_datetime($target['occurred_at'])->format('Y-m-d');}
        $utc=null;if($type!=='VOID'){if(!in_array($eventType,['COME','LEAVE'],true)||$localTime==='')throw new RuntimeException('Buchungstyp und Zeitpunkt sind erforderlich.');$dt=new DateTimeImmutable($localTime,new DateTimeZone('Europe/Berlin'));$dates[]=$dt->format('Y-m-d');$utc=$dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');}
        foreach(array_unique($dates) as $date)if(kz_is_month_closed($employeeId,$date))throw new RuntimeException('Für abgeschlossene Monate sind nur Admin-Korrekturen möglich.');
        $db->prepare("INSERT INTO correction_requests(employee_id,request_type,target_time_event_id,requested_event_type,requested_time,reason,status,submitted_at) VALUES(?,?,?,?,?,?,'PENDING',?)")->execute([$employeeId,$type,$targetId?:null,$eventType?:null,$utc,$reason,kz_now_utc()]);
        kz_flash('success','Korrekturantrag wurde eingereicht.');kz_redirect('?page=employee-times');
    }
    if ($action === 'employee_correction_cancel') {
        $employeeId=kz_employee_id();if($employeeId===null)throw new RuntimeException('Mitarbeiter-Anmeldung erforderlich.');
        $db->prepare("UPDATE correction_requests SET status='CANCELLED' WHERE id=? AND employee_id=? AND status='PENDING'")->execute([(int)($_POST['request_id']??0),$employeeId]);
        kz_flash('success','Korrekturantrag wurde zurückgezogen.');kz_redirect('?page=employee-times');
    }
    if ($action === 'employee_presence_submit') {
        $employeeId=kz_employee_id();if($employeeId===null)throw new RuntimeException('Mitarbeiter-Anmeldung erforderlich.');
        $date=kz_valid_date((string)($_POST['work_date']??''));$part=(string)($_POST['day_part']??'FULL_DAY');$note=trim((string)($_POST['note']??''));
        if(!in_array($part,['FULL_DAY','MORNING','AFTERNOON'],true))throw new RuntimeException('Tagesabschnitt ist ungültig.');if(kz_is_month_closed($employeeId,$date))throw new RuntimeException('Der Monat ist abgeschlossen.');
        $db->prepare("INSERT INTO presence_requests(employee_id,work_date,day_part,note,status,submitted_at,created_by_role) VALUES(?,?,?,?,'PENDING',?,'EMPLOYEE')")->execute([$employeeId,$date,$part,$note,kz_now_utc()]);
        kz_flash('success','Zusätzliche Anwesenheit wurde beantragt.');kz_redirect('?page=employee-absences');
    }
    if ($action === 'employee_presence_cancel') {
        $employeeId=kz_employee_id();if($employeeId===null)throw new RuntimeException('Mitarbeiter-Anmeldung erforderlich.');
        $db->prepare("UPDATE presence_requests SET status='CANCELLED' WHERE id=? AND employee_id=? AND status='PENDING'")->execute([(int)($_POST['request_id']??0),$employeeId]);kz_flash('success','Antrag wurde zurückgezogen.');kz_redirect('?page=employee-absences');
    }

    kz_require_admin();

    if ($action === 'change_password') {
        $new = (string) ($_POST['new_password'] ?? '');
        $repeat = (string) ($_POST['repeat_password'] ?? '');
        if (strlen($new) < 12 || $new !== $repeat) {
            throw new RuntimeException('Das neue Passwort muss mindestens 12 Zeichen haben und zweimal gleich eingegeben werden.');
        }
        $statement = $db->prepare('UPDATE admin_users SET password_hash=?,must_change_password=0,session_version=session_version+1 WHERE id=?');
        $statement->execute([password_hash($new, PASSWORD_DEFAULT), (int) $_SESSION['admin_id']]);
        $_SESSION['admin_session_version'] = (int) $_SESSION['admin_session_version'] + 1;
        $_SESSION['must_change_password'] = false;
        kz_audit('PASSWORD_CHANGE', 'admin_user', (int) $_SESSION['admin_id']);
        kz_flash('success', 'Passwort wurde geaendert.');
        kz_redirect('?page=admin-settings');
    }
    if ($action === 'employee_save') {
        $id = (int) ($_POST['id'] ?? 0);
        $number = trim((string) ($_POST['personnel_number'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;
        if ($number === '' || $name === '' || $fullName === '') {
            throw new RuntimeException('Personalnummer, Anzeigename und vollständiger Name sind erforderlich.');
        }
        if ($id > 0) {
            $statement = $db->prepare('SELECT * FROM employees WHERE id=?');
            $statement->execute([$id]);
            $before = $statement->fetch();
            $db->prepare('UPDATE employees SET personnel_number=?,name=?,full_name=?,active=?,updated_at=? WHERE id=?')->execute([$number, $name, $fullName, $active, kz_now_utc(), $id]);
            kz_audit('UPDATE', 'employee', $id, $before, compact('number','name','fullName','active'));
        } else {
            $db->prepare('INSERT INTO employees(personnel_number,name,full_name,active,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute([$number, $name, $fullName, $active, kz_now_utc(), kz_now_utc()]);
            $id = (int) $db->lastInsertId();
            kz_audit('CREATE', 'employee', $id, null, compact('number','name','fullName','active'));
        }
        kz_flash('success', 'Mitarbeiterdaten wurden gespeichert.');
        kz_redirect('?page=admin-employees&edit=' . $id);
    }
    if ($action === 'schedule_save') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $validFrom = kz_valid_date((string) ($_POST['valid_from'] ?? ''));
        if (kz_is_month_closed($employeeId, $validFrom)) {
            throw new RuntimeException('Der Startmonat ist bereits abgeschlossen.');
        }
        $parts = [];
        $weekly = 0;
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            foreach (['MORNING' => 'morning', 'AFTERNOON' => 'afternoon'] as $part => $field) {
                $minutes = kz_parse_duration((string) ($_POST['d' . $weekday . '_' . $field] ?? '00:00'));
                $parts[] = [$weekday, $part, $minutes];
                $weekly += $minutes;
            }
        }
        $statement = $db->prepare('SELECT 1 FROM work_schedules WHERE employee_id=? AND valid_from=?');
        $statement->execute([$employeeId, $validFrom]);
        if ($statement->fetchColumn()) {
            throw new RuntimeException('Fuer diesen Stichtag existiert bereits ein Sollplan. Bitte einen neuen Stichtag waehlen.');
        }
        $db->exec('BEGIN IMMEDIATE');
        try {
            $next = $db->prepare('SELECT valid_from FROM work_schedules WHERE employee_id=? AND valid_from>? ORDER BY valid_from LIMIT 1');
            $next->execute([$employeeId, $validFrom]);
            $nextDate = $next->fetchColumn();
            $validUntil = $nextDate ? (new DateTimeImmutable((string) $nextDate))->modify('-1 day')->format('Y-m-d') : null;
            $db->prepare('UPDATE work_schedules SET valid_until=? WHERE employee_id=? AND valid_from<? AND (valid_until IS NULL OR valid_until>=?)')->execute([(new DateTimeImmutable($validFrom))->modify('-1 day')->format('Y-m-d'), $employeeId, $validFrom, $validFrom]);
            $db->prepare('INSERT INTO work_schedules(employee_id,valid_from,valid_until,weekly_target_minutes,created_by,created_at) VALUES(?,?,?,?,?,?)')->execute([$employeeId, $validFrom, $validUntil, $weekly, (int) $_SESSION['admin_id'], kz_now_utc()]);
            $scheduleId = (int) $db->lastInsertId();
            $insert = $db->prepare('INSERT INTO work_schedule_day_parts(schedule_id,weekday,day_part,target_minutes) VALUES(?,?,?,?)');
            foreach ($parts as $part) {
                $insert->execute([$scheduleId, $part[0], $part[1], $part[2]]);
            }
            $db->exec('COMMIT');
        } catch (Throwable $exception) {
            $db->exec('ROLLBACK');
            throw $exception;
        }
        kz_audit('CREATE', 'work_schedule', $scheduleId, null, ['employee_id' => $employeeId, 'valid_from' => $validFrom, 'weekly_minutes' => $weekly]);
        kz_flash('success', 'Neuer Sollplan wurde ab ' . $validFrom . ' gespeichert.');
        kz_redirect('?page=admin-schedules&employee_id=' . $employeeId);
    }
    if ($action === 'vacation_account_save') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $year = (int) ($_POST['year'] ?? date('Y'));
        $entitlement = (float) str_replace(',', '.', (string) ($_POST['entitlement_days'] ?? '0'));
        $carried = (float) str_replace(',', '.', (string) ($_POST['carried_days'] ?? '0'));
        $adjustment = (float) str_replace(',', '.', (string) ($_POST['adjustment_days'] ?? '0'));
        $reason = trim((string) ($_POST['adjustment_reason'] ?? ''));
        $statement = $db->prepare('INSERT INTO vacation_accounts(employee_id,year,entitlement_days,carried_days,adjustment_days,adjustment_reason,updated_by,updated_at) VALUES(?,?,?,?,?,?,?,?) ON CONFLICT(employee_id,year) DO UPDATE SET entitlement_days=excluded.entitlement_days,carried_days=excluded.carried_days,adjustment_days=excluded.adjustment_days,adjustment_reason=excluded.adjustment_reason,updated_by=excluded.updated_by,updated_at=excluded.updated_at');
        $statement->execute([$employeeId, $year, $entitlement, $carried, $adjustment, $reason, (int) $_SESSION['admin_id'], kz_now_utc()]);
        kz_audit('UPDATE', 'vacation_account', $employeeId . ':' . $year, null, compact('entitlement','carried','adjustment'), $reason);
        kz_flash('success', 'Urlaubskonto wurde gespeichert.');
        kz_redirect('?page=admin-employees&edit=' . $employeeId);
    }
    if ($action === 'registration_start') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $terminalId = (int) ($_POST['terminal_id'] ?? 0);
        kz_create_terminal_action($terminalId, 'REGISTER_CARD', $employeeId);
        kz_audit('START_REGISTRATION', 'terminal', $terminalId, null, ['employee_id' => $employeeId]);
        kz_flash('success', 'Registrierung ist 60 Sekunden aktiv. Karte jetzt am Terminal vorhalten.');
        kz_redirect('?page=admin-cards');
    }
    if ($action === 'test_card_save') {
        $uid = kz_canonical_uid((string) ($_POST['uid'] ?? ''));
        $label = trim((string) ($_POST['label'] ?? 'Testkarte'));
        $ok = isset($_POST['response_ok']) ? 1 : 0;
        $title = trim((string) ($_POST['title'] ?? 'Testkarte'));
        $line1 = trim((string) ($_POST['line1'] ?? $uid));
        $line2 = trim((string) ($_POST['line2'] ?? 'Erfolgreich erkannt'));
        $note = trim((string) ($_POST['internal_note'] ?? ''));
        $statement = $db->prepare('SELECT * FROM rfid_cards WHERE uid_canonical=?');
        $statement->execute([$uid]);
        $card = $statement->fetch();
        if ($card && $card['card_type'] !== 'TEST') {
            throw new RuntimeException('Diese UID gehoert bereits zu einer Mitarbeiterkarte.');
        }
        if (!$card) {
            $db->prepare("INSERT INTO rfid_cards(uid_canonical,card_type,active,label,created_at,updated_at) VALUES(?,'TEST',1,?,?,?)")->execute([$uid, $label, kz_now_utc(), kz_now_utc()]);
            $cardId = (int) $db->lastInsertId();
        } else {
            $cardId = (int) $card['id'];
            $db->prepare('UPDATE rfid_cards SET label=?,active=1,updated_at=? WHERE id=?')->execute([$label, kz_now_utc(), $cardId]);
        }
        $db->prepare('INSERT INTO test_card_profiles(card_id,response_ok,title,line1,line2,internal_note,updated_by_admin_id,updated_at) VALUES(?,?,?,?,?,?,?,?) ON CONFLICT(card_id) DO UPDATE SET response_ok=excluded.response_ok,title=excluded.title,line1=excluded.line1,line2=excluded.line2,internal_note=excluded.internal_note,updated_by_admin_id=excluded.updated_by_admin_id,updated_at=excluded.updated_at')->execute([$cardId, $ok, $title, $line1, $line2, $note, (int) $_SESSION['admin_id'], kz_now_utc()]);
        kz_audit('UPDATE', 'test_card', $cardId, $card ?: null, compact('uid','label','ok','title','line1','line2'));
        kz_flash('success', 'Testkarte wurde gespeichert.');
        kz_redirect('?page=admin-cards');
    }
    if ($action === 'test_card_delete') {
        $cardId = (int) ($_POST['card_id'] ?? 0);
        $statement = $db->prepare("SELECT c.*,tp.response_ok,tp.title,tp.line1,tp.line2,tp.internal_note FROM rfid_cards c LEFT JOIN test_card_profiles tp ON tp.card_id=c.id WHERE c.id=? AND c.card_type='TEST'");
        $statement->execute([$cardId]);
        $card = $statement->fetch();
        if (!$card) {
            throw new RuntimeException('Testkarte wurde nicht gefunden.');
        }
        $db->exec('BEGIN IMMEDIATE');
        try {
            kz_audit('DELETE', 'test_card', $cardId, $card, null, 'Testkarte im Adminbereich geloescht');
            $delete = $db->prepare("DELETE FROM rfid_cards WHERE id=? AND card_type='TEST'");
            $delete->execute([$cardId]);
            if ($delete->rowCount() !== 1) {
                throw new RuntimeException('Testkarte konnte nicht geloescht werden.');
            }
            $db->exec('COMMIT');
        } catch (Throwable $exception) {
            $db->exec('ROLLBACK');
            throw $exception;
        }
        kz_flash('success', 'Testkarte wurde geloescht.');
        kz_redirect('?page=admin-cards');
    }
    if ($action === 'card_toggle') {
        $cardId = (int) ($_POST['card_id'] ?? 0);
        $db->prepare('UPDATE rfid_cards SET active=CASE active WHEN 1 THEN 0 ELSE 1 END,updated_at=? WHERE id=?')->execute([kz_now_utc(), $cardId]);
        kz_audit('TOGGLE_ACTIVE', 'rfid_card', $cardId);
        kz_flash('success', 'Kartenstatus wurde geaendert.');
        kz_redirect('?page=admin-cards');
    }
    if ($action === 'card_reassign') {
        $cardId = (int) ($_POST['card_id'] ?? 0);
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('Eine Begruendung fuer die Neuzuordnung ist erforderlich.');
        }
        $statement = $db->prepare("SELECT * FROM rfid_cards WHERE id=? AND card_type='EMPLOYEE'");
        $statement->execute([$cardId]);
        $card = $statement->fetch();
        $statement = $db->prepare('SELECT * FROM employees WHERE id=? AND active=1');
        $statement->execute([$employeeId]);
        $employee = $statement->fetch();
        if (!$card || !$employee) {
            throw new RuntimeException('Karte oder Mitarbeiter wurde nicht gefunden.');
        }
        $statement = $db->prepare('SELECT * FROM rfid_card_assignments WHERE card_id=? AND valid_until IS NULL ORDER BY id DESC LIMIT 1');
        $statement->execute([$cardId]);
        $before = $statement->fetch() ?: null;
        if ($before && (int) $before['employee_id'] === $employeeId) {
            throw new RuntimeException('Die Karte ist diesem Mitarbeiter bereits zugeordnet.');
        }
        $db->exec('BEGIN IMMEDIATE');
        try {
            $db->prepare('UPDATE rfid_card_assignments SET valid_until=? WHERE card_id=? AND valid_until IS NULL')->execute([kz_today(), $cardId]);
            $db->prepare('INSERT INTO rfid_card_assignments(card_id,employee_id,valid_from,reason,created_by_admin_id,created_at) VALUES(?,?,?,?,?,?)')->execute([$cardId, $employeeId, kz_today(), $reason, (int) $_SESSION['admin_id'], kz_now_utc()]);
            $db->exec('COMMIT');
        } catch (Throwable $exception) {
            $db->exec('ROLLBACK');
            throw $exception;
        }
        kz_audit('REASSIGN', 'rfid_card', $cardId, $before, ['employee_id' => $employeeId], $reason);
        kz_flash('success', 'Karte wurde neu zugeordnet.');
        kz_redirect('?page=admin-cards');
    }
    if ($action === 'holiday_save') {
        $date = kz_valid_date((string) ($_POST['holiday_date'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $creditRule = isset($_POST['credit_time']) ? 'PLANNED_TIME' : 'NO_CREDIT';
        if ($name === '' || $reason === '') {
            throw new RuntimeException('Bezeichnung und Begruendung sind erforderlich.');
        }
        kz_assert_holiday_month_open($date);
        $statement = $db->prepare('SELECT * FROM holiday_overrides WHERE holiday_date=?');
        $statement->execute([$date]);
        $before = $statement->fetch() ?: null;
        $statement = $db->prepare("INSERT INTO holiday_overrides(holiday_date,name,override_mode,credit_rule,reason,updated_by,updated_at) VALUES(?,?,'ADD_OR_REPLACE',?,?,?,?) ON CONFLICT(holiday_date) DO UPDATE SET name=excluded.name,override_mode='ADD_OR_REPLACE',credit_rule=excluded.credit_rule,reason=excluded.reason,updated_by=excluded.updated_by,updated_at=excluded.updated_at");
        $statement->execute([$date, $name, $creditRule, $reason, (int) $_SESSION['admin_id'], kz_now_utc()]);
        kz_audit('UPDATE', 'holiday', $date, $before, ['name' => $name, 'credit_rule' => $creditRule], $reason);
        kz_flash('success', 'Feiertag wurde gespeichert.');
        kz_redirect('?page=admin-holidays&year=' . substr($date, 0, 4));
    }
    if ($action === 'holiday_disable') {
        $date = kz_valid_date((string) ($_POST['holiday_date'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('Eine Begruendung fuer die Deaktivierung ist erforderlich.');
        }
        kz_assert_holiday_month_open($date);
        $automatic = kz_state_holidays((int) substr($date, 0, 4));
        $name = (string) ($automatic[$date] ?? ($_POST['name'] ?? 'Feiertag'));
        $statement = $db->prepare('SELECT * FROM holiday_overrides WHERE holiday_date=?');
        $statement->execute([$date]);
        $before = $statement->fetch() ?: null;
        $statement = $db->prepare("INSERT INTO holiday_overrides(holiday_date,name,override_mode,credit_rule,reason,updated_by,updated_at) VALUES(?,?,'DISABLE','NO_CREDIT',?,?,?) ON CONFLICT(holiday_date) DO UPDATE SET name=excluded.name,override_mode='DISABLE',credit_rule='NO_CREDIT',reason=excluded.reason,updated_by=excluded.updated_by,updated_at=excluded.updated_at");
        $statement->execute([$date, $name, $reason, (int) $_SESSION['admin_id'], kz_now_utc()]);
        kz_audit('DISABLE', 'holiday', $date, $before, ['name' => $name], $reason);
        kz_flash('success', 'Feiertag wurde dokumentiert deaktiviert.');
        kz_redirect('?page=admin-holidays&year=' . substr($date, 0, 4));
    }
    if ($action === 'holiday_restore') {
        $date = kz_valid_date((string) ($_POST['holiday_date'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('Eine Begruendung fuer die Wiederherstellung ist erforderlich.');
        }
        kz_assert_holiday_month_open($date);
        $statement = $db->prepare('SELECT * FROM holiday_overrides WHERE holiday_date=?');
        $statement->execute([$date]);
        $before = $statement->fetch();
        if (!$before) {
            throw new RuntimeException('Fuer dieses Datum besteht keine manuelle Aenderung.');
        }
        $db->prepare('DELETE FROM holiday_overrides WHERE holiday_date=?')->execute([$date]);
        kz_audit('RESTORE_AUTO', 'holiday', $date, $before, null, $reason);
        kz_flash('success', 'Automatischer Bundesland-Kalender wurde fuer dieses Datum wiederhergestellt.');
        kz_redirect('?page=admin-holidays&year=' . substr($date, 0, 4));
    }
    if ($action === 'terminal_save') {
        $code = trim((string) ($_POST['terminal_code'] ?? ''));
        $label = trim((string) ($_POST['label'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $code) || $label === '') {
            throw new RuntimeException('Terminal-ID oder Bezeichnung ist ungueltig.');
        }
        $key = bin2hex(random_bytes(24));
        $encrypted = kz_encrypt_terminal_secret($key);
        $db->prepare('INSERT INTO terminals(terminal_code,label,key_hash,key_ciphertext,key_nonce,key_tag,active,created_at,updated_at) VALUES(?,?,?,?,?,?,1,?,?)')->execute([$code, $label, hash('sha256', $key), $encrypted['ciphertext'], $encrypted['nonce'], $encrypted['tag'], kz_now_utc(), kz_now_utc()]);
        $id = (int) $db->lastInsertId();
        kz_audit('CREATE', 'terminal', $id, null, ['terminal_code' => $code, 'label' => $label]);
        kz_flash('success', 'Terminal angelegt. Das Secret kann jederzeit ueber "Anzeigen" abgerufen werden.');
        kz_redirect('?page=admin-terminals');
    }
    if ($action === 'terminal_update') {
        $id = (int) ($_POST['terminal_id'] ?? 0);
        $label = trim((string) ($_POST['label'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;
        $secret = trim((string) ($_POST['terminal_secret'] ?? ''));
        $statement = $db->prepare('SELECT * FROM terminals WHERE id=? AND archived_at IS NULL');
        $statement->execute([$id]);
        $before = $statement->fetch();
        if (!$before || $label === '') {
            throw new RuntimeException('Terminal wurde nicht gefunden oder die Bezeichnung fehlt.');
        }
        if ($secret !== '') {
            $encrypted = kz_encrypt_terminal_secret($secret);
            $statement = $db->prepare('UPDATE terminals SET label=?,active=?,key_hash=?,key_ciphertext=?,key_nonce=?,key_tag=?,updated_at=? WHERE id=?');
            $statement->execute([$label, $active, hash('sha256', $secret), $encrypted['ciphertext'], $encrypted['nonce'], $encrypted['tag'], kz_now_utc(), $id]);
        } else {
            $db->prepare('UPDATE terminals SET label=?,active=?,updated_at=? WHERE id=?')->execute([$label, $active, kz_now_utc(), $id]);
        }
        if (!$active) {
            $db->prepare("UPDATE terminal_actions SET status='CANCELLED' WHERE terminal_id=? AND status='PENDING'")->execute([$id]);
        }
        kz_audit('UPDATE', 'terminal', $id, ['label' => $before['label'], 'active' => $before['active']], ['label' => $label, 'active' => $active, 'secret_changed' => $secret !== '']);
        kz_flash('success', $secret !== '' ? 'Terminal und Secret wurden gespeichert. Das neue Secret muss auch im M5Dial eingetragen werden.' : 'Terminal wurde gespeichert.');
        kz_redirect('?page=admin-terminals');
    }
    if ($action === 'terminal_archive') {
        $id = (int) ($_POST['terminal_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('Eine Begruendung fuer das Archivieren ist erforderlich.');
        }
        $statement = $db->prepare('SELECT * FROM terminals WHERE id=? AND archived_at IS NULL');
        $statement->execute([$id]);
        $before = $statement->fetch();
        if (!$before) {
            throw new RuntimeException('Terminal wurde nicht gefunden.');
        }
        $db->exec('BEGIN IMMEDIATE');
        try {
            $db->prepare('UPDATE terminals SET active=0,key_hash=?,key_ciphertext=NULL,key_nonce=NULL,key_tag=NULL,archived_at=?,archived_by=?,archive_reason=?,updated_at=? WHERE id=?')->execute([hash('sha256', random_bytes(32)), kz_now_utc(), (int) $_SESSION['admin_id'], $reason, kz_now_utc(), $id]);
            $db->prepare("UPDATE terminal_actions SET status='CANCELLED' WHERE terminal_id=? AND status='PENDING'")->execute([$id]);
            $db->prepare("UPDATE login_challenges SET status='CANCELLED' WHERE terminal_id=? AND status='PENDING'")->execute([$id]);
            $db->exec('COMMIT');
        } catch (Throwable $exception) {
            $db->exec('ROLLBACK');
            throw $exception;
        }
        kz_audit('ARCHIVE', 'terminal', $id, ['terminal_code' => $before['terminal_code'], 'label' => $before['label']], ['archived' => true], $reason);
        kz_flash('success', 'Terminal wurde revisionssicher archiviert und ist nicht mehr sichtbar.');
        kz_redirect('?page=admin-terminals');
    }
    if ($action === 'absence_decide') {
        kz_admin_absence_decide((int) ($_POST['request_id'] ?? 0), (string) ($_POST['decision'] ?? ''), trim((string) ($_POST['admin_note'] ?? '')));
        kz_flash('success', 'Abwesenheitsantrag wurde bearbeitet.');
        kz_redirect('?page=admin-absences');
    }
    if ($action === 'absence_cancel_admin') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('Eine Begruendung fuer die Stornierung ist erforderlich.');
        }
        $statement = $db->prepare("SELECT * FROM absence_requests WHERE id=? AND status='APPROVED'");
        $statement->execute([$requestId]);
        $request = $statement->fetch();
        if (!$request) {
            throw new RuntimeException('Nur genehmigte Abwesenheiten koennen storniert werden.');
        }
        foreach (kz_date_range((string) $request['start_date'], (string) $request['end_date']) as $date) {
            if (kz_is_month_closed((int) $request['employee_id'], $date)) {
                throw new RuntimeException('Der Zeitraum enthaelt einen abgeschlossenen Monat.');
            }
        }
        $db->prepare("UPDATE absence_requests SET status='CANCELLED',admin_note=?,decided_at=?,decided_by=? WHERE id=?")->execute([$reason, kz_now_utc(), (int) $_SESSION['admin_id'], $requestId]);
        $db->prepare("INSERT INTO absence_request_history(request_id,previous_status,new_status,changed_by,actor_label,reason,changed_at) VALUES(?,'APPROVED','CANCELLED',?,?,?,?)")->execute([$requestId, (int) $_SESSION['admin_id'], (string) $_SESSION['admin_name'], $reason, kz_now_utc()]);
        kz_audit('CANCEL', 'absence_request', $requestId, $request, ['status' => 'CANCELLED'], $reason);
        kz_flash('success', 'Genehmigte Abwesenheit wurde dokumentiert storniert.');
        kz_redirect('?page=admin-absences');
    }
    if ($action === 'admin_absence_create') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $typeId = (int) ($_POST['absence_type_id'] ?? 0);
        $start = kz_valid_date((string) ($_POST['start_date'] ?? ''));
        $end = kz_valid_date((string) ($_POST['end_date'] ?? ''));
        $part = (string) ($_POST['day_part'] ?? 'FULL_DAY');
        $note = trim((string) ($_POST['note'] ?? ''));
        $db->prepare("INSERT INTO absence_requests(employee_id,absence_type_id,start_date,end_date,start_part,end_part,status,employee_note,submitted_at,created_by_role) VALUES(?,?,?,?,?,?,'PENDING',?,?, 'ADMIN')")->execute([$employeeId, $typeId, $start, $end, $part, $part, $note, kz_now_utc()]);
        $requestId = (int) $db->lastInsertId();
        kz_admin_absence_decide($requestId, 'APPROVED', $note);
        kz_flash('success', 'Abwesenheit wurde eingetragen.');
        kz_redirect('?page=admin-absences');
    }
    if (in_array($action, ['correction_add','correction_void','correction_replace'], true)) {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $targetId = (int) ($_POST['target_time_event_id'] ?? 0);
        $eventType = (string) ($_POST['event_type'] ?? 'COME');
        $localTime = trim((string) ($_POST['corrected_time'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('Eine Korrekturbegruendung ist erforderlich.');
        }
        $type = match ($action) {'correction_add' => 'ADD','correction_void' => 'VOID',default => 'REPLACE'};
        $correctedUtc = null;
        if ($type !== 'VOID') {
            $dt = new DateTimeImmutable($localTime, new DateTimeZone('Europe/Berlin'));
            if (kz_is_month_closed($employeeId, $dt->format('Y-m-d'))) {
                throw new RuntimeException('Der betroffene Monat ist abgeschlossen.');
            }
            $correctedUtc = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        }
        if ($type !== 'ADD') {
            $statement = $db->prepare('SELECT employee_id,occurred_at FROM time_events WHERE id=?');
            $statement->execute([$targetId]);
            $target = $statement->fetch();
            if (!$target || (int) $target['employee_id'] !== $employeeId) {
                throw new RuntimeException('Zielbuchung wurde nicht gefunden.');
            }
            if (kz_is_month_closed($employeeId, kz_local_datetime((string) $target['occurred_at'])->format('Y-m-d'))) {
                throw new RuntimeException('Der betroffene Monat ist abgeschlossen.');
            }
        }
        $db->prepare('INSERT INTO corrections(employee_id,target_time_event_id,correction_type,corrected_event_type,corrected_time,reason,performed_by,created_at) VALUES(?,?,?,?,?,?,?,?)')->execute([$employeeId, $type === 'ADD' ? null : $targetId, $type, $type === 'VOID' ? null : $eventType, $correctedUtc, $reason, (int) $_SESSION['admin_id'], kz_now_utc()]);
        $id = (int) $db->lastInsertId();
        kz_audit('CREATE', 'correction', $id, null, compact('employeeId','targetId','type','eventType','correctedUtc'), $reason);
        kz_flash('success', 'Korrektur wurde revisionssicher gespeichert.');
        kz_redirect('?page=admin-corrections&employee_id=' . $employeeId);
    }
    if ($action === 'month_close') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $year = (int) ($_POST['year'] ?? date('Y'));
        $month = (int) ($_POST['month'] ?? date('n'));
        $snapshot = kz_month_report($employeeId, $year, $month);
        foreach ($snapshot['days'] as $day) {
            if ($day['incomplete']) {
                throw new RuntimeException('Der Monat enthaelt unvollstaendige Buchungen und kann noch nicht abgeschlossen werden.');
            }
        }
        $statement = $db->prepare("INSERT INTO month_closures(employee_id,year,month,status,snapshot_json,closed_at,closed_by) VALUES(?,?,?,'CLOSED',?,?,?) ON CONFLICT(employee_id,year,month) DO UPDATE SET status='CLOSED',snapshot_json=excluded.snapshot_json,closed_at=excluded.closed_at,closed_by=excluded.closed_by,reopened_at=NULL,reopened_by=NULL,reopen_reason=NULL");
        $statement->execute([$employeeId, $year, $month, kz_json($snapshot), kz_now_utc(), (int) $_SESSION['admin_id']]);
        kz_audit('CLOSE', 'month', "$employeeId:$year-$month", null, $snapshot['totals']);
        kz_flash('success', 'Monat wurde abgeschlossen.');
        kz_redirect('?page=admin-attendance&employee_id=' . $employeeId . '&year=' . $year . '&month=' . $month);
    }
    if ($action === 'month_reopen') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $year = (int) ($_POST['year'] ?? date('Y'));
        $month = (int) ($_POST['month'] ?? date('n'));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('Eine Begruendung ist erforderlich.');
        }
        $db->prepare("UPDATE month_closures SET status='REOPENED',reopened_at=?,reopened_by=?,reopen_reason=? WHERE employee_id=? AND year=? AND month=? AND status='CLOSED'")->execute([kz_now_utc(), (int) $_SESSION['admin_id'], $reason, $employeeId, $year, $month]);
        kz_audit('REOPEN', 'month', "$employeeId:$year-$month", null, null, $reason);
        kz_flash('success', 'Monat wurde dokumentiert wieder geoeffnet.');
        kz_redirect('?page=admin-attendance&employee_id=' . $employeeId . '&year=' . $year . '&month=' . $month);
    }
    if ($action === 'holiday_calendar_save') {
        $year=max(2000,min(2100,(int)($_POST['year']??date('Y'))));$state=(string)($_POST['state_code']??'NW');
        if(!isset(kz_federal_states()[$state]))throw new RuntimeException('Das Bundesland ist ungültig.');
        $s=$db->prepare("SELECT COUNT(*) FROM month_closures WHERE year=? AND status='CLOSED'");$s->execute([$year]);
        if((int)$s->fetchColumn()>0)throw new RuntimeException('Für dieses Jahr bestehen abgeschlossene Monate. Diese müssen vor einem Kalenderwechsel dokumentiert wieder geöffnet werden.');
        $before=kz_holiday_state_for_year($year);
        $db->prepare('INSERT INTO holiday_calendars(year,state_code,updated_by,updated_at) VALUES(?,?,?,?) ON CONFLICT(year) DO UPDATE SET state_code=excluded.state_code,updated_by=excluded.updated_by,updated_at=excluded.updated_at')->execute([$year,$state,(int)$_SESSION['admin_id'],kz_now_utc()]);
        kz_audit('UPDATE','holiday_calendar',$year,['state_code'=>$before],['state_code'=>$state]);kz_flash('success','Bundesland für '.$year.' wurde gespeichert.');kz_redirect('?page=admin-holidays&year='.$year);
    }
    if ($action === 'opening_hours_save') {
        $db->exec('BEGIN IMMEDIATE');
        try{
            $save=$db->prepare('INSERT INTO opening_hours(weekday,day_part,start_time,end_time,updated_by,updated_at) VALUES(?,?,?,?,?,?) ON CONFLICT(weekday,day_part) DO UPDATE SET start_time=excluded.start_time,end_time=excluded.end_time,updated_by=excluded.updated_by,updated_at=excluded.updated_at');
            for($day=1;$day<=7;$day++)foreach(['MORNING'=>'morning','AFTERNOON'=>'afternoon'] as $part=>$field){
                $start=trim((string)($_POST['d'.$day.'_'.$field.'_start']??''));$end=trim((string)($_POST['d'.$day.'_'.$field.'_end']??''));
                if(($start==='')!==($end===''))throw new RuntimeException('Öffnungsbeginn und -ende müssen jeweils gemeinsam ausgefüllt werden.');
                if($start!==''&&(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$start)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$end)||$end<=$start))throw new RuntimeException('Eine Öffnungszeit ist ungültig oder endet nicht nach ihrem Beginn.');
                $save->execute([$day,$part,$start?:null,$end?:null,(int)$_SESSION['admin_id'],kz_now_utc()]);
            }
            $db->exec('COMMIT');
        }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
        kz_audit('UPDATE','opening_hours','global');kz_flash('success','Öffnungszeiten wurden gespeichert.');kz_redirect('?page=admin-schedules');
    }
    if ($action === 'balance_set') {
        $employeeId=(int)($_POST['employee_id']??0);$date=kz_valid_date((string)($_POST['effective_date']??''));$new=kz_parse_signed_duration((string)($_POST['new_balance']??''));$reason=trim((string)($_POST['reason']??''));
        if($reason==='')throw new RuntimeException('Eine Begründung für die Saldoänderung ist erforderlich.');
        if(kz_is_month_closed($employeeId,$date))throw new RuntimeException('Der betroffene Monat ist abgeschlossen.');
        $old=kz_total_balance($employeeId,$date);$difference=$new-$old;$type=(int)$db->query('SELECT COUNT(*) FROM balance_adjustments WHERE employee_id='.(int)$employeeId)->fetchColumn()===0?'OPENING':'SET_BALANCE';
        $db->prepare('INSERT INTO balance_adjustments(employee_id,effective_date,adjustment_minutes,old_balance_minutes,new_balance_minutes,adjustment_type,reason,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$employeeId,$date,$difference,$old,$new,$type,$reason,(int)$_SESSION['admin_id'],kz_now_utc()]);
        $id=(int)$db->lastInsertId();kz_audit('CREATE','balance_adjustment',$id,['balance'=>$old],['balance'=>$new,'difference'=>$difference],$reason);kz_flash('success','Gesamtsaldo wurde nachvollziehbar gesetzt.');kz_redirect('?page=admin-employees&edit='.$employeeId);
    }
    if ($action === 'correction_request_decide') {
        $id=(int)($_POST['request_id']??0);$decision=(string)($_POST['decision']??'');$note=trim((string)($_POST['admin_note']??''));
        if(!in_array($decision,['APPROVED','REJECTED'],true))throw new RuntimeException('Entscheidung ist ungültig.');
        $s=$db->prepare("SELECT * FROM correction_requests WHERE id=? AND status='PENDING'");$s->execute([$id]);$request=$s->fetch();if(!$request)throw new RuntimeException('Der Korrekturantrag ist nicht mehr offen.');
        $dates=[];if($request['requested_time'])$dates[]=kz_local_datetime($request['requested_time'])->format('Y-m-d');
        if($request['target_time_event_id']){$t=$db->prepare('SELECT occurred_at FROM time_events WHERE id=?');$t->execute([(int)$request['target_time_event_id']]);$targetTime=$t->fetchColumn();if($targetTime)$dates[]=kz_local_datetime((string)$targetTime)->format('Y-m-d');}
        if($decision==='APPROVED'){
            foreach(array_unique($dates) as $date)if(kz_is_month_closed((int)$request['employee_id'],$date))throw new RuntimeException('Der betroffene Monat ist abgeschlossen.');
            $db->prepare('INSERT INTO corrections(employee_id,target_time_event_id,correction_type,corrected_event_type,corrected_time,reason,performed_by,created_at) VALUES(?,?,?,?,?,?,?,?)')->execute([(int)$request['employee_id'],$request['request_type']==='ADD'?null:$request['target_time_event_id'],$request['request_type'],$request['request_type']==='VOID'?null:$request['requested_event_type'],$request['request_type']==='VOID'?null:$request['requested_time'],'Genehmigter Mitarbeiterantrag: '.$request['reason'],(int)$_SESSION['admin_id'],kz_now_utc()]);$correctionId=(int)$db->lastInsertId();
        }else{$correctionId=null;if($note==='')throw new RuntimeException('Bei Ablehnung ist ein Hinweis erforderlich.');}
        $db->prepare('UPDATE correction_requests SET status=?,decided_at=?,decided_by=?,admin_note=?,correction_id=? WHERE id=?')->execute([$decision,kz_now_utc(),(int)$_SESSION['admin_id'],$note,$correctionId,$id]);
        kz_audit($decision,'correction_request',$id,$request,['correction_id'=>$correctionId],$note);kz_flash('success','Korrekturantrag wurde bearbeitet.');kz_redirect('?page=admin-correction-requests');
    }
    if ($action === 'presence_decide') {
        $id=(int)($_POST['request_id']??0);$decision=(string)($_POST['decision']??'');$note=trim((string)($_POST['admin_note']??''));if(!in_array($decision,['APPROVED','REJECTED'],true))throw new RuntimeException('Entscheidung ist ungültig.');
        $s=$db->prepare("SELECT * FROM presence_requests WHERE id=? AND status='PENDING'");$s->execute([$id]);$request=$s->fetch();if(!$request)throw new RuntimeException('Der Antrag ist nicht mehr offen.');if(kz_is_month_closed((int)$request['employee_id'],(string)$request['work_date']))throw new RuntimeException('Der betroffene Monat ist abgeschlossen.');if($decision==='REJECTED'&&$note==='')throw new RuntimeException('Bei Ablehnung ist ein Hinweis erforderlich.');
        $db->prepare('UPDATE presence_requests SET status=?,decided_at=?,decided_by=?,admin_note=? WHERE id=?')->execute([$decision,kz_now_utc(),(int)$_SESSION['admin_id'],$note,$id]);kz_audit($decision,'presence_request',$id,$request,null,$note);kz_flash('success','Anwesenheitsantrag wurde bearbeitet.');kz_redirect('?page=admin-absences');
    }
    if ($action === 'admin_presence_create') {
        $employeeId=(int)($_POST['employee_id']??0);$date=kz_valid_date((string)($_POST['work_date']??''));$part=(string)($_POST['day_part']??'FULL_DAY');$note=trim((string)($_POST['note']??''));if(!in_array($part,['FULL_DAY','MORNING','AFTERNOON'],true))throw new RuntimeException('Tagesabschnitt ist ungültig.');if(kz_is_month_closed($employeeId,$date))throw new RuntimeException('Der betroffene Monat ist abgeschlossen.');
        $db->prepare("INSERT INTO presence_requests(employee_id,work_date,day_part,note,status,submitted_at,decided_at,decided_by,admin_note,created_by_role) VALUES(?,?,?,?,'APPROVED',?,?,?,?, 'ADMIN')")->execute([$employeeId,$date,$part,$note,kz_now_utc(),kz_now_utc(),(int)$_SESSION['admin_id'],$note]);$id=(int)$db->lastInsertId();kz_audit('CREATE','presence_request',$id,null,['employee_id'=>$employeeId,'date'=>$date,'part'=>$part],$note);kz_flash('success','Zusätzliche Anwesenheit wurde genehmigt eingetragen.');kz_redirect('?page=admin-absences');
    }
    if ($action === 'booking_issues_resolve') {
        $selected=array_values(array_unique(array_filter((array)($_POST['issues']??[]),'is_string')));
        $resolution=(string)($_POST['resolution']??'');
        $note=trim((string)($_POST['note']??''));
        $workedTimes=is_array($_POST['worked_time']??null)?$_POST['worked_time']:[];
        if($selected===[]||!in_array($resolution,['REVIEWED','SET_TARGET'],true))throw new RuntimeException('Bitte mindestens einen Eintrag und eine Aktion auswählen.');
        $available=[];foreach(kz_booking_issues() as $issue)$available[$issue['fingerprint']]=$issue;
        $processed=0;$applied=[];$db->exec('BEGIN IMMEDIATE');
        try{
            foreach($selected as $fingerprint){
                if(!isset($available[$fingerprint]))continue;
                $issue=$available[$fingerprint];
                if(kz_is_month_closed((int)$issue['employee']['id'],$issue['date']))throw new RuntimeException('Ein ausgewählter Tag liegt in einem abgeschlossenen Monat.');
                $issueData=['types'=>$issue['types'],'planned_minutes'=>(int)$issue['target']];
                if($resolution==='SET_TARGET'){
                    $entered=trim((string)($workedTimes[$fingerprint]??''));
                    if($entered==='')throw new RuntimeException('Bitte für '.($issue['employee']['full_name']?:$issue['employee']['name']).' am '.(new DateTimeImmutable($issue['date']))->format('d.m.Y').' eine Arbeitszeit eintragen.');
                    $workedMinutes=kz_parse_worked_duration($entered);
                    $reason='Buchungsprüfung: Arbeitszeit auf '.kz_minutes_label($workedMinutes).' gesetzt'.($note!==''?' · '.$note:'');
                    $db->prepare('INSERT INTO day_time_overrides(employee_id,work_date,worked_minutes,reason,created_by,created_at) VALUES(?,?,?,?,?,?) ON CONFLICT(employee_id,work_date) DO UPDATE SET worked_minutes=excluded.worked_minutes,reason=excluded.reason,created_by=excluded.created_by,created_at=excluded.created_at')->execute([(int)$issue['employee']['id'],$issue['date'],$workedMinutes,$reason,(int)$_SESSION['admin_id'],kz_now_utc()]);
                    $issueData['worked_minutes']=$workedMinutes;
                    $applied[]=['employee_id'=>(int)$issue['employee']['id'],'date'=>$issue['date'],'worked_minutes'=>$workedMinutes,'planned_minutes'=>(int)$issue['target']];
                }
                $db->prepare('INSERT INTO booking_issue_resolutions(issue_fingerprint,employee_id,work_date,action_type,issue_data,note,resolved_by,resolved_at) VALUES(?,?,?,?,?,?,?,?)')->execute([$fingerprint,(int)$issue['employee']['id'],$issue['date'],$resolution,kz_json($issueData),$note,(int)$_SESSION['admin_id'],kz_now_utc()]);
                $processed++;
            }
            if($processed===0)throw new RuntimeException('Die ausgewählten Prüfhinweise sind nicht mehr offen.');
            $db->exec('COMMIT');
        }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
        kz_audit('RESOLVE','booking_issues',null,null,['count'=>$processed,'resolution'=>$resolution,'applied'=>$applied],$note);kz_flash('success','Ausgewählte Prüfhinweise wurden abgeschlossen.');kz_redirect('?page=admin-booking-review');
    }
    if ($action === 'admin_user_create') {
        $username=trim((string)($_POST['username']??''));$display=trim((string)($_POST['display_name']??''));if(!preg_match('/^[A-Za-z0-9_.-]{3,64}$/',$username)||$display==='')throw new RuntimeException('Benutzername oder Anzeigename ist ungültig.');$password=bin2hex(random_bytes(9));
        $db->prepare('INSERT INTO admin_users(username,display_name,password_hash,active,must_change_password,created_at) VALUES(?,?,?,1,1,?)')->execute([$username,$display,password_hash($password,PASSWORD_DEFAULT),kz_now_utc()]);$id=(int)$db->lastInsertId();kz_audit('CREATE','admin_user',$id,null,['username'=>$username,'display_name'=>$display]);kz_flash('success','Admin angelegt. Einmaliges Übergangspasswort: '.$password);kz_redirect('?page=admin-users');
    }
    if ($action === 'admin_user_update_name') {
        $id=(int)($_POST['admin_id']??0);$display=trim((string)($_POST['display_name']??''));if($display==='')throw new RuntimeException('Der vollständige Name ist erforderlich.');
        $s=$db->prepare('SELECT id,username,display_name,active FROM admin_users WHERE id=?');$s->execute([$id]);$before=$s->fetch();if(!$before)throw new RuntimeException('Das Admin-Konto wurde nicht gefunden.');
        $db->prepare('UPDATE admin_users SET display_name=? WHERE id=?')->execute([$display,$id]);if($id===(int)$_SESSION['admin_id'])$_SESSION['admin_name']=$display;
        kz_audit('UPDATE','admin_user',$id,$before,['display_name'=>$display]);kz_flash('success','Vollständiger Adminname wurde gespeichert.');kz_redirect('?page=admin-users');
    }
    if ($action === 'admin_user_deactivate') {
        $id=(int)($_POST['admin_id']??0);$reason=trim((string)($_POST['reason']??''));if($id===(int)$_SESSION['admin_id'])throw new RuntimeException('Das eigene Konto kann nicht deaktiviert werden.');if($reason==='')throw new RuntimeException('Eine Begründung ist erforderlich.');
        $active=(int)$db->query('SELECT COUNT(*) FROM admin_users WHERE active=1')->fetchColumn();if($active<=1)throw new RuntimeException('Der letzte aktive Admin kann nicht deaktiviert werden.');$s=$db->prepare('SELECT id,username,display_name,active FROM admin_users WHERE id=?');$s->execute([$id]);$before=$s->fetch();if(!$before||!(int)$before['active'])throw new RuntimeException('Das Konto ist nicht aktiv.');$db->prepare('UPDATE admin_users SET active=0,session_version=session_version+1 WHERE id=?')->execute([$id]);kz_audit('DEACTIVATE','admin_user',$id,$before,['active'=>0],$reason);kz_flash('success','Admin wurde revisionssicher deaktiviert.');kz_redirect('?page=admin-users');
    }
    if ($action === 'settings_save') {
        $boundary = (string) ($_POST['day_part_boundary'] ?? '13:00');
        $guard = max(0, min(300, (int) ($_POST['duplicate_guard_seconds'] ?? 30)));
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $boundary)) {
            throw new RuntimeException('Trennzeit ist ungueltig.');
        }
        kz_set_setting('day_part_boundary', $boundary);
        kz_set_setting('duplicate_guard_seconds', (string) $guard);
        $public=isset($_POST['public_presence_enabled'])?'1':'0';kz_set_setting('public_presence_enabled',$public);
        $weekends=isset($_POST['show_weekends'])?'1':'0';kz_set_setting('show_weekends',$weekends);
        $expenses=isset($_POST['expenses_enabled'])?'1':'0';kz_set_setting('expenses_enabled',$expenses);
        kz_audit('UPDATE', 'settings', 'general', null, compact('boundary','guard','public','weekends','expenses'));
        kz_flash('success', 'Einstellungen wurden gespeichert.');
        kz_redirect('?page=admin-settings');
    }
}

$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');
try {
    if ($action !== '') {
        kz_process_action($action);
    }
} catch (Throwable $exception) {
    if (in_array($action, ['login_status', 'terminal_secret'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo kz_json(['ok' => false, 'status' => 'ERROR', 'message' => $exception->getMessage()]);
        exit;
    }
    kz_flash('error', $exception->getMessage());
    $fallback = str_starts_with($action, 'admin_') || kz_is_admin() ? '?page=admin-dashboard' : '?';
    kz_redirect($fallback);
}

function kz_render_header(string $title, string $context = 'public'): void
{
    $flashes = kz_take_flashes();
    $admin = $context === 'admin';
    $employee = $context === 'employee';
    ?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <title><?= kz_h($title) ?> · <?= kz_h(KZ_APP_TITLE) ?></title>
  <style>
    :root{--green:#30d20f;--green-dark:#178f08;--ink:#151b17;--muted:#647067;--line:#dce5dd;--bg:#f4f7f4;--card:#fff;--danger:#c62828;--warn:#a65c00;--blue:#1e5faa;--shadow:0 12px 34px rgba(22,50,25,.09)}
    *{box-sizing:border-box}html{height:100%}body{min-height:100vh;margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;display:flex;flex-direction:column}
    a{color:var(--green-dark);text-decoration:none}a:hover{text-decoration:underline}
    .top{background:#fff;border-bottom:1px solid var(--line);padding:10px clamp(16px,4vw,48px);display:flex;align-items:center;gap:22px;position:sticky;top:0;z-index:10}
    .logo{width:min(280px,48vw);height:64px;object-fit:contain;object-position:left center}.top-meta{margin-left:auto;text-align:right}.top-meta strong{display:block}.top-meta span{color:var(--muted);font-size:13px}.top-logout{flex:0 0 auto}.top-logout button{padding:8px 12px}
    .nav{background:#172018;color:#fff;padding:0 clamp(16px,4vw,48px);display:flex;gap:3px;overflow:auto;white-space:nowrap}.nav a{color:#e9f4e9;padding:11px 13px;text-decoration:none;border-bottom:3px solid transparent}.nav a:hover{background:#243027;border-bottom-color:var(--green)}
    main{width:min(1220px,calc(100% - 28px));margin:24px auto 40px;flex:1}.narrow{width:min(720px,calc(100% - 28px))}
    h1{font-size:clamp(25px,4vw,38px);line-height:1.15;margin:0 0 20px}h2{font-size:21px;margin:0 0 14px}h3{font-size:16px;margin:0 0 10px}.lead{font-size:17px;color:var(--muted);margin-top:-10px}
    .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:18px}.col-12{grid-column:span 12}.col-8{grid-column:span 8}.col-7{grid-column:span 7}.col-6{grid-column:span 6}.col-5{grid-column:span 5}.col-4{grid-column:span 4}.col-3{grid-column:span 3}
    .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:var(--shadow)}.card.soft{box-shadow:none}.card.accent{border-top:4px solid var(--green)}
    .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}.kpi{background:#fff;border:1px solid var(--line);border-radius:13px;padding:15px;color:var(--ink)}.kpi.alert{background:#fff0d8;border-color:#e6a84c}.kpi strong{font-size:26px;display:block}.kpi span{color:var(--muted)}
    label{display:block;font-weight:700;margin:0 0 5px}.field{margin-bottom:14px}input,select,textarea{width:100%;border:1px solid #bdc9bf;border-radius:9px;padding:10px 11px;background:#fff;color:var(--ink);font:inherit}input:focus,select:focus,textarea:focus{outline:3px solid rgba(48,210,15,.18);border-color:var(--green-dark)}input[type=checkbox]{width:auto}.inline{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.inline>*{flex:1}.inline .auto{flex:0 0 auto}
    button,.btn{border:0;border-radius:9px;background:var(--green-dark);color:#fff;padding:10px 15px;font:700 14px/1.2 inherit;cursor:pointer;display:inline-block;text-decoration:none}button:hover,.btn:hover{filter:brightness(.92);text-decoration:none}.btn.secondary,button.secondary{background:#e7eee8;color:var(--ink)}.btn.danger,button.danger{background:var(--danger)}.btn.small,button.small{padding:7px 10px;font-size:12px}
    table{border-collapse:collapse;width:100%}th,td{text-align:left;border-bottom:1px solid var(--line);padding:10px 8px;vertical-align:top}th{color:#526057;font-size:12px;text-transform:uppercase;letter-spacing:.04em;background:#f9fbf9}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:12px} .table-wrap table th:first-child,.table-wrap table td:first-child{padding-left:13px}
    .badge{display:inline-block;border-radius:999px;padding:3px 8px;font-size:12px;font-weight:800;background:#e7eee8;color:#344239}.badge.good{background:#dcf8d7;color:#176a0d}.badge.bad{background:#fde4e4;color:#9b1c1c}.badge.warn{background:#fff0d8;color:#83500b}.badge.blue{background:#dfeeff;color:#174d8c}.badge.muted{background:#edf0ee;color:#606b63}
    .flash{padding:13px 15px;border-radius:10px;margin:0 0 16px;border:1px solid}.flash.success{background:#e6f9e1;border-color:#9cdd8d}.flash.error{background:#ffebeb;border-color:#efaaaa;color:#8c1717}.flash.info{background:#eaf3ff;border-color:#a9c9ed}
    .status-cell{min-width:104px}.status-cell small{display:block;color:var(--muted)}.muted{color:var(--muted)}.danger-text{color:var(--danger);font-weight:700}.right{text-align:right}.nowrap{white-space:nowrap}.empty{padding:26px;text-align:center;color:var(--muted)}
    .week-table th:not(:first-child),.week-table td:not(:first-child){text-align:center}.week-table td{min-width:92px}.part{display:block;padding:4px 5px;border-radius:6px;margin:2px 0;font-size:11px}.part.present{background:#dff8da}.part.absent{background:#ffe4e4}.part.pending{background:#fff0d8}.part.free{background:#edf0ee;color:#6c756e}.part.planned{background:#e4f0ff}.part.holiday{background:#efe4ff;color:#5b2188}
    .presence-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}.presence-card{background:#f7fbf7;border:1px solid var(--line);border-left:5px solid var(--green-dark);border-radius:12px;padding:14px}.presence-card strong{display:block;font-size:18px}.presence-card small{display:block;color:var(--muted);margin-top:4px}.presence-card .badge{margin-top:7px}
    .schedule-grid input{min-width:75px}.schedule-grid td{text-align:center}.schedule-grid td:first-child{text-align:left;font-weight:700}
    details{border:1px solid var(--line);border-radius:10px;padding:10px 12px;background:#fff}details+details{margin-top:8px}summary{cursor:pointer;font-weight:700}
    footer{border-top:1px solid var(--line);background:rgba(255,255,255,.8);padding:12px 16px;text-align:center;color:var(--muted);font-size:12px;margin-top:auto}
    @media(max-width:850px){.col-8,.col-7,.col-6,.col-5,.col-4,.col-3{grid-column:span 12}.top-meta{display:none}.logo{height:52px}.top{position:static}.nav{font-size:13px}.card{padding:16px}main{margin-top:17px}}
    @media print{.top,.nav,.no-print,footer{display:none!important}body{background:#fff}main{width:100%;margin:0}.card{box-shadow:none;border:0;padding:0}}
  </style>
</head>
<body>
  <header class="top">
    <a href="?"><img class="logo" src="kienzlezeit.png" alt="kienzlezeit"></a>
    <div class="top-meta">
      <?php if ($admin): ?><strong>Administration</strong>
      <?php elseif ($employee): ?><strong>Mitarbeiterbereich</strong><span><?= kz_h($_SESSION['employee_name'] ?? '') ?></span>
      <?php else: ?><strong>Lokale Zeiterfassung</strong><span>Praxisintern</span><?php endif; ?>
    </div>
    <?php if ($admin || $employee): ?><form method="post" class="top-logout no-print"><?php kz_hidden_action('logout'); ?><button type="submit" class="secondary">Abmelden</button></form><?php endif; ?>
  </header>
  <?php if ($admin): ?>
  <nav class="nav" aria-label="Admin-Navigation">
    <a href="?page=admin-dashboard">Übersicht</a><a href="?page=admin-employees">Mitarbeiter</a><a href="?page=admin-attendance">Zeiten</a><a href="?page=admin-absences">Abwesenheiten</a><a href="?page=admin-exports">Auswertung</a><a href="?page=admin-schedules">Planung</a><a href="?page=admin-cards">Karten &amp; Terminals</a><?php if(kz_expenses_enabled()):?><a href="<?=kz_h(kz_expenses_url(true))?>">Auslagen</a><?php endif;?><a href="?page=admin-settings">Administration</a>
  </nav>
  <?php elseif ($employee): ?>
  <nav class="nav" aria-label="Mitarbeiter-Navigation"><a href="?page=employee-dashboard">Übersicht</a><a href="?page=employee-times">Meine Zeiten</a><a href="?page=employee-absences">Abwesenheiten</a><?php if(kz_expenses_enabled()):?><a href="<?=kz_h(kz_expenses_url())?>">Auslagen</a><?php endif;?></nav>
  <?php endif; ?>
  <main class="<?= $context === 'public' ? 'narrow' : '' ?>">
    <?php foreach ($flashes as $flash): ?><div class="flash <?= kz_h($flash['type']) ?>"><?= kz_h($flash['message']) ?></div><?php endforeach; ?>
<?php
}

function kz_render_footer(): void
{
    ?>
  </main>
  <footer><?= kz_h(KZ_APP_TITLE) ?> · v<?= kz_h(KZ_APP_VERSION) ?> · <?= kz_h(KZ_APP_AUTHOR) ?></footer>
</body>
</html>
<?php
}

function kz_hidden_action(string $action): void
{
    ?><input type="hidden" name="csrf" value="<?= kz_h(kz_csrf()) ?>"><input type="hidden" name="action" value="<?= kz_h($action) ?>"><?php
}

function kz_part_label(string $part): string
{
    return match ($part) {'MORNING' => 'Vormittag','AFTERNOON' => 'Nachmittag',default => 'Ganzer Tag'};
}

function kz_status_label(string $status): string
{
    return match ($status) {'PENDING' => 'Beantragt','APPROVED' => 'Genehmigt','REJECTED' => 'Abgelehnt','CANCELLED' => 'Zurückgezogen',default => $status};
}

function kz_team_part_status(int $employeeId, string $date, string $part, bool $public = false): ?array
{
    $target = kz_target_parts($employeeId, $date)[$part];
    $statement = kz_db()->prepare("SELECT 1 FROM presence_requests WHERE employee_id=? AND work_date=? AND status='APPROVED' AND (day_part='FULL_DAY' OR day_part=?) LIMIT 1");
    $statement->execute([$employeeId, $date, $part]);
    if ($statement->fetchColumn()) return ['label'=>'Vertretung','class'=>'present'];
    if ($date === kz_today()) {
        $latest = kz_latest_effective_event($employeeId);
        $currentPart = date('H:i') < (string) kz_setting('day_part_boundary', '13:00') ? 'MORNING' : 'AFTERNOON';
        if ($part === $currentPart && $latest && $latest['event_type'] === 'COME' && kz_local_datetime((string) $latest['occurred_at'])->format('Y-m-d') === $date) {
            return ['label' => 'Anwesend', 'class' => 'present'];
        }
    }
    if ($target <= 0) return null;
    $holiday = kz_holidays((int) substr($date, 0, 4))[$date] ?? null;
    if ($holiday !== null) {
        return ['label' => 'Feiertag', 'class' => 'holiday', 'title' => (string) $holiday['name']];
    }
    $statement = kz_db()->prepare("SELECT 1 FROM absence_days ad JOIN absence_requests ar ON ar.id=ad.request_id WHERE ad.employee_id=? AND ad.work_date=? AND ar.status='APPROVED' AND (ad.day_part='FULL_DAY' OR ad.day_part=?) LIMIT 1");
    $statement->execute([$employeeId, $date, $part]);
    if ($statement->fetchColumn()) {
        return ['label' => 'Abwesend', 'class' => 'absent'];
    }
    if (!$public) {
        $statement = kz_db()->prepare("SELECT * FROM absence_requests WHERE employee_id=? AND start_date<=? AND end_date>=? AND status='PENDING'");
        $statement->execute([$employeeId, $date, $date]);
        foreach ($statement as $request) {
            $requestedPart = kz_request_part_for_date($request, $date);
            if ($requestedPart === 'FULL_DAY' || $requestedPart === $part) {
                return ['label' => 'Beantragt', 'class' => 'pending'];
            }
        }
    }
    return ['label' => 'Soll-Anwesend', 'class' => 'planned'];
}

function kz_render_team_week(string $startDate, bool $adminDetails = false, bool $public = false): void
{
    $db = kz_db();
    $start = new DateTimeImmutable($startDate, new DateTimeZone('Europe/Berlin'));
    $start = $start->modify('monday this week');
    $currentWeek=(new DateTimeImmutable(kz_today(),new DateTimeZone('Europe/Berlin')))->modify('monday this week');
    if($public && $start<$currentWeek)$start=$currentWeek;
    $dayCount = kz_show_weekends() ? 7 : 5;
    $employees = $db->query('SELECT * FROM employees WHERE active=1 ORDER BY name')->fetchAll();
    ?>
    <div class="inline no-print" style="margin-bottom:10px">
      <?php if(!$public||$start>$currentWeek):?><a class="btn secondary auto" href="?page=<?= $public?'public-planning':($adminDetails ? 'admin-dashboard' : 'employee-dashboard') ?>&week=<?= kz_h($start->modify('-7 days')->format('Y-m-d')) ?>">← Vorwoche</a><?php endif;?>
      <strong><?= kz_h($start->format('d.m.')) ?>–<?= kz_h($start->modify('+'.($dayCount-1).' days')->format('d.m.Y')) ?></strong>
      <a class="btn secondary auto" href="?page=<?= $public?'public-planning':($adminDetails ? 'admin-dashboard' : 'employee-dashboard') ?>&week=<?= kz_h($start->modify('+7 days')->format('Y-m-d')) ?>">Folgewoche →</a>
    </div>
    <div class="table-wrap"><table class="week-table"><thead><tr><th>Mitarbeiter</th>
      <?php for ($i=0;$i<$dayCount;$i++): $date=$start->modify("+$i days"); ?><th><?= ['Mo','Di','Mi','Do','Fr','Sa','So'][$i] ?><br><?= $date->format('d.m.') ?></th><?php endfor; ?>
    </tr></thead><tbody>
    <?php foreach ($employees as $employee): ?><tr><td><strong><?= kz_h($employee['name']) ?></strong></td>
      <?php for ($i=0;$i<$dayCount;$i++): $date=$start->modify("+$i days")->format('Y-m-d'); ?><td>
        <?php foreach (['MORNING','AFTERNOON'] as $part): $status=kz_team_part_status((int)$employee['id'],$date,$part,$public); if($status===null)continue; ?>
          <span class="part <?= kz_h($status['class']) ?>" title="<?= kz_h($status['title'] ?? '') ?>"><?= kz_h($status['label']) ?></span>
        <?php endforeach; ?>
      </td><?php endfor; ?>
    </tr><?php endforeach; ?>
    <?php if ($employees===[]): ?><tr><td colspan="<?=1+$dayCount?>" class="empty">Noch keine aktiven Mitarbeiter.</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php
}

function kz_render_public_page(): void
{
    $db = kz_db();
    $terminals = $db->query('SELECT * FROM terminals WHERE active=1 AND archived_at IS NULL ORDER BY label')->fetchAll();
    $week=(string)($_GET['week']??kz_today());$presence=kz_setting('public_presence_enabled','0')==='1'?kz_current_presence():[];
    kz_render_header('Anmeldung','publicwide');
    ?>
    <h1>Willkommen bei kienzlezeit</h1>
    <p class="lead">Zeiten einsehen, Abwesenheiten planen oder die Anwendung administrieren.</p>
    <div class="grid">
      <section class="card accent col-6">
        <h2>Mit Karte anmelden</h2>
        <p class="muted">Anmeldung ohne Passwort am M5Dial.</p>
        <?php if ($terminals): ?>
        <form method="post">
          <?php kz_hidden_action('start_card_login'); ?>
          <?php if (count($terminals) === 1): ?><input type="hidden" name="terminal_id" value="<?= (int)$terminals[0]['id'] ?>"><p><strong>Terminal:</strong> <?= kz_h($terminals[0]['label']) ?></p>
          <?php else: ?><div class="field"><label for="terminal_id">Terminal</label><select id="terminal_id" name="terminal_id" required><?php foreach($terminals as $terminal): ?><option value="<?= (int)$terminal['id'] ?>"><?= kz_h($terminal['label']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
          <button type="submit">Kartenanmeldung starten</button>
        </form>
        <?php else: ?><div class="flash error">Kein aktives Terminal eingerichtet.</div><?php endif; ?>
      </section>
      <section class="card col-6">
        <h2>Administration</h2>
        <form method="post" autocomplete="on">
          <?php kz_hidden_action('admin_login'); ?>
          <div class="field"><label for="username">Benutzername</label><input id="username" name="username" autocomplete="username" required></div>
          <div class="field"><label for="password">Passwort</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
          <button type="submit">Als Admin anmelden</button>
        </form>
      </section>
    </div>
    <?php if(kz_setting('public_presence_enabled','0')==='1'):?><section class="card" style="margin-top:18px"><div class="inline"><h2>Aktuell anwesend</h2><a class="btn small secondary auto" href="?">Aktualisieren</a></div><?php if($presence):?><div class="presence-grid"><?php foreach($presence as $row):?><div class="presence-card"><strong><?=kz_h($row['employee']['name'])?></strong></div><?php endforeach;?></div><?php else:?><p class="muted">Derzeit ist niemand als anwesend gebucht.</p><?php endif;?></section><?php endif;?>
    <section class="card" style="margin-top:18px"><h2>Gemeinsame Anwesenheitsplanung</h2><p class="muted">Aktuelle und zukünftige Wochen; angezeigt werden ausschließlich Namen und bestätigte Planungsstände.</p><?php kz_render_team_week($week,false,true);?></section>
    <?php
    kz_render_footer();
}

function kz_render_card_wait(string $token): void
{
    kz_render_header('Kartenanmeldung');
    ?>
    <section class="card accent" style="text-align:center">
      <h1>Karte jetzt vorhalten</h1>
      <p class="lead">Das ausgewählte Terminal wartet 60 Sekunden auf deine Karte.</p>
      <div id="login-state" class="flash info">Warte auf Kartenscan …</div>
      <a class="btn secondary" href="?">Abbrechen</a>
    </section>
    <script>
    (()=>{const box=document.getElementById('login-state');let done=false;async function poll(){if(done)return;try{const r=await fetch('?action=login_status&token=<?= rawurlencode($token) ?>',{cache:'no-store'});const d=await r.json();if(d.status==='APPROVED'){done=true;box.className='flash success';box.textContent='Anmeldung erfolgreich. Willkommen '+(d.name||'')+'!';setTimeout(()=>location.href='?page=employee-dashboard',700);return}if(['EXPIRED','CANCELLED','INVALID'].includes(d.status)){done=true;box.className='flash error';box.textContent='Die Anmeldung ist abgelaufen. Bitte erneut starten.';return}}catch(e){}setTimeout(poll,1000)}poll()})();
    </script>
    <?php kz_render_footer();
}

function kz_render_employee_dashboard(): void
{
    $employeeId = kz_employee_id();
    if ($employeeId === null) { kz_redirect('?'); }
    $db = kz_db();
    $statement = $db->prepare('SELECT * FROM employees WHERE id=? AND active=1');
    $statement->execute([$employeeId]);
    $employee = $statement->fetch();
    if (!$employee) { session_unset(); kz_redirect('?'); }
    $report = kz_month_report($employeeId, (int)date('Y'), (int)date('n'));
    $vacation = kz_vacation_balance($employeeId, (int)date('Y'));
    $workedToDate = 0;
    $monthBalanceToDate = 0;
    foreach ($report['days'] as $day) {
        if ($day['date'] <= kz_today()) $workedToDate += (int) $day['worked'];
        if ($day['date'] < kz_today()) $monthBalanceToDate += (int) $day['balance'];
    }
    $totalBalance = kz_total_balance($employeeId, kz_today());
    $week = (string) ($_GET['week'] ?? kz_today());
    kz_render_header('Meine Übersicht', 'employee');
    ?>
    <div><h1>Hallo, <?= kz_h($employee['name']) ?></h1><p class="lead">Deine Zeiten und die gemeinsame Planung auf einen Blick.</p></div>
    <div class="kpis">
      <div class="kpi"><strong><?= kz_h(kz_minutes_label($workedToDate).' / '.kz_minutes_label($report['totals']['target'])) ?></strong><span>Arbeitszeit diesen Monat</span></div>
      <div class="kpi"><strong><?= kz_h(kz_minutes_label($monthBalanceToDate, true)) ?></strong><span>Monatssaldo (tagesaktuell)</span></div>
      <div class="kpi"><strong><?= kz_h(number_format($vacation['remaining'],1,',','.')) ?></strong><span>Resturlaub <?= date('Y') ?></span></div>
      <div class="kpi"><strong><?= kz_h(kz_minutes_label($totalBalance, true)) ?></strong><span>Gesamt-Stunden-Saldo</span></div>
    </div>
    <section class="card" style="margin-top:18px"><h2>Gemeinsame Anwesenheitsplanung</h2><p class="muted">Abwesenheitsgründe anderer Mitarbeiter bleiben vertraulich.</p><?php kz_render_team_week($week); ?></section>
    <?php kz_render_footer();
}

function kz_render_employee_absences(): void
{
    $employeeId = kz_employee_id();
    if ($employeeId === null) { kz_redirect('?'); }
    $db = kz_db();
    $types = $db->query("SELECT * FROM absence_types WHERE active=1 AND employee_requestable=1 ORDER BY CASE type_code WHEN 'VACATION' THEN 0 ELSE 1 END,name")->fetchAll();
    $statement = $db->prepare('SELECT ar.*,at.name AS type_name FROM absence_requests ar JOIN absence_types at ON at.id=ar.absence_type_id WHERE ar.employee_id=? ORDER BY ar.submitted_at DESC');
    $statement->execute([$employeeId]);
    $requests = $statement->fetchAll();
    $statement=$db->prepare('SELECT * FROM presence_requests WHERE employee_id=? ORDER BY submitted_at DESC');$statement->execute([$employeeId]);$presence=$statement->fetchAll();
    kz_render_header('Meine Abwesenheiten', 'employee');
    ?>
    <h1>Abwesenheiten</h1>
    <div class="grid">
      <section class="card col-5"><h2>Neuer Antrag</h2><form method="post"><?php kz_hidden_action('employee_absence_submit'); ?>
        <div class="field"><label>Art</label><select name="absence_type_id" required><?php foreach($types as $type): ?><option value="<?= (int)$type['id'] ?>"><?= kz_h($type['name']) ?></option><?php endforeach; ?></select></div>
        <div class="inline"><div class="field"><label>Von</label><input type="date" name="start_date" value="<?= kz_h(kz_today()) ?>" required></div><div class="field"><label>Bis</label><input type="date" name="end_date" value="<?= kz_h(kz_today()) ?>" required></div></div>
        <div class="inline"><div class="field"><label>Erster Tag</label><select name="start_part"><option value="FULL_DAY">Ganzer Tag</option><option value="MORNING">Vormittag</option><option value="AFTERNOON">Nachmittag</option></select></div><div class="field"><label>Letzter Tag</label><select name="end_part"><option value="FULL_DAY">Ganzer Tag</option><option value="MORNING">Vormittag</option><option value="AFTERNOON">Nachmittag</option></select></div></div>
        <div class="field"><label>Hinweis</label><textarea name="employee_note" rows="3" placeholder="Optional"></textarea></div><button>Antrag einreichen</button>
      </form></section>
      <section class="card col-7"><h2>Meine Anträge</h2><div class="table-wrap"><table><thead><tr><th>Art</th><th>Zeitraum</th><th>Status</th><th></th></tr></thead><tbody>
      <?php foreach($requests as $request): ?><tr><td><?= kz_h($request['type_name']) ?></td><td><?= kz_h((new DateTimeImmutable($request['start_date']))->format('d.m.Y')) ?> – <?= kz_h((new DateTimeImmutable($request['end_date']))->format('d.m.Y')) ?><small class="muted"><?= kz_h(kz_part_label($request['start_part'])) ?> / <?= kz_h(kz_part_label($request['end_part'])) ?></small></td><td><span class="badge <?= $request['status']==='APPROVED'?'good':($request['status']==='PENDING'?'warn':'muted') ?>"><?= kz_h(kz_status_label($request['status'])) ?></span></td><td><?php if($request['status']==='PENDING'): ?><form method="post"><?php kz_hidden_action('employee_absence_cancel'); ?><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><button class="small secondary">Zurückziehen</button></form><?php endif; ?></td></tr><?php endforeach; ?>
      <?php if(!$requests): ?><tr><td colspan="4" class="empty">Noch keine Anträge.</td></tr><?php endif; ?></tbody></table></div></section>
      <section class="card col-5"><h2>Zusätzliche Anwesenheit</h2><p class="muted">Für Vertretungen außerhalb des Sollplans. Die Buchungszeit wird als Pluszeit gewertet.</p><form method="post"><?php kz_hidden_action('employee_presence_submit');?><div class="field"><label>Datum</label><input type="date" name="work_date" value="<?=kz_h(kz_today())?>" required></div><div class="field"><label>Abschnitt</label><select name="day_part"><option value="FULL_DAY">Ganzer Tag</option><option value="MORNING">Vormittag</option><option value="AFTERNOON">Nachmittag</option></select></div><div class="field"><label>Hinweis</label><textarea name="note"></textarea></div><button>Zusätzliche Anwesenheit beantragen</button></form></section>
      <section class="card col-7"><h2>Anwesenheitsanträge</h2><div class="table-wrap"><table><thead><tr><th>Datum</th><th>Abschnitt</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($presence as $r):?><tr><td><?=kz_h((new DateTimeImmutable($r['work_date']))->format('d.m.Y'))?></td><td><?=kz_h(kz_part_label($r['day_part']))?></td><td><span class="badge <?=$r['status']==='APPROVED'?'good':($r['status']==='PENDING'?'warn':'muted')?>"><?=kz_h(kz_status_label($r['status']))?></span></td><td><?php if($r['status']==='PENDING'):?><form method="post"><?php kz_hidden_action('employee_presence_cancel');?><input type="hidden" name="request_id" value="<?=(int)$r['id']?>"><button class="small secondary">Zurückziehen</button></form><?php endif;?></td></tr><?php endforeach;?><?php if(!$presence):?><tr><td colspan="4" class="empty">Noch keine zusätzlichen Anwesenheiten.</td></tr><?php endif;?></tbody></table></div></section>
    </div>
    <?php kz_render_footer();
}

function kz_render_employee_times(): void
{
    $employeeId = kz_employee_id(); if($employeeId===null){kz_redirect('?');}
    $year=max(2000,min(2100,(int)($_GET['year']??date('Y'))));$month=max(1,min(12,(int)($_GET['month']??date('n'))));
    $db=kz_db();$report=kz_month_report($employeeId,$year,$month);$closed=kz_is_month_closed($employeeId,sprintf('%04d-%02d-01',$year,$month));
    $s=$db->prepare('SELECT * FROM time_events WHERE employee_id=? ORDER BY occurred_at DESC LIMIT 100');$s->execute([$employeeId]);$events=$s->fetchAll();
    $s=$db->prepare('SELECT * FROM correction_requests WHERE employee_id=? ORDER BY submitted_at DESC LIMIT 100');$s->execute([$employeeId]);$requests=$s->fetchAll();
    kz_render_header('Meine Zeiten','employee');
    ?>
    <div class="inline"><h1>Meine Zeiten</h1><form method="get" class="inline auto no-print"><input type="hidden" name="page" value="employee-times"><select name="month"><?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$m===$month?'selected':''?>><?=sprintf('%02d',$m)?></option><?php endfor;?></select><input type="number" name="year" value="<?=$year?>" min="2000" max="2100"><button>Zeigen</button></form></div>
    <div class="kpis"><div class="kpi"><strong><?=kz_h(kz_minutes_label($report['totals']['target']))?></strong><span>Soll</span></div><div class="kpi"><strong><?=kz_h(kz_minutes_label($report['totals']['worked']))?></strong><span>Arbeit</span></div><div class="kpi"><strong><?=kz_h(kz_minutes_label($report['totals']['credited']))?></strong><span>Gutschriften</span></div><div class="kpi"><strong><?=kz_h(kz_minutes_label($report['totals']['balance'],true))?></strong><span>Saldo</span></div></div>
    <div class="inline no-print" style="margin:16px 0"><a class="btn secondary auto" href="?action=export_own_month&format=csv&year=<?=$year?>&month=<?=$month?>">CSV</a><a class="btn secondary auto" href="?action=export_own_month&format=pdf&year=<?=$year?>&month=<?=$month?>">Stundenzettel (PDF)</a></div>
    <?php if($closed):?><div class="flash info">Dieser Monat ist abgeschlossen. Korrekturen können nur durch die Administration nach dokumentierter Wiederöffnung erfolgen.</div><?php endif;?>
    <div class="table-wrap"><table><thead><tr><th>Datum</th><th>Intervalle</th><th>Soll</th><th>Arbeit</th><th>Gutschrift</th><th>Saldo</th><th>Hinweis</th></tr></thead><tbody><?php foreach($report['days'] as $day):?><tr><td class="nowrap"><?=kz_h($day['weekday'].' '.(new DateTimeImmutable($day['date']))->format('d.m.'))?></td><td><?=kz_h(implode(' / ',$day['intervals']))?><?=$day['incomplete']?' <span class="badge bad">unvollständig</span>':''?></td><td><?=kz_h(kz_minutes_label($day['target']))?></td><td><?=kz_h(kz_minutes_label($day['worked']))?></td><td><?=kz_h(kz_minutes_label($day['credited']))?></td><td><?=kz_h(kz_minutes_label($day['balance'],true))?></td><td><?=kz_h($day['note'])?></td></tr><?php endforeach;?></tbody></table></div>
    <div class="grid no-print" style="margin-top:18px"><section class="card col-5"><h2>Zeitkorrektur beantragen</h2><form method="post"><?php kz_hidden_action('employee_correction_submit');?><div class="field"><label>Art</label><select name="request_type" id="correction-kind"><option value="ADD">Fehlende Buchung ergänzen</option><option value="REPLACE">Zeitpunkt/Typ ersetzen</option><option value="VOID">Falsche Buchung unwirksam stellen</option></select></div><div class="field" id="correction-target"><label>Rohbuchung</label><select name="target_time_event_id"><option value="0">Bitte auswählen</option><?php foreach($events as $event):?><option value="<?=(int)$event['id']?>">#<?=(int)$event['id']?> · <?=kz_h(kz_local_datetime($event['occurred_at'])->format('d.m.Y H:i'))?> · <?=$event['event_type']==='COME'?'Kommen':'Gehen'?></option><?php endforeach;?></select></div><div id="correction-new"><div class="field"><label>Buchungstyp</label><select name="requested_event_type"><option value="COME">Kommen</option><option value="LEAVE">Gehen</option></select></div><div class="field"><label>Zeitpunkt</label><input type="datetime-local" name="requested_time" value="<?=date('Y-m-d\TH:i')?>"></div></div><div class="field"><label>Begründung</label><textarea name="reason" required></textarea></div><button>Antrag einreichen</button></form></section>
    <section class="card col-7"><h2>Meine Korrekturanträge</h2><div class="table-wrap"><table><thead><tr><th>Zeit</th><th>Art</th><th>Wunsch</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($requests as $r):?><tr><td><?=kz_h(kz_local_datetime($r['submitted_at'])->format('d.m.Y H:i'))?></td><td><?=kz_h($r['request_type'])?></td><td><?=kz_h($r['requested_time']?kz_local_datetime($r['requested_time'])->format('d.m.Y H:i').' '.$r['requested_event_type']:'Rohbuchung #'.$r['target_time_event_id'])?></td><td><span class="badge <?=$r['status']==='APPROVED'?'good':($r['status']==='PENDING'?'warn':'muted')?>"><?=kz_h(kz_status_label($r['status']))?></span><br><small><?=kz_h($r['admin_note'])?></small></td><td><?php if($r['status']==='PENDING'):?><form method="post"><?php kz_hidden_action('employee_correction_cancel');?><input type="hidden" name="request_id" value="<?=(int)$r['id']?>"><button class="small secondary">Zurückziehen</button></form><?php endif;?></td></tr><?php endforeach;?><?php if(!$requests):?><tr><td colspan="5" class="empty">Noch keine Korrekturanträge.</td></tr><?php endif;?></tbody></table></div></section></div>
    <script>const kind=document.getElementById('correction-kind'),target=document.getElementById('correction-target'),newFields=document.getElementById('correction-new');function correctionFields(){target.style.display=kind.value==='ADD'?'none':'block';newFields.style.display=kind.value==='VOID'?'none':'block'}kind.addEventListener('change',correctionFields);correctionFields();</script>
    <?php kz_render_footer();
}

function kz_render_admin_dashboard(): void
{
    kz_require_admin();
    $db=kz_db();$week=(string)($_GET['week']??kz_today());
    $employees=(int)$db->query("SELECT COUNT(*) FROM employees WHERE active=1")->fetchColumn();
    $presence=kz_current_presence();$pending=(int)$db->query("SELECT COUNT(*) FROM absence_requests WHERE status='PENDING'")->fetchColumn()+(int)$db->query("SELECT COUNT(*) FROM presence_requests WHERE status='PENDING'")->fetchColumn();
    $correctionPending=(int)$db->query("SELECT COUNT(*) FROM correction_requests WHERE status='PENDING'")->fetchColumn();$issueCount=count(kz_booking_issues());
    kz_render_header('Administration','admin');
    ?>
    <div><h1>Übersicht</h1><p class="lead">Aktueller Betrieb und kommende Teamplanung.</p></div>
    <div class="kpis"><div class="kpi"><strong><?=$employees?></strong><span>aktive Mitarbeiter</span></div><div class="kpi"><strong><?=count($presence)?></strong><span>aktuell anwesend</span></div><a class="kpi <?=$pending?'alert':''?>" href="?page=admin-absences"><strong><?=$pending?></strong><span>Abwesenheits-/Anwesenheitsanträge</span></a><a class="kpi <?=$correctionPending?'alert':''?>" href="?page=admin-correction-requests"><strong><?=$correctionPending?></strong><span>Korrekturanträge</span></a><a class="kpi <?=$issueCount?'alert':''?>" href="?page=admin-booking-review"><strong><?=$issueCount?></strong><span>Zu prüfende Buchungen</span></a></div>
    <section class="card" style="margin-top:18px"><div class="inline"><h2>Aktuell anwesend</h2><a class="btn small secondary auto" href="?page=admin-dashboard">Aktualisieren</a></div><?php if($presence):?><div class="presence-grid"><?php foreach($presence as $row):$minutes=(int)((time()-$row['since']->getTimestamp())/60);?><div class="presence-card"><strong><?=kz_h($row['employee']['full_name']?:$row['employee']['name'])?></strong><small><?=kz_h($row['employee']['name'])?> · seit <?=kz_h($row['since']->format('H:i'))?> Uhr</small><small>Letzte Buchung: Kommen · <?=kz_h($row['since']->format('d.m.Y H:i:s'))?></small><small>Terminal: <?=kz_h($row['terminal'])?></small><?php if($minutes>600):?><span class="badge bad">über 10 Stunden</span><?php endif;?></div><?php endforeach;?></div><?php else:?><p class="muted">Derzeit ist niemand als anwesend gebucht.</p><?php endif;?></section>
    <section class="card" style="margin-top:18px"><h2>Anwesenheit und Planung</h2><?php kz_render_team_week($week,true);?></section>
    <?php kz_render_footer();
}

function kz_render_admin_employees(): void
{
    kz_require_admin();$db=kz_db();$editId=(int)($_GET['edit']??0);$showInactive=isset($_GET['show_inactive']);
    $employees=$db->query('SELECT * FROM employees '.($showInactive?'':'WHERE active=1').' ORDER BY active DESC,name')->fetchAll();$edit=null;
    if($editId){$s=$db->prepare('SELECT * FROM employees WHERE id=?');$s->execute([$editId]);$edit=$s->fetch()?:null;}
    $year=(int)date('Y');$vacation=$edit?kz_vacation_balance((int)$edit['id'],$year):null;
    $account=null;$adjustments=[];if($edit){$s=$db->prepare('SELECT * FROM vacation_accounts WHERE employee_id=? AND year=?');$s->execute([(int)$edit['id'],$year]);$account=$s->fetch()?:[];$s=$db->prepare('SELECT ba.*,au.display_name admin_name FROM balance_adjustments ba JOIN admin_users au ON au.id=ba.created_by WHERE ba.employee_id=? ORDER BY ba.created_at DESC');$s->execute([(int)$edit['id']]);$adjustments=$s->fetchAll();}
    kz_render_header('Mitarbeiter','admin');
    ?>
    <div class="inline"><h1>Mitarbeiter</h1><a class="btn secondary auto" href="?page=admin-employees<?=$showInactive?'':'&show_inactive=1'?>"><?=$showInactive?'Inaktive ausblenden':'Inaktive einblenden'?></a></div><div class="grid"><section class="card col-12"><h2>Übersicht</h2><div class="table-wrap"><table><thead><tr><th>Login-/Anzeigename</th><th>Vollständiger Name</th><th>Personalnr.</th><th>Gesamtsaldo</th><th>Urlaub gesamt / genommen / Rest</th><th>Krankheit <?=$year?></th><th>Status</th></tr></thead><tbody><?php foreach($employees as $employee):$v=kz_vacation_summary((int)$employee['id'],$year);$balance=kz_total_balance((int)$employee['id'],kz_today());?><tr><td><a href="?page=admin-employees&edit=<?=(int)$employee['id']?><?=$showInactive?'&show_inactive=1':''?>"><strong><?=kz_h($employee['name'])?></strong></a></td><td><?=kz_h($employee['full_name']?:$employee['name'])?></td><td><?=kz_h($employee['personnel_number'])?></td><td><?=kz_h(kz_minutes_label($balance,true))?></td><td><?=kz_h(number_format($v['total'],1,',','.').' / '.number_format($v['taken'],1,',','.').' / '.number_format($v['remaining'],1,',','.'))?></td><td><?=kz_h(number_format(kz_sickness_days((int)$employee['id'],$year),1,',','.'))?></td><td><span class="badge <?=$employee['active']?'good':'muted'?>"><?=$employee['active']?'aktiv':'inaktiv'?></span></td></tr><?php endforeach;?><?php if(!$employees):?><tr><td colspan="7" class="empty">Noch keine passenden Mitarbeiter.</td></tr><?php endif;?></tbody></table></div></section>
    <section class="card col-6"><h2><?=$edit?'Mitarbeiter bearbeiten':'Mitarbeiter anlegen'?></h2><form method="post"><?php kz_hidden_action('employee_save');?><input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>"><div class="field"><label>Personalnummer</label><input name="personnel_number" value="<?=kz_h($edit['personnel_number']??'')?>" required></div><div class="field"><label>Login-/Anzeigename</label><input name="name" value="<?=kz_h($edit['name']??'')?>" required><small class="muted">Wird in der öffentlichen Planung angezeigt.</small></div><div class="field"><label>Vollständiger Name</label><input name="full_name" value="<?=kz_h($edit['full_name']??'')?>" required><small class="muted">Wird in Ausdrucken und Exporten verwendet.</small></div><label class="inline" style="justify-content:flex-start"><input class="auto" type="checkbox" name="active" <?=!$edit||$edit['active']?'checked':''?>> Aktiv</label><div style="margin-top:15px"><button>Speichern</button></div></form>
    <?php if($edit):?><hr style="border:0;border-top:1px solid var(--line);margin:22px 0"><h3>Urlaubskonto <?=$year?></h3><p class="muted">Verfügbar: <?=kz_h(number_format($vacation['remaining'],1,',','.'))?> Tage</p><form method="post"><?php kz_hidden_action('vacation_account_save');?><input type="hidden" name="employee_id" value="<?=(int)$edit['id']?>"><input type="hidden" name="year" value="<?=$year?>"><div class="inline"><div class="field"><label>Anspruch</label><input name="entitlement_days" type="number" step="0.5" value="<?=kz_h($account['entitlement_days']??0)?>"></div><div class="field"><label>Übertrag</label><input name="carried_days" type="number" step="0.5" value="<?=kz_h($account['carried_days']??0)?>"></div><div class="field"><label>Anpassung</label><input name="adjustment_days" type="number" step="0.5" value="<?=kz_h($account['adjustment_days']??0)?>"></div></div><div class="field"><label>Begründung</label><input name="adjustment_reason" value="<?=kz_h($account['adjustment_reason']??'')?>"></div><button class="secondary">Urlaubskonto speichern</button></form><?php endif;?>
    </section><?php if($edit):?><section class="card col-6"><h2>Gesamtsaldo setzen</h2><p>Aktuell bis einschließlich gestern: <strong><?=kz_h(kz_minutes_label(kz_total_balance((int)$edit['id'],kz_today()),true))?></strong></p><form method="post"><?php kz_hidden_action('balance_set');?><input type="hidden" name="employee_id" value="<?=(int)$edit['id']?>"><div class="field"><label>Stichtag</label><input type="date" name="effective_date" value="<?=kz_h(kz_today())?>" required></div><div class="field"><label>Neuer Gesamtsaldo</label><input name="new_balance" value="<?=kz_h(kz_minutes_label(kz_total_balance((int)$edit['id'],kz_today()),true))?>" pattern="[+-]?[0-9]+:[0-5][0-9]" required></div><div class="field"><label>Begründung</label><textarea name="reason" required></textarea></div><button>Saldo nachvollziehbar setzen</button></form><h3 style="margin-top:20px">Saldohistorie</h3><div class="table-wrap"><table><thead><tr><th>Stichtag</th><th>Alt → Neu</th><th>Grund</th></tr></thead><tbody><?php foreach($adjustments as $a):?><tr><td><?=kz_h((new DateTimeImmutable($a['effective_date']))->format('d.m.Y'))?></td><td><?=kz_h(kz_minutes_label((int)$a['old_balance_minutes'],true).' → '.kz_minutes_label((int)$a['new_balance_minutes'],true))?></td><td><?=kz_h($a['reason'])?><br><small><?=kz_h($a['admin_name'])?></small></td></tr><?php endforeach;?><?php if(!$adjustments):?><tr><td colspan="3" class="empty">Noch keine Saldoänderung.</td></tr><?php endif;?></tbody></table></div></section><?php endif;?></div>
    <?php kz_render_footer();
}

function kz_render_admin_schedules(): void
{
    kz_require_admin();$db=kz_db();$employees=$db->query('SELECT * FROM employees WHERE active=1 ORDER BY name')->fetchAll();$employeeId=(int)($_GET['employee_id']??($employees[0]['id']??0));
    $employee=null;foreach($employees as $e){if((int)$e['id']===$employeeId)$employee=$e;}
    $history=[];$currentParts=[];if($employeeId){$s=$db->prepare('SELECT * FROM work_schedules WHERE employee_id=? ORDER BY valid_from DESC');$s->execute([$employeeId]);$history=$s->fetchAll();$schedule=kz_schedule_for_date($employeeId,kz_today());if($schedule){$s=$db->prepare('SELECT * FROM work_schedule_day_parts WHERE schedule_id=?');$s->execute([(int)$schedule['id']]);foreach($s as $r)$currentParts[(int)$r['weekday']][$r['day_part']]=(int)$r['target_minutes'];}}
    $days=[1=>'Montag',2=>'Dienstag',3=>'Mittwoch',4=>'Donnerstag',5=>'Freitag',6=>'Samstag',7=>'Sonntag'];$opening=kz_opening_hours();
    kz_render_header('Planung','admin');
    ?>
    <div class="inline"><h1>Planung</h1><a class="btn secondary auto" href="?page=admin-holidays">Feiertage verwalten</a><form method="get" class="inline auto"><input type="hidden" name="page" value="admin-schedules"><select name="employee_id" onchange="this.form.submit()"><?php foreach($employees as $e):?><option value="<?=(int)$e['id']?>" <?=(int)$e['id']===$employeeId?'selected':''?>><?=kz_h($e['name'])?></option><?php endforeach;?></select></form></div>
    <?php if(!$employee):?><div class="card empty">Bitte zuerst einen Mitarbeiter anlegen.</div><?php else:?><div class="grid"><section class="card col-8"><h2>Neuer Sollplan für <?=kz_h($employee['name'])?></h2><p class="muted">Dauer je Tagesabschnitt in Stunden und Minuten. 00:00 bedeutet frei. Der bisherige Plan bleibt historisch erhalten.</p><form method="post" id="schedule-form"><?php kz_hidden_action('schedule_save');?><input type="hidden" name="employee_id" value="<?=$employeeId?>"><div class="inline"><div class="field"><label>Gültig ab</label><input type="date" name="valid_from" value="<?=kz_h(kz_today())?>" required></div><button type="button" id="copy-opening" class="secondary auto">Öffnungszeiten übernehmen</button></div><div class="table-wrap"><table class="schedule-grid"><thead><tr><th>Wochentag</th><th>Vormittag (Std.:Min.)</th><th>Nachmittag (Std.:Min.)</th></tr></thead><tbody><?php foreach($days as $n=>$label):$m=$opening[$n]['MORNING']??null;$a=$opening[$n]['AFTERNOON']??null;$mMin=$m&&$m['start_time']&&$m['end_time']?(strtotime($m['end_time'])-strtotime($m['start_time']))/60:0;$aMin=$a&&$a['start_time']&&$a['end_time']?(strtotime($a['end_time'])-strtotime($a['start_time']))/60:0;?><tr><td><?=$label?></td><td><input type="time" min="00:00" max="12:00" step="300" name="d<?=$n?>_morning" data-opening="<?=kz_h(kz_duration_input((int)$mMin))?>" value="<?=kz_h(kz_duration_input((int)($currentParts[$n]['MORNING']??0)))?>"></td><td><input type="time" min="00:00" max="12:00" step="300" name="d<?=$n?>_afternoon" data-opening="<?=kz_h(kz_duration_input((int)$aMin))?>" value="<?=kz_h(kz_duration_input((int)($currentParts[$n]['AFTERNOON']??0)))?>"></td></tr><?php endforeach;?></tbody></table></div><button style="margin-top:14px">Neuen Sollplan speichern</button></form></section>
    <section class="card col-4"><h2>Historie</h2><?php foreach($history as $schedule):?><div style="padding:10px 0;border-bottom:1px solid var(--line)"><strong><?=kz_h((new DateTimeImmutable($schedule['valid_from']))->format('d.m.Y'))?></strong> bis <?=kz_h($schedule['valid_until']?(new DateTimeImmutable($schedule['valid_until']))->format('d.m.Y'):'offen')?><br><span class="muted"><?=kz_h(kz_minutes_label((int)$schedule['weekly_target_minutes']))?> Stunden/Woche</span></div><?php endforeach;?><?php if(!$history):?><p class="muted">Noch kein Sollplan.</p><?php endif;?></section></div><?php endif;?>
    <section class="card" style="margin-top:18px"><h2>Öffnungszeiten</h2><p class="muted">Globale Vorlage. Die Übernahme in einen persönlichen Sollplan erfolgt erst über den obigen Knopf und anschließendes Speichern.</p><form method="post"><?php kz_hidden_action('opening_hours_save');?><div class="table-wrap"><table><thead><tr><th>Tag</th><th>Vormittag von</th><th>bis</th><th>Nachmittag von</th><th>bis</th></tr></thead><tbody><?php foreach($days as $n=>$label):?><tr><td><strong><?=$label?></strong></td><?php foreach(['MORNING','AFTERNOON'] as $part):$field=strtolower($part);$field=$part==='MORNING'?'morning':'afternoon';$row=$opening[$n][$part]??[];?><td><input type="time" name="d<?=$n?>_<?=$field?>_start" value="<?=kz_h($row['start_time']??'')?>"></td><td><input type="time" name="d<?=$n?>_<?=$field?>_end" value="<?=kz_h($row['end_time']??'')?>"></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><button style="margin-top:14px">Öffnungszeiten speichern</button></form></section><script>document.getElementById('copy-opening')?.addEventListener('click',()=>{document.querySelectorAll('#schedule-form [data-opening]').forEach(input=>input.value=input.dataset.opening);});</script>
    <?php kz_render_footer();
}

function kz_render_admin_cards(): void
{
    kz_require_admin();$db=kz_db();$cards=$db->query("SELECT c.*,tp.response_ok,tp.title,tp.line1,tp.line2,e.name AS employee_name FROM rfid_cards c LEFT JOIN test_card_profiles tp ON tp.card_id=c.id LEFT JOIN rfid_card_assignments ca ON ca.card_id=c.id AND ca.valid_until IS NULL LEFT JOIN employees e ON e.id=ca.employee_id ORDER BY c.card_type,c.uid_canonical")->fetchAll();
    $employees=$db->query('SELECT * FROM employees WHERE active=1 ORDER BY name')->fetchAll();$terminals=$db->query('SELECT * FROM terminals WHERE active=1 AND archived_at IS NULL ORDER BY label')->fetchAll();$employeeCards=array_values(array_filter($cards,static fn(array $card):bool=>$card['card_type']==='EMPLOYEE'));
    kz_render_header('Karten','admin');
    ?>
    <div class="inline" style="margin-bottom:12px"><a class="btn secondary auto" href="?page=admin-terminals">Terminals verwalten</a></div>
    <h1>Kartenverwaltung</h1><div class="grid"><section class="card col-12"><h2>Registrierte Karten</h2><div class="table-wrap"><table><thead><tr><th>UID</th><th>Typ</th><th>Zuordnung/Ausgabe</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($cards as $card):?><tr><td class="nowrap"><strong><?=kz_h($card['uid_canonical'])?></strong><br><small class="muted"><?=kz_h($card['label'])?></small></td><td><?=kz_h($card['card_type']==='TEST'?'Testkarte':'Mitarbeiterkarte')?></td><td><?=kz_h($card['card_type']==='TEST'?($card['title'].' · '.$card['line1'].' · '.$card['line2']):($card['employee_name']??'nicht zugeordnet'))?></td><td><span class="badge <?=$card['active']?'good':'muted'?>"><?=$card['active']?'aktiv':'gesperrt'?></span></td><td><div class="inline"><form method="post" class="auto"><?php kz_hidden_action('card_toggle');?><input type="hidden" name="card_id" value="<?=(int)$card['id']?>"><button class="small secondary"><?=$card['active']?'Sperren':'Aktivieren'?></button></form><?php if($card['card_type']==='TEST'):?><form method="post" class="auto" onsubmit="return confirm('Testkarte <?=kz_h($card['uid_canonical'])?> wirklich löschen?')"><?php kz_hidden_action('test_card_delete');?><input type="hidden" name="card_id" value="<?=(int)$card['id']?>"><button class="small danger">Löschen</button></form><?php endif;?></div></td></tr><?php endforeach;?></tbody></table></div></section>
    <section class="card col-6"><h2>Mitarbeiterkarte registrieren</h2><p class="muted">Der nächste Scan am gewählten Terminal wird 60 Sekunden lang diesem Mitarbeiter zugeordnet.</p><form method="post"><?php kz_hidden_action('registration_start');?><div class="field"><label>Mitarbeiter</label><select name="employee_id" required><?php foreach($employees as $e):?><option value="<?=(int)$e['id']?>"><?=kz_h($e['name'])?></option><?php endforeach;?></select></div><div class="field"><label>Terminal</label><select name="terminal_id" required><?php foreach($terminals as $t):?><option value="<?=(int)$t['id']?>"><?=kz_h($t['label'])?></option><?php endforeach;?></select></div><button <?=!$employees||!$terminals?'disabled':''?>>Registrierung starten</button></form></section>
    <section class="card col-6"><h2>Testkarte definieren</h2><form method="post"><?php kz_hidden_action('test_card_save');?><div class="inline"><div class="field"><label>UID</label><input name="uid" placeholder="E9-4A-2C-83" required></div><div class="field"><label>Bezeichnung</label><input name="label" value="Testkarte"></div></div><label class="inline" style="justify-content:flex-start;margin-bottom:12px"><input class="auto" type="checkbox" name="response_ok" checked> Erfolgsanzeige und Erfolgston</label><div class="field"><label>Titel</label><input name="title" value="Test erfolgreich" maxlength="80"></div><div class="field"><label>Zeile 1</label><input name="line1" value="Terminal in Ordnung" maxlength="80"></div><div class="field"><label>Zeile 2</label><input name="line2" value="Verbindung steht" maxlength="80"></div><div class="field"><label>Interne Notiz</label><input name="internal_note"></div><button>Testkarte speichern</button></form></section>
    <section class="card col-12"><h2>Mitarbeiterkarte neu zuordnen</h2><p class="muted">Die bisherige Zuordnung bleibt historisch erhalten. Eine Begründung ist verpflichtend.</p><form method="post" class="inline"><?php kz_hidden_action('card_reassign');?><div class="field"><label>Karte</label><select name="card_id" required><?php foreach($employeeCards as $c):?><option value="<?=(int)$c['id']?>"><?=kz_h($c['uid_canonical'].' · '.($c['employee_name']??'nicht zugeordnet'))?></option><?php endforeach;?></select></div><div class="field"><label>Neuer Mitarbeiter</label><select name="employee_id" required><?php foreach($employees as $e):?><option value="<?=(int)$e['id']?>"><?=kz_h($e['name'])?></option><?php endforeach;?></select></div><div class="field"><label>Begründung</label><input name="reason" required placeholder="z. B. Ersatzkarte"></div><button class="auto" <?=!$employeeCards||!$employees?'disabled':''?>>Neu zuordnen</button></form></section></div>
    <?php kz_render_footer();
}

function kz_render_admin_absences(): void
{
    kz_require_admin();$db=kz_db();$requests=$db->query("SELECT ar.*,at.name AS type_name,e.name AS employee_name FROM absence_requests ar JOIN absence_types at ON at.id=ar.absence_type_id JOIN employees e ON e.id=ar.employee_id ORDER BY CASE ar.status WHEN 'PENDING' THEN 0 ELSE 1 END,ar.submitted_at DESC LIMIT 300")->fetchAll();
    $employees=$db->query('SELECT * FROM employees WHERE active=1 ORDER BY name')->fetchAll();$types=$db->query("SELECT * FROM absence_types WHERE active=1 ORDER BY CASE type_code WHEN 'VACATION' THEN 0 ELSE 1 END,name")->fetchAll();$presence=$db->query("SELECT pr.*,e.name employee_name FROM presence_requests pr JOIN employees e ON e.id=pr.employee_id ORDER BY CASE pr.status WHEN 'PENDING' THEN 0 ELSE 1 END,pr.submitted_at DESC LIMIT 300")->fetchAll();
    kz_render_header('Abwesenheiten','admin');
    ?>
    <h1>Abwesenheiten</h1><div class="grid"><section class="card col-8"><h2>Anträge und Einträge</h2><div class="table-wrap"><table><thead><tr><th>Mitarbeiter</th><th>Art</th><th>Zeitraum</th><th>Status</th><th>Entscheidung</th></tr></thead><tbody><?php foreach($requests as $r):?><tr><td><strong><?=kz_h($r['employee_name'])?></strong></td><td><?=kz_h($r['type_name'])?></td><td><?=kz_h((new DateTimeImmutable($r['start_date']))->format('d.m.Y'))?> – <?=kz_h((new DateTimeImmutable($r['end_date']))->format('d.m.Y'))?><br><small class="muted"><?=kz_h(kz_part_label($r['start_part']))?> / <?=kz_h(kz_part_label($r['end_part']))?><?= $r['employee_note']?' · '.kz_h($r['employee_note']):''?></small></td><td><span class="badge <?=$r['status']==='APPROVED'?'good':($r['status']==='PENDING'?'warn':'muted')?>"><?=kz_h(kz_status_label($r['status']))?></span></td><td><?php if($r['status']==='PENDING'):?><form method="post" class="inline"><?php kz_hidden_action('absence_decide');?><input type="hidden" name="request_id" value="<?=(int)$r['id']?>"><input name="admin_note" placeholder="Hinweis"><button class="small" name="decision" value="APPROVED">Genehmigen</button><button class="small danger" name="decision" value="REJECTED">Ablehnen</button></form><?php elseif($r['status']==='APPROVED'):?><form method="post" class="inline"><?php kz_hidden_action('absence_cancel_admin');?><input type="hidden" name="request_id" value="<?=(int)$r['id']?>"><input name="reason" placeholder="Stornierungsgrund" required><button class="small danger">Stornieren</button></form><?php else:?><small class="muted"><?=kz_h($r['admin_note'])?></small><?php endif;?></td></tr><?php endforeach;?><?php if(!$requests):?><tr><td colspan="5" class="empty">Noch keine Abwesenheiten.</td></tr><?php endif;?></tbody></table></div></section>
    <section class="card col-4"><h2>Direkt eintragen</h2><form method="post"><?php kz_hidden_action('admin_absence_create');?><div class="field"><label>Mitarbeiter</label><select name="employee_id"><?php foreach($employees as $e):?><option value="<?=(int)$e['id']?>"><?=kz_h($e['name'])?></option><?php endforeach;?></select></div><div class="field"><label>Art</label><select name="absence_type_id"><?php foreach($types as $t):?><option value="<?=(int)$t['id']?>"><?=kz_h($t['name'])?></option><?php endforeach;?></select></div><div class="inline"><div class="field"><label>Von</label><input type="date" name="start_date" value="<?=kz_h(kz_today())?>"></div><div class="field"><label>Bis</label><input type="date" name="end_date" value="<?=kz_h(kz_today())?>"></div></div><div class="field"><label>Abschnitt</label><select name="day_part"><option value="FULL_DAY">Ganzer Tag</option><option value="MORNING">Vormittag</option><option value="AFTERNOON">Nachmittag</option></select></div><div class="field"><label>Begründung/Hinweis</label><textarea name="note" rows="3"></textarea></div><button>Genehmigt eintragen</button></form></section>
    <section class="card col-8"><h2>Zusätzliche Anwesenheiten</h2><div class="table-wrap"><table><thead><tr><th>Mitarbeiter</th><th>Datum</th><th>Abschnitt</th><th>Status</th><th>Entscheidung</th></tr></thead><tbody><?php foreach($presence as $r):?><tr><td><strong><?=kz_h($r['employee_name'])?></strong></td><td><?=kz_h((new DateTimeImmutable($r['work_date']))->format('d.m.Y'))?></td><td><?=kz_h(kz_part_label($r['day_part']))?></td><td><span class="badge <?=$r['status']==='APPROVED'?'good':($r['status']==='PENDING'?'warn':'muted')?>"><?=kz_h(kz_status_label($r['status']))?></span></td><td><?php if($r['status']==='PENDING'):?><form method="post" class="inline"><?php kz_hidden_action('presence_decide');?><input type="hidden" name="request_id" value="<?=(int)$r['id']?>"><input name="admin_note" placeholder="Hinweis"><button class="small" name="decision" value="APPROVED">Genehmigen</button><button class="small danger" name="decision" value="REJECTED">Ablehnen</button></form><?php else:?><small><?=kz_h($r['admin_note'])?></small><?php endif;?></td></tr><?php endforeach;?><?php if(!$presence):?><tr><td colspan="5" class="empty">Noch keine zusätzlichen Anwesenheiten.</td></tr><?php endif;?></tbody></table></div></section>
    <section class="card col-4"><h2>Anwesenheit direkt eintragen</h2><form method="post"><?php kz_hidden_action('admin_presence_create');?><div class="field"><label>Mitarbeiter</label><select name="employee_id"><?php foreach($employees as $e):?><option value="<?=(int)$e['id']?>"><?=kz_h($e['name'])?></option><?php endforeach;?></select></div><div class="field"><label>Datum</label><input type="date" name="work_date" value="<?=kz_h(kz_today())?>" required></div><div class="field"><label>Abschnitt</label><select name="day_part"><option value="FULL_DAY">Ganzer Tag</option><option value="MORNING">Vormittag</option><option value="AFTERNOON">Nachmittag</option></select></div><div class="field"><label>Hinweis</label><textarea name="note"></textarea></div><button>Genehmigt eintragen</button></form></section></div>
    <?php kz_render_footer();
}

function kz_render_admin_attendance(): void
{
    kz_require_admin();$db=kz_db();$employees=$db->query('SELECT * FROM employees ORDER BY active DESC,name')->fetchAll();$employeeId=(int)($_GET['employee_id']??($employees[0]['id']??0));$year=max(2000,min(2100,(int)($_GET['year']??date('Y'))));$month=max(1,min(12,(int)($_GET['month']??date('n'))));
    $report=$employeeId?kz_month_report($employeeId,$year,$month):['days'=>[],'totals'=>['target'=>0,'worked'=>0,'credited'=>0,'balance'=>0]];$s=$db->prepare('SELECT * FROM month_closures WHERE employee_id=? AND year=? AND month=?');$s->execute([$employeeId,$year,$month]);$closure=$s->fetch()?:null;
    kz_render_header('Zeiten','admin');
    ?>
    <div class="inline" style="margin-bottom:12px"><a class="btn secondary auto" href="?page=admin-correction-requests">Korrekturanträge</a><a class="btn secondary auto" href="?page=admin-booking-review">Zu prüfende Buchungen</a><a class="btn secondary auto" href="?page=admin-corrections">Admin-Korrekturen</a></div>
    <div class="inline"><h1>Monatsübersicht</h1><form method="get" class="inline auto"><input type="hidden" name="page" value="admin-attendance"><select name="employee_id"><?php foreach($employees as $e):?><option value="<?=(int)$e['id']?>" <?=(int)$e['id']===$employeeId?'selected':''?>><?=kz_h($e['name'])?></option><?php endforeach;?></select><select name="month"><?php for($m=1;$m<=12;$m++):?><option value="<?=$m?>" <?=$m===$month?'selected':''?>><?=sprintf('%02d',$m)?></option><?php endfor;?></select><input type="number" name="year" value="<?=$year?>" min="2000" max="2100"><button>Zeigen</button></form></div>
    <?php if($closure&&$closure['status']==='CLOSED'):?><div class="flash info">Dieser Monat ist abgeschlossen. Änderungen sind gesperrt.</div><?php endif;?>
    <div class="kpis"><div class="kpi"><strong><?=kz_h(kz_minutes_label($report['totals']['target']))?></strong><span>Soll</span></div><div class="kpi"><strong><?=kz_h(kz_minutes_label($report['totals']['worked']))?></strong><span>Arbeit</span></div><div class="kpi"><strong><?=kz_h(kz_minutes_label($report['totals']['credited']))?></strong><span>Gutschriften</span></div><div class="kpi"><strong><?=kz_h(kz_minutes_label($report['totals']['balance'],true))?></strong><span>Saldo</span></div></div>
    <div class="inline no-print" style="margin:16px 0"><a class="btn secondary auto" href="?action=export_admin_month&format=csv&employee_id=<?=$employeeId?>&year=<?=$year?>&month=<?=$month?>">CSV exportieren</a><a class="btn secondary auto" href="?action=export_admin_month&format=pdf&employee_id=<?=$employeeId?>&year=<?=$year?>&month=<?=$month?>">PDF exportieren</a><?php if(!$closure||$closure['status']!=='CLOSED'):?><form method="post" class="auto" onsubmit="return confirm('Monat wirklich abschließen?')"><?php kz_hidden_action('month_close');?><input type="hidden" name="employee_id" value="<?=$employeeId?>"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="month" value="<?=$month?>"><button>Monat abschließen</button></form><?php else:?><form method="post" class="inline"><?php kz_hidden_action('month_reopen');?><input type="hidden" name="employee_id" value="<?=$employeeId?>"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="month" value="<?=$month?>"><input name="reason" placeholder="Pflichtbegründung" required><button class="danger">Wieder öffnen</button></form><?php endif;?></div>
    <div class="table-wrap"><table><thead><tr><th>Datum</th><th>Intervalle</th><th>Soll</th><th>Arbeit</th><th>Gutschrift</th><th>Saldo</th><th>Hinweis</th></tr></thead><tbody><?php foreach($report['days'] as $day):if($day['target']===0&&$day['worked']===0&&$day['credited']===0&&$day['note']==='')continue;?><tr><td class="nowrap"><?=kz_h($day['weekday'].' '.(new DateTimeImmutable($day['date']))->format('d.m.'))?></td><td><?=kz_h(implode(' / ',$day['intervals']))?><?=$day['incomplete']?' <span class="badge bad">unvollständig</span>':''?></td><td><?=kz_h(kz_minutes_label($day['target']))?></td><td><?=kz_h(kz_minutes_label($day['worked']))?></td><td><?=kz_h(kz_minutes_label($day['credited']))?></td><td><?=kz_h(kz_minutes_label($day['balance'],true))?></td><td><?=kz_h($day['note'])?></td></tr><?php endforeach;?></tbody></table></div>
    <?php kz_render_footer();
}

function kz_render_admin_corrections(): void
{
    kz_require_admin();$db=kz_db();$employees=$db->query('SELECT * FROM employees ORDER BY name')->fetchAll();$employeeId=(int)($_GET['employee_id']??($employees[0]['id']??0));
    $s=$db->prepare('SELECT te.*,t.label AS terminal_label FROM time_events te JOIN terminals t ON t.id=te.terminal_id WHERE te.employee_id=? ORDER BY te.occurred_at DESC LIMIT 100');$s->execute([$employeeId]);$events=$s->fetchAll();$s=$db->prepare('SELECT * FROM corrections WHERE employee_id=? ORDER BY created_at DESC LIMIT 100');$s->execute([$employeeId]);$corrections=$s->fetchAll();
    kz_render_header('Korrekturen','admin');
    ?>
    <details style="margin-bottom:16px"><summary>Rohbuchung ersetzen</summary><form method="post" class="inline" style="margin-top:12px"><?php kz_hidden_action('correction_replace');?><input type="hidden" name="employee_id" value="<?=$employeeId?>"><div class="field"><label>Rohbuchungs-ID</label><input type="number" name="target_time_event_id" required></div><div class="field"><label>Neuer Typ</label><select name="event_type"><option value="COME">Kommen</option><option value="LEAVE">Gehen</option></select></div><div class="field"><label>Neuer Zeitpunkt</label><input type="datetime-local" name="corrected_time" required></div><div class="field"><label>Begründung</label><input name="reason" required></div><button class="auto">Buchung ersetzen</button></form></details>
    <div class="inline"><h1>Korrekturen</h1><form method="get" class="inline auto"><input type="hidden" name="page" value="admin-corrections"><select name="employee_id" onchange="this.form.submit()"><?php foreach($employees as $e):?><option value="<?=(int)$e['id']?>" <?=(int)$e['id']===$employeeId?'selected':''?>><?=kz_h($e['name'])?></option><?php endforeach;?></select></form></div>
    <div class="grid"><section class="card col-7"><h2>Unveränderte Rohbuchungen</h2><div class="table-wrap"><table><thead><tr><th>ID</th><th>Zeit</th><th>Typ</th><th>Terminal</th></tr></thead><tbody><?php foreach($events as $e):?><tr><td>#<?=(int)$e['id']?></td><td><?=kz_h(kz_local_datetime($e['occurred_at'])->format('d.m.Y H:i:s'))?></td><td><?=kz_h($e['event_type']==='COME'?'Kommen':'Gehen')?></td><td><?=kz_h($e['terminal_label'])?></td></tr><?php endforeach;?></tbody></table></div></section>
    <section class="card col-5"><h2>Korrektur erfassen</h2><form method="post"><?php kz_hidden_action('correction_add');?><input type="hidden" name="employee_id" value="<?=$employeeId?>"><div class="field"><label>Neue Buchung</label><select name="event_type"><option value="COME">Kommen</option><option value="LEAVE">Gehen</option></select></div><div class="field"><label>Zeitpunkt</label><input type="datetime-local" name="corrected_time" value="<?=date('Y-m-d\TH:i')?>" required></div><div class="field"><label>Begründung</label><textarea name="reason" required></textarea></div><button>Buchung ergänzen</button></form><hr style="border:0;border-top:1px solid var(--line);margin:20px 0"><form method="post"><?php kz_hidden_action('correction_void');?><input type="hidden" name="employee_id" value="<?=$employeeId?>"><div class="field"><label>Rohbuchungs-ID stornieren</label><input type="number" name="target_time_event_id" required></div><div class="field"><label>Begründung</label><textarea name="reason" required></textarea></div><button class="danger">Buchung unwirksam stellen</button></form></section>
    <section class="card col-12"><h2>Korrekturprotokoll</h2><div class="table-wrap"><table><thead><tr><th>Zeit</th><th>Art</th><th>Ziel</th><th>Neuer Wert</th><th>Grund</th></tr></thead><tbody><?php foreach($corrections as $c):?><tr><td><?=kz_h(kz_local_datetime($c['created_at'])->format('d.m.Y H:i'))?></td><td><?=kz_h($c['correction_type'])?></td><td><?=kz_h($c['target_time_event_id']?'#'.$c['target_time_event_id']:'–')?></td><td><?=kz_h($c['corrected_time']?kz_local_datetime($c['corrected_time'])->format('d.m.Y H:i').' '.$c['corrected_event_type']:'–')?></td><td><?=kz_h($c['reason'])?></td></tr><?php endforeach;?></tbody></table></div></section></div>
    <?php kz_render_footer();
}

function kz_render_admin_correction_requests(): void
{
    kz_require_admin();$rows=kz_db()->query("SELECT cr.*,e.name employee_name FROM correction_requests cr JOIN employees e ON e.id=cr.employee_id ORDER BY CASE cr.status WHEN 'PENDING' THEN 0 ELSE 1 END,cr.submitted_at DESC LIMIT 300")->fetchAll();kz_render_header('Korrekturanträge','admin');
    ?><div class="inline"><h1>Korrekturanträge</h1><a class="btn secondary auto" href="?page=admin-corrections">Administrative Korrekturen</a></div><div class="table-wrap"><table><thead><tr><th>Mitarbeiter</th><th>Antrag</th><th>Begründung</th><th>Status</th><th>Entscheidung</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=kz_h($r['employee_name'])?></strong><br><small><?=kz_h(kz_local_datetime($r['submitted_at'])->format('d.m.Y H:i'))?></small></td><td><?=kz_h($r['request_type'])?><?php if($r['target_time_event_id']):?> · Rohbuchung #<?=(int)$r['target_time_event_id']?><?php endif;?><?php if($r['requested_time']):?><br><?=kz_h(kz_local_datetime($r['requested_time'])->format('d.m.Y H:i').' '.$r['requested_event_type'])?><?php endif;?></td><td><?=kz_h($r['reason'])?></td><td><span class="badge <?=$r['status']==='APPROVED'?'good':($r['status']==='PENDING'?'warn':'muted')?>"><?=kz_h(kz_status_label($r['status']))?></span></td><td><?php if($r['status']==='PENDING'):?><form method="post" class="inline"><?php kz_hidden_action('correction_request_decide');?><input type="hidden" name="request_id" value="<?=(int)$r['id']?>"><input name="admin_note" placeholder="Hinweis / bei Ablehnung Pflicht"><button class="small" name="decision" value="APPROVED">Genehmigen</button><button class="small danger" name="decision" value="REJECTED">Ablehnen</button></form><?php else:?><small><?=kz_h($r['admin_note'])?></small><?php endif;?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="5" class="empty">Keine Korrekturanträge vorhanden.</td></tr><?php endif;?></tbody></table></div><?php kz_render_footer();
}

function kz_render_admin_booking_review(): void
{
    kz_require_admin();$issues=kz_booking_issues();kz_render_header('Zu prüfende Buchungen','admin');
    ?>
    <h1>Zu prüfende Buchungen</h1>
    <p class="lead">Unvollständige, doppelte oder ungewöhnlich lange Anwesenheiten gesammelt abschließen. Die vorgeschlagene Sollzeit kann vor dem Setzen frei geändert werden.</p>
    <form method="post">
      <?php kz_hidden_action('booking_issues_resolve'); ?>
      <div class="table-wrap"><table><thead><tr><th><input type="checkbox" id="check-all" aria-label="Alle auswählen"></th><th>Mitarbeiter</th><th>Tag</th><th>Prüfhinweis</th><th>Arbeitszeit setzen</th><th>Buchungen</th></tr></thead><tbody>
      <?php foreach($issues as $issue): $target=(int)$issue['target']; $fingerprint=(string)$issue['fingerprint']; ?>
        <tr>
          <td><input type="checkbox" name="issues[]" value="<?=kz_h($fingerprint)?>"></td>
          <td><strong><?=kz_h($issue['employee']['name'])?></strong></td>
          <td><?=kz_h((new DateTimeImmutable($issue['date']))->format('d.m.Y'))?></td>
          <td><?php foreach($issue['types'] as $type):?><span class="badge bad"><?=kz_h($type)?></span> <?php endforeach;?></td>
          <td><input name="worked_time[<?=kz_h($fingerprint)?>]" value="<?=$target>0?kz_h(kz_duration_input($target)):''?>" inputmode="decimal" placeholder="z. B. 8:00 oder 8,5" aria-label="Arbeitszeit für <?=kz_h($issue['employee']['name'])?> am <?=kz_h((new DateTimeImmutable($issue['date']))->format('d.m.Y'))?>" style="min-width:145px"><?php if($target>0):?><br><small class="muted">Soll: <?=kz_h(kz_minutes_label($target))?></small><?php else:?><br><span class="badge warn">kein Sollplan</span><?php endif;?></td>
          <td><?=kz_h(implode(' · ',array_map(static fn($e)=>$e['local']->format('H:i').' '.($e['event_type']==='COME'?'Kommen':'Gehen'),$issue['events'])))?></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$issues):?><tr><td colspan="6" class="empty">Keine offenen Prüfhinweise.</td></tr><?php endif; ?>
      </tbody></table></div>
      <div class="inline" style="margin-top:14px"><div class="field"><label>Notiz (optional)</label><input name="note"></div><button class="auto" name="resolution" value="SET_TARGET">Eingetragene Zeit setzen</button><button class="secondary auto" name="resolution" value="REVIEWED">Als geprüft markieren</button></div>
    </form>
    <script>document.getElementById('check-all').addEventListener('change',event=>document.querySelectorAll('input[name="issues[]"]').forEach(box=>box.checked=event.target.checked));</script>
    <?php kz_render_footer();
}

function kz_render_admin_users(): void
{
    kz_require_admin();$rows=kz_db()->query('SELECT * FROM admin_users ORDER BY active DESC,username')->fetchAll();kz_render_header('Admin-Konten','admin');
    ?><h1>Admin-Konten</h1><p class="lead">Alle Admins sind gleichberechtigt. Namensänderungen und Deaktivierungen bleiben nachvollziehbar.</p><div class="grid"><section class="card col-8"><div class="table-wrap"><table><thead><tr><th>Benutzername</th><th>Vollständiger Name</th><th>Status</th><th>Letzte Anmeldung</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=kz_h($r['username'])?></strong></td><td><form method="post" class="inline"><?php kz_hidden_action('admin_user_update_name');?><input type="hidden" name="admin_id" value="<?=(int)$r['id']?>"><input name="display_name" value="<?=kz_h($r['display_name'])?>" aria-label="Vollständiger Name für <?=kz_h($r['username'])?>" required><button class="small auto">Name speichern</button></form></td><td><span class="badge <?=$r['active']?'good':'muted'?>"><?=$r['active']?'aktiv':'deaktiviert'?></span><?=$r['must_change_password']?' <span class="badge warn">Passwortwechsel</span>':''?></td><td><?=kz_h($r['last_login_at']?kz_local_datetime($r['last_login_at'])->format('d.m.Y H:i'):'–')?></td><td><?php if($r['active']&&(int)$r['id']!==(int)$_SESSION['admin_id']):?><form method="post" class="inline" onsubmit="return confirm('Admin wirklich deaktivieren?')"><?php kz_hidden_action('admin_user_deactivate');?><input type="hidden" name="admin_id" value="<?=(int)$r['id']?>"><input name="reason" placeholder="Begründung" required><button class="small danger">Deaktivieren</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section><section class="card col-4 accent"><h2>Weiteren Admin anlegen</h2><p class="muted">Das einmalige Übergangspasswort wird nach dem Anlegen angezeigt und muss beim ersten Login geändert werden.</p><form method="post"><?php kz_hidden_action('admin_user_create');?><div class="field"><label>Benutzername</label><input name="username" required pattern="[A-Za-z0-9_.-]{3,64}"></div><div class="field"><label>Vollständiger Name</label><input name="display_name" required></div><button>Admin anlegen</button></form></section></div><?php kz_render_footer();
}

function kz_render_admin_exports(): void
{
    kz_require_admin();$db=kz_db();$employees=$db->query('SELECT * FROM employees ORDER BY name')->fetchAll();
    $overview=kz_team_month_overview((int)($_GET['year']??date('Y')),(int)($_GET['month']??date('n')));$year=(int)$overview['year'];$month=(int)$overview['month'];
    kz_render_header('Auswertung','admin');
    ?>
    <h1>Auswertung</h1>
    <div class="grid"><section class="card col-6"><h2>Monatsnachweis</h2><p class="muted">Kommen, Gehen, Gutschriften, Soll-, Ist- und Saldozeiten.</p><form method="get"><input type="hidden" name="action" value="export_admin_month"><div class="field"><label>Mitarbeiter</label><select name="employee_id"><?php foreach($employees as $e):?><option value="<?=(int)$e['id']?>"><?=kz_h($e['name'])?></option><?php endforeach;?></select></div><div class="inline"><div class="field"><label>Monat</label><input type="number" name="month" min="1" max="12" value="<?=$month?>"></div><div class="field"><label>Jahr</label><input type="number" name="year" min="2000" max="2100" value="<?=$year?>"></div><div class="field"><label>Format</label><select name="format"><option value="pdf">PDF</option><option value="csv">CSV</option></select></div></div><button>Export erzeugen</button></form></section>
    <section class="card col-6 accent"><h2>Personendaten und Sollzeiten</h2><p class="muted">Datensparsamer Jahresabschluss ohne Buchungen, RFID-Daten oder Abwesenheitsgründe.</p><form method="get"><input type="hidden" name="action" value="export_annual_targets"><div class="field"><label>Mitarbeiter</label><select name="employee_id"><option value="0">Alle Mitarbeiter</option><?php foreach($employees as $e):?><option value="<?=(int)$e['id']?>"><?=kz_h($e['name'])?></option><?php endforeach;?></select></div><div class="inline"><div class="field"><label>Jahr</label><input type="number" name="year" min="2000" max="2100" value="<?=$year?>"></div><div class="field"><label>Format</label><select name="format"><option value="pdf">PDF</option><option value="csv">CSV</option></select></div></div><button>Jahresdaten exportieren</button></form></section>
    <section class="card col-12"><div class="inline"><div><h2>Mitarbeiterübersicht <?=sprintf('%02d/%04d',$month,$year)?></h2><p class="muted">Berechnungsstand: <?=kz_h($overview['cutoff_label'])?>. Zeiten werden entsprechend der Tagesabschlussregel nur aus abgeschlossenen Tagen berechnet.</p></div><form method="get" class="inline auto no-print"><input type="hidden" name="page" value="admin-exports"><div class="field"><label>Monat</label><input type="number" name="month" min="1" max="12" value="<?=$month?>"></div><div class="field"><label>Jahr</label><input type="number" name="year" min="2000" max="2100" value="<?=$year?>"></div><button class="secondary auto">Anzeigen</button></form></div>
      <div class="inline no-print" style="margin-bottom:14px"><a class="btn secondary auto" href="?action=export_team_month_summary&amp;format=pdf&amp;year=<?=$year?>&amp;month=<?=$month?>">PDF</a><a class="btn secondary auto" href="?action=export_team_month_summary&amp;format=csv&amp;year=<?=$year?>&amp;month=<?=$month?>">CSV</a><span class="muted">Urlaub genommen/geplant berücksichtigt alle genehmigten Urlaubstage des Jahres.</span></div>
      <div class="table-wrap"><table><thead><tr><th>Mitarbeiter</th><th>Gesamtsaldo</th><th>Monatssaldo</th><th>Urlaub gesamt</th><th>genommen / geplant</th><th>Resturlaub</th><th>Krankheit <?=$year?></th><th>Fortbildung <?=$year?></th><th>Sonstige <?=$year?></th><th>Unbezahlt frei <?=$year?></th></tr></thead><tbody>
      <?php foreach($overview['rows'] as $row):$employee=$row['employee'];?><tr><td><strong><?=kz_h($employee['full_name']?:$employee['name'])?></strong><br><small class="muted"><?=kz_h($employee['personnel_number'])?></small></td><td class="nowrap"><?=kz_h(kz_minutes_label((int)$row['total_balance'],true))?></td><td class="nowrap"><?=kz_h(kz_minutes_label((int)$row['month_balance'],true))?></td><td><?=kz_h(kz_days_label((float)$row['vacation_total']))?></td><td><?=kz_h(kz_days_label((float)$row['vacation_used']))?></td><td><?=kz_h(kz_days_label((float)$row['vacation_remaining']))?></td><td><?=kz_h(kz_days_label((float)$row['illness']))?></td><td><?=kz_h(kz_days_label((float)$row['training']))?></td><td><?=kz_h(kz_days_label((float)$row['other']))?></td><td><?=kz_h(kz_days_label((float)$row['unpaid']))?></td></tr><?php endforeach;?>
      <?php if(!$overview['rows']):?><tr><td colspan="10" class="empty">Keine aktiven Mitarbeiter vorhanden.</td></tr><?php endif;?></tbody></table></div>
    </section></div>
    <?php kz_render_footer();
}

function kz_render_admin_holidays(): void
{
    kz_require_admin();
    $db = kz_db();
    $year = max(2000, min(2100, (int) ($_GET['year'] ?? date('Y'))));
    $state = kz_holiday_state_for_year($year);
    $states = kz_federal_states();
    $rows = kz_holiday_admin_rows($year);
    $statement = $db->prepare('SELECT * FROM holiday_overrides WHERE holiday_date>=? AND holiday_date<? ORDER BY holiday_date');
    $statement->execute([sprintf('%04d-01-01', $year), sprintf('%04d-01-01', $year + 1)]);
    $overrides = [];
    foreach ($statement as $override) {
        $overrides[(string) $override['holiday_date']] = $override;
    }
    $editDate = (string) ($_GET['edit_date'] ?? '');
    $edit = $rows[$editDate] ?? null;
    $editOverride = $overrides[$editDate] ?? null;
    kz_render_header('Feiertage', 'admin');
    ?>
    <div class="inline">
      <div><h1>Feiertage</h1><p class="lead"><?=kz_h($states[$state])?> mit dokumentierten lokalen Anpassungen.</p></div>
      <form method="get" class="inline auto"><input type="hidden" name="page" value="admin-holidays"><label class="auto" for="holiday-year">Jahr</label><input id="holiday-year" type="number" name="year" min="2000" max="2100" value="<?= $year ?>"><button class="secondary">Anzeigen</button></form>
    </div>
    <section class="card" style="margin-bottom:18px"><form method="post" class="inline"><?php kz_hidden_action('holiday_calendar_save');?><input type="hidden" name="year" value="<?=$year?>"><div class="field"><label>Bundesland für <?=$year?></label><select name="state_code"><?php foreach($states as $code=>$label):?><option value="<?=kz_h($code)?>" <?=$code===$state?'selected':''?>><?=kz_h($label)?></option><?php endforeach;?></select></div><button class="auto">Bundesland speichern</button><p class="muted">Landesweite gesetzliche Feiertage werden automatisch geführt; kommunale Ausnahmen werden unten manuell ergänzt.</p></form></section>
    <div class="grid">
      <section class="card col-8">
        <div class="inline"><h2>Kalender <?= $year ?></h2><div class="auto"><a class="btn small secondary" href="?action=export_holidays&amp;year=<?= $year ?>&amp;format=csv">CSV</a> <a class="btn small secondary" href="?action=export_holidays&amp;year=<?= $year ?>&amp;format=pdf">PDF</a></div></div>
        <div class="table-wrap"><table><thead><tr><th>Datum</th><th>Bezeichnung</th><th>Quelle</th><th>Gutschrift</th><th>Aktionen</th></tr></thead><tbody>
        <?php foreach ($rows as $row): $date=(string)$row['date']; $override=$overrides[$date]??null; $disabled=$row['source']==='DISABLED'; $source=match($row['source']){'STATE_AUTO'=>'Bundesland automatisch','MANUAL_OVERRIDE'=>'manuell geändert','MANUAL'=>'manuell ergänzt','DISABLED'=>'deaktiviert',default=>$row['source']}; ?>
          <tr class="<?= $disabled ? 'muted' : '' ?>"><td class="nowrap"><?= kz_h((new DateTimeImmutable($date))->format('d.m.Y')) ?></td><td><?= kz_h($row['name']) ?></td><td><span class="badge <?= $disabled?'bad':($override?'warn':'good') ?>"><?= kz_h($source) ?></span><?php if($override):?><small class="muted" title="<?=kz_h($override['reason'])?>"> · <?=kz_h($override['reason'])?></small><?php endif;?></td><td><?= $disabled?'–':($row['credit_rule']==='PLANNED_TIME'?'volle Sollzeit':'keine') ?></td><td class="nowrap"><a class="btn small secondary" href="?page=admin-holidays&amp;year=<?= $year ?>&amp;edit_date=<?= kz_h($date) ?>">Bearbeiten</a>
          <?php if($override):?><form method="post" style="display:inline" onsubmit="return confirm('Automatischen Bundesland-Stand für dieses Datum wiederherstellen?')"><?php kz_hidden_action('holiday_restore');?><input type="hidden" name="holiday_date" value="<?=kz_h($date)?>"><input type="hidden" name="reason" value="Manuelle Änderung über Feiertagsverwaltung zurückgenommen"><button class="small secondary">Automatik</button></form><?php endif;?>
          <?php if(!$disabled):?><details><summary>Deaktivieren</summary><form method="post"><?php kz_hidden_action('holiday_disable');?><input type="hidden" name="holiday_date" value="<?=kz_h($date)?>"><input type="hidden" name="name" value="<?=kz_h($row['name'])?>"><div class="field"><label>Begründung</label><input name="reason" required></div><button class="small danger">Deaktivieren</button></form></details><?php endif;?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      </section>
      <section class="card col-4 accent">
        <h2><?= $edit ? 'Feiertag bearbeiten' : 'Feiertag ergänzen' ?></h2>
        <p class="muted">Änderungen wirken in Planung, Monatsauswertung und Export. Abgeschlossene Monate sind geschützt.</p>
        <form method="post"><?php kz_hidden_action('holiday_save'); ?>
          <div class="field"><label>Datum</label><input type="date" name="holiday_date" value="<?= kz_h($editDate ?: sprintf('%04d-01-01',$year)) ?>" required></div>
          <div class="field"><label>Bezeichnung</label><input name="name" value="<?= kz_h($edit['name'] ?? '') ?>" required></div>
          <div class="field"><label><input type="checkbox" name="credit_time" <?= !$edit || $edit['credit_rule']==='PLANNED_TIME'?'checked':'' ?>> Sollzeit vollständig gutschreiben</label></div>
          <div class="field"><label>Begründung</label><textarea name="reason" required><?= kz_h($editOverride['reason'] ?? '') ?></textarea></div>
          <button>Speichern</button><?php if($edit):?> <a class="btn secondary" href="?page=admin-holidays&amp;year=<?= $year ?>">Abbrechen</a><?php endif;?>
        </form>
      </section>
    </div>
    <?php kz_render_footer();
}

function kz_render_admin_terminals(): void
{
    kz_require_admin();$db=kz_db();kz_expire_terminal_actions($db);$terminals=$db->query("SELECT t.*,ta.action_type,ta.expires_at FROM terminals t LEFT JOIN terminal_actions ta ON ta.terminal_id=t.id AND ta.status='PENDING' WHERE t.archived_at IS NULL ORDER BY t.label")->fetchAll();
    kz_render_header('Terminals','admin');
    ?>
    <h1>Terminals</h1><p class="lead">Secrets können kontrolliert angezeigt oder ersetzt werden. Archivierte Geräte verschwinden dauerhaft aus der Bedienoberfläche.</p>
    <div class="grid">
      <section class="col-8"><?php if(!$terminals):?><div class="card empty">Noch kein sichtbares Terminal vorhanden.</div><?php endif;?><?php foreach($terminals as $t): $id=(int)$t['id']; ?>
        <section class="card" style="margin-bottom:18px"><div class="inline"><div><h2><?=kz_h($t['label'])?></h2><code><?=kz_h($t['terminal_code'])?></code></div><span class="badge auto <?=$t['active']?'good':'muted'?>"><?=$t['active']?'aktiv':'inaktiv'?></span></div>
          <p class="muted">Zuletzt gesehen: <?=kz_h($t['last_seen_at']?kz_local_datetime($t['last_seen_at'])->format('d.m.Y H:i:s'):'noch nie')?> · Modus: <?=kz_h($t['action_type']??'A · Kommen/Gehen')?></p>
          <form method="post"><?php kz_hidden_action('terminal_update');?><input type="hidden" name="terminal_id" value="<?=$id?>"><div class="inline"><div class="field"><label for="terminal-label-<?=$id?>">Bezeichnung</label><input id="terminal-label-<?=$id?>" name="label" value="<?=kz_h($t['label'])?>" required></div><div class="field"><label for="terminal-secret-<?=$id?>">Secret</label><input id="terminal-secret-<?=$id?>" type="password" name="terminal_secret" autocomplete="new-password" placeholder="leer = unverändert"><small class="muted">Beliebige Länge; ein leeres Feld lässt das bestehende Secret unverändert.</small></div></div><div class="inline"><label class="auto"><input type="checkbox" name="active" <?=$t['active']?'checked':''?>> aktiv</label><button type="button" class="small secondary auto reveal-secret" data-terminal="<?=$id?>">Secret anzeigen</button><button class="auto">Speichern</button><span id="terminal-message-<?=$id?>" class="muted"></span></div></form>
          <details style="margin-top:15px"><summary>Terminal archivieren</summary><p class="muted">Das Gerät wird deaktiviert, sein Secret ungültig und es verschwindet aus allen Auswahllisten. Historische Buchungen bleiben erhalten.</p><form method="post" onsubmit="return confirm('Terminal wirklich dauerhaft archivieren?')"><?php kz_hidden_action('terminal_archive');?><input type="hidden" name="terminal_id" value="<?=$id?>"><div class="field"><label>Begründung</label><input name="reason" required></div><button class="danger">Revisionssicher archivieren</button></form></details>
        </section><?php endforeach;?></section>
      <section class="card col-4 accent"><h2>Terminal anlegen</h2><p class="muted">Das Secret wird sicher erzeugt und kann anschließend über „Secret anzeigen“ abgerufen werden.</p><form method="post"><?php kz_hidden_action('terminal_save');?><div class="field"><label>Terminal-ID</label><input name="terminal_code" placeholder="eingang-2" required></div><div class="field"><label>Bezeichnung</label><input name="label" placeholder="Hintereingang" required></div><button>Anlegen</button></form></section>
    </div>
    <script>
    document.querySelectorAll('.reveal-secret').forEach(button=>button.addEventListener('click',async()=>{const id=button.dataset.terminal,input=document.getElementById('terminal-secret-'+id),message=document.getElementById('terminal-message-'+id);button.disabled=true;message.textContent='wird geladen …';try{const response=await fetch('?action=terminal_secret&terminal_id='+encodeURIComponent(id)+'&csrf=<?=rawurlencode(kz_csrf())?>',{credentials:'same-origin',cache:'no-store'});const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.message||'Secret konnte nicht geladen werden.');input.value=data.secret;input.type='text';message.textContent='Anzeige wurde protokolliert.';}catch(error){message.textContent=error.message;}finally{button.disabled=false;}}));
    </script>
    <?php kz_render_footer();
}

function kz_render_admin_audit(): void
{
    kz_require_admin();$rows=kz_db()->query('SELECT * FROM admin_audit_log ORDER BY created_at DESC LIMIT 500')->fetchAll();kz_render_header('Änderungsprotokoll','admin');
    ?><h1>Änderungsprotokoll</h1><p class="lead">Unveränderbare Nachvollziehbarkeit administrativer Eingriffe.</p><div class="table-wrap"><table><thead><tr><th>Zeit</th><th>Bearbeiter</th><th>Aktion</th><th>Objekt</th><th>Begründung</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td class="nowrap"><?=kz_h(kz_local_datetime($r['created_at'])->format('d.m.Y H:i:s'))?></td><td><?=kz_h($r['actor_label'])?></td><td><?=kz_h($r['action'])?></td><td><?=kz_h($r['entity_type'].' '.($r['entity_id']??''))?></td><td><?=kz_h($r['reason']??'')?></td></tr><?php endforeach;?></tbody></table></div><?php kz_render_footer();
}

function kz_render_admin_settings(): void
{
    kz_require_admin();$must=(bool)($_SESSION['must_change_password']??false);kz_render_header('Einstellungen','admin');
    ?>
    <h1>Einstellungen</h1><?php if($must):?><div class="flash error">Das vom Installer erzeugte Übergangspasswort muss jetzt geändert werden.</div><?php endif;?>
    <div class="inline" style="margin-bottom:18px"><a class="btn secondary auto" href="?page=admin-users">Admin-Konten</a><a class="btn secondary auto" href="?page=admin-audit">Änderungsprotokoll</a></div><div class="grid"><section class="card col-6"><h2>Zeiterfassung und Anzeige</h2><form method="post"><?php kz_hidden_action('settings_save');?><div class="field"><label>Trennung Vormittag/Nachmittag</label><input type="time" name="day_part_boundary" value="<?=kz_h(kz_setting('day_part_boundary','13:00'))?>"></div><div class="field"><label>Doppelauflege-Schutz in Sekunden</label><input type="number" min="0" max="300" name="duplicate_guard_seconds" value="<?=kz_h(kz_setting('duplicate_guard_seconds','30'))?>"></div><label class="inline" style="justify-content:flex-start;margin-bottom:14px"><input class="auto" type="checkbox" name="show_weekends" <?=kz_show_weekends()?'checked':''?>> Samstag und Sonntag in Wochenplanungen anzeigen</label><label class="inline" style="justify-content:flex-start;margin-bottom:14px"><input class="auto" type="checkbox" name="public_presence_enabled" <?=kz_setting('public_presence_enabled','0')==='1'?'checked':''?>> Aktuell anwesende Namen öffentlich auf der Startseite anzeigen</label><label class="inline" style="justify-content:flex-start;margin-bottom:14px"><input class="auto" type="checkbox" name="expenses_enabled" <?=kz_expenses_enabled()?'checked':''?>> Auslagenverwaltung aktivieren</label><small class="muted" style="display:block;margin:-8px 0 14px">Bei Deaktivierung verschwinden die Menüpunkte und direkte Aufrufe werden gesperrt. Gespeicherte Auslagen bleiben erhalten.</small><button>Speichern</button></form></section>
    <section class="card col-6 accent"><h2>Admin-Passwort ändern</h2><form method="post"><?php kz_hidden_action('change_password');?><div class="field"><label>Neues Passwort</label><input type="password" name="new_password" minlength="12" autocomplete="new-password" required></div><div class="field"><label>Neues Passwort wiederholen</label><input type="password" name="repeat_password" minlength="12" autocomplete="new-password" required></div><button>Passwort ändern</button></form></section></div>
    <?php kz_render_footer();
}

$page=(string)($_GET['page']??'');
if(kz_is_admin()){
    if((bool)($_SESSION['must_change_password']??false)){$page='admin-settings';}
    if($page===''||!str_starts_with($page,'admin-')){$page='admin-dashboard';}
    match($page){
        'admin-dashboard'=>kz_render_admin_dashboard(),
        'admin-employees'=>kz_render_admin_employees(),
        'admin-schedules'=>kz_render_admin_schedules(),
        'admin-cards'=>kz_render_admin_cards(),
        'admin-absences'=>kz_render_admin_absences(),
        'admin-holidays'=>kz_render_admin_holidays(),
        'admin-attendance'=>kz_render_admin_attendance(),
        'admin-corrections'=>kz_render_admin_corrections(),
        'admin-correction-requests'=>kz_render_admin_correction_requests(),
        'admin-booking-review'=>kz_render_admin_booking_review(),
        'admin-exports'=>kz_render_admin_exports(),
        'admin-terminals'=>kz_render_admin_terminals(),
        'admin-audit'=>kz_render_admin_audit(),
        'admin-settings'=>kz_render_admin_settings(),
        'admin-users'=>kz_render_admin_users(),
        default=>kz_render_admin_dashboard(),
    };exit;
}
if(kz_employee_id()!==null){
    if($page===''||!str_starts_with($page,'employee-')){$page='employee-dashboard';}
    match($page){'employee-absences'=>kz_render_employee_absences(),'employee-times'=>kz_render_employee_times(),default=>kz_render_employee_dashboard()};exit;
}
if($page==='card-wait'){kz_render_card_wait((string)($_GET['token']??''));exit;}
kz_render_public_page();
