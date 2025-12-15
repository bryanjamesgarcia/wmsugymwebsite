<?php
$servername = "sql104.infinityfree.com";
$username = "if0_40677566";
$password = "bryanjames0906";
$database = "if0_40677566_db_wmsu_gym";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
