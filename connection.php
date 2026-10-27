<?php

$host = getenv('DB_HOST') ?: 'sql308.infinityfree.com';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'if0_43281539_waste_management';
$username = getenv('DB_USERNAME') ?: 'if0_43281539';
$password = getenv('DB_PASSWORD') ?: '7Mi3wMDMpz';
try {

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    die(
        "Database connection failed. Make sure MySQL is running and listening "
        . "at {$host}:{$port}, and that the '{$dbname}' database exists. "
        . "Configure DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, and DB_PASSWORD "
        . "to match your MySQL setup. Details: " . $e->getMessage()
    );

}
?>