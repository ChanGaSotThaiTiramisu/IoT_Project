<?php

// Configure these values for each installation or provide the ARS_DB_* environment variables.
$host = getenv('ARS_DB_HOST') ?: 'localhost';
$dbname = getenv('ARS_DB_NAME') ?: 'ars_db';
$dbuser = getenv('ARS_DB_USER') ?: 'ars_app';
$dbpass = getenv('ARS_DB_PASSWORD') ?: '';

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die('Database connection failed. Check the ARS_DB_* settings.');
}
$conn->set_charset('utf8mb4');
