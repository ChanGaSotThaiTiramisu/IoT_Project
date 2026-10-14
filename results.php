<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

require_once "account.php";

if (!empty($_GET['session'])) { account_require_owned($conn, 'sessions', (int) $_GET['session']); }



/* =====================================================
   SESSION ID
===================================================== */

$session_id = isset($_GET["session"])
    ? intval($_GET["session"])
    : 0;

if ($session_id <= 0) {
    require __DIR__ . '/results_overview.php';
    exit;
}


/* =====================================================
   LẤY THÔNG TIN SESSION
===================================================== */

$stmt = $conn->prepare(
    "SELECT
        s.id,
        s.session_name,
        s.status,
        s.started_at,
        s.ended_at,
        qs.set_name
     FROM sessions s
     JOIN question_sets qs
        ON s.question_set_id = qs.id
     WHERE s.id = ?"
);

$stmt->bind_param("i", $session_id);
$stmt->execute();

$session_result = $stmt->get_result();

$session = $session_result->fetch_assoc();

$stmt->close();


if (!$session) {
    die("Session not found.");
}


/* =====================================================
   LẤY DANH SÁCH CÂU HỎI
===================================================== */

$stmt = $conn->prepare(
    "SELECT
        id,
        question_text,
        option_a,
        option_b,
        option_c,
        option_d,
        correct_answer
     FROM questions
     WHERE question_set_id = (
        SELECT question_set_id
        FROM sessions
        WHERE id = ?
     )
     ORDER BY id ASC"
);

$stmt->bind_param("i", $session_id);

$stmt->execute();

$questions_result = $stmt->get_result();

$questions = [];

while ($row = $questions_result->fetch_assoc()) {

    $questions[] = $row;

}

$stmt->close();


/* =====================================================
   LẤY RESPONSE BAN ĐẦU
===================================================== */

$response_counts = [];

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

$count_result = $stmt->get_result();


while ($row = $count_result->fetch_assoc()) {

    $question_id = $row["question_id"];

    $answer = $row["answer"];


    if (!isset($response_counts[$question_id])) {

        $response_counts[$question_id] = [
            "A" => 0,
            "B" => 0,
            "C" => 0,
            "D" => 0
        ];

    }


    if (
        isset(
            $response_counts[$question_id][$answer]
        )
    ) {

        $response_counts[$question_id][$answer] =
            intval($row["total"]);

    }

}

$stmt->close();

/* =====================================================
   DEVICE RESULT MATRIX
===================================================== */

$device_results = [];

$stmt = $conn->prepare(
    "SELECT
        d.id AS device_id,
        d.device_code,
        d.device_name,
        s.student_code,
        s.full_name,
        r.question_id,
        r.answer
     FROM devices d
     LEFT JOIN students s ON s.id = d.student_id AND s.user_id = d.user_id
     LEFT JOIN responses r
        ON r.device_id = d.id
       AND r.session_id = ?
     WHERE d.user_id = {$account_id} AND d.status = 'online'
     ORDER BY COALESCE(s.full_name, d.device_name, d.device_code), d.id, r.question_id"
);
$stmt->bind_param("i", $session_id);
$stmt->execute();
$matrix_result = $stmt->get_result();

while ($row = $matrix_result->fetch_assoc()) {
    $device_id = (int) $row["device_id"];

    if (!isset($device_results[$device_id])) {
        $device_results[$device_id] = [
            "device_code" => $row["device_code"],
            "device_name" => $row["device_name"],
            "student_code" => $row["student_code"],
            "full_name" => $row["full_name"],
            "answers" => [],
        ];
    }

    if ($row["question_id"] !== null) {
        $device_results[$device_id]["answers"][(int) $row["question_id"]] = $row["answer"];
    }
}

$stmt->close();

$question_count = count($questions);
$matrix_total_answered = 0;
$matrix_total_correct = 0;

foreach ($device_results as &$device_result) {
    $correct = 0;
    $answered = count($device_result["answers"]);

    foreach ($questions as $question) {
        $question_id = (int) $question["id"];
        if (
            isset($device_result["answers"][$question_id])
            && $device_result["answers"][$question_id] === $question["correct_answer"]
        ) {
            $correct++;
        }
    }

    $device_result["answered"] = $answered;
    $device_result["correct"] = $correct;
    $device_result["score"] = $question_count > 0
        ? (int) round(($correct / $question_count) * 100)
        : 0;
    $matrix_total_answered += $answered;
    $matrix_total_correct += $correct;
}
unset($device_result);

$matrix_average = count($device_results) > 0
    ? (int) round($matrix_total_correct / (count($device_results) * max($question_count, 1)) * 100)
    : 0;

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Astralis | Results</title>

<link
    rel="stylesheet"
    href="css/style.css?v=202610061407"
>


<style>

/* =====================================================
   HEADER
===================================================== */

.results-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

}


.session-info {

    color: #6b7280;

    font-size: 14px;

    margin-top: 6px;

}


/* =====================================================
   LIVE BADGE
===================================================== */

.live-badge {

    display: inline-flex;

    align-items: center;

    padding: 7px 13px;

    border-radius: 20px;

    background: #dcfce7;

    color: #166534;

    font-size: 12px;

    font-weight: bold;

}


.live-dot {

    margin-right: 6px;

}


/* =====================================================
   QUESTION CARD
===================================================== */

.question-result {

    background: white;

    border-radius: 12px;

    padding: 25px;

    margin-bottom: 25px;

    border: 1px solid #e5e7eb;

    box-shadow: 0 2px 6px rgba(0,0,0,0.03);

}


.question-title {

    font-size: 18px;

    font-weight: 600;

    margin-bottom: 22px;

    line-height: 1.5;

}


/* =====================================================
   OPTION
===================================================== */

.option-row {

    margin-bottom: 17px;

}


.option-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 7px;

    font-size: 14px;

}


.option-text {

    flex: 1;

}


.option-count {

    white-space: nowrap;

    font-weight: bold;

}


/* =====================================================
   PROGRESS BAR
===================================================== */

.bar-container {

    width: 100%;

    height: 28px;

    background: #f1f5f9;

    border-radius: 7px;

    overflow: hidden;

}


.bar {

    height: 100%;

    width: 0%;

    border-radius: 7px;

    transition:
        width 0.35s ease;

}


.bar-a {

    background: #ef4444;

}


.bar-b {

    background: #f59e0b;

}


.bar-c {

    background: #22c55e;

}


.bar-d {

    background: #3b82f6;

}


/* =====================================================
   TOTAL
===================================================== */

.total-response {

    margin-top: 22px;

    padding-top: 15px;

    border-top: 1px solid #e5e7eb;

    color: #6b7280;

    font-size: 13px;

}


.total-response strong {

    color: #111827;

}


/* =====================================================
   NO RESPONSE
===================================================== */

.no-response {

    margin-top: 20px;

    padding: 12px 15px;

    background: #f9fafb;

    border-radius: 8px;

    color: #6b7280;

    font-size: 13px;

}


/* =====================================================
   STATUS
===================================================== */

.status-normal {

    padding: 6px 11px;

    border-radius: 15px;

    background: #e5e7eb;

    color: #374151;

    font-size: 12px;

    font-weight: bold;

}

/* =====================================================
   DEVICE RESULT MATRIX
===================================================== */

.matrix-panel {
    margin-bottom: 28px;
    padding: 26px;
    border: 1px solid #eaded2;
    border-radius: 16px;
    background:
        linear-gradient(135deg, rgba(255, 247, 239, 0.94), #ffffff 44%),
        #ffffff;
    box-shadow: 0 12px 28px rgba(117, 68, 36, 0.08);
}

.matrix-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 22px;
}

.matrix-heading h2 {
    margin: 0 0 5px;
    color: #4a2d20;
    font-size: 22px;
}

.matrix-heading p {
    color: #8b6b59;
    font-size: 13px;
}

.matrix-stat-list {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.matrix-stat {
    min-width: 88px;
    padding: 10px 12px;
    border: 1px solid #f0ddd0;
    border-radius: 9px;
    background: #ffffff;
    text-align: center;
}

.matrix-stat strong {
    display: block;
    color: #c65f2e;
    font-size: 20px;
}

.matrix-stat span {
    color: #967b6a;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}

.matrix-tools {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-bottom: 14px;
    color: #765847;
    font-size: 12px;
}

.matrix-tools label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}

.matrix-scroll {
    overflow-x: auto;
    border: 1px solid #eddfd4;
    border-radius: 10px;
    background: #ffffff;
}

.matrix-table {
    width: 100%;
    min-width: 760px;
    border-collapse: separate;
    border-spacing: 4px;
}

.matrix-table th {
    padding: 9px 8px;
    background: #fff8f2;
    color: #725240;
    font-size: 11px;
    font-weight: 800;
    text-align: center;
    white-space: nowrap;
}

.matrix-table th:first-child,
.matrix-table td:first-child {
    position: sticky;
    left: 0;
    z-index: 1;
}

.matrix-table th:first-child {
    z-index: 2;
}

.matrix-table td {
    padding: 0;
    border: 0;
    background: transparent;
    text-align: center;
}

.matrix-person {
    min-width: 190px;
    padding: 10px 12px !important;
    background: #fffaf6 !important;
    color: #513426;
    text-align: left !important;
}

.matrix-person strong,
.matrix-person small {
    display: block;
}

.matrix-person strong {
    font-size: 13px;
}

.matrix-person small {
    margin-top: 3px;
    color: #9b7c68;
    font-size: 10px;
}

.matrix-score {
    width: 74px;
    padding: 10px 7px !important;
    background: #fffaf6 !important;
    color: #c65f2e;
    font-size: 15px !important;
    font-weight: 800;
}

.matrix-cell {
    min-width: 82px;
    padding: 11px 8px !important;
    border-radius: 5px !important;
    font-size: 13px !important;
    font-weight: 700;
}

.matrix-cell.correct {
    background: #d8f1df !important;
    color: #286342;
}

.matrix-cell.incorrect {
    background: #f8c9c2 !important;
    color: #9d3d31;
}

.matrix-cell.empty {
    background: #f2f4f5 !important;
    color: #b1b8bd;
}

.matrix-answer {
    display: block;
    font-size: 14px;
}

.matrix-answer-detail {
    display: block;
    margin-top: 3px;
    font-size: 10px;
    font-weight: 500;
    opacity: 0.78;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.matrix-empty {
    padding: 28px !important;
    color: #8b6b59 !important;
    text-align: center !important;
}

@media (max-width: 700px) {
    .matrix-panel {
        padding: 18px;
    }

    .matrix-heading {
        flex-direction: column;
    }
}

/* =====================================================
   MOBILE
===================================================== */

@media (max-width: 700px) {

    .results-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 12px;

    }

    .question-result {

        padding: 18px;

    }

    .option-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 4px;

    }

}

</style>

    <link rel="stylesheet" href="css/workspace.css?v=20261006-3">
    <script src="js/workspace.js?v=20261006" defer></script>
</head>


<body class="workspace-page results-workspace">


<div class="layout">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<?php $ars_active_page = 'results'; require __DIR__ . '/admin_sidebar.php'; ?>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="main-content">
        <?php $workspace_page = ['title' => 'Results', 'hero' => false]; require __DIR__ . '/workspace_header.php'; ?>



<!-- =====================================================
     HEADER
===================================================== -->

<div class="results-header">

    <div>

        <h1>
            Live Results
        </h1>


        <div class="session-info">

            Session:

            <strong>
                <?= htmlspecialchars(
                    $session["session_name"]
                ) ?>
            </strong>


            &nbsp; | &nbsp;


            Question Set:

            <strong>
                <?= htmlspecialchars(
                    $session["set_name"]
                ) ?>
            </strong>

        </div>

    </div>


    <?php if ($session["status"] === "running"): ?>

        <span class="live-badge">

            <span class="live-dot">
                ●
            </span>

            LIVE

        </span>

    <?php else: ?>

        <span class="status-normal">

            <?= htmlspecialchars(
                strtoupper(
                    $session["status"]
                )
            ) ?>

        </span>

    <?php endif; ?>

</div>

<section class="matrix-panel">
    <div class="matrix-heading">
        <div>
            <h2>Online device performance</h2>
            <p>Overview of answers from currently online devices.</p>
        </div>
        <div class="matrix-stat-list">
            <div class="matrix-stat">
                <strong><?= count($device_results) ?></strong>
                <span>Online devices</span>
            </div>
            <div class="matrix-stat">
                <strong><?= $question_count ?></strong>
                <span>Questions</span>
            </div>
            <div class="matrix-stat">
                <strong><?= $matrix_average ?>%</strong>
                <span>Average</span>
            </div>
        </div>
    </div>

    <div class="matrix-tools">
        <label>
            <input type="checkbox" id="toggle-device-names" checked>
            Show names
        </label>
        <label>
            <input type="checkbox" id="toggle-answer-details" checked>
            Show answers
        </label>
    </div>

    <div class="matrix-scroll" tabindex="0" role="region" aria-label="Results table">
        <table class="matrix-table">
            <thead>
                <tr>
                    <th>DEVICE / STUDENT</th>
                    <th>SCORE</th>
                    <?php foreach ($questions as $index => $question): ?>
                        <th>Q<?= $index + 1 ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (count($device_results) === 0): ?>
                    <tr><td class="matrix-empty" colspan="<?= $question_count + 2 ?>">No device responses yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($device_results as $device_result): ?>
                        <tr>
                            <td class="matrix-person">
                                <strong class="device-display-name">
                                    <?= htmlspecialchars($device_result["full_name"] ?: $device_result["device_name"] ?: $device_result["device_code"]) ?>
                                </strong>
                                <small>
                                    <?= htmlspecialchars($device_result["student_code"] ?: $device_result["device_code"]) ?>
                                </small>
                            </td>
                            <td class="matrix-score"><?= $device_result["score"] ?>%</td>
                            <?php foreach ($questions as $question): ?>
                                <?php
                                $question_id = (int) $question["id"];
                                $answer = $device_result["answers"][$question_id] ?? null;
                                $is_correct = $answer !== null && $answer === $question["correct_answer"];
                                $cell_class = $answer === null ? "empty" : ($is_correct ? "correct" : "incorrect");
                                ?>
                                <td class="matrix-cell <?= $cell_class ?>">
                                    <span class="matrix-answer">
                                        <?= $answer === null ? "—" : ($is_correct ? "✓ " : "✕ ") . htmlspecialchars($answer) ?>
                                    </span>
                                    <span class="matrix-answer-detail">
                                        <?= $answer === null ? "Not answered" : ($is_correct ? "Correct" : "Incorrect") ?>
                                    </span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- =====================================================
     QUESTIONS
===================================================== -->

<?php foreach ($questions as $question): ?>


<?php

$question_id =
    intval($question["id"]);


$counts =
    $response_counts[$question_id]
    ?? [
        "A" => 0,
        "B" => 0,
        "C" => 0,
        "D" => 0
    ];


$total =

    $counts["A"] +

    $counts["B"] +

    $counts["C"] +

    $counts["D"];


/* =========================
   PERCENT
========================= */

$percent_a =
    $total > 0
    ? ($counts["A"] / $total) * 100
    : 0;


$percent_b =
    $total > 0
    ? ($counts["B"] / $total) * 100
    : 0;


$percent_c =
    $total > 0
    ? ($counts["C"] / $total) * 100
    : 0;


$percent_d =
    $total > 0
    ? ($counts["D"] / $total) * 100
    : 0;

?>


<div class="question-result">


<div class="question-title">

    Question <?= $question_id ?>:

    <?= htmlspecialchars(
        $question["question_text"]
    ) ?>

</div>


<!-- =====================================================
     A
===================================================== -->

<div class="option-row">

    <div class="option-header">

        <div class="option-text">

            <strong>A.</strong>

            <?= htmlspecialchars(
                $question["option_a"]
            ) ?>

        </div>


        <div class="option-count">

            <span
                id="count-<?= $question_id ?>-A"
            >
                <?= $counts["A"] ?>
            </span>

            (

            <span
                id="percent-<?= $question_id ?>-A"
            >
                <?= round($percent_a) ?>
            </span>

            %)

        </div>

    </div>


    <div class="bar-container">

        <div

            id="bar-<?= $question_id ?>-A"

            class="bar bar-a"

            style="
                width:
                <?= $percent_a ?>%;
            "

        ></div>

    </div>

</div>


<!-- =====================================================
     B
===================================================== -->

<div class="option-row">

    <div class="option-header">

        <div class="option-text">

            <strong>B.</strong>

            <?= htmlspecialchars(
                $question["option_b"]
            ) ?>

        </div>


        <div class="option-count">

            <span
                id="count-<?= $question_id ?>-B"
            >
                <?= $counts["B"] ?>
            </span>

            (

            <span
                id="percent-<?= $question_id ?>-B"
            >
                <?= round($percent_b) ?>
            </span>

            %)

        </div>

    </div>


    <div class="bar-container">

        <div

            id="bar-<?= $question_id ?>-B"

            class="bar bar-b"

            style="
                width:
                <?= $percent_b ?>%;
            "

        ></div>

    </div>

</div>


<!-- =====================================================
     C
===================================================== -->

<div class="option-row">

    <div class="option-header">

        <div class="option-text">

            <strong>C.</strong>

            <?= htmlspecialchars(
                $question["option_c"]
            ) ?>

        </div>


        <div class="option-count">

            <span
                id="count-<?= $question_id ?>-C"
            >
                <?= $counts["C"] ?>
            </span>

            (

            <span
                id="percent-<?= $question_id ?>-C"
            >
                <?= round($percent_c) ?>
            </span>

            %)

        </div>

    </div>


    <div class="bar-container">

        <div

            id="bar-<?= $question_id ?>-C"

            class="bar bar-c"

            style="
                width:
                <?= $percent_c ?>%;
            "

        ></div>

    </div>

</div>


<!-- =====================================================
     D
===================================================== -->

<div class="option-row">

    <div class="option-header">

        <div class="option-text">

            <strong>D.</strong>

            <?= htmlspecialchars(
                $question["option_d"]
            ) ?>

        </div>


        <div class="option-count">

            <span
                id="count-<?= $question_id ?>-D"
            >
                <?= $counts["D"] ?>
            </span>

            (

            <span
                id="percent-<?= $question_id ?>-D"
            >
                <?= round($percent_d) ?>
            </span>

            %)

        </div>

    </div>


    <div class="bar-container">

        <div

            id="bar-<?= $question_id ?>-D"

            class="bar bar-d"

            style="
                width:
                <?= $percent_d ?>%;
            "

        ></div>

    </div>

</div>


<!-- =====================================================
     TOTAL
===================================================== -->

<?php if ($total > 0): ?>

<div class="total-response">

    Total responses:

    <strong
        id="total-<?= $question_id ?>"
    >
        <?= $total ?>
    </strong>

</div>

<?php else: ?>

<div
    class="no-response"
    id="no-response-<?= $question_id ?>"
>

    No responses yet.

</div>

<div
    class="total-response"
    id="total-box-<?= $question_id ?>"
    style="display:none;"
>

    Total responses:

    <strong
        id="total-<?= $question_id ?>"
    >
        0
    </strong>

</div>

<?php endif; ?>


</div>


<?php endforeach; ?>


<footer class="workspace-footer"><span>© <?= date('Y') ?> Astralis</span><span>Ask. Connect. Understand.</span></footer>
    </main>

</div>


<!-- =====================================================
     REALTIME JAVASCRIPT
===================================================== -->

<script>

const namesToggle = document.getElementById("toggle-device-names");
const answersToggle = document.getElementById("toggle-answer-details");

if (namesToggle) {
    namesToggle.addEventListener("change", function () {
        document.querySelectorAll(".device-display-name").forEach(function (name) {
            name.style.visibility = namesToggle.checked ? "visible" : "hidden";
        });
    });
}

if (answersToggle) {
    answersToggle.addEventListener("change", function () {
        document.querySelectorAll(".matrix-answer-detail").forEach(function (detail) {
            detail.style.display = answersToggle.checked ? "block" : "none";
        });
    });
}

const sessionId = <?= $session_id ?>;

async function updateResults() {

    try {

        const response = await fetch(
            "api_results.php?session=" +
            sessionId +
            "&t=" +
            Date.now()
        );

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        Object.keys(data).forEach(function(questionId) {

            const result = data[questionId];

            const counts = {
                A: Number(result.A || 0),
                B: Number(result.B || 0),
                C: Number(result.C || 0),
                D: Number(result.D || 0)
            };

            const total =
                counts.A +
                counts.B +
                counts.C +
                counts.D;

            updateOption(
                questionId,
                "A",
                counts.A,
                total
            );

            updateOption(
                questionId,
                "B",
                counts.B,
                total
            );

            updateOption(
                questionId,
                "C",
                counts.C,
                total
            );

            updateOption(
                questionId,
                "D",
                counts.D,
                total
            );

            updateTotal(
                questionId,
                total
            );

        });

    } catch (error) {

        console.log(
            "Không thể cập nhật Live Results:",
            error
        );

    }

}


function updateOption(
    questionId,
    answer,
    count,
    total
) {

    const countElement =
        document.getElementById(
            "count-" +
            questionId +
            "-" +
            answer
        );

    const percentElement =
        document.getElementById(
            "percent-" +
            questionId +
            "-" +
            answer
        );

    const barElement =
        document.getElementById(
            "bar-" +
            questionId +
            "-" +
            answer
        );


    if (!countElement) {
        return;
    }


    // Cập nhật số lượng
    countElement.innerText = count;


    // Tính phần trăm
    const percent =
        total > 0
            ? (count / total) * 100
            : 0;


    // Cập nhật %
    if (percentElement) {

        percentElement.innerText =
            percent.toFixed(1);

    }


    // Cập nhật thanh bar
    if (barElement) {

        barElement.style.width =
            percent + "%";

    }

}


function updateTotal(
    questionId,
    total
) {

    const totalElements =
        document.querySelectorAll(
            ".total-response"
        );

    totalElements.forEach(function(element) {

        const questionElement =
            element.closest(".question-card");

        if (!questionElement) {
            return;
        }

        const strong =
            element.querySelector("strong");

        if (strong) {
            strong.innerText = total;
        }

    });

}


// Cập nhật ngay khi mở trang
updateResults();

// Sau đó cập nhật mỗi 1 giây
setInterval(
    updateResults,
    1000
);


<?php if ($session["status"] === "running"): ?>
setInterval(function () {
    window.location.reload();
}, 10000);
<?php endif; ?>

</script>


</body>

</html>
