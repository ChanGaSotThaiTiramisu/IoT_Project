<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once "account.php";

foreach (['delete_set' => 'question_sets', 'delete_question' => 'questions', 'set' => 'question_sets'] as $key => $table) {
    if (!empty($_GET[$key])) { account_require_owned($conn, $table, (int) $_GET[$key]); }
}


/* =========================
   DELETE QUESTION SET
========================= */
if (isset($_GET['delete_set'])) {
    $id = intval($_GET['delete_set']);

    $stmt = $conn->prepare("DELETE FROM question_sets WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: questions.php?deleted=1");
    exit;
}

/* =========================
   DELETE QUESTION
========================= */
if (isset($_GET['delete_question'])) {
    $id = intval($_GET['delete_question']);

    $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: questions.php?deleted_question=1");
    exit;
}

/* =========================
   CREATE QUESTION SET
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['create_set'])) {

    $set_name = trim($_POST['set_name']);
    $description = trim($_POST['description']);

    if ($set_name !== "") {

        $stmt = $conn->prepare(
            "INSERT INTO question_sets(user_id, set_name, description)
             VALUES ({$account_id}, ?, ?)"
        );

        $stmt->bind_param("ss", $set_name, $description);
        $stmt->execute();

        header("Location: questions.php?created=1");
        exit;
    }
}

/* =========================
   GET QUESTION SETS
========================= */

$sets = [];
$total_questions = 0;

$result = $conn->query(
    "SELECT qs.*,
            COUNT(q.id) AS question_count
     FROM question_sets qs
     LEFT JOIN questions q
       ON qs.id = q.question_set_id
     WHERE qs.user_id = {$account_id}
     GROUP BY qs.id
     ORDER BY qs.id DESC"
);

while ($row = $result->fetch_assoc()) {
    $sets[] = $row;
    $total_questions += (int) $row['question_count'];
}

/* =========================
   SELECT SET
========================= */

$selected_set = isset($_GET['set'])
    ? intval($_GET['set'])
    : 0;

$questions = [];

if ($selected_set > 0) {

    $stmt = $conn->prepare(
        "SELECT *
         FROM questions
         WHERE question_set_id = ?
         ORDER BY id ASC"
    );

    $stmt->bind_param("i", $selected_set);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
}


$selected_name = '';
foreach ($sets as $set) {
    if ((int) $set['id'] === $selected_set) {
        $selected_name = $set['set_name'];
        break;
    }
}

function qb_icon(string $name): string {
    $paths = [
        'folder' => '<path d="M3 7V5a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>',
        'layers' => '<path d="m12 3 10 5-10 5L2 8l10-5Zm-10 9 10 5 10-5M2 16l10 5 10-5"/>',
        'question' => '<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M9 9a3 3 0 0 1 6 0c0 2-3 2-3 4m0 3h.01"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'upload' => '<path d="M12 16V3m-5 5 5-5 5 5M4 15v5a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'spark' => '<path d="m12 2 3 7 7 3-7 3-3 7-3-7-7-3 7-3 3-7Z"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'trash' => '<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>',
    ];
    return '<svg viewBox="0 0 24 24" class="qb-icon" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f6f7f9">
    <title>Astralis | Question Bank</title>
    <link rel="stylesheet" href="css/style.css?v=202610061407">
    <link rel="stylesheet" href="css/questions.css?v=20261006-3">
    <script src="js/questions.js?v=20261006-2" defer></script>
    <link rel="stylesheet" href="css/workspace.css?v=20261006-3">
    <script src="js/workspace.js?v=20261006" defer></script>
</head>
<body class="question-bank-page workspace-page">
<div class="layout">
    <?php $ars_active_page = 'questions'; require __DIR__ . '/admin_sidebar.php'; ?>
    <main class="main-content">
        <div class="qb-ambient" aria-hidden="true">
            <?php foreach ([[2,9,.7],[19,1,.4],[43,3,.6],[76,0,.5],[94,12,.9],[4,40,.4],[91,39,.5],[1,70,.8],[96,72,.6],[20,91,.5],[49,93,.4],[77,90,.7],[64,5,.3],[8,90,.35],[92,95,.4],[35,85,.3]] as $i => [$x,$y,$scale]): ?>
                <span style="--x:<?= $x ?>%;--y:<?= $y ?>%;--scale:<?= $scale ?>;--delay:-<?= $i * .7 ?>s"></span>
            <?php endforeach; ?>
        </div>

        <div class="qb-topline">
            <p class="qb-breadcrumb">Workspace <span>/</span> <strong>Question Bank</strong></p>
            <div class="qb-account"><?= qb_icon('user') ?><span><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Teacher workspace', ENT_QUOTES, 'UTF-8') ?></span></div>
        </div>

        <header class="qb-header">
            <div class="qb-heading">
                <p class="qb-eyebrow"><span class="qb-dot"></span> A SPACE FOR GREAT QUESTIONS</p>
                <h1>Question Bank<span>.</span></h1>
                <p>Thoughtful questions. Meaningful conversations.<br>Build your next great classroom moment here.</p>
                <a href="import_questions.php" class="primary-btn qb-upload-btn"><?= qb_icon('upload') ?> Import Questions</a>
            </div>
            <div class="qb-hero-art" aria-hidden="true">
                <span class="qb-art-orbit"></span>
                <span class="qb-art-spark">✳</span>
                <div class="qb-art-card qb-art-back"></div>
                <div class="qb-art-card qb-art-front">
                    <div class="qb-art-label"><?= qb_icon('question') ?><span>A LITTLE CURIOSITY</span><span>01</span></div>
                    <strong>What will you ask next?</strong>
                    <div class="qb-art-answer"><span>A</span> A new perspective</div>
                    <div class="qb-art-answer is-chosen"><span>B</span> A shared discovery <?= qb_icon('check') ?></div>
                </div>
                <div class="qb-art-caption"><?= qb_icon('spark') ?> Every answer opens a possibility.</div>
            </div>
        </header>

        <section class="qb-overview" aria-label="Question Bank Overview">
            <div class="qb-metric"><span class="qb-metric-icon"><?= qb_icon('folder') ?></span><div><span class="qb-metric-label">QUESTION SETS</span><strong><?= count($sets) ?></strong><span class="qb-metric-note">Collections in your library</span></div></div>
            <div class="qb-metric"><span class="qb-metric-icon"><?= qb_icon('layers') ?></span><div><span class="qb-metric-label">TOTAL QUESTIONS</span><strong><?= $total_questions ?></strong><span class="qb-metric-note">Ideas ready for your classroom</span></div></div>
            <div class="qb-metric qb-metric-accent"><span class="qb-metric-icon"><?= qb_icon('target') ?></span><div><span class="qb-metric-label">CURRENT SET</span><strong><?= $selected_set > 0 ? count($questions) : '—' ?></strong><span class="qb-metric-note"><?= $selected_set > 0 ? 'Questions in ' . htmlspecialchars($selected_name, ENT_QUOTES, 'UTF-8') : 'Open a set to explore its questions' ?></span></div></div>
        </section>

        <?php foreach (['created' => 'Question set created successfully.', 'deleted' => 'Question set deleted.', 'deleted_question' => 'Question deleted.'] as $flag => $message): ?>
            <?php if (isset($_GET[$flag])): ?><div class="success-message qb-notice" role="status"><?= qb_icon('check') ?> <?= $message ?></div><?php endif; ?>
        <?php endforeach; ?>

        <div class="qb-workspace">
            <section class="content-card qb-card qb-create-card" aria-labelledby="create-title">
                <div class="qb-section-heading"><span class="qb-section-icon"><?= qb_icon('plus') ?></span><div><p class="qb-section-kicker">START SOMETHING GOOD</p><h2 id="create-title">Create a question set</h2></div></div>
                <p class="qb-section-description">Give your ideas a place to grow.</p>
                <form method="POST" class="qb-create-form">
                    <div class="form-row">
                        <div><label for="set_name">Question set name</label><input type="text" name="set_name" id="set_name" placeholder="e.g., IoT Quiz — Week 1" maxlength="255" required></div>
                        <div><label for="description">Description <span>optional</span></label><textarea name="description" id="description" placeholder="What is this set about?" rows="3"></textarea></div>
                    </div>
                    <button type="submit" name="create_set" class="primary-btn"><?= qb_icon('plus') ?> Create Question Set</button>
                </form>
                <p class="qb-create-note"><?= qb_icon('spark') ?><span>Start with a question.<br>Make room for every voice.</span></p>
            </section>

            <section class="content-card qb-card qb-sets-card" aria-labelledby="library-title">
                <div class="card-header"><div><p class="qb-section-kicker">YOUR TEACHING TOOLKIT</p><h2 id="library-title">Question library <span class="qb-total-badge"><?= count($sets) ?></span></h2><p>All your question sets, ready for the next lesson.</p></div><span class="qb-library-icon" aria-hidden="true"><?= qb_icon('layers') ?></span></div>
                <?php if (count($sets) > 0): ?>
                    <div class="qb-library-toolbar" data-library-tools hidden>
                        <label class="qb-search" for="qb-search"><?= qb_icon('search') ?><input id="qb-search" type="search" placeholder="Search question sets…" aria-label="Search question sets by name or description" aria-controls="qb-set-rows" autocomplete="off"></label>
                        <span class="qb-search-count" data-search-count role="status"><?= count($sets) ?> sets</span>
                    </div>
                    <div class="table-wrapper" tabindex="0" role="region" aria-label="Question sets">
                        <table>
                            <thead><tr><th scope="col">QUESTION SET</th><th scope="col">QUESTIONS</th><th scope="col">CREATED</th><th scope="col">ACTIONS</th></tr></thead>
                            <tbody id="qb-set-rows">
                            <?php foreach ($sets as $set): ?>
                                <tr data-question-set class="<?= (int) $set['id'] === $selected_set ? 'is-selected' : '' ?>">
                                    <td><div class="qb-set-cell"><span class="qb-folder-icon"><?= qb_icon('folder') ?></span><div><a class="qb-set-link" href="questions.php?set=<?= (int) $set['id'] ?>" <?= (int) $set['id'] === $selected_set ? 'aria-current="true"' : '' ?>><?= htmlspecialchars($set['set_name'], ENT_QUOTES, 'UTF-8') ?></a><p class="qb-set-description"><?= htmlspecialchars($set['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p><span class="qb-set-id">SET #<?= (int) $set['id'] ?></span></div></div></td>
                                    <td><span class="qb-question-count"><?= (int) $set['question_count'] ?></span></td>
                                    <td class="qb-created-at"><?= htmlspecialchars($set['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><div class="qb-row-actions"><a href="questions.php?set=<?= (int) $set['id'] ?>" class="action-btn" aria-label="Open <?= htmlspecialchars($set['set_name'], ENT_QUOTES, 'UTF-8') ?>"><?= (int) $set['id'] === $selected_set ? 'Opened' : 'Open' ?> <?= qb_icon('arrow') ?></a><a href="questions.php?delete_set=<?= (int) $set['id'] ?>" class="delete-btn qb-icon-button" aria-label="Delete <?= htmlspecialchars($set['set_name'], ENT_QUOTES, 'UTF-8') ?>" title="Delete question set" onclick="return confirm('Delete this question set?');"><?= qb_icon('trash') ?></a></div></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="qb-empty-state qb-no-results" data-no-results hidden><?= qb_icon('search') ?><h3>No matching question sets</h3><p>Try another name or description.</p></div>
                    <p class="qb-library-note"><?= qb_icon('folder') ?> Open a set to view its questions and answer choices.</p>
                <?php else: ?>
                    <div class="qb-empty-state"><span class="qb-empty-mark"><?= qb_icon('folder') ?></span><h3>A fresh page for your ideas</h3><p>Create your first question set, or bring in questions you already have.</p><a href="import_questions.php" class="action-btn"><?= qb_icon('upload') ?> Import your first questions</a></div>
                <?php endif; ?>
            </section>
        </div>

        <?php if ($selected_set > 0): ?>
            <section class="content-card qb-card qb-questions-card" aria-labelledby="selected-title">
                <div class="card-header"><div><p class="qb-section-kicker">INSIDE YOUR QUESTION SET</p><h2 id="selected-title"><?= htmlspecialchars($selected_name, ENT_QUOTES, 'UTF-8') ?></h2><p><?= count($questions) ?> questions · Ready to spark a conversation</p></div><a href="import_questions.php?set=<?= $selected_set ?>" class="primary-btn"><?= qb_icon('plus') ?> Add Questions</a></div>
                <?php if (count($questions) === 0): ?>
                    <div class="qb-empty-state"><span class="qb-empty-mark"><?= qb_icon('question') ?></span><h3>Your next great question goes here</h3><p>Import a question file to add content to this set.</p></div>
                <?php else: ?>
                    <?php foreach ($questions as $index => $q): ?>
                        <article class="question-item qb-question-item">
                            <div class="qb-question-top"><span class="qb-question-number">QUESTION <?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span><a href="questions.php?delete_question=<?= (int) $q['id'] ?>&set=<?= $selected_set ?>" class="delete-btn qb-icon-button" aria-label="Delete question <?= $index + 1 ?>" title="Delete question" onclick="return confirm('Delete this question?');"><?= qb_icon('trash') ?></a></div>
                            <h3 class="question-title qb-question-title"><?= htmlspecialchars($q['question_text'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <div class="question-options">
                                <?php foreach (['A', 'B', 'C', 'D'] as $choice): ?>
                                    <div class="<?= strtoupper($q['correct_answer'] ?? '') === $choice ? 'is-correct' : '' ?>"><strong><?= $choice ?></strong><span><?= htmlspecialchars($q['option_' . strtolower($choice)], ENT_QUOTES, 'UTF-8') ?></span><?php if (strtoupper($q['correct_answer'] ?? '') === $choice): ?><span class="qb-correct-label"><?= qb_icon('check') ?> Correct</span><?php endif; ?></div>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <footer class="qb-footer"><span>© <?= date('Y') ?> Astralis</span><span>Ask. Connect. Understand.</span></footer>
    </main>
</div>
</body>
</html>
