<?php
/**
 * auslagen.php - Auslagen & Kilometer
 *
 * Version: 1.1.3
 * Author: Dr. Thomas Kienzle
 * Stand: 2026-07-06
 *
 * Changelog (komplett):
 * - 1.1.3 (2026-07-06):
 *   - Beleg-vorhanden wird nur durch Admins als Buchhaltungsstatus gepflegt.
 *   - Mitarbeiterantrag, Mitarbeiterdetails und Mitarbeiterdruck enthalten keinen Belegstatus.
 *   - Admin-Vorgangsliste fasst Beleglage als offen, fehlt oder vollstaendig zusammen.
 *
 * - 1.1.2 (2026-07-05):
 *   - Belegschalter wird in der Mitarbeitererfassung serverseitig ausgegeben.
 *   - Mitarbeiter-Detailansicht ohne QR-Code und mit breiterer Vorschau.
 *   - Admin-Detailansicht behaelt den EPC-QR-Code.
 *
 * - 1.1.1 (2026-07-05):
 *   - Jeder Belegposten hat einen gespeicherten Ja/Nein-Schalter "Beleg vorhanden".
 *   - Alte Posten bleiben unveraendert und werden als "nicht erfasst" gekennzeichnet.
 *   - Der EPC-QR-Code erscheint auch in der Web-Detailansicht eines Vorgangs.
 *
 * - 1.1 (2026-07-05):
 *   - Gemeinsame, serverseitig validierte Anmeldung mit kienzlezeit.
 *   - Zugriff nur bei global aktivierter Auslagenverwaltung.
 *   - Historisierte Erstattungskonten je Mitarbeiter mit unveraenderlichem Kontoschnappschuss je Beleg.
 *   - Mitarbeiter sehen und drucken ausschliesslich ihre eigenen Vorgänge.
 *   - Admin-Workflow Eingereicht, Genehmigt, Bezahlt, Abgelehnt oder Storniert.
 *   - Revisionsprotokoll statt dauerhafter Loeschung; Altdaten bleiben unveraendert und zuordenbar.
 *   - Getrennte SQLite-Datenbank ausserhalb des Webverzeichnisses und lokaler EPC-QR-Code.
 * - 1.0 (2026-06-25):
 *   - Ausgangsversion mit lokaler SQLite-Ablage, Beleg-/Kilometererfassung,
 *     Admin-Verwaltung, Drucklayout und lokalem QR-Code.
 */

declare(strict_types=1);

date_default_timezone_set('Europe/Berlin');

const APP_TITLE = 'Auslagen & Kilometer';
const APP_VERSION = '1.1.3';
const APP_AUTHOR = 'Dr. Thomas Kienzle';
const DEFAULT_DB_FILE = '/var/lib/kienzlezeit/auslagen.sqlite';
const DEFAULT_KZ_DB_FILE = '/var/lib/kienzlezeit/kienzlezeit.sqlite';

function env_path(string $name, string $default): string
{
    $value = getenv($name);
    return is_string($value) && $value !== '' ? $value : $default;
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now_utc(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function start_shared_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
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

function kz_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $path = env_path('KZ_DB_PATH', DEFAULT_KZ_DB_FILE);
    if (!is_file($path)) {
        throw new RuntimeException('Die kienzlezeit-Datenbank wurde nicht gefunden.');
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 1000');
    return $pdo;
}

function kz_setting(string $key, string $default = ''): string
{
    $statement = kz_db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
    $statement->execute([$key]);
    $value = $statement->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function current_user(): ?array
{
    if (isset($_SESSION['admin_id'], $_SESSION['admin_session_version'])) {
        $statement = kz_db()->prepare('SELECT id,display_name,active,session_version FROM admin_users WHERE id=?');
        $statement->execute([(int) $_SESSION['admin_id']]);
        $admin = $statement->fetch();
        if ($admin && (int) $admin['active'] === 1 && (int) $admin['session_version'] === (int) $_SESSION['admin_session_version']) {
            return ['role' => 'ADMIN', 'id' => (int) $admin['id'], 'name' => (string) $admin['display_name']];
        }
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_session_version']);
    }
    if (isset($_SESSION['employee_id'])) {
        $statement = kz_db()->prepare('SELECT id,name,full_name,active FROM employees WHERE id=?');
        $statement->execute([(int) $_SESSION['employee_id']]);
        $employee = $statement->fetch();
        if ($employee && (int) $employee['active'] === 1) {
            $name = trim((string) ($employee['full_name'] ?? '')) ?: (string) $employee['name'];
            return ['role' => 'EMPLOYEE', 'id' => (int) $employee['id'], 'name' => $name];
        }
        unset($_SESSION['employee_id'], $_SESSION['employee_name']);
    }
    return null;
}

function is_admin(): bool
{
    global $user;
    return is_array($user) && $user['role'] === 'ADMIN';
}

function require_admin(): void
{
    if (!is_admin()) {
        throw new RuntimeException('Admin-Anmeldung erforderlich.');
    }
}

function render_gate(string $title, string $message, int $status): never
{
    http_response_code($status);
    ?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?></title>
<style>body{margin:0;background:#f4f7f4;color:#151b17;font:16px/1.5 system-ui,sans-serif}.box{width:min(650px,calc(100% - 32px));margin:10vh auto;background:#fff;border:1px solid #dce5dd;border-radius:16px;padding:28px;box-shadow:0 12px 34px rgba(22,50,25,.09)}a{display:inline-block;background:#178f08;color:#fff;padding:10px 15px;border-radius:9px;text-decoration:none;font-weight:700}</style></head><body><main class="box"><h1><?=h($title)?></h1><p><?=h($message)?></p><a href="<?=h(kz_web_path())?>">Zu kienzlezeit</a></main></body></html>
    <?php
    exit;
}

function kz_web_path(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    return str_contains($script, '/auslagen/') ? '../kienzlezeit.php' : 'kienzlezeit.php';
}

function logo_web_path(): string
{
    return str_starts_with(kz_web_path(), '../') ? '../kienzlezeit.png' : 'kienzlezeit.png';
}

function sqlite_available(): bool
{
    return extension_loaded('pdo_sqlite') && in_array('sqlite', PDO::getAvailableDrivers(), true);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!sqlite_available()) {
        throw new RuntimeException('SQLite-Unterstützung fehlt.');
    }
    $path = env_path('AUSLAGEN_DB_PATH', DEFAULT_DB_FILE);
    $directory = dirname($path);
    if (!is_dir($directory)) {
        throw new RuntimeException('Das Datenverzeichnis fehlt: ' . $directory);
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 1000');
    init_db($pdo);
    return $pdo;
}

function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')') as $existing) {
        if ((string) $existing['name'] === $column) {
            return;
        }
    }
    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function init_db(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS payees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    iban TEXT NOT NULL,
    bic TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(name,iban)
);
CREATE TABLE IF NOT EXISTS reimbursements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    doc_id TEXT NOT NULL UNIQUE,
    doc_year INTEGER NOT NULL,
    doc_number INTEGER NOT NULL,
    expense_date TEXT NOT NULL,
    subject TEXT NOT NULL,
    claimant_name TEXT NOT NULL,
    iban TEXT NOT NULL,
    bic TEXT NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    km_rate REAL NOT NULL DEFAULT 0,
    total_km REAL NOT NULL DEFAULT 0,
    total_receipts REAL NOT NULL DEFAULT 0,
    total_amount REAL NOT NULL DEFAULT 0,
    transfer_purpose TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reimbursement_id INTEGER NOT NULL REFERENCES reimbursements(id) ON DELETE CASCADE,
    item_type TEXT NOT NULL,
    item_date TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    kilometers REAL NOT NULL DEFAULT 0,
    amount REAL NOT NULL DEFAULT 0,
    receipt_present INTEGER CHECK(receipt_present IN (0,1)),
    sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS reimbursement_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    account_holder TEXT NOT NULL,
    iban TEXT NOT NULL,
    bic TEXT NOT NULL DEFAULT '',
    valid_from TEXT NOT NULL,
    valid_until TEXT,
    active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
    created_by_role TEXT NOT NULL CHECK(created_by_role IN ('EMPLOYEE','ADMIN')),
    created_by_id INTEGER NOT NULL,
    change_reason TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_reimbursement_account_current
ON reimbursement_accounts(employee_id) WHERE active=1 AND valid_until IS NULL;
CREATE TABLE IF NOT EXISTS reimbursement_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reimbursement_id INTEGER NOT NULL REFERENCES reimbursements(id),
    previous_status TEXT,
    new_status TEXT NOT NULL,
    actor_role TEXT NOT NULL,
    actor_id INTEGER NOT NULL,
    actor_name TEXT NOT NULL,
    note TEXT NOT NULL DEFAULT '',
    changed_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS expense_audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_role TEXT NOT NULL,
    actor_id INTEGER NOT NULL,
    actor_name TEXT NOT NULL,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT,
    details TEXT NOT NULL DEFAULT '',
    ip_address TEXT,
    created_at TEXT NOT NULL
);
SQL);
    add_column_if_missing($pdo, 'reimbursements', 'employee_id', 'INTEGER');
    add_column_if_missing($pdo, 'reimbursements', 'reimbursement_account_id', 'INTEGER');
    add_column_if_missing($pdo, 'reimbursements', 'status', "TEXT NOT NULL DEFAULT 'LEGACY'");
    add_column_if_missing($pdo, 'reimbursements', 'submitted_at', 'TEXT');
    add_column_if_missing($pdo, 'reimbursements', 'decided_at', 'TEXT');
    add_column_if_missing($pdo, 'reimbursements', 'decided_by', 'INTEGER');
    add_column_if_missing($pdo, 'reimbursements', 'decision_note', "TEXT NOT NULL DEFAULT ''");
    add_column_if_missing($pdo, 'reimbursements', 'paid_at', 'TEXT');
    add_column_if_missing($pdo, 'reimbursements', 'paid_by', 'INTEGER');
    add_column_if_missing($pdo, 'reimbursements', 'cancelled_at', 'TEXT');
    add_column_if_missing($pdo, 'reimbursements', 'cancelled_by', 'INTEGER');
    add_column_if_missing($pdo, 'reimbursements', 'cancel_reason', "TEXT NOT NULL DEFAULT ''");
    add_column_if_missing($pdo, 'items', 'receipt_present', 'INTEGER CHECK(receipt_present IN (0,1))');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reimbursements_employee ON reimbursements(employee_id,created_at)');
    foreach ([
        'km_rate' => '0.30',
        'practice_name' => 'Praxis',
        'transfer_prefix' => 'Erstattung von Auslage Praxis',
        'counter_year' => date('Y'),
        'next_counter' => '1',
        'schema_version' => '3',
    ] as $key => $value) {
        $statement = $pdo->prepare('INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)');
        $statement->execute([$key, $value]);
    }
    $pdo->prepare("UPDATE reimbursements SET status='LEGACY' WHERE status IS NULL OR trim(status)='' ")->execute();
    $pdo->prepare("UPDATE settings SET value='3' WHERE key='schema_version'")->execute();
}

function get_setting(string $key, ?string $default = null): ?string
{
    $statement = db()->prepare('SELECT value FROM settings WHERE key=?');
    $statement->execute([$key]);
    $value = $statement->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function set_setting(string $key, string $value): void
{
    $statement = db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
    $statement->execute([$key, $value]);
}

function csrf_token(): string
{
    if (empty($_SESSION['expense_csrf'])) {
        $_SESSION['expense_csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['expense_csrf'];
}

function require_csrf(): void
{
    $provided = (string) ($_POST['csrf_token'] ?? '');
    if ($provided === '' || !hash_equals(csrf_token(), $provided)) {
        throw new RuntimeException('Die Sitzung ist abgelaufen. Bitte erneut versuchen.');
    }
}

function logout_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['csrf'];
}

function redirect(string $target): never
{
    header('Location: ' . $target, true, 303);
    exit;
}

function audit(string $action, string $entityType, string|int|null $entityId, string $details = ''): void
{
    global $user;
    $statement = db()->prepare('INSERT INTO expense_audit_log(actor_role,actor_id,actor_name,action,entity_type,entity_id,details,ip_address,created_at) VALUES(?,?,?,?,?,?,?,?,?)');
    $statement->execute([$user['role'], $user['id'], $user['name'], $action, $entityType, $entityId === null ? null : (string) $entityId, $details, $_SERVER['REMOTE_ADDR'] ?? null, now_utc()]);
}

function normalize_date_input(mixed $date): string
{
    $date = trim((string) $date);
    if ($date === '') {
        return '';
    }
    foreach (['Y-m-d', 'd.m.Y', 'd.m.y'] as $format) {
        $value = DateTimeImmutable::createFromFormat('!' . $format, $date);
        if ($value && $value->format($format) === $date) {
            return $value->format('Y-m-d');
        }
    }
    return '';
}

function parse_decimal(mixed $value): float
{
    $value = str_replace(' ', '', trim((string) $value));
    if ($value === '') {
        return 0.0;
    }
    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = strrpos($value, ',') > strrpos($value, '.') ? str_replace(',', '.', str_replace('.', '', $value)) : str_replace(',', '', $value);
    } elseif (str_contains($value, ',')) {
        $value = str_replace(',', '.', str_replace('.', '', $value));
    }
    return is_numeric($value) ? round((float) $value, 2) : 0.0;
}

function format_decimal(float $value, int $decimals = 2): string
{
    return number_format($value, $decimals, ',', '.');
}

function format_eur(float $value): string
{
    return format_decimal($value) . ' €';
}

function normalize_iban(mixed $iban): string
{
    return preg_replace('/\s+/', '', strtoupper((string) $iban)) ?? '';
}

function format_iban(string $iban): string
{
    return trim(chunk_split(normalize_iban($iban), 4, ' '));
}

function iban_last4(string $iban): string
{
    return substr(normalize_iban($iban), -4);
}

function validate_iban(string $iban): bool
{
    $iban = normalize_iban($iban);
    if (!preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/', $iban)) {
        return false;
    }
    $rearranged = substr($iban, 4) . substr($iban, 0, 4);
    $numeric = '';
    foreach (str_split($rearranged) as $char) {
        $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
    }
    $mod = 0;
    foreach (str_split($numeric) as $digit) {
        $mod = ($mod * 10 + (int) $digit) % 97;
    }
    return $mod === 1;
}

function normalize_bic(mixed $bic): string
{
    return preg_replace('/\s+/', '', strtoupper(trim((string) $bic))) ?? '';
}

function validate_bic(string $bic): bool
{
    return $bic === '' || (bool) preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/', $bic);
}

function current_account(int $employeeId): ?array
{
    $statement = db()->prepare('SELECT * FROM reimbursement_accounts WHERE employee_id=? AND active=1 AND valid_until IS NULL ORDER BY id DESC LIMIT 1');
    $statement->execute([$employeeId]);
    return $statement->fetch() ?: null;
}

function save_account(array $input): void
{
    global $user;
    if ($user['role'] !== 'EMPLOYEE') {
        throw new RuntimeException('Erstattungskonten werden vom jeweiligen Mitarbeiter gepflegt.');
    }
    $holder = trim((string) ($input['account_holder'] ?? ''));
    $iban = normalize_iban($input['iban'] ?? '');
    $bic = normalize_bic($input['bic'] ?? '');
    $reason = trim((string) ($input['change_reason'] ?? ''));
    $previous = current_account((int) $user['id']);
    if ($holder === '' || !validate_iban($iban) || !validate_bic($bic)) {
        throw new RuntimeException('Bitte Kontoinhaber, eine gültige IBAN und gegebenenfalls eine gültige BIC angeben.');
    }
    if ($previous && $previous['account_holder'] === $holder && $previous['iban'] === $iban && $previous['bic'] === $bic) {
        throw new RuntimeException('Die Kontodaten sind unverändert.');
    }
    if ($previous && $reason === '') {
        throw new RuntimeException('Bei einer Kontoänderung ist eine kurze Begründung erforderlich.');
    }
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $now = now_utc();
        $pdo->prepare('UPDATE reimbursement_accounts SET active=0,valid_until=? WHERE employee_id=? AND active=1 AND valid_until IS NULL')->execute([$now, $user['id']]);
        $pdo->prepare('INSERT INTO reimbursement_accounts(employee_id,account_holder,iban,bic,valid_from,active,created_by_role,created_by_id,change_reason,created_at) VALUES(?,?,?,?,?,1,?,?,?,?)')
            ->execute([$user['id'], $holder, $iban, $bic, $now, $user['role'], $user['id'], $reason, $now]);
        $accountId = (int) $pdo->lastInsertId();
        $pdo->exec('COMMIT');
    } catch (Throwable $exception) {
        $pdo->exec('ROLLBACK');
        throw $exception;
    }
    audit($previous ? 'ACCOUNT_REPLACE' : 'ACCOUNT_CREATE', 'reimbursement_account', $accountId, 'IBAN-Endung …' . iban_last4($iban) . ($reason !== '' ? ' · ' . $reason : ''));
}

function allocate_doc_id(PDO $pdo): array
{
    $year = (int) date('Y');
    $storedYear = (int) (get_setting('counter_year', (string) $year) ?? $year);
    $next = max(1, (int) (get_setting('next_counter', '1') ?? 1));
    if ($storedYear !== $year) {
        $storedYear = $year;
        $next = 1;
        set_setting('counter_year', (string) $year);
    }
    set_setting('next_counter', (string) ($next + 1));
    return [sprintf('%d-AUSL-%04d', $storedYear, $next), $storedYear, $next];
}

function build_transfer_purpose(string $docId): string
{
    $prefix = trim((string) get_setting('transfer_prefix', 'Erstattung von Auslage Praxis')) ?: 'Erstattung von Auslage Praxis';
    return $prefix . '. Beleg: ' . $docId;
}

function save_reimbursement(array $input): string
{
    global $user;
    if ($user['role'] !== 'EMPLOYEE') {
        throw new RuntimeException('Nur angemeldete Mitarbeiter können eine Auslage einreichen.');
    }
    $account = current_account((int) $user['id']);
    if (!$account) {
        throw new RuntimeException('Bitte zuerst ein Erstattungskonto hinterlegen.');
    }
    $subject = trim((string) ($input['subject'] ?? ''));
    $note = trim((string) ($input['note'] ?? ''));
    if ($subject === '') {
        throw new RuntimeException('Bitte einen Betreff angeben.');
    }
    $types = is_array($input['item_type'] ?? null) ? $input['item_type'] : [];
    $dates = is_array($input['item_date'] ?? null) ? $input['item_date'] : [];
    $descriptions = is_array($input['item_description'] ?? null) ? $input['item_description'] : [];
    $kilometers = is_array($input['item_kilometers'] ?? null) ? $input['item_kilometers'] : [];
    $amounts = is_array($input['item_amount'] ?? null) ? $input['item_amount'] : [];
    $kmRate = (float) (get_setting('km_rate', '0.30') ?? '0.30');
    $rows = [];
    $totalKm = $totalReceipts = $total = 0.0;
    $count = max(count($types), count($dates), count($descriptions), count($kilometers), count($amounts));
    for ($index = 0; $index < $count; $index++) {
        $type = ($types[$index] ?? 'receipt') === 'km' ? 'km' : 'receipt';
        $date = normalize_date_input($dates[$index] ?? '') ?: date('Y-m-d');
        $description = trim((string) ($descriptions[$index] ?? ''));
        $km = parse_decimal($kilometers[$index] ?? '');
        $amount = parse_decimal($amounts[$index] ?? '');
        $receiptPresent = null;
        if ($description === '' && $km <= 0 && $amount <= 0 && trim((string) ($dates[$index] ?? '')) === '') {
            continue;
        }
        if ($type === 'km') {
            if ($km <= 0) {
                throw new RuntimeException('Bei Kilometer-Posten muss eine Kilometerzahl größer 0 angegeben werden.');
            }
            $amount = round($km * $kmRate, 2);
            $description = $description ?: 'Kilometerpauschale';
            $totalKm += $km;
        } else {
            if ($amount <= 0) {
                throw new RuntimeException('Bei Beleg-Posten muss ein Betrag größer 0 angegeben werden.');
            }
            $description = $description ?: 'Beleg';
            $totalReceipts += $amount;
        }
        $total += $amount;
        $rows[] = compact('type', 'date', 'description', 'km', 'amount', 'receiptPresent');
    }
    if (!$rows) {
        throw new RuntimeException('Bitte mindestens einen Beleg- oder Kilometer-Posten eingeben.');
    }
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        [$docId, $year, $number] = allocate_doc_id($pdo);
        $now = now_utc();
        $statement = $pdo->prepare("INSERT INTO reimbursements(doc_id,doc_year,doc_number,expense_date,subject,claimant_name,iban,bic,note,km_rate,total_km,total_receipts,total_amount,transfer_purpose,created_at,updated_at,employee_id,reimbursement_account_id,status,submitted_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $statement->execute([$docId, $year, $number, date('Y-m-d'), $subject, $account['account_holder'], $account['iban'], $account['bic'], $note, $kmRate, round($totalKm, 2), round($totalReceipts, 2), round($total, 2), build_transfer_purpose($docId), $now, $now, $user['id'], $account['id'], 'SUBMITTED', $now]);
        $id = (int) $pdo->lastInsertId();
        $item = $pdo->prepare('INSERT INTO items(reimbursement_id,item_type,item_date,description,kilometers,amount,receipt_present,sort_order) VALUES(?,?,?,?,?,?,?,?)');
        foreach ($rows as $index => $row) {
            $item->execute([$id, $row['type'], $row['date'], $row['description'], $row['type'] === 'km' ? $row['km'] : 0, $row['amount'], $row['receiptPresent'], $index + 1]);
        }
        $pdo->prepare('INSERT INTO reimbursement_history(reimbursement_id,previous_status,new_status,actor_role,actor_id,actor_name,note,changed_at) VALUES(?,NULL,?,?,?,?,?,?)')
            ->execute([$id, 'SUBMITTED', $user['role'], $user['id'], $user['name'], 'Eingereicht', $now]);
        $pdo->exec('COMMIT');
    } catch (Throwable $exception) {
        $pdo->exec('ROLLBACK');
        throw $exception;
    }
    audit('CREATE', 'reimbursement', $id, $docId . ' · ' . format_eur($total));
    return $docId;
}

function find_reimbursement(string $docId): ?array
{
    global $user;
    $sql = 'SELECT * FROM reimbursements WHERE doc_id=?';
    $params = [trim($docId)];
    if ($user['role'] === 'EMPLOYEE') {
        $sql .= ' AND employee_id=?';
        $params[] = $user['id'];
    }
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();
    if (!$row) {
        return null;
    }
    $items = db()->prepare('SELECT * FROM items WHERE reimbursement_id=? ORDER BY sort_order,id');
    $items->execute([(int) $row['id']]);
    $row['items'] = $items->fetchAll();
    return $row;
}

function reimbursement_rows(): array
{
    global $user;
    if ($user['role'] === 'ADMIN') {
        return db()->query("SELECT r.*,SUM(CASE WHEN i.item_type='receipt' THEN 1 ELSE 0 END) AS receipt_count,SUM(CASE WHEN i.item_type='receipt' AND i.receipt_present=1 THEN 1 ELSE 0 END) AS receipt_present_count,SUM(CASE WHEN i.item_type='receipt' AND i.receipt_present=0 THEN 1 ELSE 0 END) AS receipt_missing_count,SUM(CASE WHEN i.item_type='receipt' AND i.receipt_present IS NULL THEN 1 ELSE 0 END) AS receipt_open_count FROM reimbursements r LEFT JOIN items i ON i.reimbursement_id=r.id GROUP BY r.id ORDER BY r.created_at DESC LIMIT 300")->fetchAll();
    }
    $statement = db()->prepare('SELECT * FROM reimbursements WHERE employee_id=? ORDER BY created_at DESC LIMIT 200');
    $statement->execute([$user['id']]);
    return $statement->fetchAll();
}

function status_label(string $status): string
{
    return match ($status) {
        'SUBMITTED' => 'Eingereicht', 'APPROVED' => 'Genehmigt', 'PAID' => 'Bezahlt',
        'REJECTED' => 'Abgelehnt', 'CANCELLED' => 'Storniert', 'LEGACY' => 'Altbestand',
        default => $status,
    };
}

function receipt_overview(array $row): array
{
    $count = (int) ($row['receipt_count'] ?? 0);
    $present = (int) ($row['receipt_present_count'] ?? 0);
    $missing = (int) ($row['receipt_missing_count'] ?? 0);
    $open = (int) ($row['receipt_open_count'] ?? 0);
    if ($count === 0) {
        return ['label'=>'keine Belege','class'=>'muted'];
    }
    if ($missing > 0) {
        return ['label'=>$missing . ' fehlt' . ($missing === 1 ? '' : 'en'),'class'=>'bad'];
    }
    if ($open > 0) {
        return ['label'=>$open . ' offen','class'=>'warn'];
    }
    return ['label'=>$present . '/' . $count . ' vollständig','class'=>'good'];
}

function set_item_receipt_status(int $itemId, int $present): string
{
    require_admin();
    if (!in_array($present, [0,1], true)) {
        throw new RuntimeException('Der Belegstatus ist ungültig.');
    }
    $pdo = db();
    $statement = $pdo->prepare('SELECT i.*,r.doc_id FROM items i JOIN reimbursements r ON r.id=i.reimbursement_id WHERE i.id=?');
    $statement->execute([$itemId]);
    $item = $statement->fetch();
    if (!$item || $item['item_type'] !== 'receipt') {
        throw new RuntimeException('Der Belegposten wurde nicht gefunden.');
    }
    $before = $item['receipt_present'] === null ? 'OFFEN' : ((int) $item['receipt_present'] === 1 ? 'JA' : 'NEIN');
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->prepare('UPDATE items SET receipt_present=? WHERE id=?')->execute([$present,$itemId]);
        audit('RECEIPT_STATUS', 'item', $itemId, $item['doc_id'] . ' · ' . $before . ' → ' . ($present === 1 ? 'JA' : 'NEIN'));
        $pdo->exec('COMMIT');
    } catch (Throwable $exception) {
        $pdo->exec('ROLLBACK');
        throw $exception;
    }
    return (string) $item['doc_id'];
}

function set_reimbursement_status(int $id, string $newStatus, string $note): void
{
    global $user;
    require_admin();
    $statement = db()->prepare('SELECT * FROM reimbursements WHERE id=?');
    $statement->execute([$id]);
    $row = $statement->fetch();
    if (!$row) {
        throw new RuntimeException('Der Vorgang wurde nicht gefunden.');
    }
    $allowed = ['SUBMITTED' => ['APPROVED','REJECTED','CANCELLED'], 'APPROVED' => ['PAID','CANCELLED']];
    if (!in_array($newStatus, $allowed[(string) $row['status']] ?? [], true)) {
        throw new RuntimeException('Dieser Statuswechsel ist nicht zulässig.');
    }
    if (in_array($newStatus, ['REJECTED','CANCELLED'], true) && $note === '') {
        throw new RuntimeException('Für Ablehnung oder Stornierung ist eine Begründung erforderlich.');
    }
    $now = now_utc();
    $fields = match ($newStatus) {
        'APPROVED','REJECTED' => ['decided_at'=>$now,'decided_by'=>$user['id'],'decision_note'=>$note],
        'PAID' => ['paid_at'=>$now,'paid_by'=>$user['id']],
        'CANCELLED' => ['cancelled_at'=>$now,'cancelled_by'=>$user['id'],'cancel_reason'=>$note],
    };
    $sets = ['status=?'];
    $params = [$newStatus];
    foreach ($fields as $field => $value) {
        $sets[] = $field . '=?';
        $params[] = $value;
    }
    $params[] = $id;
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->prepare('UPDATE reimbursements SET ' . implode(',', $sets) . ',updated_at=? WHERE id=?')->execute(array_merge(array_slice($params, 0, -1), [$now, $id]));
        $pdo->prepare('INSERT INTO reimbursement_history(reimbursement_id,previous_status,new_status,actor_role,actor_id,actor_name,note,changed_at) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$id, $row['status'], $newStatus, $user['role'], $user['id'], $user['name'], $note, $now]);
        $pdo->exec('COMMIT');
    } catch (Throwable $exception) {
        $pdo->exec('ROLLBACK');
        throw $exception;
    }
    audit('STATUS_' . $newStatus, 'reimbursement', $id, $note);
}

function assign_legacy(int $id, int $employeeId): void
{
    require_admin();
    $employee = kz_db()->prepare('SELECT id,full_name,name FROM employees WHERE id=?');
    $employee->execute([$employeeId]);
    if (!$employee->fetch()) {
        throw new RuntimeException('Der Mitarbeiter wurde nicht gefunden.');
    }
    $statement = db()->prepare("UPDATE reimbursements SET employee_id=?,updated_at=? WHERE id=? AND employee_id IS NULL AND status='LEGACY'");
    $statement->execute([$employeeId, now_utc(), $id]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Der Altbeleg ist bereits zugeordnet oder wurde nicht gefunden.');
    }
    audit('ASSIGN', 'legacy_reimbursement', $id, 'employee_id=' . $employeeId);
}

function build_epc_payload(array $doc): string
{
    $name = function_exists('mb_substr') ? mb_substr((string) $doc['claimant_name'], 0, 70) : substr((string) $doc['claimant_name'], 0, 70);
    $purpose = function_exists('mb_substr') ? mb_substr((string) $doc['transfer_purpose'], 0, 140) : substr((string) $doc['transfer_purpose'], 0, 140);
    return implode("\n", ['BCD','002','1','SCT',(string) $doc['bic'],$name,normalize_iban($doc['iban']),'EUR'.number_format((float) $doc['total_amount'],2,'.',''),'','',$purpose,'']);
}

function qr_data_uri(string $payload): ?string
{
    if (!function_exists('shell_exec')) {
        return null;
    }
    $binary = @shell_exec('command -v qrencode 2>/dev/null');
    if (!is_string($binary) || trim($binary) === '') {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'ausl_qr_');
    if ($tmp === false) {
        return null;
    }
    @shell_exec('qrencode -s 5 -m 1 -o ' . escapeshellarg($tmp) . ' ' . escapeshellarg($payload) . ' 2>/dev/null');
    $data = is_file($tmp) ? file_get_contents($tmp) : false;
    @unlink($tmp);
    return is_string($data) && $data !== '' ? 'data:image/png;base64,' . base64_encode($data) : null;
}

function receipt_presence_label(array $item): string
{
    if (($item['item_type'] ?? '') !== 'receipt') {
        return '–';
    }
    if (!array_key_exists('receipt_present', $item) || $item['receipt_present'] === null) {
        return 'Offen';
    }
    return (int) $item['receipt_present'] === 1 ? 'Ja' : 'Nein';
}

function render_web_items_and_qr(array $doc, ?string $qr, bool $showQr = true, bool $manageReceiptStatus = false): void
{
    ?>
    <div class="detail-grid<?=$showQr?'':' no-qr'?>">
      <div class="table-wrap"><table><thead><tr><th>Datum</th><th>Typ</th><th>Beschreibung</th><?php if($manageReceiptStatus):?><th>Beleg vorhanden</th><?php endif;?><th class="right">Kilometer</th><th class="right">Betrag</th></tr></thead><tbody>
      <?php foreach($doc['items'] as $item):?><tr><td><?=h(date('d.m.Y',strtotime($item['item_date'])))?></td><td><?=$item['item_type']==='km'?'Kilometer':'Beleg'?></td><td><?=h($item['description'])?></td><?php if($manageReceiptStatus):?><td><?php if($item['item_type']==='receipt'):$receiptOpen=$item['receipt_present']===null;$receiptYes=(int)$item['receipt_present']===1&&!$receiptOpen;$receiptNo=(int)$item['receipt_present']===0&&!$receiptOpen;?><form method="post" class="receipt-control"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="admin_receipt_status"><input type="hidden" name="item_id" value="<?=(int)$item['id']?>"><button class="small <?=$receiptYes?'':'secondary'?>" name="receipt_present" value="1">Ja</button><button class="small <?=$receiptNo?'danger':'secondary'?>" name="receipt_present" value="0">Nein</button><?php if($receiptOpen):?><span class="badge warn">offen</span><?php endif;?></form><?php else:?>–<?php endif;?></td><?php endif;?><td class="right"><?=$item['item_type']==='km'?h(format_decimal((float)$item['kilometers'])).' km':'–'?></td><td class="right"><?=h(format_eur((float)$item['amount']))?></td></tr><?php endforeach;?>
      </tbody></table></div>
      <?php if($showQr):?><aside class="qr-panel"><h3>Überweisungs-QR-Code</h3><?php if($qr):?><img src="<?=h($qr)?>" alt="EPC-QR-Code für <?=h($doc['doc_id'])?>"><p class="muted">Mit einer Banking-App scannen.</p><?php else:?><p class="muted">QR-Code nicht verfügbar. Bitte prüfen, ob <code>qrencode</code> installiert ist.</p><?php endif;?></aside><?php endif;?>
    </div>
    <?php
}

function render_form_item_row(array $item): void
{
    $type = ($item['type'] ?? 'receipt') === 'km' ? 'km' : 'receipt';
    ?>
    <tr>
      <td><select name="item_type[]" class="type"><option value="receipt" <?=$type==='receipt'?'selected':''?>>Beleg</option><option value="km" <?=$type==='km'?'selected':''?>>Kilometer</option></select></td>
      <td><input type="date" name="item_date[]" value="<?=h($item['date']??'')?>"></td>
      <td><input name="item_description[]" value="<?=h($item['description']??'')?>"></td>
      <td><input name="item_kilometers[]" class="kilometers" value="<?=h($item['kilometers']??'')?>" <?=$type==='receipt'?'readonly':''?>></td>
      <td><input name="item_amount[]" class="amount" value="<?=h($item['amount']??'')?>" <?=$type==='km'?'readonly':''?>></td>
      <td><button type="button" class="secondary small remove" aria-label="Posten entfernen">×</button></td>
    </tr>
    <?php
}

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === '--migrate') {
    db();
    echo json_encode(['ok'=>true,'message'=>'Auslagen-Datenbank initialisiert oder additiv migriert.','schema_version'=>3], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

start_shared_session();
try {
    $user = current_user();
    if (!$user) {
        render_gate('Anmeldung erforderlich', 'Bitte zuerst über kienzlezeit anmelden und anschließend die Auslagenverwaltung öffnen.', 401);
    }
    if (kz_setting('expenses_enabled', '0') !== '1') {
        render_gate('Auslagenverwaltung deaktiviert', 'Die Auslagenverwaltung ist in den kienzlezeit-Einstellungen derzeit nicht freigeschaltet.', 403);
    }
    db();
} catch (Throwable $exception) {
    render_gate('Auslagenverwaltung nicht verfügbar', $exception->getMessage(), 503);
}

$flash = '';
$error = '';
$action = (string) ($_POST['action'] ?? '');
try {
    if ($action !== '') {
        require_csrf();
    }
    if ($action === 'account_save') {
        save_account($_POST);
        $_SESSION['expense_flash'] = 'Erstattungskonto wurde historisiert gespeichert.';
        redirect('auslagen.php');
    }
    if ($action === 'create_reimbursement') {
        $docId = save_reimbursement($_POST);
        $_SESSION['expense_flash'] = 'Auslage wurde eingereicht. Bitte Originalbelege beifügen und den Ausdruck unterschreiben.';
        redirect('auslagen.php?view=' . rawurlencode($docId));
    }
    if ($action === 'admin_save_settings') {
        require_admin();
        $rate = parse_decimal($_POST['km_rate'] ?? '');
        $practice = trim((string) ($_POST['practice_name'] ?? '')) ?: 'Praxis';
        $prefix = trim((string) ($_POST['transfer_prefix'] ?? '')) ?: 'Erstattung von Auslage Praxis';
        $year = (int) ($_POST['counter_year'] ?? date('Y'));
        $next = (int) ($_POST['next_counter'] ?? 1);
        if ($rate < 0 || $year < 2000 || $year > 2100 || $next < 1) {
            throw new RuntimeException('Kilometersatz oder Belegzähler ist ungültig.');
        }
        set_setting('km_rate', number_format($rate, 2, '.', ''));
        set_setting('practice_name', $practice);
        set_setting('transfer_prefix', $prefix);
        set_setting('counter_year', (string) $year);
        set_setting('next_counter', (string) $next);
        audit('UPDATE', 'settings', 'general', 'Kilometersatz und Belegzähler');
        $_SESSION['expense_flash'] = 'Auslagen-Einstellungen wurden gespeichert.';
        redirect('auslagen.php?admin=1');
    }
    if ($action === 'admin_status') {
        set_reimbursement_status((int) ($_POST['id'] ?? 0), (string) ($_POST['new_status'] ?? ''), trim((string) ($_POST['note'] ?? '')));
        $_SESSION['expense_flash'] = 'Status wurde nachvollziehbar aktualisiert.';
        redirect('auslagen.php?admin=1');
    }
    if ($action === 'admin_receipt_status') {
        $docId = set_item_receipt_status((int) ($_POST['item_id'] ?? 0), (int) ($_POST['receipt_present'] ?? -1));
        $_SESSION['expense_flash'] = 'Buchhalterischer Belegstatus wurde gespeichert.';
        redirect('auslagen.php?admin=1&view=' . rawurlencode($docId));
    }
    if ($action === 'admin_assign_legacy') {
        assign_legacy((int) ($_POST['id'] ?? 0), (int) ($_POST['employee_id'] ?? 0));
        $_SESSION['expense_flash'] = 'Altbeleg wurde dem Mitarbeiter zugeordnet.';
        redirect('auslagen.php?admin=1');
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

if (isset($_SESSION['expense_flash'])) {
    $flash = (string) $_SESSION['expense_flash'];
    unset($_SESSION['expense_flash']);
}

$docView = null;
if (!empty($_GET['view'])) {
    $docView = find_reimbursement((string) $_GET['view']);
    if (!$docView) {
        $error = 'Der Beleg wurde nicht gefunden oder gehört nicht zu diesem Konto.';
    }
}
$printMode = false;
if (!empty($_GET['print'])) {
    $docView = find_reimbursement((string) $_GET['print']);
    if (!$docView) {
        $error = 'Die Druckansicht ist für diesen Beleg nicht verfügbar.';
    } else {
        $printMode = true;
    }
}

$rows = reimbursement_rows();
$account = $user['role'] === 'EMPLOYEE' ? current_account((int) $user['id']) : null;
$kmRate = (float) (get_setting('km_rate', '0.30') ?? '0.30');
$practiceName = (string) (get_setting('practice_name', 'Praxis') ?? 'Praxis');
$counterYear = (string) (get_setting('counter_year', date('Y')) ?? date('Y'));
$nextCounter = (string) (get_setting('next_counter', '1') ?? '1');
$transferPrefix = (string) (get_setting('transfer_prefix', 'Erstattung von Auslage Praxis') ?? 'Erstattung von Auslage Praxis');
$employees = [];
$accounts = [];
if (is_admin()) {
    $employees = kz_db()->query('SELECT id,name,full_name,active FROM employees ORDER BY active DESC,name')->fetchAll();
    $accounts = db()->query('SELECT * FROM reimbursement_accounts WHERE active=1 AND valid_until IS NULL ORDER BY employee_id')->fetchAll();
}

$formData = [
    'subject' => (string) ($_POST['subject'] ?? ''),
    'note' => (string) ($_POST['note'] ?? ''),
    'item_type' => is_array($_POST['item_type'] ?? null) ? array_values($_POST['item_type']) : [],
    'item_date' => is_array($_POST['item_date'] ?? null) ? array_values($_POST['item_date']) : [],
    'item_description' => is_array($_POST['item_description'] ?? null) ? array_values($_POST['item_description']) : [],
    'item_kilometers' => is_array($_POST['item_kilometers'] ?? null) ? array_values($_POST['item_kilometers']) : [],
    'item_amount' => is_array($_POST['item_amount'] ?? null) ? array_values($_POST['item_amount']) : [],
];
$formItems = [];
$formCount = max(count($formData['item_type']), count($formData['item_date']), count($formData['item_description']), count($formData['item_kilometers']), count($formData['item_amount']));
for ($index = 0; $index < $formCount; $index++) {
    $formItems[] = ['type'=>$formData['item_type'][$index]??'receipt','date'=>$formData['item_date'][$index]??'','description'=>$formData['item_description'][$index]??'','kilometers'=>$formData['item_kilometers'][$index]??'','amount'=>$formData['item_amount'][$index]??''];
}
if ($formItems === []) {
    $formItems[] = ['type'=>'receipt','date'=>'','description'=>'','kilometers'=>'','amount'=>''];
}

if ($printMode && $docView):
    $qr = qr_data_uri(build_epc_payload($docView));
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><title><?=h($docView['doc_id'])?></title><style>@page{size:A4;margin:14mm}body{font:12px/1.4 Arial,sans-serif;color:#111}h1{font-size:22px;margin:0 0 4px}.meta{color:#555;margin-bottom:18px}.boxes{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:15px 0}.box{border:1px solid #bbb;padding:8px}.box b{display:block;font-size:10px;text-transform:uppercase;color:#666}table{width:100%;border-collapse:collapse;margin:15px 0}th,td{border:1px solid #aaa;padding:6px;text-align:left}th{background:#eee}.right{text-align:right}.summary{margin-left:auto;width:280px}.sign{display:grid;grid-template-columns:1fr 1fr;gap:45px;margin-top:50px}.line{border-top:1px solid #333;padding-top:4px}.qr{width:135px;height:135px}.footer{position:fixed;bottom:0;left:0;right:0;text-align:center;color:#666;font-size:9px}@media print{.no-print{display:none}}</style></head><body>
<button class="no-print" onclick="window.print()">Drucken</button><h1><?=h($practiceName)?> · Auslagenerstattung</h1><div class="meta">Beleg <?=h($docView['doc_id'])?> · erstellt <?=h(date('d.m.Y H:i'))?> Uhr · Status <?=h(status_label((string)$docView['status']))?></div>
<div class="boxes"><div class="box"><b>Betreff</b><?=h($docView['subject'])?></div><div class="box"><b>Mitarbeiter/in</b><?=h($docView['claimant_name'])?></div><div class="box"><b>Erstattungskonto</b><?=h(format_iban($docView['iban']))?><?=trim((string)$docView['bic'])!==''?'<br>'.h($docView['bic']):''?></div><div class="box"><b>Überweisungszweck</b><?=h($docView['transfer_purpose'])?></div></div>
<table><thead><tr><th>Datum</th><th>Typ</th><th>Beschreibung</th><?php if(is_admin()):?><th>Beleg vorhanden</th><?php endif;?><th class="right">Kilometer</th><th class="right">Betrag</th></tr></thead><tbody><?php foreach($docView['items'] as $item):?><tr><td><?=h(date('d.m.Y',strtotime($item['item_date'])))?></td><td><?=$item['item_type']==='km'?'Kilometer':'Beleg'?></td><td><?=h($item['description'])?></td><?php if(is_admin()):?><td><?=h(receipt_presence_label($item))?></td><?php endif;?><td class="right"><?=$item['item_type']==='km'?h(format_decimal((float)$item['kilometers'])).' km':'–'?></td><td class="right"><?=h(format_eur((float)$item['amount']))?></td></tr><?php endforeach;?></tbody></table>
<table class="summary"><tr><th>Belege</th><td class="right"><?=h(format_eur((float)$docView['total_receipts']))?></td></tr><tr><th>Kilometer</th><td class="right"><?=h(format_decimal((float)$docView['total_km']))?> km × <?=h(format_eur((float)$docView['km_rate']))?></td></tr><tr><th>Gesamt</th><td class="right"><strong><?=h(format_eur((float)$docView['total_amount']))?></strong></td></tr></table>
<?php if(trim((string)$docView['note'])!==''):?><div class="box"><b>Hinweis</b><?=nl2br(h($docView['note']))?></div><?php endif;?><div style="margin-top:18px"><?php if($qr):?><img class="qr" src="<?=h($qr)?>" alt="EPC-QR-Code"><?php endif;?></div><div class="sign"><div class="line">Datum, Unterschrift Mitarbeiter/in</div><div class="line">Geprüft / freigegeben</div></div><div class="footer"><?=h(APP_TITLE)?> · v<?=h(APP_VERSION)?> · <?=h(APP_AUTHOR)?> · Erstellt <?=h(date('d.m.Y H:i'))?> Uhr</div></body></html>
<?php exit; endif; $docQr=$docView&&is_admin()?qr_data_uri(build_epc_payload($docView)):null; ?>
<!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title><?=h(APP_TITLE)?></title>
<style>
:root{--green:#30d20f;--green-dark:#178f08;--ink:#151b17;--muted:#647067;--line:#dce5dd;--bg:#f4f7f4;--card:#fff;--danger:#c62828;--warn:#a65c00;--blue:#1e5faa;--shadow:0 12px 34px rgba(22,50,25,.09)}*{box-sizing:border-box}html{height:100%}body{min-height:100vh;margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;display:flex;flex-direction:column}a{color:var(--green-dark);text-decoration:none}.top{background:#fff;border-bottom:1px solid var(--line);padding:10px clamp(16px,4vw,48px);display:flex;align-items:center;gap:20px}.logo{width:min(280px,48vw);height:64px;object-fit:contain;object-position:left center}.top-meta{margin-left:auto;text-align:right}.top-meta strong,.top-meta span{display:block}.top-meta span{color:var(--muted);font-size:13px}.nav{background:#172018;padding:0 clamp(16px,4vw,48px);display:flex;gap:3px;overflow:auto}.nav a{color:#e9f4e9;padding:11px 13px;white-space:nowrap}.nav a:hover{background:#243027}main{width:min(1220px,calc(100% - 28px));margin:24px auto 40px;flex:1}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:18px}.col-9{grid-column:span 9}.col-8{grid-column:span 8}.col-7{grid-column:span 7}.col-6{grid-column:span 6}.col-5{grid-column:span 5}.col-4{grid-column:span 4}.col-3{grid-column:span 3}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:var(--shadow)}.card+.card{margin-top:18px}.card.accent{border-top:4px solid var(--green)}h1{font-size:clamp(25px,4vw,38px);margin:0 0 18px}h2{font-size:21px;margin:0 0 14px}h3{font-size:16px;margin:0 0 9px}.muted{color:var(--muted)}.field{margin-bottom:14px}label{display:block;font-weight:700;margin-bottom:5px}input,select,textarea{width:100%;border:1px solid #bdc9bf;border-radius:9px;padding:10px 11px;background:#fff;font:inherit}textarea{min-height:78px}.inline{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.inline>*{flex:1}.inline .auto{flex:0 0 auto}button,.button{border:0;border-radius:9px;background:var(--green-dark);color:#fff;padding:10px 15px;font:700 14px/1.2 inherit;cursor:pointer;display:inline-block;text-decoration:none}.secondary{background:#e7eee8!important;color:var(--ink)!important}.danger{background:var(--danger)!important}.small{padding:7px 10px!important;font-size:12px!important}.flash{padding:13px 15px;border-radius:10px;margin-bottom:16px;border:1px solid}.success{background:#e6f9e1;border-color:#9cdd8d}.error{background:#ffebeb;border-color:#efaaaa;color:#8c1717}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:12px}table{width:100%;border-collapse:collapse}th,td{text-align:left;border-bottom:1px solid var(--line);padding:10px 8px;vertical-align:top}th{font-size:12px;text-transform:uppercase;color:#526057;background:#f9fbf9}.right{text-align:right}.nowrap{white-space:nowrap}.badge{display:inline-block;border-radius:999px;padding:3px 8px;font-size:12px;font-weight:800;background:#edf0ee}.badge.SUBMITTED{background:#fff0d8;color:#83500b}.badge.APPROVED{background:#dfeeff;color:#174d8c}.badge.PAID{background:#dcf8d7;color:#176a0d}.badge.REJECTED,.badge.CANCELLED{background:#fde4e4;color:#9b1c1c}.doc-boxes{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}.doc-box{border:1px solid var(--line);border-radius:10px;padding:10px}.doc-box .k{font-size:11px;text-transform:uppercase;color:var(--muted)}.doc-box .v{font-weight:700;margin-top:3px}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:18px}.summary>div{background:#f7faf7;border:1px solid var(--line);border-radius:10px;padding:11px}.summary b{display:block;font-size:18px}.items-table input,.items-table select{min-width:100px}.receipt-control{display:flex;gap:6px;align-items:center;white-space:nowrap}.detail-grid{display:grid;grid-template-columns:minmax(0,1fr) 220px;gap:18px;margin-top:18px}.detail-grid.no-qr{grid-template-columns:1fr}.qr-panel{border:1px solid var(--line);border-radius:12px;padding:14px;text-align:center;background:#f9fbf9}.qr-panel img{display:block;width:180px;height:180px;max-width:100%;margin:0 auto 8px}details{border:1px solid var(--line);border-radius:10px;padding:10px 12px;background:#fff}summary{font-weight:700;cursor:pointer}footer{border-top:1px solid var(--line);background:#fff;padding:12px;text-align:center;color:var(--muted);font-size:12px;margin-top:auto}@media(max-width:850px){.col-9,.col-8,.col-7,.col-6,.col-5,.col-4,.col-3{grid-column:span 12}.top-meta{display:none}.summary{grid-template-columns:1fr 1fr}.detail-grid{grid-template-columns:1fr}.card{padding:16px}.logo{height:52px}}@media print{.top,.nav,footer,.no-print{display:none!important}main{width:100%;margin:0}.card{box-shadow:none}}
.badge.good{background:#dcf8d7;color:#176a0d}.badge.bad{background:#fde4e4;color:#9b1c1c}.badge.warn{background:#fff0d8;color:#83500b}.badge.muted{background:#edf0ee;color:#526057}
</style></head><body>
<header class="top"><a href="<?=h(kz_web_path())?>"><img class="logo" src="<?=h(logo_web_path())?>" alt="kienzlezeit"></a><div class="top-meta"><strong><?=is_admin()?'Administration':'Mitarbeiterbereich'?></strong><span><?=h($user['name'])?></span></div><form method="post" action="<?=h(kz_web_path())?>" class="no-print"><input type="hidden" name="csrf" value="<?=h(logout_csrf())?>"><input type="hidden" name="action" value="logout"><button class="secondary">Abmelden</button></form></header>
<nav class="nav"><a href="<?=h(kz_web_path())?>?<?=is_admin()?'page=admin-dashboard':'page=employee-dashboard'?>">kienzlezeit</a><a href="auslagen.php<?=is_admin()?'?admin=1':''?>">Auslagen</a><?php if(is_admin()):?><a href="<?=h(kz_web_path())?>?page=admin-settings">Einstellungen</a><?php endif;?></nav>
<main><?php if($flash!==''):?><div class="flash success"><?=h($flash)?></div><?php endif;?><?php if($error!==''):?><div class="flash error"><?=h($error)?></div><?php endif;?>
<?php if(is_admin()):?>
<div class="inline"><div><h1>Auslagenverwaltung</h1><p class="muted">Alle Vorgänge, Konten und Statusänderungen bleiben nachvollziehbar erhalten.</p></div><span class="badge auto"><?=count(array_filter($rows,fn($row)=>$row['status']==='SUBMITTED'))?> offen</span></div>
<div class="grid"><section class="card col-8"><h2>Vorgänge</h2><div class="table-wrap"><table><thead><tr><th>Beleg</th><th>Mitarbeiter</th><th>Betreff</th><th>Status</th><th>Belege</th><th class="right">Summe</th><th>Aktion</th></tr></thead><tbody><?php foreach($rows as $row):$receiptOverview=receipt_overview($row);?><tr><td class="nowrap"><a href="?admin=1&amp;view=<?=rawurlencode($row['doc_id'])?>"><strong><?=h($row['doc_id'])?></strong></a><br><small><?=h(date('d.m.Y',strtotime($row['created_at'])))?></small></td><td><?=h($row['claimant_name'])?><?php if($row['employee_id']===null):?><br><span class="badge">nicht zugeordnet</span><?php endif;?></td><td><?=h($row['subject'])?></td><td><span class="badge <?=h($row['status'])?>"><?=h(status_label((string)$row['status']))?></span></td><td><span class="badge <?=h($receiptOverview['class'])?>"><?=h($receiptOverview['label'])?></span></td><td class="right nowrap"><?=h(format_eur((float)$row['total_amount']))?></td><td><?php if($row['status']==='SUBMITTED'||$row['status']==='APPROVED'):?><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="admin_status"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><input name="note" placeholder="Hinweis / Begründung"><div class="inline"><?php if($row['status']==='SUBMITTED'):?><button class="small auto" name="new_status" value="APPROVED">Genehmigen</button><button class="small danger auto" name="new_status" value="REJECTED">Ablehnen</button><?php else:?><button class="small auto" name="new_status" value="PAID">Als bezahlt</button><?php endif;?><button class="small secondary auto" name="new_status" value="CANCELLED">Stornieren</button></div></form><?php elseif($row['status']==='LEGACY'&&$row['employee_id']===null):?><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="admin_assign_legacy"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><select name="employee_id"><?php foreach($employees as $employee):?><option value="<?=(int)$employee['id']?>"><?=h(trim((string)($employee['full_name']??''))?:$employee['name'])?></option><?php endforeach;?></select><button class="small auto">Zuordnen</button></form><?php else:?>–<?php endif;?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="7" class="muted">Noch keine Vorgänge vorhanden.</td></tr><?php endif;?></tbody></table></div></section>
<aside class="col-4"><section class="card accent"><h2>Einstellungen</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="admin_save_settings"><div class="field"><label>Kilometersatz in €</label><input name="km_rate" value="<?=h(number_format($kmRate,2,',',''))?>"></div><div class="field"><label>Praxisname</label><input name="practice_name" value="<?=h($practiceName)?>"></div><div class="field"><label>Überweisungspräfix</label><input name="transfer_prefix" value="<?=h($transferPrefix)?>"></div><div class="inline"><div class="field"><label>Zählerjahr</label><input type="number" name="counter_year" value="<?=h($counterYear)?>"></div><div class="field"><label>Nächste Nummer</label><input type="number" min="1" name="next_counter" value="<?=h($nextCounter)?>"></div></div><button>Speichern</button></form></section>
<section class="card"><h2>Aktuelle Erstattungskonten</h2><?php foreach($accounts as $entry):$employeeName='Mitarbeiter #'.$entry['employee_id'];foreach($employees as $employee)if((int)$employee['id']===(int)$entry['employee_id'])$employeeName=trim((string)($employee['full_name']??''))?:$employee['name'];?><div class="doc-box" style="margin-bottom:8px"><div class="k"><?=h($employeeName)?></div><div class="v"><?=h($entry['account_holder'])?><br><?=h(format_iban($entry['iban']))?><?=trim($entry['bic'])!==''?'<br>'.h($entry['bic']):''?></div></div><?php endforeach;?><?php if(!$accounts):?><p class="muted">Noch keine Konten hinterlegt.</p><?php endif;?></section></aside></div>
<?php if($docView):?><section class="card" style="margin-top:18px"><div class="inline"><h2><?=h($docView['doc_id'])?></h2><a class="button secondary auto" href="?admin=1&amp;print=<?=rawurlencode($docView['doc_id'])?>" target="_blank">Druckansicht</a></div><div class="doc-boxes"><div class="doc-box"><div class="k">Mitarbeiter</div><div class="v"><?=h($docView['claimant_name'])?></div></div><div class="doc-box"><div class="k">Erstattungskonto (Schnappschuss)</div><div class="v"><?=h(format_iban($docView['iban']))?></div></div><div class="doc-box"><div class="k">Status</div><div class="v"><?=h(status_label((string)$docView['status']))?></div></div><div class="doc-box"><div class="k">Gesamt</div><div class="v"><?=h(format_eur((float)$docView['total_amount']))?></div></div></div><?php render_web_items_and_qr($docView,$docQr,true,true);?></section><?php endif;?>
<?php else:?>
<div class="inline"><div><h1>Meine Auslagen</h1><p class="muted">Belege und Kilometer einreichen, Status verfolgen und eigene Vorgänge ausdrucken.</p></div><span class="badge auto">Kilometersatz <?=h(format_eur($kmRate))?></span></div>
<div class="grid"><section class="<?=$docView?'col-9':'col-8'?>"><?php if($docView):?><div class="card"><div class="inline"><h2><?=h($docView['doc_id'])?></h2><span class="badge <?=h($docView['status'])?> auto"><?=h(status_label((string)$docView['status']))?></span></div><div class="doc-boxes"><div class="doc-box"><div class="k">Betreff</div><div class="v"><?=h($docView['subject'])?></div></div><div class="doc-box"><div class="k">Gesamt</div><div class="v"><?=h(format_eur((float)$docView['total_amount']))?></div></div><div class="doc-box"><div class="k">Erstattungskonto beim Einreichen</div><div class="v"><?=h(format_iban($docView['iban']))?></div></div></div><?php render_web_items_and_qr($docView,null,false);?><div class="inline" style="margin-top:15px"><a class="button auto" href="?print=<?=rawurlencode($docView['doc_id'])?>" target="_blank">Druckansicht</a><a class="button secondary auto" href="auslagen.php">Neue Auslage</a></div></div><?php elseif(!$account):?><div class="card accent"><h2>Erstattungskonto erforderlich</h2><p>Bitte rechts zunächst das Konto hinterlegen, auf das Auslagen erstattet werden sollen.</p></div><?php else:?><div class="card"><h2>Neue Auslage / Kilometerabrechnung</h2><p class="muted">Erstattung an <?=h($account['account_holder'])?> · IBAN endet auf …<?=h(iban_last4($account['iban']))?></p><form method="post" id="reimbursement-form"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="create_reimbursement"><div class="field"><label>Betreff</label><input name="subject" value="<?=h($formData['subject'])?>" placeholder="z. B. Hausbesuch / Material" required></div><div class="field"><label>Notiz (optional)</label><textarea name="note"><?=h($formData['note'])?></textarea></div><div class="inline"><strong>Posten</strong><span class="auto"><button type="button" class="secondary" id="add-receipt">+ Beleg</button> <button type="button" class="secondary" id="add-km">+ Kilometer</button></span></div><div class="table-wrap" style="margin-top:10px"><table class="items-table"><thead><tr><th>Typ</th><th>Datum</th><th>Beschreibung</th><th>km</th><th>Betrag</th><th></th></tr></thead><tbody id="items-body"><?php foreach($formItems as $item)render_form_item_row($item);?></tbody></table></div><div class="summary"><div><span>Belege</span><b id="sum-receipts">0,00 €</b></div><div><span>Kilometer</span><b id="sum-km">0,00 km</b></div><div><span>Satz</span><b><?=h(format_eur($kmRate))?></b></div><div><span>Gesamt</span><b id="sum-total">0,00 €</b></div></div><button style="margin-top:18px">Verbindlich einreichen</button></form></div><?php endif;?></section>
<aside class="<?=$docView?'col-3':'col-4'?>"><section class="card accent"><h2>Mein Erstattungskonto</h2><?php if($account):?><p><strong><?=h($account['account_holder'])?></strong><br><?=h(format_iban($account['iban']))?><?=trim($account['bic'])!==''?'<br>'.h($account['bic']):''?></p><details><summary>Konto ändern</summary><?php endif;?><form method="post" style="margin-top:12px"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="account_save"><div class="field"><label>Kontoinhaber</label><input name="account_holder" value="<?=h($account['account_holder']??$user['name'])?>" required></div><div class="field"><label>IBAN</label><input name="iban" value="<?=h(isset($account['iban'])?format_iban($account['iban']):'')?>" required></div><div class="field"><label>BIC (optional)</label><input name="bic" value="<?=h($account['bic']??'')?>"></div><?php if($account):?><div class="field"><label>Begründung der Änderung</label><input name="change_reason" required></div><?php endif;?><button><?= $account?'Geändertes Konto speichern':'Konto speichern' ?></button></form><?php if($account):?></details><?php endif;?><p class="muted">Kontoänderungen gelten nur für neue Vorgänge. Frühere Belege behalten ihren Kontoschnappschuss.</p></section>
<section class="card"><h2>Meine Vorgänge</h2><div class="table-wrap"><table><thead><tr><th>Beleg</th><th>Status</th><th class="right">Summe</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><a href="?view=<?=rawurlencode($row['doc_id'])?>"><strong><?=h($row['doc_id'])?></strong></a><br><small><?=h($row['subject'])?></small></td><td><span class="badge <?=h($row['status'])?>"><?=h(status_label((string)$row['status']))?></span></td><td class="right nowrap"><?=h(format_eur((float)$row['total_amount']))?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="3" class="muted">Noch keine Auslagen eingereicht.</td></tr><?php endif;?></tbody></table></div></section></aside></div>
<?php endif;?></main><footer><?=h(APP_TITLE)?> · v<?=h(APP_VERSION)?> · <?=h(APP_AUTHOR)?></footer>
<script>
const KM_RATE=<?=json_encode(number_format($kmRate,2,'.',''))?>,itemsBody=document.getElementById('items-body');
function numberValue(value){let v=String(value||'').trim().replace(/\s+/g,'');if(v.includes(',')&&v.includes('.'))v=v.lastIndexOf(',')>v.lastIndexOf('.')?v.replace(/\./g,'').replace(',','.'):v.replace(/,/g,'');else if(v.includes(','))v=v.replace(/\./g,'').replace(',','.');let n=parseFloat(v);return Number.isFinite(n)?n:0}function euro(v){return v.toLocaleString('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2})+' €'}function km(v){return v.toLocaleString('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2})+' km'}function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
function addRow(type,date='',description='',kilometers='',amount=''){if(!itemsBody)return;const tr=document.createElement('tr');tr.innerHTML=`<td><select name="item_type[]" class="type"><option value="receipt"${type==='receipt'?' selected':''}>Beleg</option><option value="km"${type==='km'?' selected':''}>Kilometer</option></select></td><td><input type="date" name="item_date[]" value="${esc(date)}"></td><td><input name="item_description[]" value="${esc(description)}"></td><td><input name="item_kilometers[]" class="kilometers" value="${esc(kilometers)}"></td><td><input name="item_amount[]" class="amount" value="${esc(amount)}"></td><td><button type="button" class="secondary small remove" aria-label="Posten entfernen">×</button></td>`;itemsBody.appendChild(tr);bind(tr)}
function bind(tr){const type=tr.querySelector('.type'),k=tr.querySelector('.kilometers'),a=tr.querySelector('.amount');function sync(){const isKm=type.value==='km';k.readOnly=!isKm;a.readOnly=isKm;if(!isKm)k.value='';recalc()}type.addEventListener('change',sync);k.addEventListener('input',recalc);a.addEventListener('input',recalc);tr.querySelector('.remove').addEventListener('click',()=>{tr.remove();recalc()});sync()}
function recalc(){if(!itemsBody)return;let kilometers=0,receipts=0,total=0;itemsBody.querySelectorAll('tr').forEach(tr=>{let isKm=tr.querySelector('.type').value==='km',k=numberValue(tr.querySelector('.kilometers').value),a=numberValue(tr.querySelector('.amount').value);if(isKm){a=Math.round(k*parseFloat(KM_RATE)*100)/100;tr.querySelector('.amount').value=k>0?a.toLocaleString('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2}):'';kilometers+=k}else receipts+=a;total+=a});document.getElementById('sum-km').textContent=km(kilometers);document.getElementById('sum-receipts').textContent=euro(receipts);document.getElementById('sum-total').textContent=euro(total)}
document.getElementById('add-receipt')?.addEventListener('click',()=>addRow('receipt'));document.getElementById('add-km')?.addEventListener('click',()=>addRow('km'));if(itemsBody)itemsBody.querySelectorAll('tr').forEach(bind)
</script></body></html>
