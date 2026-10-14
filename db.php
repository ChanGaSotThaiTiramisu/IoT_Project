<?php

$host = "localhost";
$dbname = "ars_db";
$dbuser = "luutanphat1508";
$dbpass = "1";

$conn = new mysqli(
    $host,
    $dbuser,
    $dbpass,
    $dbname
);

if ($conn->connect_error) {
    die(
        "Database connection failed: "
        . $conn->connect_error
    );
}

$conn->set_charset("utf8mb4");

?>
