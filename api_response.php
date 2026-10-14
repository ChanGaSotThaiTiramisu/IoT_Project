<?php

require_once "account.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') { account_require_owned($conn, 'sessions', (int) ($_POST['session_id'] ?? 0)); }


header("Content-Type: application/json; charset=utf-8");


/* =====================================================
   CHECK METHOD
===================================================== */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method Not Allowed"
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/* =====================================================
   RECEIVE PACKET
===================================================== */

$packet_id = isset($_POST["packet_id"])
    ? intval($_POST["packet_id"])
    : 0;

$device_code = isset($_POST["device_id"])
    ? trim($_POST["device_id"])
    : "";

$session_id = isset($_POST["session_id"])
    ? intval($_POST["session_id"])
    : 0;

$question_id = isset($_POST["question_id"])
    ? intval($_POST["question_id"])
    : 0;

$answer = isset($_POST["answer"])
    ? strtoupper(trim($_POST["answer"]))
    : "";


/* =====================================================
   VALIDATE
===================================================== */

if (
    $packet_id <= 0 ||
    $device_code === "" ||
    $session_id <= 0 ||
    $question_id <= 0 ||
    !in_array($answer, ["A", "B", "C", "D"])
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid packet."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/* =====================================================
   FIND DEVICE
===================================================== */

$stmt = $conn->prepare(
    "SELECT id, student_id
     FROM devices
     WHERE device_code = ? AND user_id = {$account_id}"
);

$stmt->bind_param(
    "s",
    $device_code
);

$stmt->execute();

$result = $stmt->get_result();

$device = $result->fetch_assoc();

$stmt->close();


if (!$device) {

    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => "Device not found: " . $device_code
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


$device_id = intval($device["id"]);

$student_id = $device["student_id"];
if ($student_id !== null && !account_owns($conn, "students", (int) $student_id)) { account_denied(); }


/* =====================================================
   CHECK SESSION
===================================================== */

$stmt = $conn->prepare(
    "SELECT id, question_set_id, status
     FROM sessions
     WHERE id = ?"
);

$stmt->bind_param(
    "i",
    $session_id
);

$stmt->execute();

$result = $stmt->get_result();

$session = $result->fetch_assoc();

$stmt->close();


if (!$session) {

    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => "Session not found."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/* =====================================================
   SESSION MUST BE RUNNING
===================================================== */

if ($session["status"] !== "running") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "The session is not running or has ended."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


$question_set_id =
    intval($session["question_set_id"]);


/* =====================================================
   CHECK QUESTION
===================================================== */

$stmt = $conn->prepare(
    "SELECT id
     FROM questions
     WHERE id = ?
     AND question_set_id = ?"
);

$stmt->bind_param(
    "ii",
    $question_id,
    $question_set_id
);

$stmt->execute();

$result = $stmt->get_result();

$question = $result->fetch_assoc();

$stmt->close();


if (!$question) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "The question does not belong to this question set."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/* =====================================================
   CHECK EXISTING RESPONSE
===================================================== */

$stmt = $conn->prepare(
    "SELECT id, answer
     FROM responses
     WHERE session_id = ?
     AND question_id = ?
     AND device_id = ?
     LIMIT 1"
);

$stmt->bind_param(
    "iii",
    $session_id,
    $question_id,
    $device_id
);

$stmt->execute();

$result = $stmt->get_result();

$existing = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   UPDATE EXISTING RESPONSE
===================================================== */

if ($existing) {

    $response_id =
        intval($existing["id"]);

    $old_answer =
        $existing["answer"];


    $stmt = $conn->prepare(
        "UPDATE responses
         SET answer = ?,
             student_id = ?,
             received_at = NOW()
         WHERE id = ?"
    );

    $stmt->bind_param(
        "sii",
        $answer,
        $student_id,
        $response_id
    );


    if (!$stmt->execute()) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Unable to update the response."
        ], JSON_UNESCAPED_UNICODE);

        $stmt->close();

        exit;
    }

    $stmt->close();


    /* UPDATE DEVICE */

    $stmt = $conn->prepare(
        "UPDATE devices
         SET status = 'online',
             last_seen = NOW()
         WHERE id = ?"
    );

    $stmt->bind_param(
        "i",
        $device_id
    );

    $stmt->execute();

    $stmt->close();


    /* RESPONSE */

    echo json_encode([

        "success" => true,

        "action" => "updated",

        "message" =>
            "Existing response updated.",

        "data" => [

            "packet_id" => $packet_id,

            "response_id" => $response_id,

            "session_id" => $session_id,

            "device_id" => $device_code,

            "question_id" => $question_id,

            "old_answer" => $old_answer,

            "answer" => $answer

        ]

    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/* =====================================================
   INSERT NEW RESPONSE
===================================================== */

$stmt = $conn->prepare(
    "INSERT INTO responses
    (
        session_id,
        question_id,
        device_id,
        student_id,
        answer
    )
    VALUES (?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "iiiis",
    $session_id,
    $question_id,
    $device_id,
    $student_id,
    $answer
);


if (!$stmt->execute()) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to save the response."
    ], JSON_UNESCAPED_UNICODE);

    $stmt->close();

    exit;
}


$response_id =
    $stmt->insert_id;

$stmt->close();


/* UPDATE DEVICE */

$stmt = $conn->prepare(
    "UPDATE devices
     SET status = 'online',
         last_seen = NOW()
     WHERE id = ?"
);

$stmt->bind_param(
    "i",
    $device_id
);

$stmt->execute();

$stmt->close();


/* =====================================================
   SUCCESS - INSERT
===================================================== */

echo json_encode([

    "success" => true,

    "action" => "inserted",

    "message" =>
        "Response recorded.",

    "data" => [

        "packet_id" => $packet_id,

        "response_id" => $response_id,

        "session_id" => $session_id,

        "device_id" => $device_code,

        "question_id" => $question_id,

        "answer" => $answer

    ]

], JSON_UNESCAPED_UNICODE);

?>

