<?php

require_once "account.php";
require_once "device_presence.php";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

session_write_close();
$stmt = $conn->prepare(
    "SELECT d.id, CASE WHEN " . DEVICE_ONLINE_SQL . " THEN 'online' ELSE 'offline' END AS status
     FROM devices d WHERE d.user_id = ?"
);
$stmt->bind_param('i', $account_id);
$stmt->execute();
$devices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'devices' => $devices]);
