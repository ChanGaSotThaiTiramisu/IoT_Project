<?php
require_once __DIR__ . '/db.php';

function account_denied(int $status = 404, ?string $auth_error = null): void
{
    http_response_code($status);
    if (str_starts_with(basename($_SERVER['SCRIPT_NAME'] ?? ''), 'api_')) {
        header('Content-Type: application/json; charset=utf-8');
        $body = ['success' => false, 'message' => $status === 401 ? 'Authentication required.' : 'Resource not found.'];
        if ($status === 401 && $auth_error !== null) { $body['auth_error'] = $auth_error; }
        echo json_encode($body);
    } else {
        echo $status === 401 ? 'Please sign in.' : 'Resource not found.';
    }
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$account_id = (int) ($_SESSION['user_id'] ?? 0);
// Keyless gateway requests use the target session's owner as their data scope.
// Browser requests still use the signed-in account and its ownership checks.
if ($account_id < 1 && defined('ARS_GATEWAY_REQUEST') && ARS_GATEWAY_REQUEST) {
    $input = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : $_POST;
    $gateway_session_id = (int) ($input['session_id'] ?? 0);
    if (defined('ARS_GATEWAY_AUTO_SESSION') && ARS_GATEWAY_AUTO_SESSION) {
        // Device codes are unique per account, not globally. Never guess an
        // owner when the same code is registered by multiple accounts.
        $device_code = trim((string) ($input['device_id'] ?? ''));
        $stmt = $conn->prepare('SELECT user_id FROM devices WHERE device_code = ? LIMIT 2');
        $stmt->bind_param('s', $device_code);
        $stmt->execute();
        $owners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (count($owners) !== 1) {
            http_response_code(count($owners) > 1 ? 409 : 404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => count($owners) > 1
                ? 'Device code belongs to multiple accounts. Set ARS_SESSION_ID explicitly.'
                : 'Device not found: ' . $device_code]);
            exit;
        }
        $account_id = (int) $owners[0]['user_id'];
    } else {
        if ($gateway_session_id < 1) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'session_id is required.']);
            exit;
        }
        $stmt = $conn->prepare('SELECT user_id FROM sessions WHERE id = ?');
        $stmt->bind_param('i', $gateway_session_id);
        $stmt->execute();
        $account_id = (int) ($stmt->get_result()->fetch_assoc()['user_id'] ?? 0);
        $stmt->close();
        if ($account_id < 1) { account_denied(); }
    }
}
if ($account_id < 1) { account_denied(401); }

function account_owns(mysqli $conn, string $table, int $id): bool
{
    global $account_id;
    if ($table === 'questions') {
        $sql = 'SELECT q.id FROM questions q JOIN question_sets qs ON qs.id = q.question_set_id WHERE q.id = ? AND qs.user_id = ?';
    } elseif (in_array($table, ['sessions', 'question_sets', 'students', 'devices'], true)) {
        $sql = "SELECT id FROM `$table` WHERE id = ? AND user_id = ?";
    } else {
        throw new InvalidArgumentException('Unsupported owned resource');
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $id, $account_id);
    $stmt->execute();
    $owned = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    return $owned;
}

function account_require_owned(mysqli $conn, string $table, int $id): void
{
    if (!account_owns($conn, $table, $id)) { account_denied(); }
}
