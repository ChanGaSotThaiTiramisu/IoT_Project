<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$root = dirname(__DIR__);
$tag = 'isolation_' . bin2hex(random_bytes(6));
$dir = sys_get_temp_dir() . '/' . $tag;
mkdir($dir, 0700);
$users = [];
$fixtures = [];
$server = null;
$checks = 0;
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($label); }
    $checks++;
}
function request_page(string $path, ?int $user = 0, ?array $post = null, ?string $token = null): array {
    global $port, $fixtures;
    $headers = "Connection: close\r\n";
    if ($user !== null) $headers .= 'Cookie: PHPSESSID=' . $fixtures[$user]['cookie'] . "\r\n";
    if ($token !== null) $headers .= "Authorization: Bearer $token\r\n";
    if ($post !== null) $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $ctx = stream_context_create(['http' => ['method' => $post === null ? 'GET' : 'POST', 'header' => $headers, 'content' => $post === null ? '' : http_build_query($post), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $body = file_get_contents("http://127.0.0.1:$port/$path", false, $ctx);
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches);
    check(!preg_match('/Fatal error|Warning:|Notice:/', $body), "PHP runtime error at $path");
    return [(int) $matches[1], $body];
}
function upload_csv(string $page, int $user, string $field, string $csv, array $fields = []): void {
    global $port, $fixtures;
    $boundary = 'upload' . bin2hex(random_bytes(8));
    $body = '';
    foreach ($fields as $key => $value) $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";
    $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$field\"; filename=\"test.csv\"\r\nContent-Type: text/csv\r\n\r\n$csv\r\n--$boundary--\r\n";
    $ctx = stream_context_create(['http' => ['method'=>'POST', 'header'=>"Cookie: PHPSESSID=".$fixtures[$user]['cookie']."\r\nContent-Type: multipart/form-data; boundary=$boundary\r\n", 'content'=>$body, 'ignore_errors'=>true]]);
    $result = file_get_contents("http://127.0.0.1:$port/$page", false, $ctx);
    check(!preg_match('/Fatal error|Warning:|Notice:/', $result), 'Import has no PHP error');
}
try {
    for ($i = 0; $i < 2; $i++) {
        $name = $tag . '_' . $i;
        $token = bin2hex(random_bytes(32));
        $conn->query("INSERT INTO users (username,password,full_name,gateway_token) VALUES ('$name','unused','$name','$token')");
        $uid = $conn->insert_id; $users[] = $uid;
        $conn->query("INSERT INTO question_sets (user_id,set_name) VALUES ($uid,'$tag')");
        $set = $conn->insert_id;
        $conn->query("INSERT INTO questions (question_set_id,question_text,option_a,option_b,option_c,option_d) VALUES ($set,'$name question','a','b','c','d')");
        $question = $conn->insert_id;
        $conn->query("INSERT INTO students (user_id,student_code,full_name) VALUES ($uid,'$tag','$name student')");
        $student = $conn->insert_id;
        $conn->query("INSERT INTO devices (user_id,device_code,device_name,student_id,status) VALUES ($uid,'$tag','$name device',$student,'online')");
        $device = $conn->insert_id;
        $conn->query("INSERT INTO sessions (user_id,session_name,question_set_id,status,current_question_id,advance_mode) VALUES ($uid,'$name session',$set,'finished',$question,'manual')");
        $sid = $conn->insert_id;
        $conn->query("INSERT INTO responses (packet_id,session_id,question_id,device_id,student_id,answer) VALUES (1,$sid,$question,$device,$student,'A')");
        $cookie = str_replace('_', '-', $tag) . '-' . $i;
        file_put_contents("$dir/sess_$cookie", 'user_id|i:' . $uid . ';username|s:' . strlen($name) . ':"' . $name . '";full_name|s:' . strlen($name) . ':"' . $name . '";sessions_csrf_token|s:4:"test";');
        $fixtures[] = compact('uid','name','token','set','question','student','device','sid','cookie');
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $server = proc_open([PHP_BINARY, '-d', "session.save_path=$dir", '-S', "127.0.0.1:$port", '-t', $root], [0 => ['pipe','r'], 1 => ['file', "$dir/server.log", 'a'], 2 => ['file', "$dir/server.log", 'a']], $pipes);
    usleep(300000);
    $auto_url = 'api_gateway_state.php?auto_session=1&device_id='.$tag;
    check(request_page($auto_url, null)[0] === 409, 'Auto selection refuses device codes shared by accounts');
    check(request_page('api_gateway_state.php?auto_session=1&device_id=missing-'.$tag, null)[0] === 404, 'Auto selection rejects unknown devices');
    $conn->query("UPDATE devices SET device_code='{$tag}_unique' WHERE id=".$fixtures[0]['device']);
    $auto_url .= '_unique';
    check(request_page($auto_url, 1)[0] === 404, 'Signed-in account cannot discover another account device');
    $auto = json_decode(request_page($auto_url, null)[1], true);
    check($auto['success'] && $auto['session']['status'] === 'waiting' && $auto['session']['id'] === null, 'Known device stays accepted with no running session');
    $conn->query("UPDATE sessions SET status='running' WHERE id=".$fixtures[1]['sid']);
    $auto = json_decode(request_page($auto_url, null)[1], true);
    check($auto['session']['status'] === 'waiting', 'Auto selection ignores another account running session');
    $conn->query("UPDATE sessions SET status='running' WHERE id=".$fixtures[0]['sid']);
    $auto = json_decode(request_page($auto_url, null)[1], true);
    check($auto['session']['id'] === $fixtures[0]['sid'], 'Auto selection finds the owner running session');
    $owner = $fixtures[0];
    $conn->query("INSERT INTO sessions (user_id,session_name,question_set_id,status,current_question_id,advance_mode)
        VALUES ({$owner['uid']},'auto test',{$owner['set']},'running',{$owner['question']},'manual')");
    $new_session = $conn->insert_id;
    $auto = json_decode(request_page($auto_url, null)[1], true);
    check($auto['success'] && $auto['session']['status'] === 'ambiguous' && $auto['session']['id'] === null, 'Multiple running sessions keep BLE accepted without choosing one');
    $conn->query("UPDATE sessions SET status='finished' WHERE id={$owner['sid']}");
    $auto = json_decode(request_page($auto_url, null)[1], true);
    check($auto['session']['id'] === $new_session, 'Auto selection switches to a newly started session');
    $conn->query("DELETE FROM sessions WHERE id=$new_session");
    $conn->query("UPDATE sessions SET status='finished' WHERE id=".$fixtures[1]['sid']);
    $conn->query("UPDATE devices SET device_code='$tag' WHERE id=".$fixtures[0]['device']);
    check(request_page('api_device_presence.php', null)[0] === 401, 'Anonymous device presence blocked');
    check(request_page('api_device_presence.php', 0, [])[0] === 405, 'Device presence is read-only');
    $presence_device = $fixtures[0]['device'];
    $conn->query("UPDATE devices SET status='online', last_seen=NOW() WHERE id=".$fixtures[1]['device']);
    foreach ([
        ['online', 'NOW()', 'online'],
        ['online', 'NOW() - INTERVAL 11 SECOND', 'offline'],
        ['online', 'NULL', 'offline'],
        ['offline', 'NOW()', 'offline'],
    ] as [$stored_status, $last_seen, $expected]) {
        $conn->query("UPDATE devices SET status='$stored_status', last_seen=$last_seen WHERE id=$presence_device");
        [$status, $body] = request_page('api_device_presence.php', 0);
        $presence = json_decode($body, true)['devices'];
        check($status === 200 && count($presence) === 1 && (int)$presence[0]['id'] === $presence_device, 'Presence only exposes own devices');
        check($presence[0]['status'] === $expected, "Presence: $stored_status / $last_seen becomes $expected");
        $online_count = $expected === 'online' ? 1 : 0;
        check(str_contains(request_page('dashboard.php', 0)[1], "data-devices-online>$online_count</h2>"), 'Dashboard uses heartbeat expiry');
        check(str_contains(request_page('devices.php', 0)[1], "class=\"device-status device-$expected\" data-device-id=\"$presence_device\""), 'Device badge uses heartbeat expiry');
    }
    check(request_page('api_gateway_state.php?session_id='.$fixtures[0]['sid'].'&device_id='.$tag, null)[0] === 200, 'Gateway heartbeat reconnects device');
    $presence = json_decode(request_page('api_device_presence.php', 0)[1], true)['devices'];
    check($presence[0]['status'] === 'online', 'Device returns online after a new heartbeat');
    foreach ($fixtures as $i => $own) {
        $other = $fixtures[1-$i];
        foreach (['dashboard.php','students.php','students.php?search='.$tag,'questions.php','devices.php','sessions.php','results.php','import_questions.php','import_students.php'] as $page) {
            [$status,$body] = request_page($page,$i);
            check($status === 200, "$page renders");
            check(!str_contains($body,$other['name']), "$page hides the other account");
        }
        foreach (["results.php?session={$other['sid']}","presentation.php?session={$other['sid']}","api_results.php?session={$other['sid']}","edit_student.php?id={$other['student']}","students.php?delete={$other['student']}","devices.php?delete={$other['device']}","questions.php?set={$other['set']}","questions.php?delete_set={$other['set']}","questions.php?delete_question={$other['question']}","import_questions.php?set={$other['set']}"] as $page) {
            check(request_page($page,$i)[0] === 404, "Foreign ID blocked: $page");
        }
        foreach (['start','finish','next','prev'] as $action) check(request_page("sessions.php?$action={$other['sid']}",$i)[0] === 404, "Foreign session $action blocked");
        foreach (['delete','reopen'] as $action) check(request_page('sessions.php',$i,['session_action'=>$action,'session_id'=>$other['sid'],'csrf_token'=>'test'])[0] === 404, "Foreign $action blocked");
        check(request_page('sessions.php',$i,['save_advance_settings'=>1,'session_id'=>$other['sid']])[0] === 404,'Foreign settings blocked');
        check(request_page('sessions.php',$i,['create_session'=>1,'session_name'=>'forged','question_set_id'=>$other['set']])[0] === 404,'Foreign set creation blocked');
        check(request_page('import_questions.php',$i,['import_file'=>1,'question_set_id'=>$other['set']])[0] === 404,'Foreign import blocked');
        check(request_page('sessions.php',$i,['session_action'=>'reopen','session_id'=>$own['sid'],'csrf_token'=>'test'])[0] === 302,'Own reopen succeeds');
        foreach (["results.php?session={$own['sid']}","presentation.php?session={$own['sid']}","api_results.php?session={$own['sid']}"] as $page) check(request_page($page,$i)[0] === 200, "Own page works: $page");
        check(request_page("api_gateway_state.php?session_id={$own['sid']}&device_id=$tag",null)[0] === 200,'Own gateway works');
        check(request_page("api_gateway_state.php?session_id={$other['sid']}&device_id=$tag",$i)[0] === 404,'Foreign gateway blocked');
        $packet = ['packet_id'=>2,'session_id'=>$own['sid'],'question_id'=>$own['question'],'device_id'=>$tag,'answer'=>'B'];
        check(request_page('api_response.php',null,$packet)[0] === 200,'Own response works');
        $packet['session_id'] = $other['sid'];
        check(request_page('api_response.php',$i,$packet)[0] === 404,'Foreign response blocked');
        check(request_page('api_next_question.php',$i,['session_id'=>$other['sid'],'current_question_id'=>$other['question']])[0] === 404,'Foreign advance blocked');
        check(request_page('questions.php',$i,['create_set'=>1,'set_name'=>$tag.'_new','description'=>''])[0] === 302,'Create owned question set');
        check(request_page('sessions.php',$i,['create_session'=>1,'session_name'=>'Created '.$tag,'question_set_id'=>$own['set']])[0] === 302,'Create owned session');
        check(request_page('students.php',$i,['add_student'=>1,'student_code'=>$tag.'_new','full_name'=>'New','class_name'=>'','email'=>''])[0] === 200,'Create owned student');
        check(request_page('devices.php',$i,['add_device'=>1,'device_code'=>$tag.'_new','device_name'=>'New'])[0] === 200,'Create owned device');
    }
    upload_csv('import_students.php', 0, 'student_file', "Student Code,Full Name\n$tag,Updated owner zero\n");
    check($conn->query('SELECT full_name FROM students WHERE id='.$fixtures[0]['student'])->fetch_assoc()['full_name'] === 'Updated owner zero', 'Import updates own student');
    check($conn->query('SELECT full_name FROM students WHERE id='.$fixtures[1]['student'])->fetch_assoc()['full_name'] === $fixtures[1]['name'].' student', 'Import preserves other student with identical code');
    upload_csv('import_questions.php', 0, 'question_file', "Question,A,B,C,D,Answer\nImported,a,b,c,d,A\n", ['import_file'=>1,'question_set_id'=>$fixtures[0]['set']]);
    check((int) $conn->query('SELECT COUNT(*) n FROM questions WHERE question_set_id='.$fixtures[0]['set'])->fetch_assoc()['n'] === 2, 'Import creates own question');
    check((int) $conn->query('SELECT COUNT(*) n FROM questions WHERE question_set_id='.$fixtures[1]['set'])->fetch_assoc()['n'] === 1, 'Import preserves other question set');
    check(request_page('api_results.php?session='.$fixtures[0]['sid'],null)[0] === 401,'Anonymous results blocked');
    check(request_page('api_response.php',null,[])[0] === 400,'Gateway response requires session ID');
    check(request_page('api_next_question.php',null,[])[0] === 400,'Gateway advance requires session ID');
    check(request_page('api_gateway_state.php',null)[0] === 400,'Gateway state requires session ID');
    check(request_page('api_gateway_state.php?session_id=0&device_id=missing',null)[0] === 400,'Invalid session rejected');
    check(request_page('api_next_question.php',null,['session_id'=>$fixtures[1]['sid'],'current_question_id'=>$fixtures[1]['question']])[0] === 200,'Keyless advance works');
    check(request_page('sessions.php',0,['session_action'=>'delete','session_id'=>$fixtures[0]['sid'],'csrf_token'=>'test'])[0] === 302,'Own delete works');
    check($conn->query('SELECT id FROM responses WHERE session_id='.$fixtures[0]['sid'])->num_rows === 0,'Delete cascades own responses');
    check($conn->query('SELECT id FROM responses WHERE session_id='.$fixtures[1]['sid'])->num_rows === 1,'Other responses preserved');
    echo "PASS: $checks account isolation checks\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    foreach ($users as $uid) {
        foreach (['sessions','devices','students','question_sets'] as $table) $conn->query("DELETE FROM $table WHERE user_id = $uid");
        $conn->query("DELETE FROM users WHERE id = $uid");
    }
    foreach (glob("$dir/*") as $file) unlink($file);
    rmdir($dir);
}
