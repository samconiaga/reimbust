<?php
// parcel_delete.php
session_start();
require __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) {
  header('Location: login.php'); exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: parcel_list.php'); exit; }

// ambil record
$stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) { header('Location: parcel_list.php'); exit; }

// cek ownership: hanya yang submit boleh hapus
if ($r['user_id'] != ($_SESSION['user_id'] ?? 0)) {
  die('Not allowed');
}

// hapus file fisik
$filepath = __DIR__ . '/uploads/parcels/' . $r['filename'];
if (is_file($filepath)) @unlink($filepath);

// hapus record DB
$del = $pdo->prepare("DELETE FROM parcels WHERE id = ?");
$del->execute([$id]);

header('Location: parcel_list.php?msg=deleted');
exit;