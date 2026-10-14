<?php
require_once __DIR__ . '/workspace_ui.php';
$workspace_title = $workspace_page['title'];
?>
<?php require __DIR__ . '/workspace_background.php'; ?>
<div class="workspace-topline">
    <p class="workspace-breadcrumb">Workspace <span>/</span> <strong><?= htmlspecialchars($workspace_title, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <div class="workspace-account"><?= workspace_icon('user') ?><span><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Teacher workspace', ENT_QUOTES, 'UTF-8') ?></span></div>
</div>
<?php if ($workspace_page['hero'] ?? true): ?>
<header class="workspace-hero">
    <div class="workspace-heading">
        <p class="workspace-eyebrow"><span></span><?= htmlspecialchars($workspace_page['eyebrow'], ENT_QUOTES, 'UTF-8') ?></p>
        <h1><?= htmlspecialchars($workspace_title, ENT_QUOTES, 'UTF-8') ?><span>.</span></h1>
        <p class="workspace-description"><?= htmlspecialchars($workspace_page['description'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php if (isset($workspace_page['href'])): ?>
            <a class="primary-btn" href="<?= htmlspecialchars($workspace_page['href'], ENT_QUOTES, 'UTF-8') ?>"><?= workspace_icon($workspace_page['action_icon'] ?? 'arrow') ?><?= htmlspecialchars($workspace_page['action'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif; ?>
    </div>
    <div class="workspace-illustration" aria-hidden="true">
        <span class="workspace-orbit"></span><span class="workspace-spark">✳</span>
        <div class="workspace-art-back"></div>
        <div class="workspace-art-front">
            <span class="workspace-art-icon"><?= workspace_icon($workspace_page['icon']) ?></span>
            <span class="workspace-art-lines"><i></i><i></i><i></i></span>
            <span class="workspace-art-label"><?= htmlspecialchars($workspace_page['caption'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <span class="workspace-art-note"><?= workspace_icon('spark') ?> Ask. Connect. Understand.</span>
    </div>
</header>
<?php endif; ?>
