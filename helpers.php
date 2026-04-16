<?php
// helpers.php
require_once __DIR__ . '/config.php';
$config = require __DIR__ . '/config.php';

function jsonResponse($obj) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($obj);
    exit;
}

function normalizeDate($v) {
    if (!$v) return null;
    $d = date_create($v);
    return $d ? date_format($d, 'Y-m-d') : null;
}

function formatNumberInput($v) {
    // remove non-digit except dot and minus
    return preg_replace('/[^\d\.\-]/', '', $v);
}

function ensureUploadDir($subdir = '') {
    $cfg = require __DIR__ . '/config.php';
    $base = rtrim($cfg['upload_dir'], '/');
    $path = $base . ($subdir ? '/' . trim($subdir, '/') : '');
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
    return $path;
}

function saveUploadedFile($fileInfo, $targetDir, $prefix = '') {
    // $fileInfo is from $_FILES entry
    if (!isset($fileInfo['tmp_name']) || !is_uploaded_file($fileInfo['tmp_name'])) {
        throw new Exception("File not uploaded");
    }
    $orig = basename($fileInfo['name']);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($orig, PATHINFO_FILENAME));
    $ts = date('Ymd_His');
    $filename = ($prefix ? $prefix . '_' : '') . $safeName . '_' . $ts . ( $ext ? '.' . $ext : '');
    $dest = rtrim($targetDir, '/') . '/' . $filename;
    if (!move_uploaded_file($fileInfo['tmp_name'], $dest)) {
        throw new Exception("Gagal menyimpan file");
    }
    return [
        'path' => $dest,
        'name' => $orig,
        'mime' => mime_content_type($dest),
        'size' => filesize($dest)
    ];
}

function allowedMime($mime) {
    $map = [
      'image/jpeg','image/jpg','image/png','image/gif','image/webp','application/pdf'
    ];
    return in_array($mime, $map);
}