<?php
// api/get_users.php
require_once __DIR__ . '/../db.php';

try {
    $stmt = $pdo->query("SELECT nik, nama, departemen, uid FROM users ORDER BY nama ASC");
    $users = $stmt->fetchAll();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($users);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([]);
}