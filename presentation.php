<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

require_once "account.php";

if (!empty($_GET['session'])) { account_require_owned($conn, 'sessions', (int) $_GET['session']); }


$session_id = intval($_GET["session"] ?? 0);

if ($session_id <= 0) {
    die("Invalid session.");
}

function load_presentation_session(mysqli $conn, int $session_id): ?array
{
    $stmt = $conn->prepare(
        "SELECT
            s.id,
            s.session_name,
            s.status,
            s.current_question_id,
            s.advance_mode,
            s.answer_time_seconds,
            s.question_started_at,
            q.question_text,
            q.option_a,
            q.option_b,
            q.option_c,
            q.option_d
         FROM sessions s
         LEFT JOIN questions q ON q.id = s.current_question_id
         WHERE s.id = ?"
    );
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $session ?: null;
}

function advance_presentation_session(mysqli $conn, int $session_id, int $current_question_id): void
{
    $stmt = $conn->prepare(
        "SELECT question_set_id
         FROM sessions
         WHERE id = ? AND status = 'running'"
    );
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$session) {
        return;
    }

    $stmt = $conn->prepare(
        "SELECT id
         FROM questions
         WHERE question_set_id = ? AND id > ?
         ORDER BY id ASC
         LIMIT 1"
    );
    $question_set_id = (int) $session["question_set_id"];
    $stmt->bind_param("ii", $question_set_id, $current_question_id);
    $stmt->execute();
    $next = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($next) {
        $next_id = (int) $next["id"];
        $stmt = $conn->prepare(
            "UPDATE sessions
             SET current_question_id = ?, question_started_at = NOW()
             WHERE id = ? AND status = 'running'"
        );
        $stmt->bind_param("ii", $next_id, $session_id);
    } else {
        $stmt = $conn->prepare(
            "UPDATE sessions
             SET status = 'finished', ended_at = NOW(), question_started_at = NULL
             WHERE id = ? AND status = 'running'"
        );
        $stmt->bind_param("i", $session_id);
    }

    $stmt->execute();
    $stmt->close();
}

$session = load_presentation_session($conn, $session_id);

if (!$session) {
    die("Session not found.");
}

if (
    $session["status"] === "running"
    && $session["current_question_id"]
    && $session["advance_mode"] !== "manual"
) {
    $should_advance = false;

    if (
        $session["advance_mode"] === "timer"
        && $session["question_started_at"]
    ) {
        $started_at = strtotime($session["question_started_at"]);
        $should_advance = time() >= $started_at + (int) $session["answer_time_seconds"];
    }

    if ($session["advance_mode"] === "all_answered") {
        $device_result = $conn->query(
            "SELECT COUNT(*) AS total
             FROM devices WHERE user_id = {$account_id}"
        );
        $device_count = (int) ($device_result->fetch_assoc()["total"] ?? 0);

        $stmt = $conn->prepare(
            "SELECT COUNT(DISTINCT device_id) AS total
             FROM responses
             WHERE session_id = ? AND question_id = ?"
        );
        $question_id = (int) $session["current_question_id"];
        $stmt->bind_param("ii", $session_id, $question_id);
        $stmt->execute();
        $response_count = (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0);
        $stmt->close();

        $should_advance = $device_count > 0 && $response_count >= $device_count;
    }

    if ($should_advance) {
        advance_presentation_session($conn, $session_id, (int) $session["current_question_id"]);
        $session = load_presentation_session($conn, $session_id);
    }
}

$remaining_seconds = null;
if (
    $session["status"] === "running"
    && $session["advance_mode"] === "timer"
    && $session["question_started_at"]
) {
    $remaining_seconds = max(
        0,
        strtotime($session["question_started_at"]) + (int) $session["answer_time_seconds"] - time()
    );
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presentation - Astralis</title>
    <link rel="stylesheet" href="/ars2/css/style.css?v=202610061407">
    <link rel="stylesheet" href="css/workspace.css?v=20261006-3">
</head>
<body class="presentation-page workspace-presentation">
<?php require __DIR__ . '/workspace_background.php'; ?>
<main class="presentation-shell">
    <header class="presentation-header">
        <div class="presentation-brand">
            <div class="presentation-brand-mark" aria-hidden="true">
                <svg viewBox="0 0 110 82" role="img">
                    <g fill="#ff633d">
                        <ellipse cx="78" cy="18" rx="14" ry="29" transform="rotate(42 78 18)"/>
                        <ellipse cx="38" cy="34" rx="14" ry="29" transform="rotate(42 38 34)"/>
                        <ellipse cx="78" cy="55" rx="14" ry="29" transform="rotate(42 78 55)"/>
                        <ellipse cx="38" cy="70" rx="14" ry="29" transform="rotate(42 38 70)"/>
                    </g>
                </svg>
            </div>
            <div>
                <p class="presentation-kicker">Astralis</p>
                <h1><?= htmlspecialchars($session["session_name"]) ?></h1>
            </div>
        </div>
        <div class="presentation-header-actions">
            <span class="presentation-live-pill">
                <span class="presentation-live-dot"></span>
                <?= $session["status"] === "running" ? "LIVE SESSION" : strtoupper($session["status"]) ?>
            </span>
            <a href="sessions.php" class="presentation-exit">Exit presentation</a>
        </div>
    </header>

    <?php if ($session["status"] === "running" && $session["question_text"]): ?>
        <section class="presentation-question">
            <div class="presentation-question-topline">
                <p class="presentation-label">CURRENT QUESTION</p>
                <span class="presentation-mode">
                    <?php if ($session["advance_mode"] === "timer"): ?>
                        Timed response
                    <?php elseif ($session["advance_mode"] === "all_answered"): ?>
                        Auto advance
                    <?php else: ?>
                        Manual advance
                    <?php endif; ?>
                </span>
            </div>
            <div class="presentation-question-number">
                QUESTION <?= (int) $session["current_question_id"] ?>
            </div>
            <h2><?= htmlspecialchars($session["question_text"]) ?></h2>
            <div class="presentation-answer-grid">
                <?php foreach (["A", "B", "C", "D"] as $option): ?>
                    <?php $option_key = "option_" . strtolower($option); ?>
                    <div class="presentation-answer">
                        <strong class="presentation-option-key"><?= $option ?></strong>
                        <span><?= htmlspecialchars($session[$option_key]) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="presentation-status">
                <?php if ($session["advance_mode"] === "timer"): ?>
                    <span class="presentation-status-icon">◷</span>
                    Time remaining
                    <strong id="remaining-time"><?= (int) $remaining_seconds ?></strong>
                    <span>seconds</span>
                <?php elseif ($session["advance_mode"] === "all_answered"): ?>
                    <span class="presentation-status-icon">◎</span>
                    Waiting for all registered devices to answer
                <?php else: ?>
                    <span class="presentation-status-icon">→</span>
                    Waiting for the host to continue
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($session["status"] === "finished"): ?>
        <section class="presentation-question">
            <div class="presentation-empty-icon">✓</div>
            <h2>Session finished</h2>
            <p>All questions have been completed.</p>
        </section>
    <?php else: ?>
        <section class="presentation-question">
            <div class="presentation-empty-icon">◷</div>
            <h2>Session is not running</h2>
            <p>Start the session from the Sessions page.</p>
        </section>
    <?php endif; ?>
<footer class="workspace-footer"><span>Astralis classroom</span><span>Every voice. Every answer. Every student.</span></footer>
</main>

<?php if ($session["status"] === "running"): ?>
<script>
    setTimeout(function () {
        window.location.reload();
    }, 2000);

    const remainingTime = document.getElementById("remaining-time");
    if (remainingTime) {
        let seconds = Number(remainingTime.textContent);
        setInterval(function () {
            seconds = Math.max(0, seconds - 1);
            remainingTime.textContent = seconds;
        }, 1000);
    }
</script>
<?php endif; ?>
</body>
</html>
