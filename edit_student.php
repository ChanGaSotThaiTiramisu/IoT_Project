<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

require_once "account.php";

account_require_owned($conn, 'students', (int) ($_GET['id'] ?? 0));


$id = intval($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: students.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| UPDATE STUDENT
|--------------------------------------------------------------------------
*/

if (isset($_POST["update_student"])) {

    $student_code = trim($_POST["student_code"]);
    $full_name = trim($_POST["full_name"]);
    $class_name = trim($_POST["class_name"]);
    $email = trim($_POST["email"]);

    $sql = "
        UPDATE students
        SET
            student_code = ?,
            full_name = ?,
            class_name = ?,
            email = ?
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "ssssi",
        $student_code,
        $full_name,
        $class_name,
        $email,
        $id
    );

    if ($stmt->execute()) {

        header("Location: students.php?updated=1");
        exit;

    } else {

        $error = $stmt->error;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| GET STUDENT
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT *
    FROM students
    WHERE id = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    header("Location: students.php");
    exit;
}

$student = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Astralis | Edit Student</title>

    <link
        rel="stylesheet"
        href="css/style.css?v=202610061407"
    >

    <link rel="stylesheet" href="css/workspace.css?v=20261006-3">
    <script src="js/workspace.js?v=20261006" defer></script>
</head>

<body class="workspace-page edit-student-workspace">

<div class="admin-layout">

    <?php $ars_active_page = 'students'; require __DIR__ . '/admin_sidebar.php'; ?>


    <main class="main-content">
        <?php $workspace_page = ['title' => 'Edit Student', 'eyebrow' => 'KEEP YOUR CLASS CONNECTED', 'description' => 'Update student details so every response finds the right person.', 'icon' => 'edit', 'caption' => 'Every student matters']; require __DIR__ . '/workspace_header.php'; ?>


        


        <?php if (isset($error)): ?>

            <div class="error-message">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <div class="content-card workspace-edit-card">

            <form
                method="POST"
                class="student-form"
            >

                <div class="form-row">

                    <div class="form-group">

                        <label for="edit_student_student_code">
                            Student Code
                        </label>

                        <input id="edit_student_student_code"
                            type="text"
                            name="student_code"
                            value="<?php
                                echo htmlspecialchars(
                                    $student["student_code"]
                                );
                            ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="edit_student_full_name">
                            Full Name
                        </label>

                        <input id="edit_student_full_name"
                            type="text"
                            name="full_name"
                            value="<?php
                                echo htmlspecialchars(
                                    $student["full_name"]
                                );
                            ?>"
                            required
                        >

                    </div>

                </div>


                <div class="form-row">

                    <div class="form-group">

                        <label for="edit_student_class_name">
                            Class
                        </label>

                        <input id="edit_student_class_name"
                            type="text"
                            name="class_name"
                            value="<?php
                                echo htmlspecialchars(
                                    $student["class_name"]
                                );
                            ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="edit_student_email">
                            Email
                        </label>

                        <input id="edit_student_email"
                            type="email"
                            name="email"
                            value="<?php
                                echo htmlspecialchars(
                                    $student["email"]
                                );
                            ?>"
                        >

                    </div>

                </div>


                <button
                    type="submit"
                    name="update_student"
                    class="primary-btn"
                >
                    Save Changes
                </button>


                <a
                    href="students.php"
                    class="secondary-btn"
                    style="
                        display:inline-block;
                        text-decoration:none;
                        margin-left:8px;
                    "
                >
                    Cancel
                </a>

            </form>

        </div>

    <footer class="workspace-footer"><span>© <?= date('Y') ?> Astralis</span><span>Ask. Connect. Understand.</span></footer>
    </main>

</div>

</body>

</html>
