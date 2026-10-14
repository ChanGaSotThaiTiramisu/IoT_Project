<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once "account.php";

foreach (['start', 'finish', 'next', 'prev'] as $session_action_key) {
    if (isset($_GET[$session_action_key])) {
        account_require_owned($conn, 'sessions', (int) $_GET[$session_action_key]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['session_action']) || isset($_POST['save_advance_settings'])) {
        account_require_owned($conn, 'sessions', (int) ($_POST['session_id'] ?? 0));
    }
    if (isset($_POST['create_session'])) {
        account_require_owned($conn, 'question_sets', (int) ($_POST['question_set_id'] ?? 0));
    }
}


function redirect_to_sessions(): void
{
    header("Location: sessions.php");
    exit;
}

if (empty($_SESSION['sessions_csrf_token'])) {
    $_SESSION['sessions_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_action'])) {
    $token = $_POST['csrf_token'] ?? '';
    $id = filter_var($_POST['session_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['session_action'];

    if (!is_string($token) || !hash_equals($_SESSION['sessions_csrf_token'], $token)
        || !$id || $id < 1 || !in_array($action, ['delete', 'reopen'], true)) {
        $_SESSION['sessions_flash'] = ['error', 'Invalid request. Please try again.'];
        redirect_to_sessions();
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn->begin_transaction();
        if ($action === 'delete') {
            // Lock the session before removing dependent legacy responses.
            $stmt = $conn->prepare('SELECT id FROM sessions WHERE id = ? FOR UPDATE');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->get_result()->free();
            $stmt->close();

            $stmt = $conn->prepare('DELETE FROM responses_legacy WHERE session_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            // Current responses are removed by the database's ON DELETE CASCADE.
            $stmt = $conn->prepare('DELETE FROM sessions WHERE id = ?');
        } else {
            $stmt = $conn->prepare(
                "UPDATE sessions s
                 SET s.status = 'running',
                     s.ended_at = NULL,
                     s.started_at = COALESCE(s.started_at, NOW()),
                     s.question_started_at = NOW(),
                     s.current_question_id = COALESCE(s.current_question_id, (
                         SELECT MIN(q.id) FROM questions q
                         WHERE q.question_set_id = s.question_set_id
                     ))
                 WHERE s.id = ? AND s.status IN ('finished', 'stopped')"
            );
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $changed = $stmt->affected_rows > 0;
        $stmt->close();
        $conn->commit();
        $_SESSION['sessions_flash'] = $changed
            ? ['success', $action === 'delete' ? 'Session deleted successfully.' : 'Session reopened successfully.']
            : ['error', 'Session not found or its status has changed. Please refresh and try again.'];
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        error_log('Session action failed: ' . $e->getMessage());
        $_SESSION['sessions_flash'] = ['error', 'Unable to update the session. Please try again.'];
    }
    redirect_to_sessions();
}

$session_flash = $_SESSION['sessions_flash'] ?? null;
unset($_SESSION['sessions_flash']);

/* =====================================================
   SAVE QUESTION ADVANCE SETTINGS
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["save_advance_settings"])
) {
    $id = intval($_POST["session_id"] ?? 0);
    $mode = $_POST["advance_mode"] ?? "manual";
    $seconds = max(5, min(3600, intval($_POST["answer_time_seconds"] ?? 30)));

    if ($id > 0 && in_array($mode, ["manual", "timer", "all_answered"], true)) {
        $stmt = $conn->prepare(
            "UPDATE sessions
             SET advance_mode = ?, answer_time_seconds = ?
             WHERE id = ?"
        );
        $stmt->bind_param("sii", $mode, $seconds, $id);
        $stmt->execute();
        $stmt->close();
    }

    redirect_to_sessions();
}


/* =====================================================
   START SESSION
===================================================== */

if (isset($_GET['start'])) {

    $id = intval($_GET['start']);

    $stmt = $conn->prepare(
        "UPDATE sessions s
         SET
            s.status = 'running',
            s.started_at = NOW(),
            s.question_started_at = NOW(),
            s.current_question_id = (
                SELECT MIN(q.id)
                FROM questions q
                WHERE q.question_set_id = s.question_set_id
            )
         WHERE s.id = ?
         AND s.status = 'waiting'"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    redirect_to_sessions();
}


/* =====================================================
   FINISH SESSION
===================================================== */

if (isset($_GET['finish'])) {

    $id = intval($_GET['finish']);

    $stmt = $conn->prepare(
        "UPDATE sessions
         SET
            status = 'finished',
            ended_at = NOW(),
            question_started_at = NULL
         WHERE id = ?
         AND status = 'running'"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    redirect_to_sessions();
}


/* =====================================================
   CHANGE CURRENT QUESTION
===================================================== */

if (isset($_GET['next']) || isset($_GET['prev'])) {

    $id = intval(
        $_GET['next'] ??
        $_GET['prev']
    );

    if ($id > 0) {

        /* =============================================
           LẤY DANH SÁCH QUESTION CỦA QUESTION SET
        ============================================= */

        $stmt = $conn->prepare(
            "SELECT
                s.id,
                s.question_set_id,
                s.current_question_id,
                q.id AS question_id
             FROM sessions s
             LEFT JOIN questions q
                ON q.question_set_id = s.question_set_id
             WHERE s.id = ?
             AND s.status = 'running'
             ORDER BY q.id ASC"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();

        $questions = [];

        while ($row = $result->fetch_assoc()) {

            if ($row['question_id'] !== null) {

                $questions[] =
                    intval($row['question_id']);
            }
        }

        $stmt->close();


        /* =============================================
           NẾU CÓ QUESTION
        ============================================= */

        if (count($questions) > 0) {

            /* =========================================
               LẤY CÂU HIỆN TẠI
            ========================================= */

            $stmt = $conn->prepare(
                "SELECT current_question_id
                 FROM sessions
                 WHERE id = ?
                 AND status = 'running'"
            );

            $stmt->bind_param("i", $id);
            $stmt->execute();

            $current =
                $stmt->get_result()->fetch_assoc();

            $stmt->close();


            $current_question_id =
                intval(
                    $current['current_question_id'] ?? 0
                );


            /* =========================================
               TÌM VỊ TRÍ CÂU HIỆN TẠI
            ========================================= */

            $current_index =
                array_search(
                    $current_question_id,
                    $questions
                );


            /* =========================================
               NẾU CHƯA CÓ CÂU HIỆN TẠI
               → MỞ CÂU ĐẦU TIÊN
            ========================================= */

            if ($current_index === false) {

                $new_question_id =
                    $questions[0];

            }


            /* =========================================
               CÂU TIẾP
            ========================================= */

            elseif (isset($_GET['next'])) {

                if (
                    $current_index <
                    count($questions) - 1
                ) {

                    $new_question_id =
                        $questions[
                            $current_index + 1
                        ];

                } else {

                    /* ĐANG Ở CÂU CUỐI */

                    $new_question_id =
                        $questions[
                            $current_index
                        ];
                }

            }


            /* =========================================
               CÂU TRƯỚC
            ========================================= */

            else {

                if ($current_index > 0) {

                    $new_question_id =
                        $questions[
                            $current_index - 1
                        ];

                } else {

                    /* ĐANG Ở CÂU ĐẦU */

                    $new_question_id =
                        $questions[0];
                }
            }


            /* =========================================
               UPDATE CURRENT QUESTION
            ========================================= */

            $stmt = $conn->prepare(
                "UPDATE sessions
                 SET current_question_id = ?
                     , question_started_at = NOW()
                 WHERE id = ?
                 AND status = 'running'"
            );

            $stmt->bind_param(
                "ii",
                $new_question_id,
                $id
            );

            $stmt->execute();
            $stmt->close();
        }
    }

    redirect_to_sessions();
}


/* =====================================================
   CREATE SESSION
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST['create_session'])
) {

    $session_name =
        trim($_POST['session_name']);

    $question_set_id =
        intval($_POST['question_set_id']);


    if (
        $session_name !== ""
        && $question_set_id > 0
    ) {

        $stmt = $conn->prepare(
            "INSERT INTO sessions
            (
                user_id,
                session_name,
                question_set_id,
                status
            )
            VALUES
            (
                {$account_id},
                ?,
                ?,
                'waiting'
            )"
        );

        $stmt->bind_param(
            "si",
            $session_name,
            $question_set_id
        );

        $stmt->execute();
        $stmt->close();

        header(
            "Location: sessions.php?created=1"
        );

        exit;
    }
}


/* =====================================================
   GET QUESTION SETS
===================================================== */

$question_sets = [];

$result = $conn->query(
    "SELECT *
     FROM question_sets
     WHERE user_id = {$account_id}
     ORDER BY id DESC"
);

while ($row = $result->fetch_assoc()) {

    $question_sets[] = $row;
}


/* =====================================================
   GET SESSIONS
===================================================== */

$sessions = [];

$result = $conn->query(
    "SELECT
        s.*,
        qs.set_name,
        COUNT(r.id) AS response_count

     FROM sessions s

     LEFT JOIN question_sets qs
        ON s.question_set_id = qs.id

     LEFT JOIN responses r
        ON s.id = r.session_id

     WHERE s.user_id = {$account_id}
     GROUP BY s.id

     ORDER BY s.id DESC"
);

while ($row = $result->fetch_assoc()) {

    $sessions[] = $row;
}

$running_question = null;

foreach ($sessions as $session) {
    if ($session["status"] !== "running" || !$session["current_question_id"]) {
        continue;
    }

    $stmt = $conn->prepare(
        "SELECT id, question_text, option_a, option_b, option_c, option_d
         FROM questions
         WHERE id = ?"
    );
    $question_id = (int) $session["current_question_id"];
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $running_question = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $running_question["session_id"] = (int) $session["id"];
    $running_question["session_name"] = $session["session_name"];
    $running_question["advance_mode"] = $session["advance_mode"];
    $running_question["answer_time_seconds"] = (int) $session["answer_time_seconds"];
    break;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Astralis | Sessions</title>

<link
    rel="stylesheet"
    href="css/style.css?v=202610061407"
>

    <link rel="stylesheet" href="css/workspace.css?v=20261006-3">
    <script src="js/workspace.js?v=20261006" defer></script>
</head>


<body class="workspace-page sessions-workspace">

<div class="layout">


    <!-- =================================================
         SIDEBAR
    ================================================== -->

    <?php $ars_active_page = 'sessions'; require __DIR__ . '/admin_sidebar.php'; ?>


    <!-- =================================================
         MAIN
    ================================================== -->

    <main class="main-content">
        <?php $workspace_page = ['title' => 'Sessions', 'eyebrow' => 'TURN QUESTIONS INTO CONVERSATIONS', 'description' => 'Set the stage for your next lesson. Ask, listen, and discover together.', 'icon' => 'sessions', 'caption' => 'Your next classroom moment']; require __DIR__ . '/workspace_header.php'; ?>



        <!-- =================================================
             TOPBAR
        ================================================== -->

        

        <?php if ($session_flash): ?>
            <div class="<?= $session_flash[0] === 'success' ? 'success-message' : 'error-message' ?>" role="status">
                <?= htmlspecialchars($session_flash[1]) ?>
            </div>
        <?php endif; ?>

        <?php if ($running_question): ?>
            <section class="content-card current-question-card">
                <div class="card-header">
                    <div>
                        <h2>Current Question</h2>
                        <p><?= htmlspecialchars($running_question["session_name"]) ?></p>
                    </div>
                    <a class="primary-btn"
                       href="presentation.php?session=<?= $running_question["session_id"] ?>">
                        Open Presentation
                    </a>
                </div>

                <h3><?= htmlspecialchars($running_question["question_text"]) ?></h3>
                <div class="presentation-options">
                    <?php foreach (["A", "B", "C", "D"] as $option): ?>
                        <?php $option_key = "option_" . strtolower($option); ?>
                        <div><strong><?= $option ?>.</strong> <?= htmlspecialchars($running_question[$option_key]) ?></div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- =================================================
             CREATED MESSAGE
        ================================================== -->

        <?php if (isset($_GET['created'])): ?>

            <div class="success-message">

                Session created successfully.

            </div>

        <?php endif; ?>


        <!-- =================================================
             CREATE SESSION
        ================================================== -->

        <section class="content-card session-create-card">

            <div class="session-create-header">
                <div class="session-create-title">
                    <span class="session-create-mark" aria-hidden="true">+</span>
                    <div>
                        <p class="session-create-kicker">SESSION SETUP</p>
                        <h2>Create a New Session</h2>
                        <p>Select a question set and create a new survey session.</p>
                    </div>
                </div>
                <span class="session-create-index" aria-hidden="true">01</span>
            </div>

            <form method="POST" class="session-create-form">

                <div class="session-create-fields">

                    <div class="session-create-field">
                        <label for="session_name">Session Name</label>
                        <input
                            type="text"
                            name="session_name"
                            id="session_name"
                            placeholder="e.g., IoT Survey - Week 3"
                            required
                        >
                    </div>

                    <div class="session-create-field">
                        <label for="question_set_id">Question Set</label>
                        <select
                            name="question_set_id"
                            id="question_set_id"
                            required
                        >

                            <option value="">
                                -- Select a question set --
                            </option>


                            <?php foreach (
                                $question_sets
                                as $set
                            ): ?>

                                <option
                                    value="<?= $set['id'] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $set['set_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                </div>

                <div class="session-create-actions">
                    <button
                        type="submit"
                        name="create_session"
                        class="primary-btn"
                    >
                        <span aria-hidden="true">+</span> Create Session
                    </button>
                </div>
            </form>

        </section>


        <!-- =================================================
             SESSION LIST
        ================================================== -->

        <div class="content-card">


            <div class="card-header">

                <div>

                    <h2>
                        Session List
                    </h2>


                    <p>
                        Survey sessions
                    </p>

                </div>

            </div>


            <?php if (
                count($sessions) === 0
            ): ?>

                <p>
                    No sessions found.
                </p>


            <?php else: ?>


                <div class="table-wrapper" tabindex="0" role="region" aria-label="Sessions table">


                    <table class="session-table">


                        <thead>

                            <tr>

                                <th>
                                    ID
                                </th>


                                <th>
                                    Session
                                </th>


                                <th>
                                    Question Set
                                </th>


                                <th>
                                    Status
                                </th>


                                <th>
                                    Current Question
                                </th>


                                <th>
                                    Responses
                                </th>

                                <th>
                                    Advance
                                </th>

                                <th>
                                    Time
                                </th>


                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $sessions
                            as $session
                        ): ?>


                            <tr>


                                <!-- ID -->

                                <td>

                                    <?= $session['id'] ?>

                                </td>


                                <!-- SESSION NAME -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $session[
                                                'session_name'
                                            ]
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- QUESTION SET -->

                                <td>

                                    <?= htmlspecialchars(
                                        $session[
                                            'set_name'
                                        ] ?? ''
                                    ) ?>

                                </td>


                                <!-- STATUS -->

                                <td>


                                    <?php if (
                                        $session['status']
                                        === 'waiting'
                                    ): ?>

                                        <span
                                            class="status waiting"
                                        >
                                            WAITING
                                        </span>


                                    <?php elseif (
                                        $session['status']
                                        === 'running'
                                    ): ?>

                                        <span
                                            class="status running"
                                        >
                                            RUNNING
                                        </span>


                                    <?php else: ?>

                                        <span
                                            class="status finished"
                                        >
                                            FINISHED
                                        </span>

                                    <?php endif; ?>


                                </td>


                                <!-- CURRENT QUESTION -->

                                <td>

                                    <?php if (
                                        $session[
                                            'current_question_id'
                                        ]
                                    ): ?>

                                        Question
                                        <?= $session[
                                            'current_question_id'
                                        ] ?>

                                    <?php else: ?>

                                        Not opened

                                    <?php endif; ?>

                                </td>


                                <!-- RESPONSES -->

                                <td>

                                    <?= $session[
                                        'response_count'
                                    ] ?>

                                </td>

                                <td>
                                    <?php if ($session["status"] !== "finished"): ?>
                                        <form method="POST" class="session-settings-form">
                                            <input type="hidden" name="session_id" value="<?= (int) $session["id"] ?>">
                                            <select name="advance_mode" aria-label="Question advance mode" title="Question advance mode">
                                                <option value="manual" <?= $session["advance_mode"] === "manual" ? "selected" : "" ?>>Manual</option>
                                                <option value="timer" <?= $session["advance_mode"] === "timer" ? "selected" : "" ?>>Timer</option>
                                                <option value="all_answered" <?= $session["advance_mode"] === "all_answered" ? "selected" : "" ?>>All answered</option>
                                            </select>
                                            <label class="seconds-field" title="Answer time in seconds">
                                                <input type="number" name="answer_time_seconds" min="5" max="3600"
                                                       value="<?= (int) $session["answer_time_seconds"] ?>"
                                                       aria-label="Answer time in seconds">
                                                <span>s</span>
                                            </label>
                                            <button type="submit" name="save_advance_settings" class="action-btn" title="Save advance settings">Save</button>
                                        </form>
                                    <?php else: ?>
                                        <?= htmlspecialchars(ucwords(str_replace("_", " ", $session["advance_mode"]))) ?>
                                    <?php endif; ?>
                                </td>

                                <!-- TIME -->

                                <td>


                                    <?php if (
                                        $session[
                                            'started_at'
                                        ]
                                    ): ?>

                                        <?= $session[
                                            'started_at'
                                        ] ?>

                                    <?php else: ?>

                                        Not started

                                    <?php endif; ?>


                                </td>


                                <!-- ACTIONS -->

                                <td>


                                    <?php if (
                                        $session['status']
                                        === 'waiting'
                                    ): ?>


                                        <!-- START -->

                                        <a
                                            href="sessions.php?start=<?= $session['id'] ?>"
                                            class="action-btn"
                                        >

                                            ▶ Start

                                        </a>


                                    <?php elseif (
                                        $session['status']
                                        === 'running'
                                    ): ?>


                                        <!-- PREVIOUS QUESTION -->

                                        <a
                                            href="sessions.php?prev=<?= $session['id'] ?>"
                                            class="action-btn"
                                        >

                                            ← Previous

                                        </a>


                                        <!-- NEXT QUESTION -->

                                        <a
                                            href="sessions.php?next=<?= $session['id'] ?>"
                                            class="action-btn"
                                        >

                                            Next →

                                        </a>


                                        <!-- LIVE RESULTS -->

                                        <a
                                            href="results.php?session=<?= $session['id'] ?>"
                                            class="action-btn"
                                        >

                                            Live Results

                                        </a>

                                        <a
                                            href="presentation.php?session=<?= $session['id'] ?>"
                                            class="action-btn"
                                        >
                                            Presentation
                                        </a>


                                        <!-- FINISH -->

                                        <a
                                            href="sessions.php?finish=<?= $session['id'] ?>"
                                            class="delete-btn"
                                            onclick="return confirm('Finish this session?');"
                                        >

                                            ■ Stop

                                        </a>


                                    <?php else: ?>


                                        <!-- FINISHED RESULTS -->

                                        <?php if (in_array($session['status'], ['finished', 'stopped'], true)): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['sessions_csrf_token']) ?>">
                                                <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                                                <button type="submit" name="session_action" value="reopen" class="action-btn">▶ Reopen</button>
                                            </form>
                                        <?php endif; ?>

                                        <a
                                            href="results.php?session=<?= $session['id'] ?>"
                                            class="action-btn"
                                        >

                                            Results

                                        </a>


                                    <?php endif; ?>

                                    <form method="POST" style="display: inline;"
                                          onsubmit="return confirm('Delete this session and all its responses? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['sessions_csrf_token']) ?>">
                                        <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                                        <button type="submit" name="session_action" value="delete" class="delete-btn">Delete</button>
                                    </form>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php endif; ?>


        </div>


    <footer class="workspace-footer"><span>© <?= date('Y') ?> Astralis</span><span>Ask. Connect. Understand.</span></footer>
    </main>

</div>

</body>

</html>
