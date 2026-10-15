<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once "account.php";
require_once "device_presence.php";

if (isset($_GET['delete'])) { account_require_owned($conn, 'devices', (int) $_GET['delete']); }


header('Cache-Control: no-store');

$message = "";
$error = "";

/* =========================
   ADD DEVICE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_device"])) {

    $device_code = trim($_POST["device_code"]);
    $device_name = trim($_POST["device_name"]);

    if ($device_code === "") {
        $error = "Please enter a device code.";
    } else {

        $stmt = $conn->prepare(
            "INSERT INTO devices (user_id, device_code, device_name)
             VALUES ({$account_id}, ?, ?)"
        );

        $stmt->bind_param(
            "ss",
            $device_code,
            $device_name
        );

        if ($stmt->execute()) {
            $message = "Device added successfully.";
        } else {
            if ($conn->errno == 1062) {
                $error = "That device code already exists.";
            } else {
                $error = "Unable to add device: " . $conn->error;
            }
        }

        $stmt->close();
    }
}

/* =========================
   DELETE DEVICE
========================= */
if (isset($_GET["delete"])) {

    $id = intval($_GET["delete"]);

    $stmt = $conn->prepare(
        "DELETE FROM devices WHERE id = ?"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: devices.php");
    exit;
}

/* =========================
   GET DEVICES
========================= */
$result = $conn->query(
    "SELECT
        d.id,
        d.device_code,
        d.device_name,
        CASE WHEN " . DEVICE_ONLINE_SQL . " THEN 'online' ELSE 'offline' END AS status,
        d.last_seen,
        s.student_code,
        s.full_name,
        s.class_name
     FROM devices d
     LEFT JOIN students s
        ON d.student_id = s.id AND s.user_id = d.user_id
     WHERE d.user_id = {$account_id}
     ORDER BY d.id ASC"
);

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Astralis | Devices</title>

<link rel="stylesheet" href="css/style.css?v=202610061407">

<style>

.device-status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: bold;
}

.device-online {
    background: #dcfce7;
    color: #166534;
}

.device-offline {
    background: #fee2e2;
    color: #991b1b;
}

.device-id {
    font-weight: bold;
    color: #2563eb;
}

</style>

    <link rel="stylesheet" href="css/workspace.css?v=20261015-devices">
    <script src="js/workspace.js?v=20261006" defer></script>
</head>

<body class="workspace-page devices-workspace">

<div class="layout">

    <?php $ars_active_page = 'devices'; require __DIR__ . '/admin_sidebar.php'; ?>


    <!-- MAIN -->

    <main class="main-content">
        <?php $workspace_page = ['title' => 'Devices', 'eyebrow' => 'SMALL DEVICES. BIG CONNECTIONS.', 'description' => 'Connect your response devices and keep every student part of the conversation.', 'icon' => 'devices', 'caption' => 'Connected to your classroom']; require __DIR__ . '/workspace_header.php'; ?>


        


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


        <div class="workspace-two-column">
        <!-- ADD DEVICE -->


        <div class="content-card">

            <div class="card-header">

                <h2>
                    Add Device
                </h2>

            </div>


            <form method="POST">

                <div class="form-group">

                    <label for="devices_device_code">
                        Device Code
                    </label>

                    <input id="devices_device_code"
                        type="text"
                        name="device_code"
                        placeholder="e.g., NRF001"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="devices_device_name">
                        Device Name
                    </label>

                    <input id="devices_device_name"
                        type="text"
                        name="device_name"
                        placeholder="e.g., Audience Device 01"
                    >

                </div>


                <button
                    type="submit"
                    name="add_device"
                    class="primary-btn"
                >
                    + Add Device
                </button>

            </form>

        </div>


        <!-- DEVICE LIST -->

        <div class="content-card device-list-card">

            <div class="card-header">

                <h2>
                    Device List
                </h2>

            </div>


            <?php if ($result->num_rows > 0): ?>

            <div class="table-container" tabindex="0" role="region" aria-label="Devices table">

                <table>

                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Device Code</th>

                            <th>Device Name</th>

                            <th>Student</th>

                            <th>Status</th>

                            <th>Last Seen</th>

                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php while ($device = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= $device["id"] ?>
                            </td>


                            <td class="device-id">

                                <?= htmlspecialchars(
                                    $device["device_code"]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $device["device_name"]
                                ) ?>

                            </td>


                            <td>

                                <?php if ($device["student_code"]): ?>

                                    <?= htmlspecialchars(
                                        $device["student_code"]
                                    ) ?>

                                    <br>

                                    <small>
                                        <?= htmlspecialchars(
                                            $device["full_name"]
                                        ) ?>
                                    </small>

                                <?php else: ?>

                                    <span>
                                        Unassigned
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if ($device["status"] === "online"): ?>

                                    <span class="device-status device-online" data-device-id="<?= (int) $device['id'] ?>">
                                        ONLINE
                                    </span>

                                <?php else: ?>

                                    <span class="device-status device-offline" data-device-id="<?= (int) $device['id'] ?>">
                                        OFFLINE
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= $device["last_seen"]
                                    ? htmlspecialchars($device["last_seen"])
                                    : "Not connected"
                                ?>

                            </td>


                            <td>

                                <a
                                    href="devices.php?delete=<?= $device["id"] ?>"
                                    class="delete-btn"
                                    onclick="return confirm('Delete this device?')"
                                >
                                    Delete
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

            <?php else: ?>

                <p>
                    No devices found.
                </p>

            <?php endif; ?>

        </div>

    </div>
        <footer class="workspace-footer"><span>© <?= date('Y') ?> Astralis</span><span>Ask. Connect. Understand.</span></footer>
    </main>

</div>

<script src="js/device_presence.js"></script>
</body>

</html>
