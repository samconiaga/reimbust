<?php
// db.php

$config = require __DIR__ . '/config.php';
$db = $config['db'];

try {
    $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}";

    $pdo = new PDO(
        $dsn,
        $db['user'],
        $db['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection unavailable'
    ]);
    exit;
}