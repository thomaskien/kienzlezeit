<?php
/**
 * rfid-scan.php
 * Schlanker, idempotenter RFID-Endpunkt fuer kienzlezeit.
 *
 * Version: 1.2
 * Author: Dr. Thomas Kienzle
 * Stand: 2026-07-06
 *
 * Changelog (komplett):
 * - 1.2 (2026-07-06):
 *   - Administrativ gesetzte Tagesarbeitszeiten schliessen offene Altbuchungen auch am Terminal ab.
 *   - Die naechste Buchung nach einem solchen Tagesabschluss wird wieder als Kommen erfasst.
 *
 * - 1.1 (2026-07-04):
 *   - Archivierte Terminals werden am RFID-Endpunkt sicher abgewiesen.
 *
 * - 1.0 (2026-07-02):
 *   - Erste SQLite-Version fuer Kommen/Gehen, Kartenregistrierung und Web-Login.
 *   - Dauerhafte Idempotenz ueber Terminal und event_id.
 *   - Testkarten mit frei konfigurierbarer Displayantwort.
 *   - Doppelauflege-Schutz und Schutz vor offenen Altbuchungen.
 */

declare(strict_types=1);

define('KZ_LIBRARY_ONLY', true);
require_once __DIR__ . '/kienzlezeit.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function api_respond(int $status, array|string $response): never
{
    http_response_code($status);
    echo is_string($response) ? $response : kz_json($response);
    exit;
}

function api_error(int $status, string $title, string $line1, string $line2 = ''): never
{
    api_respond($status, ['ok' => false, 'title' => $title, 'line1' => $line1, 'line2' => $line2]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    api_error(405, 'Fehler', 'Nur POST erlaubt');
}

try {
    $db = kz_db();
} catch (Throwable $exception) {
    error_log('[kienzlezeit] Datenbank: ' . $exception->getMessage());
    api_error(500, 'Serverfehler', 'Datenbank fehlt', 'Installer starten');
}

$providedKey = (string) ($_SERVER['HTTP_X_TERMINAL_KEY'] ?? '');
$rawBody = PHP_SAPI === 'cli'
    ? stream_get_contents(STDIN)
    : file_get_contents('php://input');
$data = json_decode($rawBody ?: '', true);
if (!is_array($data)) {
    api_error(400, 'Fehler', 'JSON ungueltig');
}

$terminalCode = trim((string) ($data['terminal'] ?? ''));
$uidInput = (string) ($data['uid'] ?? '');
$eventId = trim((string) ($data['event_id'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $terminalCode)) {
    api_error(400, 'Fehler', 'Terminal-ID ungueltig');
}
if (!preg_match('/^[A-Za-z0-9_-]{12,96}$/', $eventId)) {
    api_error(400, 'Fehler', 'event_id ungueltig');
}
try {
    $uid = kz_canonical_uid($uidInput);
} catch (Throwable) {
    api_error(400, 'Fehler', 'Karten-ID ungueltig');
}

$statement = $db->prepare('SELECT * FROM terminals WHERE terminal_code=? AND active=1 AND archived_at IS NULL');
$statement->execute([$terminalCode]);
$terminal = $statement->fetch();
if (!$terminal || $providedKey === '' || !hash_equals((string) $terminal['key_hash'], hash('sha256', $providedKey))) {
    api_error(401, 'Terminal abgelehnt', 'Terminal-Key pruefen');
}

$requestHash = hash('sha256', $rawBody ?: '');
$now = kz_now_utc();
$today = kz_today();

try {
    $db->exec('BEGIN IMMEDIATE');
    kz_expire_terminal_actions($db);

    $statement = $db->prepare('SELECT * FROM event_requests WHERE terminal_id=? AND event_id=?');
    $statement->execute([(int) $terminal['id'], $eventId]);
    $cached = $statement->fetch();
    if ($cached) {
        if (!hash_equals((string) $cached['request_hash'], $requestHash)) {
            $db->exec('ROLLBACK');
            api_error(409, 'Protokollfehler', 'event_id mehrfach', 'Inhalt verschieden');
        }
        $db->exec('COMMIT');
        api_respond((int) $cached['http_status'], (string) $cached['response_body']);
    }

    $db->prepare('UPDATE terminals SET last_seen_at=?,updated_at=? WHERE id=?')->execute([$now, $now, (int) $terminal['id']]);
    $statement = $db->prepare("SELECT * FROM terminal_actions WHERE terminal_id=? AND status='PENDING' AND expires_at>=? ORDER BY id DESC LIMIT 1");
    $statement->execute([(int) $terminal['id'], $now]);
    $terminalAction = $statement->fetch() ?: null;

    $statement = $db->prepare('SELECT c.*,tp.response_ok,tp.title,tp.line1,tp.line2 FROM rfid_cards c LEFT JOIN test_card_profiles tp ON tp.card_id=c.id WHERE c.uid_canonical=?');
    $statement->execute([$uid]);
    $card = $statement->fetch() ?: null;
    $response = null;
    $actionType = 'TIME_BOOKING';
    $pendingTimeEvent = null;

    if ($terminalAction && $terminalAction['action_type'] === 'REGISTER_CARD') {
        $actionType = 'REGISTER_CARD';
        $employeeId = (int) $terminalAction['employee_id'];
        $employeeStatement = $db->prepare('SELECT * FROM employees WHERE id=? AND active=1');
        $employeeStatement->execute([$employeeId]);
        $employee = $employeeStatement->fetch();
        if (!$employee) {
            $response = ['ok' => false, 'title' => 'Registrierung', 'line1' => 'Mitarbeiter inaktiv', 'line2' => 'Nicht gespeichert'];
        } elseif ($card && $card['card_type'] === 'TEST') {
            $response = ['ok' => false, 'title' => 'Registrierung', 'line1' => 'Ist eine Testkarte', 'line2' => 'Nicht gespeichert'];
        } else {
            if (!$card) {
                $db->prepare("INSERT INTO rfid_cards(uid_canonical,card_type,active,label,created_at,updated_at) VALUES(?,'EMPLOYEE',1,?,?,?)")->execute([$uid, 'Mitarbeiterkarte', $now, $now]);
                $cardId = (int) $db->lastInsertId();
            } else {
                $cardId = (int) $card['id'];
                $db->prepare("UPDATE rfid_cards SET active=1,card_type='EMPLOYEE',updated_at=? WHERE id=?")->execute([$now, $cardId]);
            }
            $current = $db->prepare('SELECT ca.*,e.name FROM rfid_card_assignments ca JOIN employees e ON e.id=ca.employee_id WHERE ca.card_id=? AND ca.valid_until IS NULL ORDER BY ca.id DESC LIMIT 1');
            $current->execute([$cardId]);
            $assignment = $current->fetch();
            if ($assignment && (int) $assignment['employee_id'] !== $employeeId) {
                $response = ['ok' => false, 'title' => 'Karte vergeben', 'line1' => (string) $assignment['name'], 'line2' => 'Admin pruefen'];
            } else {
                if (!$assignment) {
                    $db->prepare('INSERT INTO rfid_card_assignments(card_id,employee_id,valid_from,reason,created_by_admin_id,created_at) VALUES(?,?,?,?,?,?)')->execute([$cardId, $employeeId, $today, 'Registrierung am Terminal', $terminalAction['created_by_admin_id'], $now]);
                }
                $response = ['ok' => true, 'title' => 'Karte registriert', 'line1' => (string) $employee['name'], 'line2' => $uid];
            }
        }
        $db->prepare("UPDATE terminal_actions SET status='CONSUMED',consumed_at=? WHERE id=?")->execute([$now, (int) $terminalAction['id']]);
    } elseif ($terminalAction && $terminalAction['action_type'] === 'WEB_LOGIN') {
        $actionType = 'WEB_LOGIN';
        $employee = null;
        if ($card && $card['card_type'] === 'EMPLOYEE' && (bool) $card['active']) {
            $employeeStatement = $db->prepare('SELECT e.* FROM rfid_card_assignments ca JOIN employees e ON e.id=ca.employee_id WHERE ca.card_id=? AND ca.valid_from<=? AND (ca.valid_until IS NULL OR ca.valid_until>=?) AND e.active=1 ORDER BY ca.valid_from DESC,ca.id DESC LIMIT 1');
            $employeeStatement->execute([(int) $card['id'], $today, $today]);
            $employee = $employeeStatement->fetch() ?: null;
        }
        if ($employee) {
            $db->prepare("UPDATE login_challenges SET status='APPROVED',employee_id=?,approved_at=? WHERE id=? AND status='PENDING'")->execute([(int) $employee['id'], $now, (int) $terminalAction['login_challenge_id']]);
            $response = ['ok' => true, 'title' => 'Login erfolgreich', 'line1' => (string) $employee['name'], 'line2' => 'Browser freigegeben'];
        } else {
            $response = ['ok' => false, 'title' => 'Login abgelehnt', 'line1' => 'Karte unbekannt', 'line2' => 'Admin informieren'];
            $db->prepare("UPDATE login_challenges SET status='CANCELLED' WHERE id=? AND status='PENDING'")->execute([(int) $terminalAction['login_challenge_id']]);
        }
        $db->prepare("UPDATE terminal_actions SET status='CONSUMED',consumed_at=? WHERE id=?")->execute([$now, (int) $terminalAction['id']]);
    } elseif ($card && $card['card_type'] === 'TEST') {
        $actionType = 'TEST_CARD';
        if (!(bool) $card['active']) {
            $response = ['ok' => false, 'title' => 'Testkarte gesperrt', 'line1' => $uid, 'line2' => 'Admin informieren'];
        } else {
            $response = [
                'ok' => (bool) $card['response_ok'],
                'title' => (string) ($card['title'] ?: 'Testkarte'),
                'line1' => (string) ($card['line1'] ?: $uid),
                'line2' => (string) ($card['line2'] ?: 'Erkannt'),
            ];
        }
    } else {
        $employee = null;
        if ($card && $card['card_type'] === 'EMPLOYEE' && (bool) $card['active']) {
            $employeeStatement = $db->prepare('SELECT e.* FROM rfid_card_assignments ca JOIN employees e ON e.id=ca.employee_id WHERE ca.card_id=? AND ca.valid_from<=? AND (ca.valid_until IS NULL OR ca.valid_until>=?) AND e.active=1 ORDER BY ca.valid_from DESC,ca.id DESC LIMIT 1');
            $employeeStatement->execute([(int) $card['id'], $today, $today]);
            $employee = $employeeStatement->fetch() ?: null;
        }
        if (!$employee) {
            $response = ['ok' => false, 'title' => $card ? 'Karte gesperrt' : 'Unbekannte Karte', 'line1' => $uid, 'line2' => 'Bitte anmelden'];
        } else {
            $latest = kz_latest_effective_event((int) $employee['id']);
            $guard = (int) kz_setting('duplicate_guard_seconds', '30');
            $latestDate = null;
            $staleOpenResolved = false;
            if ($latest) {
                $lastUtc = new DateTimeImmutable((string) $latest['occurred_at'], new DateTimeZone('UTC'));
                $seconds = time() - $lastUtc->getTimestamp();
                $latestDate = kz_local_datetime((string) $latest['occurred_at'])->format('Y-m-d');
                if ($latest['event_type'] === 'COME' && $latestDate < $today) {
                    $overrideStatement = $db->prepare('SELECT 1 FROM day_time_overrides WHERE employee_id=? AND work_date=?');
                    $overrideStatement->execute([(int) $employee['id'], $latestDate]);
                    $staleOpenResolved = (bool) $overrideStatement->fetchColumn();
                }
            } else {
                $seconds = PHP_INT_MAX;
            }
            if ($latest && $seconds >= 0 && $seconds < $guard) {
                $response = ['ok' => true, 'title' => 'Bereits erfasst', 'line1' => (string) $employee['name'], 'line2' => $latest['event_type'] === 'COME' ? 'Kommen bleibt aktiv' : 'Gehen bleibt aktiv'];
            } elseif ($latest && $latest['event_type'] === 'COME' && $latestDate < $today && !$staleOpenResolved) {
                $response = ['ok' => false, 'title' => 'Buchung offen', 'line1' => (string) $employee['name'], 'line2' => 'Admin informieren'];
            } else {
                $eventType = $staleOpenResolved ? 'COME' : ($latest && $latest['event_type'] === 'COME' ? 'LEAVE' : 'COME');
                $localTime = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('H:i');
                $response = ['ok' => true, 'title' => (string) $employee['name'], 'line1' => ($eventType === 'COME' ? 'Kommen ' : 'Gehen ') . $localTime, 'line2' => 'Erfolgreich gebucht'];
                $pendingTimeEvent = ['employee_id' => (int) $employee['id'], 'card_id' => (int) $card['id'], 'event_type' => $eventType];
            }
        }
    }

    $body = kz_json($response);
    $statement = $db->prepare('INSERT INTO event_requests(terminal_id,event_id,uid_received,request_body,request_hash,received_at,http_status,response_body,action_type) VALUES(?,?,?,?,?,?,200,?,?)');
    $statement->execute([(int) $terminal['id'], $eventId, $uid, $rawBody ?: '', $requestHash, $now, $body, $actionType]);
    $requestId = (int) $db->lastInsertId();
    if ($pendingTimeEvent) {
        $statement = $db->prepare('INSERT INTO time_events(employee_id,card_id,terminal_id,event_request_id,event_type,occurred_at,uid_snapshot,created_at) VALUES(?,?,?,?,?,?,?,?)');
        $statement->execute([$pendingTimeEvent['employee_id'], $pendingTimeEvent['card_id'], (int) $terminal['id'], $requestId, $pendingTimeEvent['event_type'], $now, $uid, $now]);
        $timeEventId = (int) $db->lastInsertId();
        $db->prepare('UPDATE event_requests SET time_event_id=? WHERE id=?')->execute([$timeEventId, $requestId]);
    }
    $db->exec('COMMIT');
    api_respond(200, $body);
} catch (Throwable $exception) {
    try {
        if ($db->inTransaction()) {
            $db->rollBack();
        } else {
            $db->exec('ROLLBACK');
        }
    } catch (Throwable) {
    }
    error_log('[kienzlezeit] RFID: ' . $exception->getMessage());
    api_error(500, 'Serverfehler', 'Bitte erneut halten');
}
