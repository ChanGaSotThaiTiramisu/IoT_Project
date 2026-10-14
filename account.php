<?php
require_once __DIR__ . '/db.php';

function account_denied(int $status = 404): void
{
    http_response_code($status);
    if (str_starts_with(basename($_SERVER['SCRIPT_NAME'] ?? ''), 'api_')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $status === 401 ? 'Authentication required.' : 'Resource not found.']);
    } else {
        echo $status === 401 ? 'Please sign in.' : 'Resource not found.';
    }
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$account_id = (int) ($_SESSION['user_id'] ?? 0);
// Gateways authenticate with the owner's token; never accept an owner ID from a request.
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($authorization !== '') {
    $account_id = 0;
    if (preg_match('/^Bearer ([a-f0-9]{64})$/i', $authorization, $matches)) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE gateway_token = ?');
        $stmt->bind_param('s', $matches[1]);
        $stmt->execute();
        $account_id = (int) ($stmt->get_result()->fetch_assoc()['id'] ?? 0);
        $stmt->close();
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
