<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    exit;
}

require_once "account.php";

if (!empty($_GET['session'])) { account_require_owned($conn, 'sessions', (int) $_GET['session']); }


$session_id = isset($_GET["session"])
    ? intval($_GET["session"])
    : 0;

if ($session_id <= 0) {
    http_response_code(400);
    exit;
}

$stmt = $conn->prepare(
    "SELECT
        question_id,
        answer,
        COUNT(*) AS total
     FROM responses
     WHERE session_id = ?
     GROUP BY question_id, answer"
);

$stmt->bind_param("i", $session_id);
$stmt->execute();

$result = $stmt->get_result();

$data = [];

while ($row = $result->fetch_assoc()) {

    $question_id = $row["question_id"];
    $answer = $row["answer"];

    if (!isset($data[$question_id])) {

        $data[$question_id] = [
            "A" => 0,
            "B" => 0,
            "C" => 0,
            "D" => 0
        ];

    }

    $data[$question_id][$answer] =
        intval($row["total"]);
}

$stmt->close();

header("Content-Type: application/json; charset=utf-8");

echo json_encode(
    $data,
    JSON_UNESCAPED_UNICODE
);
