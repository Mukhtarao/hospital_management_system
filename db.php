<?php

$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$user = getenv('DB_USER');
$password = getenv('DB_PASS');
$database = getenv('DB_NAME');

$conn = mysqli_connect(
    $host,
    $user,
    $password,
    $database,
    (int)$port
);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
