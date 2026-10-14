<?php

$ars_active_page = $ars_active_page ?? '';
$results_href = isset($session_id) && (int) $session_id > 0
    ? 'results.php?session=' . (int) $session_id
    : 'results.php';

$presentation_session_id = 0;
if (isset($session_id) && (int) $session_id > 0) {
    $presentation_session_id = (int) $session_id;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $presentation_result = $conn->query(
        "SELECT id
         FROM sessions
         WHERE user_id = {$account_id} AND status = 'running'
         ORDER BY started_at DESC, id DESC
         LIMIT 1"
    );

    if ($presentation_result) {
        $presentation_session = $presentation_result->fetch_assoc();
        $presentation_session_id = (int) ($presentation_session['id'] ?? 0);
        $presentation_result->free();
    }
}

$presentation_href = $presentation_session_id > 0
    ? 'presentation.php?session=' . $presentation_session_id
    : 'sessions.php';

$navigation_items = [
    'dashboard' => ['dashboard.php', 'Dashboard'],
    'students' => ['students.php', 'Students'],
    'questions' => ['questions.php', 'Question Bank'],
    'devices' => ['devices.php', 'Devices'],
    'sessions' => ['sessions.php', 'Sessions'],
    'presentation' => [$presentation_href, 'Presentation'],
    'results' => [$results_href, 'Results'],
];

?>
<aside class="sidebar ars-sidebar">
    <div class="sidebar-logo ars-sidebar-brand">
        <div class="ars-brand-mark" aria-hidden="true">
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
            <h2>Astralis</h2>
        </div>
    </div>

    <p class="ars-sidebar-caption"> WORKSPACE</p>
   


    <nav class="menu ars-navigation" aria-label="Điều hướng chính">
        <?php foreach ($navigation_items as $page => [$href, $label]): ?>
            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
               class="<?= $ars_active_page === $page ? 'active' : '' ?>"
               <?= $ars_active_page === $page ? 'aria-current="page"' : '' ?>>
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>

        <a href="logout.php" class="ars-logout-link">
            Logout
        </a>
    </nav>
</aside>
