<?php
// Rendered by results.php after its authentication and database checks.
if (!isset($conn)) {
    header('Location: results.php');
    exit;
}

$result_sessions = $conn->query(
    "SELECT s.id, s.session_name, s.status, s.started_at, qs.set_name
     FROM sessions s
     LEFT JOIN question_sets qs ON qs.id = s.question_set_id
     WHERE s.user_id = {$account_id}
     ORDER BY s.id DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Astralis | Results</title>
    <link rel="stylesheet" href="css/style.css?v=202610061407">
    <link rel="stylesheet" href="css/workspace.css?v=20261006-3">
    <script src="js/workspace.js?v=20261006" defer></script>
</head>
<body class="workspace-page results-workspace">
<div class="layout">
    <?php $ars_active_page = 'results'; require __DIR__ . '/admin_sidebar.php'; ?>
    <main class="main-content">
        <?php
        $workspace_page = [
            'title' => 'Results',
            'eyebrow' => 'EVERY RESPONSE TELLS A STORY',
            'description' => 'Choose a session to explore student responses and classroom results.',
            'icon' => 'results',
            'caption' => 'Turn answers into understanding',
        ];
        require __DIR__ . '/workspace_header.php';
        ?>

        <section class="content-card" aria-labelledby="results-list-title">
            <div class="card-header">
                <div>
                    <h2 id="results-list-title">Session results</h2>
                    <p>View live responses or revisit a previous session.</p>
                </div>
                <a href="sessions.php" class="secondary-btn">Manage Sessions</a>
            </div>

            <?php if ($result_sessions->num_rows === 0): ?>
                <div class="no-response" role="status">
                    No sessions yet. Create a session to start collecting responses.
                </div>
                <a href="sessions.php" class="primary-btn">Create a Session</a>
            <?php else: ?>
                <div class="table-wrapper" tabindex="0" role="region" aria-label="Session results">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Session</th>
                                <th scope="col">Question set</th>
                                <th scope="col">Status</th>
                                <th scope="col">Started</th>
                                <th scope="col">Results</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($result_session = $result_sessions->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($result_session['session_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><?= htmlspecialchars($result_session['set_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="status <?= htmlspecialchars($result_session['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(strtoupper($result_session['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars($result_session['started_at'] ?? 'Not started', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><a class="action-btn" href="results.php?session=<?= (int) $result_session['id'] ?>"><?= $result_session['status'] === 'running' ? 'View Live Results' : 'View Results' ?></a></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <footer class="workspace-footer"><span>© <?= date('Y') ?> Astralis</span><span>Ask. Connect. Understand.</span></footer>
    </main>
</div>
</body>
</html>
