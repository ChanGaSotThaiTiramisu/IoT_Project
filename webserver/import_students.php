<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

require_once "account.php";

$message = "";
$error = "";
$imported = 0;
$skipped = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!isset($_FILES["student_file"])) {

        $error = "Please select a file.";

    } else {

        $file = $_FILES["student_file"];

        if ($file["error"] !== UPLOAD_ERR_OK) {

            $error = "File upload failed.";

        } else {

            $extension =
                strtolower(
                    pathinfo(
                        $file["name"],
                        PATHINFO_EXTENSION
                    )
                );

            if ($extension !== "csv") {

                $error =
                    "Please upload a CSV file.";

            } else {

                $handle =
                    fopen(
                        $file["tmp_name"],
                        "r"
                    );

                if (!$handle) {

                    $error =
                        "Cannot read uploaded file.";

                } else {

                    /*
                     * Read header
                     */

                    $header =
                        fgetcsv(
                            $handle,
                            0,
                            ","
                        );

                    if (!$header) {

                        $error =
                            "CSV file is empty.";

                    } else {

                        /*
                         * Remove UTF-8 BOM
                         */

                        $header[0] =
                            preg_replace(
                                '/^\xEF\xBB\xBF/',
                                '',
                                $header[0]
                            );

                        /*
                         * Normalize header
                         */

                        $header = array_map(
                            function ($value) {

                                return mb_strtolower(
                                    trim($value)
                                );

                            },
                            $header
                        );


                        /*
                         * Find columns
                         */

                        $studentCodeIndex = -1;
                        $nameIndex = -1;
                        $classIndex = -1;
                        $emailIndex = -1;


                        foreach (
                            $header as $index => $column
                        ) {

                            if (
                                in_array(
                                    $column,
                                    [
                                        "mssv",
                                        "ma sinh vien",
                                        "mã số sinh viên",
                                        "student code",
                                        "student_id"
                                    ]
                                )
                            ) {

                                $studentCodeIndex =
                                    $index;
                            }


                            if (
                                in_array(
                                    $column,
                                    [
                                        "họ tên",
                                        "ho ten",
                                        "họ và tên",
                                        "ho va ten",
                                        "full name",
                                        "name"
                                    ]
                                )
                            ) {

                                $nameIndex =
                                    $index;
                            }


                            if (
                                in_array(
                                    $column,
                                    [
                                        "lớp",
                                        "lop",
                                        "class",
                                        "class name"
                                    ]
                                )
                            ) {

                                $classIndex =
                                    $index;
                            }


                            if (
                                in_array(
                                    $column,
                                    [
                                        "email",
                                        "e-mail"
                                    ]
                                )
                            ) {

                                $emailIndex =
                                    $index;
                            }
                        }


                        if (
                            $studentCodeIndex === -1 ||
                            $nameIndex === -1
                        ) {

                            $error =
                                "CSV must contain Student Code and Full Name columns.";

                        } else {

                            /*
                             * Prepare INSERT
                             */

                            $sql = "
                                INSERT INTO students
                                (
                                    user_id,
                                    student_code,
                                    full_name,
                                    class_name,
                                    email
                                )
                                VALUES ({$account_id}, ?, ?, ?, ?)

                                ON DUPLICATE KEY UPDATE

                                    full_name =
                                        VALUES(full_name),

                                    class_name =
                                        VALUES(class_name),

                                    email =
                                        VALUES(email)
                            ";

                            $stmt =
                                $conn->prepare($sql);


                            /*
                             * Read rows
                             */

                            while (
                                ($row =
                                    fgetcsv(
                                        $handle,
                                        0,
                                        ","
                                    )) !== false
                            ) {

                                if (
                                    count($row) === 0
                                ) {
                                    continue;
                                }


                                $studentCode =
                                    trim(
                                        $row[
                                            $studentCodeIndex
                                        ] ?? ""
                                    );

                                $fullName =
                                    trim(
                                        $row[
                                            $nameIndex
                                        ] ?? ""
                                    );

                                $className =
                                    $classIndex >= 0
                                    ? trim(
                                        $row[
                                            $classIndex
                                        ] ?? ""
                                    )
                                    : "";

                                $email =
                                    $emailIndex >= 0
                                    ? trim(
                                        $row[
                                            $emailIndex
                                        ] ?? ""
                                    )
                                    : "";


                                /*
                                 * Skip empty rows
                                 */

                                if (
                                    $studentCode === "" ||
                                    $fullName === ""
                                ) {

                                    $skipped++;

                                    continue;
                                }


                                $stmt->bind_param(
                                    "ssss",
                                    $studentCode,
                                    $fullName,
                                    $className,
                                    $email
                                );


                                if (
                                    $stmt->execute()
                                ) {

                                    $imported++;

                                } else {

                                    $skipped++;
                                }
                            }


                            $stmt->close();

                            fclose($handle);


                            $message =
                                "Import completed. "
                                . $imported
                                . " student records processed.";

                        }
                    }

                    if (is_resource($handle)) {
                        fclose($handle);
                    }
                }
            }
        }
    }
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

<title>Astralis | Import Students</title>

<link
    rel="stylesheet"
    href="css/style.css?v=202610061407"
>

    <link rel="stylesheet" href="css/workspace.css?v=20261006-3">
    <script src="js/workspace.js?v=20261006" defer></script>
</head>

<body class="workspace-page import-students-workspace">

<div class="admin-layout">

    <?php $ars_active_page = 'students'; require __DIR__ . '/admin_sidebar.php'; ?>


    <!-- MAIN -->

    <main class="main-content">
        <?php $workspace_page = ['title' => 'Import Students', 'eyebrow' => 'BRING YOUR CLASS TOGETHER', 'description' => 'Upload a CSV file to add your students and get ready for the next lesson.', 'icon' => 'upload', 'caption' => 'A place for every learner']; require __DIR__ . '/workspace_header.php'; ?>


        


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


        <div class="workspace-import-grid">
        <div class="content-card">

            <div class="card-header">

                <div>

                    <h2>
                        Upload Student File
                    </h2>

                    <p>
                        Supported format: CSV
                    </p>

                </div>

            </div>


            <form
                method="POST"
                enctype="multipart/form-data"
            >

                <div class="form-group">

                    <label for="import_students_student_file">
                        Student List
                    </label>

                    <input id="import_students_student_file"
                        type="file"
                        name="student_file"
                        accept=".csv"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="primary-btn"
                >
                    Upload & Import
                </button>

            </form>

        </div>


        <div class="content-card">

            <h2>
                Required CSV Format
            </h2>

            <p style="margin-top:10px;color:#6b7280;">
                The system needs at least these two columns:
            </p>


            <div style="
                margin-top:20px;
                background:#f3f4f6;
                padding:20px;
                border-radius:8px;
                overflow:auto;
            ">

<pre>Student Code,Full Name,Class,Email
S1001,Alex Morgan,Class 01,alex.morgan@example.com
S1002,Jamie Lee,Class 01,jamie.lee@example.com
S1003,Taylor Reed,Class 02,taylor.reed@example.com</pre>

            </div>

        </div>


        <div style="margin-top:20px;">

            <a
                href="students.php"
                class="secondary-btn"
                style="
                    display:inline-block;
                    text-decoration:none;
                "
            >
                ← Back to Students
            </a>

        </div>

    </div>
        <footer class="workspace-footer"><span>© <?= date('Y') ?> Astralis</span><span>Ask. Connect. Understand.</span></footer>
    </main>

</div>

</body>

</html>
