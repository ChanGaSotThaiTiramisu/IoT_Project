<?php

session_start();

if (!isset($_SESSION["user_id"])) {

    header("Location: index.php");

    exit;
}

require_once "account.php";
require_once "device_presence.php";

header('Cache-Control: no-store');

$username = $_SESSION["username"];
$fullname = $_SESSION["full_name"];

$dashboard_counts = [
    "students" => 0,
    "question_sets" => 0,
    "questions" => 0,
    "devices" => 0,
    "devices_online" => 0,
    "active_sessions" => 0,
];

$count_queries = [
    "students" => "SELECT COUNT(*) AS total FROM students WHERE user_id = {$account_id}",
    "question_sets" => "SELECT COUNT(*) AS total FROM question_sets WHERE user_id = {$account_id}",
    "questions" => "SELECT COUNT(*) AS total FROM questions q JOIN question_sets qs ON qs.id = q.question_set_id WHERE qs.user_id = {$account_id}",
    "devices" => "SELECT COUNT(*) AS total FROM devices WHERE user_id = {$account_id}",
    "devices_online" => "SELECT COUNT(*) AS total FROM devices d WHERE d.user_id = {$account_id} AND " . DEVICE_ONLINE_SQL,
    "active_sessions" => "SELECT COUNT(*) AS total FROM sessions WHERE user_id = {$account_id} AND status = 'running'",
];

foreach ($count_queries as $key => $query) {
    $result = $conn->query($query);

    if ($result === false) {
        die("Unable to load dashboard data: " . htmlspecialchars($conn->error));
    }

    $row = $result->fetch_assoc();
    $dashboard_counts[$key] = (int) ($row["total"] ?? 0);
    $result->free();
}

$active_session = null;
$answer_counts = ["A" => 0, "B" => 0, "C" => 0, "D" => 0];

$active_session_stmt = $conn->prepare(
    "SELECT
        s.id,
        s.session_name,
        s.current_question_id,
        q.question_text
     FROM sessions s
     LEFT JOIN questions q
        ON q.id = s.current_question_id
     WHERE s.user_id = {$account_id} AND s.status = 'running'
     ORDER BY s.started_at DESC, s.id DESC
     LIMIT 1"
);

if (!$active_session_stmt) {
    die("Unable to load active session: " . htmlspecialchars($conn->error));
}

$active_session_stmt->execute();
$active_session = $active_session_stmt->get_result()->fetch_assoc();
$active_session_stmt->close();

if ($active_session) {
    $response_stmt = $conn->prepare(
        "SELECT answer, COUNT(*) AS total
         FROM responses
         WHERE session_id = ?
           AND question_id = ?
         GROUP BY answer"
    );

    if (!$response_stmt) {
        die("Unable to load live responses: " . htmlspecialchars($conn->error));
    }

    $response_stmt->bind_param(
        "ii",
        $active_session["id"],
        $active_session["current_question_id"]
    );
    $response_stmt->execute();
    $response_result = $response_stmt->get_result();

    while ($row = $response_result->fetch_assoc()) {
        $answer = strtoupper((string) $row["answer"]);

        if (isset($answer_counts[$answer])) {
            $answer_counts[$answer] = (int) $row["total"];
        }
    }

    $response_stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Astralis | Dashboard</title>

    <link rel="stylesheet"
          href="css/style.css?v=202610061410">

    <link rel="stylesheet" href="css/workspace.css?v=20261006-3">
    <script src="js/workspace.js?v=20261006" defer></script>
</head>

<body class="workspace-page dashboard-page dashboard-workspace">

<div class="admin-layout">

    <?php $ars_active_page = 'dashboard'; require __DIR__ . '/admin_sidebar.php'; ?>


    <!-- MAIN -->

    <main class="main-content">
        <?php $workspace_page = ['title' => 'Dashboard', 'eyebrow' => 'YOUR CLASSROOM, AT A GLANCE', 'description' => 'Every student. Every response. A clearer picture of your classroom.', 'icon' => 'dashboard', 'caption' => 'A classroom in sync', 'href' => 'sessions.php', 'action' => 'Manage Sessions']; require __DIR__ . '/workspace_header.php'; ?>


        


        <!-- STATISTICS -->

        <div class="stats">

            <div class="stat-card">

                <span class="workspace-stat-icon"><?= workspace_icon('students') ?></span>
                <a href="students.php">Students</a>

                <h2><?= $dashboard_counts["students"] ?></h2>

            </div>


            <div class="stat-card">

                <span class="workspace-stat-icon"><?= workspace_icon('questions') ?></span>
                <a href="questions.php">Question Sets</a>

                <h2><?= $dashboard_counts["question_sets"] ?></h2>

                <small><?= $dashboard_counts["questions"] ?> questions total</small>

            </div>


            <div class="stat-card">

                <span class="workspace-stat-icon"><?= workspace_icon('devices') ?></span>
                <a href="devices.php">Devices Online</a>

                <h2 data-devices-online><?= $dashboard_counts["devices_online"] ?></h2>

                <small><?= $dashboard_counts["devices"] ?> devices registered</small>

            </div>


            <div class="stat-card">

                <span class="workspace-stat-icon"><?= workspace_icon('sessions') ?></span>
                <a href="sessions.php">Active Sessions</a>

                <h2><?= $dashboard_counts["active_sessions"] ?></h2>

            </div>

        </div>


        <div class="workspace-shortcuts" aria-label="Classroom shortcuts">
            <a href="questions.php"><span class="workspace-shortcut-icon"><?= workspace_icon('questions') ?></span><span><strong>Prepare your questions</strong><small>Build your next classroom conversation</small></span><?= workspace_icon('arrow') ?></a>
            <a href="students.php"><span class="workspace-shortcut-icon"><?= workspace_icon('students') ?></span><span><strong>Bring your class together</strong><small>Manage students and their details</small></span><?= workspace_icon('arrow') ?></a>
            <a href="sessions.php"><span class="workspace-shortcut-icon"><?= workspace_icon('sessions') ?></span><span><strong>Start a new conversation</strong><small>Create and manage response sessions</small></span><?= workspace_icon('arrow') ?></a>
        </div>

        <!-- LIVE RESULT -->

        <div class="result-panel">

            <h2>
                Live Response
            </h2>

            <?php if ($active_session): ?>

                <p class="dashboard-live-meta">
                    <?= htmlspecialchars($active_session["session_name"]) ?>
                    <?php if ($active_session["question_text"]): ?>
                        &middot; <?= htmlspecialchars($active_session["question_text"]) ?>
                    <?php endif; ?>
                </p>

            <?php else: ?>

                <p class="dashboard-live-meta">
                    Waiting for an active session...
                </p>

            <?php endif; ?>


            <div class="answer-grid">

                <div class="answer-card">

                    <h3><?= $answer_counts["A"] ?></h3>

                    <p>Answer A</p>

                </div>


                <div class="answer-card">

                    <h3><?= $answer_counts["B"] ?></h3>

                    <p>Answer B</p>

                </div>


                <div class="answer-card">

                    <h3><?= $answer_counts["C"] ?></h3>

                    <p>Answer C</p>

                </div>


                <div class="answer-card">

                    <h3><?= $answer_counts["D"] ?></h3>

                    <p>Answer D</p>

                </div>

            </div>

        </div>

    <footer class="workspace-footer"><span>© <?= date('Y') ?> Astralis</span><span>Ask. Connect. Understand.</span></footer>
    </main>

</div>

<script src="js/device_presence.js"></script>
</body>

</html>
