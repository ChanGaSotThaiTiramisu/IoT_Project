<?php
// Run once from CLI: php migrations/account_ownership.php admin
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$username = $argv[1] ?? '';
$stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
$stmt->bind_param('s', $username);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
if (!$owner) { throw new RuntimeException('Specify the existing owner username for legacy data.'); }
$owner_id = (int) $owner['id'];
// Keep a private recovery snapshot outside the document root before schema changes.
$backup = [];
foreach (['users', 'students', 'devices', 'question_sets', 'questions', 'sessions', 'responses', 'responses_legacy'] as $table) {
    $backup[$table] = [
        'schema' => $conn->query("SHOW CREATE TABLE `$table`")->fetch_row()[1],
        'rows' => $conn->query("SELECT * FROM `$table`")->fetch_all(MYSQLI_ASSOC),
    ];
}
$backup_path = tempnam(sys_get_temp_dir(), 'ars2-before-ownership-');
chmod($backup_path, 0600);
file_put_contents($backup_path, json_encode($backup, JSON_THROW_ON_ERROR));
foreach (['students', 'devices', 'question_sets', 'sessions'] as $table) {
    if (!$conn->query("SHOW COLUMNS FROM `$table` LIKE 'user_id'")->num_rows) {
        $conn->query("ALTER TABLE `$table` ADD user_id INT UNSIGNED NULL, ADD INDEX (user_id), ADD CONSTRAINT fk_{$table}_owner FOREIGN KEY (user_id) REFERENCES users(id)");
    }
    $conn->query("UPDATE `$table` SET user_id = $owner_id WHERE user_id IS NULL");
    $conn->query("ALTER TABLE `$table` MODIFY user_id INT UNSIGNED NOT NULL");
}
foreach (['students' => 'student_code', 'devices' => 'device_code', 'question_sets' => 'set_name'] as $table => $column) {
    if ($conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '$column'")->num_rows) {
        $conn->query("ALTER TABLE `$table` DROP INDEX `$column`, ADD UNIQUE KEY uq_owner_{$column} (user_id, `$column`)");
    }
}
if ($conn->query("SHOW INDEX FROM responses WHERE Key_name = 'packet_id'")->num_rows) {
    $conn->query('ALTER TABLE responses DROP INDEX packet_id, ADD UNIQUE KEY uq_session_packet (session_id, packet_id)');
}
if (!$conn->query("SHOW COLUMNS FROM users LIKE 'gateway_token'")->num_rows) {
    $conn->query('ALTER TABLE users ADD gateway_token CHAR(64) NULL, ADD UNIQUE KEY uq_gateway_token (gateway_token)');
}
echo "Ownership migration complete. Recovery snapshot: $backup_path\n";
