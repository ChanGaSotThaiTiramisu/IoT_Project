<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}
define('ARS_GATEWAY_REQUEST', true);
require_once "account.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') { account_require_owned($conn, 'sessions', (int) ($_POST['session_id'] ?? 0)); }


header("Content-Type: application/json; charset=utf-8");

function send_json($status, $data)
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    send_json(405, [
        "success" => false,
        "message" => "Method Not Allowed"
    ]);
}

$session_id = isset($_POST["session_id"])
    ? intval($_POST["session_id"])
    : 0;

$current_question_id =
    isset($_POST["current_question_id"])
    ? intval($_POST["current_question_id"])
    : 0;

if ($session_id <= 0 || $current_question_id <= 0) {
    send_json(400, [
        "success" => false,
        "message" => "Invalid data"
    ]);
}

/* Đọc trạng thái session */

$stmt = $conn->prepare(
    "SELECT question_set_id,
            status,
            current_question_id
     FROM sessions
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $session_id);
$stmt->execute();

$session = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();

if (!$session) {
    send_json(404, [
        "success" => false,
        "message" => "Session not found"
    ]);
}

if ($session["status"] !== "running") {
    send_json(409, [
        "success" => false,
        "message" => "Session is not running"
    ]);
}

if (
    intval($session["current_question_id"])
    !== $current_question_id
) {
    send_json(409, [
        "success" => false,
        "message" => "Question has already changed"
    ]);
}

$question_set_id =
    intval($session["question_set_id"]);

/* Tìm câu tiếp theo */

$stmt = $conn->prepare(
    "SELECT id
     FROM questions
     WHERE question_set_id = ?
       AND id > ?
     ORDER BY id ASC
     LIMIT 1"
);

$stmt->bind_param(
    "ii",
    $question_set_id,
    $current_question_id
);

$stmt->execute();

$next_question = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();

/* Nếu còn câu tiếp theo */

if ($next_question) {
    $next_question_id =
        intval($next_question["id"]);

    $stmt = $conn->prepare(
        "UPDATE sessions
         SET current_question_id = ?
         WHERE id = ?"
    );

    $stmt->bind_param(
        "ii",
        $next_question_id,
        $session_id
    );

    $stmt->execute();
    $stmt->close();

    send_json(200, [
        "success" => true,
        "finished" => false,
        "next_question_id" => $next_question_id
    ]);
}

/* Không còn câu nào thì kết thúc */

$stmt = $conn->prepare(
    "UPDATE sessions
     SET status = 'finished'
     WHERE id = ?"
);

$stmt->bind_param("i", $session_id);
$stmt->execute();
$stmt->close();

send_json(200, [
    "success" => true,
    "finished" => true,
    "next_question_id" => null
]);