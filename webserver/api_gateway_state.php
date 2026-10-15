<?php

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}
define('ARS_GATEWAY_REQUEST', true);
define('ARS_GATEWAY_AUTO_SESSION', ($_GET['auto_session'] ?? '') === '1' && (int) ($_GET['session_id'] ?? 0) === 0);
require_once "account.php";

if (!ARS_GATEWAY_AUTO_SESSION) { account_require_owned($conn, 'sessions', (int) ($_GET['session_id'] ?? 0)); }


header("Content-Type: application/json; charset=utf-8");

function reply_json(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    reply_json(405, [
        "success" => false,
        "message" => "Method Not Allowed"
    ]);
}

$session_id = isset($_GET["session_id"])
    ? intval($_GET["session_id"])
    : 0;

$device_code = isset($_GET["device_id"])
    ? trim($_GET["device_id"])
    : "";

if ((!ARS_GATEWAY_AUTO_SESSION && $session_id <= 0) || $device_code === "") {
    reply_json(400, [
        "success" => false,
        "message" => "session_id and device_id are required."
    ]);
}

$stmt = $conn->prepare(
    "SELECT id, device_code, device_name, status
     FROM devices
     WHERE device_code = ? AND user_id = {$account_id}
     LIMIT 1"
);
$stmt->bind_param("s", $device_code);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$device) {
    reply_json(404, [
        "success" => false,
        "message" => "Device not found: " . $device_code
    ]);
}

if (ARS_GATEWAY_AUTO_SESSION) {
    $stmt = $conn->prepare(
        "SELECT id, status, current_question_id FROM sessions
         WHERE user_id = ? AND status = 'running' ORDER BY id DESC LIMIT 2"
    );
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $session = count($sessions) === 1 ? $sessions[0] : [
        'id' => null,
        'status' => count($sessions) > 1 ? 'ambiguous' : 'waiting',
        'current_question_id' => null,
    ];
} else {
    $stmt = $conn->prepare(
        "SELECT id, status, current_question_id
         FROM sessions
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$session) {
        reply_json(404, [
            "success" => false,
            "message" => "Session not found."
        ]);
    }
}

// Gateway đang liên lạc được với thiết bị.
$stmt = $conn->prepare(
    "UPDATE devices
     SET status = 'online', last_seen = NOW()
     WHERE id = ?"
);
$device_id = intval($device["id"]);
$stmt->bind_param("i", $device_id);
$stmt->execute();
$stmt->close();

reply_json(200, [
    "success" => true,
    "device" => [
        "id" => $device_id,
        "device_code" => $device["device_code"],
        "device_name" => $device["device_name"]
    ],
    "session" => [
        "id" => $session["id"] === null ? null : intval($session["id"]),
        "status" => $session["status"],
        "current_question_id" => $session["current_question_id"] === null
            ? null
            : intval($session["current_question_id"])
    ]
]);

