<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once "account.php";

if (!empty($_GET['set'])) { account_require_owned($conn, 'question_sets', (int) $_GET['set']); }


$message = "";
$error = "";

$selected_set = isset($_GET['set'])
    ? intval($_GET['set'])
    : intval($_POST['question_set_id'] ?? 0);


/* =========================
   GET QUESTION SETS
========================= */

$sets = [];

$result = $conn->query(
    "SELECT id, set_name
     FROM question_sets
     WHERE user_id = {$account_id}
     ORDER BY id DESC"
);

while ($row = $result->fetch_assoc()) {
    $sets[] = $row;
}


/* =========================
   IMPORT FILE
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST['import_file'])) {

    $question_set_id = intval($_POST['question_set_id']);
    account_require_owned($conn, 'question_sets', $question_set_id);

    if ($question_set_id <= 0) {

        $error = "Please select a question set.";

    } elseif (!isset($_FILES['question_file'])
        || $_FILES['question_file']['error'] !== UPLOAD_ERR_OK) {

        $error = "Please select a file.";

    } else {

        $file = $_FILES['question_file'];

        $filename = $file['name'];

        $extension = strtolower(
            pathinfo($filename, PATHINFO_EXTENSION)
        );


        if (!in_array($extension, ['csv', 'xlsx'])) {

            $error = "Only CSV and XLSX files are supported.";

        } else {

            $rows = [];


            /* =========================
               CSV
            ========================= */

            if ($extension === 'csv') {

                $handle = fopen($file['tmp_name'], "r");

                if (!$handle) {

                    $error = "Unable to read the CSV file.";

                } else {

                    while (($data = fgetcsv(
                        $handle,
                        0,
                        ","
                    )) !== false) {

                        $rows[] = $data;
                    }

                    fclose($handle);
                }
            }


            /* =========================
               XLSX
            ========================= */

            if ($extension === 'xlsx'
                && $error === "") {

                $autoload = __DIR__ . "/vendor/autoload.php";

                if (!file_exists($autoload)) {

                    $error =
                        "PhpSpreadsheet is not installed. "
                        . "Run composer require phpoffice/phpspreadsheet.";

                } else {

                    require_once $autoload;

                    try {

                        $spreadsheet =
                            \PhpOffice\PhpSpreadsheet\IOFactory
                            ::load($file['tmp_name']);

                        $sheet =
                            $spreadsheet
                            ->getActiveSheet();

                        $rows =
                            $sheet
                            ->toArray(
                                null,
                                true,
                                true,
                                false
                            );

                    } catch (Exception $e) {

                        $error =
                            "Unable to read the XLSX file: "
                            . $e->getMessage();
                    }
                }
            }


            /* =========================
               PROCESS ROWS
            ========================= */

            if ($error === "" && count($rows) > 0) {

                $headers = array_shift($rows);


                /*
                 * Normalize headers
                 */

                $headers = array_map(
                    function ($header) {

                        $header = trim($header);

                        if (substr($header, 0, 3) === "\xEF\xBB\xBF") {
                            $header = substr($header, 3);
                        }

                        return mb_strtolower(
                            $header,
                            'UTF-8'
                        );
                    },
                    $headers
                );


                /*
                 * Find column
                 */

                function findColumn($headers, $names)
                {
                    foreach ($names as $name) {

                        $name = mb_strtolower(
                            $name,
                            'UTF-8'
                        );

                        $index = array_search(
                            $name,
                            $headers
                        );

                        if ($index !== false) {
                            return $index;
                        }
                    }

                    return null;
                }


                $question_col = findColumn(
                    $headers,
                    [
                        'câu hỏi',
                        'cau hoi',
                        'question',
                        'question text'
                    ]
                );


                $a_col = findColumn(
                    $headers,
                    [
                        'a',
                        'đáp án a',
                        'dap an a',
                        'option a'
                    ]
                );


                $b_col = findColumn(
                    $headers,
                    [
                        'b',
                        'đáp án b',
                        'dap an b',
                        'option b'
                    ]
                );


                $c_col = findColumn(
                    $headers,
                    [
                        'c',
                        'đáp án c',
                        'dap an c',
                        'option c'
                    ]
                );


                $d_col = findColumn(
                    $headers,
                    [
                        'd',
                        'đáp án d',
                        'dap an d',
                        'option d'
                    ]
                );


                $answer_col = findColumn(
                    $headers,
                    [
                        'đáp án',
                        'dap an',
                        'answer',
                        'correct answer',
                        'correct_answer'
                    ]
                );


                if ($question_col === null) {

                    $error =
                        "The Question column was not found.";

                } elseif (
                    $a_col === null ||
                    $b_col === null ||
                    $c_col === null ||
                    $d_col === null
                ) {

                    $error =
                        "The file must include columns A, B, C, and D.";

                } else {

                    $imported = 0;
                    $skipped = 0;


                    $stmt = $conn->prepare(
                        "INSERT INTO questions
                        (
                            question_set_id,
                            question_text,
                            option_a,
                            option_b,
                            option_c,
                            option_d,
                            correct_answer
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );


                    foreach ($rows as $row) {

                        $question =
                            trim($row[$question_col] ?? '');

                        $option_a =
                            trim($row[$a_col] ?? '');

                        $option_b =
                            trim($row[$b_col] ?? '');

                        $option_c =
                            trim($row[$c_col] ?? '');

                        $option_d =
                            trim($row[$d_col] ?? '');

                        $answer = "";

                        if ($answer_col !== null) {

                            $answer =
                                strtoupper(
                                    trim(
                                        $row[$answer_col] ?? ''
                                    )
                                );
                        }


                        /*
                         * Skip empty rows
                         */

                        if ($question === "") {

                            $skipped++;

                            continue;
                        }


                        /*
                         * Validate answer
                         */

                        if (!in_array(
                            $answer,
                            ['', 'A', 'B', 'C', 'D']
                        )) {

                            $answer = "";
                        }


                        $stmt->bind_param(
                            "issssss",
                            $question_set_id,
                            $question,
                            $option_a,
                            $option_b,
                            $option_c,
                            $option_d,
                            $answer
                        );


                        if ($stmt->execute()) {

                            $imported++;

                        } else {

                            $skipped++;
                        }
                    }


                    $message =
                        "Imported "
                        . $imported
                        . " questions.";

                    if ($skipped > 0) {

                        $message .=
                            " questions. Skipped "
                            . $skipped
                            . " rows.";
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

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Astralis | Import Questions</title>

<link rel="stylesheet"
      href="css/style.css?v=202610061407">

    <link rel="stylesheet" href="css/workspace.css?v=20261006-3">
    <script src="js/workspace.js?v=20261006" defer></script>
</head>

<body class="workspace-page import-questions-workspace">

<div class="layout">

    <?php $ars_active_page = 'questions'; require __DIR__ . '/admin_sidebar.php'; ?>


    <main class="main-content">
        <?php $workspace_page = ['title' => 'Import Questions', 'eyebrow' => 'MORE IDEAS. MORE POSSIBILITIES.', 'description' => 'Bring your existing questions into Astralis with a CSV or Excel file.', 'icon' => 'questions', 'caption' => 'Your ideas, ready to share']; require __DIR__ . '/workspace_header.php'; ?>


        


        <?php if ($message): ?>

            <div class="success-message">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="error-message">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <div class="workspace-import-grid">
        <div class="content-card">

            <h2>Upload Question File</h2>

            <form
                method="POST"
                enctype="multipart/form-data"
            >

                <div class="form-row">

                    <div>

                        <label for="import_questions_question_set_id">
                            Question Set
                        </label>

                        <select id="import_questions_question_set_id"
                            name="question_set_id"
                            required
                        >

                            <option value="">
                                -- Select a question set --
                            </option>

                            <?php foreach ($sets as $set): ?>

                                <option
                                    value="<?= $set['id'] ?>"
                                    <?= $selected_set == $set['id']
                                        ? 'selected'
                                        : '' ?>
                                >

                                    <?= htmlspecialchars(
                                        $set['set_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>


                <div class="form-row">

                    <div>

                        <label for="import_questions_question_file">
                            Question File
                        </label>

                        <input id="import_questions_question_file"
                            type="file"
                            name="question_file"
                            accept=".csv,.xlsx"
                            required
                        >

                    </div>

                </div>


                <button
                    type="submit"
                    name="import_file"
                    class="primary-btn"
                >

                    📤 Upload & Import

                </button>

                <a
                    href="questions.php"
                    class="secondary-btn"
                >
                    Back to Question Bank
                </a>

            </form>

        </div>


        <div class="content-card">

            <h2>File Format</h2>

            <p>
                The first row must contain the column headers.
            </p>

            <div class="table-wrapper" tabindex="0" role="region" aria-label="Import Questions table">

                <table>

                    <thead>

                        <tr>

                            <th>Question</th>
                            <th>A</th>
                            <th>B</th>
                            <th>C</th>
                            <th>D</th>
                            <th>Correct Answer</th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr>

                            <td>
                                What is 2 + 2?
                            </td>

                            <td>2</td>

                            <td>3</td>

                            <td>4</td>

                            <td>5</td>

                            <td>C</td>

                        </tr>

                    </tbody>

                </table>

            </div>

            <p class="help-text">

                Accepted column names:
                <br>

                <strong>
                    Question / Question Text
                </strong>
                ,
                <strong>
                    A / Option A
                </strong>
                ,
                <strong>
                    B / Option B
                </strong>
                ,
                <strong>
                    C / Option C
                </strong>
                ,
                <strong>
                    D / Option D
                </strong>
                ,
                <strong>
                    Answer / Correct Answer
                </strong>

            </p>

        </div>

    </div>
        <footer class="workspace-footer"><span>© <?= date('Y') ?> Astralis</span><span>Ask. Connect. Understand.</span></footer>
    </main>

</div>

</body>

</html>
