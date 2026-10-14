<?php

session_start();
require_once "db.php";

$error = "";
$full_name = "";
$username = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($full_name === "" || $username === "" || $password === "") {
        $error = "Please complete all required fields.";
    } elseif (strlen($username) > 50) {
        $error = "Username must be 50 characters or fewer.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            $error = "That username is already in use.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO users (username, password, full_name) VALUES (?, ?, ?)"
            );
            $stmt->bind_param("sss", $username, $password_hash, $full_name);

            if ($stmt->execute()) {
                header("Location: index.php?registered=1");
                exit;
            }

            $error = "We could not create your account. Please try again.";
            $stmt->close();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f6f7f9">
    <title>Astralis | Create teacher account</title>
    <link rel="stylesheet" href="css/style.css?v=202610061428">
    <link rel="stylesheet" href="css/login.css?v=20261006-3">
    <link rel="stylesheet" href="css/register.css?v=20261006">
    <script src="js/register.js?v=20261006" defer></script>
</head>
<body class="login-body signin-page register-page">
<div class="login-hex-background" aria-hidden="true">
    <?php for ($i = 0; $i < 32; $i++): ?><span></span><?php endfor; ?>
</div>
<div class="signin-layout">
    <header class="signin-header">
        <a class="ars-wordmark" href="index.php" aria-label="Astralis home">
            <svg class="ars-wordmark-mark" viewBox="0 0 110 82" aria-hidden="true">
                <g fill="#ff633d">
                    <ellipse cx="78" cy="18" rx="14" ry="29" transform="rotate(42 78 18)"/>
                    <ellipse cx="38" cy="34" rx="14" ry="29" transform="rotate(42 38 34)"/>
                    <ellipse cx="78" cy="55" rx="14" ry="29" transform="rotate(42 78 55)"/>
                    <ellipse cx="38" cy="70" rx="14" ry="29" transform="rotate(42 38 70)"/>
                </g>
            </svg>
            <span>Astralis</span>
        </a>
        <span class="signin-header-note">A little interaction. A lot of possibility.</span>
    </header>

    <main class="signin-shell">
        <section class="signin-story" aria-labelledby="register-story-title">
            <span class="story-eyebrow"><span></span> YOUR CLASSROOM STARTS HERE</span>
            <h2 id="register-story-title">Great lessons.<br>Start with<br><em>connection.</em></h2>
            <p class="story-description">Make space for every student to take part.<br>Your next classroom conversation starts with you.</p>

            <div class="classroom-preview register-preview" aria-label="Getting started with Astralis">
                <div class="preview-orbit" aria-hidden="true"></div>
                <div class="preview-spark preview-spark-one" aria-hidden="true">✳</div>
                <div class="preview-spark preview-spark-two" aria-hidden="true">+</div>
                <div class="response-preview">
                    <div class="preview-heading">
                        <span class="preview-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3 9 9-5 9 5-9 5-9-5Zm4 3v5c3 2 7 2 10 0v-5M21 9v7"/></svg></span>
                        <div><strong>A little setup. A lot of possibility.</strong><span>Your first steps with Astralis</span></div>
                    </div>
                    <ol class="register-steps">
                        <li><span class="register-step-number">01</span><div><strong>Create your account</strong><span>A workspace of your own</span></div><span class="register-step-now">YOU ARE HERE</span></li>
                        <li><span class="register-step-number">02</span><div><strong>Bring your class together</strong><span>Add students and prepare questions</span></div></li>
                        <li><span class="register-step-number">03</span><div><strong>Start a conversation</strong><span>Ask, listen, and discover together</span></div></li>
                    </ol>
                </div>
                <div class="preview-caption"><span aria-hidden="true">✓</span> Every voice has a place here.</div>
            </div>
            <div class="story-footer"><span class="story-footer-line"></span><p>Ask. Connect. Understand.</p></div>
        </section>

        <section class="signin-panel" aria-labelledby="register-title">
            <div class="signin-panel-inner">
                <div class="workspace-label"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 9 9-5 9 5-9 5-9-5Zm4 3v5c3 2 7 2 10 0v-5M21 9v7"/></svg> TEACHER WORKSPACE</div>
                <h1 id="register-title">Create your account<span>.</span></h1>
                <p class="signin-description">A new chapter for your classroom.</p>
                <nav class="auth-switch" aria-label="Authentication">
                    <a href="index.php">Sign in</a>
                    <a href="register.php" class="active" aria-current="page">Create account</a>
                </nav>

                <?php if ($error): ?>
                    <div class="error-message" id="register-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form method="POST" class="login-form" data-register-form<?= $error ? ' aria-describedby="register-error"' : '' ?>>
                    <div class="form-group">
                        <label for="full_name">Full name</label>
                        <div class="signin-input">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20v-2a7 7 0 0 1 14 0v2"/></svg>
                            <input id="full_name" type="text" name="full_name" placeholder="Your full name" value="<?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') ?>" autocomplete="name" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="register_username">Username</label>
                        <div class="signin-input">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="15" rx="3"/><circle cx="9" cy="11" r="2"/><path d="M6 17a3 3 0 0 1 6 0m3-7h3m-3 4h3"/></svg>
                            <input id="register_username" type="text" name="username" placeholder="Choose a username" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" maxlength="50" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="register_password">Password</label>
                        <div class="signin-input password-field">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg>
                            <input id="register_password" type="password" name="password" placeholder="Create a password" autocomplete="new-password" minlength="6" aria-describedby="password-hint register-caps-lock" required>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-controls="register_password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg></button>
                        </div>
                        <p id="password-hint" class="register-field-hint">Use at least 6 characters.</p>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm password</label>
                        <div class="signin-input password-field">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg>
                            <input id="confirm_password" type="password" name="confirm_password" placeholder="Repeat your password" autocomplete="new-password" aria-describedby="confirm-password-note register-caps-lock" required>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show confirmation password" aria-controls="confirm_password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg></button>
                        </div>
                        <p id="confirm-password-note" class="register-field-error" aria-live="polite" hidden>Passwords do not match.</p>
                    </div>
                    <p id="register-caps-lock" class="caps-lock-note" role="status" hidden>Caps Lock is on.</p>
                    <button type="submit" class="login-btn"><span data-submit-label>Create your teacher account</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></button>
                </form>

                <p class="login-register">Already have an account? <a href="index.php">Sign in <span aria-hidden="true">↗</span></a></p>
                <div class="signin-form-footer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg><span>Your space to connect, teach, and inspire.</span></div>
            </div>
        </section>
    </main>
    <footer class="signin-footer"><span>© <?= date('Y') ?> Astralis</span><span>Built around your classroom.</span></footer>
</div>
</body>
</html>
