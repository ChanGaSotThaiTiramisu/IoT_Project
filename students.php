<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

require_once "account.php";

if (isset($_GET['delete'])) { account_require_owned($conn, 'students', (int) $_GET['delete']); }


$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| ADD STUDENT
|--------------------------------------------------------------------------
*/

if (isset($_POST["add_student"])) {

    $student_code = trim($_POST["student_code"]);
    $full_name = trim($_POST["full_name"]);
    $class_name = trim($_POST["class_name"]);
    $email = trim($_POST["email"]);

    if ($student_code === "" || $full_name === "") {

        $error = "Student code and full name are required.";

    } else {

        $sql = "
            INSERT INTO students
            (user_id, student_code, full_name, class_name, email)
            VALUES ({$account_id}, ?, ?, ?, ?)
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ssss",
            $student_code,
            $full_name,
            $class_name,
            $email
        );

        if ($stmt->execute()) {

            $message = "Student added successfully.";

        } else {

            if ($stmt->errno == 1062) {
                $error = "Student code already exists.";
            } else {
                $error = "Error: " . $stmt->error;
            }
        }

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| DELETE STUDENT
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete"])) {

    $id = intval($_GET["delete"]);

    $sql = "
        DELETE FROM students
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "i",
        $id
    );

    if ($stmt->execute()) {

        header("Location: students.php?deleted=1");
        exit;

    } else {

        $error = "Unable to delete student.";
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| DELETE MESSAGE
|--------------------------------------------------------------------------
*/

if (isset($_GET["deleted"])) {

    $message = "Student deleted successfully.";
}
if (isset($_GET["updated"])) {

    $message = "Student updated successfully.";
}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search = "";

if (isset($_GET["search"])) {
    $search = trim($_GET["search"]);
}

if ($search !== "") {

    $keyword = "%" . $search . "%";

    $sql = "
        SELECT *
        FROM students
        WHERE user_id = {$account_id}
          AND (student_code LIKE ?
           OR full_name LIKE ?
           OR class_name LIKE ?)
        ORDER BY id DESC
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "sss",
        $keyword,
        $keyword,
        $keyword
    );

    $stmt->execute();

    $students = $stmt->get_result();

} else {

    $sql = "
        SELECT *
        FROM students
        WHERE user_id = {$account_id}
        ORDER BY id DESC
    ";

    $students = $conn->query($sql);
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

    <title>Astralis | Students</title>

    <link
        rel="stylesheet"
        href="css/style.css?v=202610061407"
    >

    <link rel="stylesheet" href="css/workspace.css?v=20261006-3">
    <script src="js/workspace.js?v=20261006" defer></script>
</head>

<body class="workspace-page students-workspace">

<div class="admin-layout">

    <?php $ars_active_page = 'students'; require __DIR__ . '/admin_sidebar.php'; ?>


    <!-- MAIN -->

    <main class="main-content">
        <?php $workspace_page = ['title' => 'Students', 'eyebrow' => 'MAKE ROOM FOR EVERY VOICE', 'description' => 'Bring your class together. Organize the people behind every answer.', 'icon' => 'students', 'caption' => 'Different minds. Shared discovery.', 'href' => 'import_students.php', 'action' => 'Import Students', 'action_icon' => 'upload']; require __DIR__ . '/workspace_header.php'; ?>


        


        <!-- MESSAGE -->

        <?php if ($message): ?>

            <div class="success-message">

                <?php
                echo htmlspecialchars($message);
                ?>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="error-message">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <div class="workspace-two-column">
        <!-- ADD STUDENT -->

        <div class="content-card">

            <div class="card-header">

                <div>

                    <h2>
                        Add Student
                    </h2>

                    <p>
                        Enter student information.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                class="student-form"
            >

                <div class="form-row">

                    <div class="form-group">

                        <label for="students_student_code">
                            Student Code *
                        </label>

                        <input id="students_student_code"
                            type="text"
                            name="student_code"
                            placeholder="Example: 22303001"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="students_full_name">
                            Full Name *
                        </label>

                        <input id="students_full_name"
                            type="text"
                            name="full_name"
                            placeholder="Example: Nguyen Van A"
                            required
                        >

                    </div>

                </div>


                <div class="form-row">

                    <div class="form-group">

                        <label for="students_class_name">
                            Class
                        </label>

                        <input id="students_class_name"
                            type="text"
                            name="class_name"
                            placeholder="Example: 22D..."
                        >

                    </div>


                    <div class="form-group">

                        <label for="students_email">
                            Email
                        </label>

                        <input id="students_email"
                            type="email"
                            name="email"
                            placeholder="student@example.com"
                        >

                    </div>

                </div>


                <button
                    type="submit"
                    name="add_student"
                    class="primary-btn"
                >
                    + Add Student
                </button>

            </form>

        </div>


        <!-- STUDENT LIST -->

        <div class="content-card">

            <div class="card-header">

                <div>

                    <h2>
                        Student List
                    </h2>
                
                    <p>
                        Registered students
                    </p>


                </div>


                <form
                    method="GET"
                    class="search-form"
                >

                    <input
                        type="text"
                        name="search" aria-label="Search students"
                        value="<?php
                            echo htmlspecialchars($search);
                        ?>"
                        placeholder="Search student..."
                    >

                    <button
                        type="submit"
                        class="secondary-btn"
                    >
                        Search
                    </button>

                </form>

            </div>


            <div class="table-container" tabindex="0" role="region" aria-label="Students table">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Student Code
                            </th>

                            <th>
                                Full Name
                            </th>

                            <th>
                                Class
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php

                    if ($students->num_rows > 0):

                        $number = 1;

                        while (
                            $student =
                            $students->fetch_assoc()
                        ):

                    ?>

                        <tr>

                            <td>
                                <?php
                                echo $number++;
                                ?>
                            </td>

                            <td>

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $student["student_code"]
                                    );
                                    ?>
                                </strong>

                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $student["full_name"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $student["class_name"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $student["email"]
                                );
                                ?>
                            </td>

                            <td>

                                <a
                                    href="edit_student.php?id=<?php
                                        echo $student["id"];
                                    ?>"
                                    class="edit-btn"
                                >
                                    Edit
                                </a>


                                <a
                                    href="students.php?delete=<?php
                                        echo $student["id"];
                                    ?>"
                                    class="delete-btn"
                                    onclick="return confirm(
                                        'Delete this student?'
                                    );"
                                >
                                    Delete
                                </a>

                            </td>

                        </tr>

                    <?php

                        endwhile;

                    else:

                    ?>

                        <tr>

                            <td
                                colspan="6"
                                class="empty-table"
                            >

                                No students found.

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>
        <footer class="workspace-footer"><span>© <?= date('Y') ?> Astralis</span><span>Ask. Connect. Understand.</span></footer>
    </main>

</div>

</body>

</html>