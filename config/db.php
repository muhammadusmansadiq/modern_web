<?php

$host = 'localhost'; 
$username = 'root'; 
$password = ''; 
$dbname = 'project_db'; 

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Function to escape strings
if (!function_exists('escape')) {
    function escape($string) {
        global $pdo;
        return $pdo->quote($string);
    }
}

// Function to close connection
// function closeConnection() {
//     global $pdo;
//     $pdo = null;
// }

// define('BASE_DIR', 'http://localhost/dms_project/');
?>


