<?php

session_start();

require_once "db.php";

$error = "";
$username_value = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $username_value = $username;
    $password = $_POST["password"];

    $sql = "
        SELECT id, username, password, full_name
        FROM users
        WHERE username = ?
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "s",
        $username
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        if (
            password_verify(
                $password,
                $user["password"]
            )
        ) {

            session_regenerate_id(true);
            $_SESSION = [];

            $_SESSION["user_id"]
                = $user["id"];

            $_SESSION["username"]
                = $user["username"];

            $_SESSION["full_name"]
                = $user["full_name"];

            header(
                "Location: dashboard.php"
            );

            exit;

        } else {

            $error = "Invalid username or password.";

        }

    } else {

        $error = "Invalid username or password.";

    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f6f7f9">
    <title>Astralis | Teacher sign in</title>
    <link rel="stylesheet" href="css/style.css?v=202610061428">
    <link rel="stylesheet" href="css/login.css?v=20261006-3">
    <script src="js/login.js?v=20261006" defer></script>
</head>
<body class="login-body signin-page">
    <div class="login-hex-background" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span>
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
            <section class="signin-story" aria-labelledby="story-title">
                <span class="story-eyebrow"><span></span> MADE FOR MEANINGFUL LEARNING</span>
                <h2 id="story-title">Every voice.<br>Every answer.<br><em>Every student.</em></h2>
                <p class="story-description">Turn everyday lessons into conversations.<br>Bring your classroom together with Astralis.</p>

                <div class="classroom-preview" aria-label="Illustration of a classroom response session">
                    <div class="preview-orbit" aria-hidden="true"></div>
                    <div class="preview-spark preview-spark-one" aria-hidden="true">✳</div>
                    <div class="preview-spark preview-spark-two" aria-hidden="true">+</div>
                    <div class="response-preview">
                        <div class="preview-heading">
                            <span class="preview-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M5 19V11m7 8V5m7 14v-8"/></svg>
                            </span>
                            <div><strong>A classroom in sync</strong><span>Every response tells a story</span></div>
                            <span class="preview-example">PREVIEW</span>
                        </div>
                        <div class="preview-chart" aria-hidden="true">
                            <div><span class="chart-bar bar-a"></span><span>A</span></div>
                            <div><span class="chart-bar bar-b"></span><span>B</span></div>
                            <div><span class="chart-bar bar-c"></span><span>C</span></div>
                            <div><span class="chart-bar bar-d"></span><span>D</span></div>
                        </div>
                        <div class="preview-bottom"><span class="preview-avatars" aria-hidden="true"><i>J</i><i>M</i><i>A</i><i>+</i></span><span>Different minds. Shared discovery.</span></div>
                    </div>
                    <div class="preview-caption"><span aria-hidden="true">✓</span> Small answers. Big understanding.</div>
                </div>

                <div class="story-footer"><span class="story-footer-line"></span><p>Ask. Connect. Understand.</p></div>
            </section>

            <section class="signin-panel" aria-labelledby="signin-title">
                <div class="signin-panel-inner">
                    <div class="workspace-label">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 9 9-5 9 5-9 5-9-5Zm4 3v5c3 2 7 2 10 0v-5M21 9v7"/></svg>
                        TEACHER WORKSPACE
                    </div>
                    <h1 id="signin-title">Welcome back<span>.</span></h1>
                    <p class="signin-description">Your next great lesson starts here.</p>

                    <nav class="auth-switch" aria-label="Authentication">
                        <a href="index.php" class="active" aria-current="page">Sign in</a>
                        <a href="register.php">Create account</a>
                    </nav>

                    <?php if ($error): ?>
                        <div class="error-message" id="signin-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php elseif (isset($_GET["registered"])): ?>
                        <div class="success-message" role="status">Your teacher account is ready. Please sign in.</div>
                    <?php endif; ?>

                    <form method="POST" class="login-form" data-signin-form>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <div class="signin-input">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20v-2a7 7 0 0 1 14 0v2"/></svg>
                                <input type="text" name="username" id="username" placeholder="Enter your username" value="<?= htmlspecialchars($username_value, ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required<?= $error ? ' aria-describedby="signin-error"' : '' ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="signin-input password-field">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg>
                                <input type="password" name="password" id="password" placeholder="Enter your password" autocomplete="current-password" aria-describedby="caps-lock<?= $error ? ' signin-error' : '' ?>" required>
                                <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-controls="password" aria-pressed="false">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg>
                                </button>
                            </div>
                            <p id="caps-lock" class="caps-lock-note" role="status" hidden>Caps Lock is on.</p>
                        </div>
                        <button type="submit" class="login-btn"><span data-submit-label>Sign in to your workspace</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></button>
                    </form>

                    <p class="login-register">New to Astralis? <a href="register.php">Create a teacher account <span aria-hidden="true">↗</span></a></p>
                    <div class="signin-form-footer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg><span>Your space to connect, teach, and inspire.</span></div>
                </div>
            </section>
        </main>
        <footer class="signin-footer"><span>© <?= date('Y') ?> Astralis</span><span>Built around your classroom.</span></footer>
    </div>
</body>
</html>
