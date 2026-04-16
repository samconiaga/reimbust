<?php
// config.php
return [
    'db' => [
        'host' => '127.0.0.1',
        'dbname' => 'reimb_db',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],
    'upload_dir' => __DIR__ . '/uploads',
    'max_file_size_mb' => 10,
    'max_total_size_mb' => 40,
];