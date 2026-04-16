<?php
// parcel_store.php
session_start();
require __DIR__ . '/db.php'; // pastikan $pdo tersedia

// DEV: tampilkan error PDO (hapus/ubah di production)
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/*
DB expected:
- parcels(id, user_id, nik, category, created_at)
- parcel_outlets(id, parcel_id, outlet_name, amount, created_at)
- parcel_files(id, outlet_id, filename, original_name, mime, size, created_at)
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo "Method not allowed";
  exit;
}

// CSRF check (lebih toleran: token harus sama)
$token = $_POST['token'] ?? '';
if (empty($token) || !isset($_SESSION['parcel_token']) || !hash_equals($_SESSION['parcel_token'], $token)) {
  die('Invalid CSRF token');
}
// NOTE: tidak meng-unset token otomatis agar reload/testing tidak memicu error

// user
$user_id = $_SESSION['user_id'] ?? null;
$nik     = $_SESSION['nik'] ?? null;

// input
$category = trim($_POST['category'] ?? '');
$outlet_names = $_POST['outlet_name'] ?? [];
$outlet_amounts = $_POST['outlet_amount'] ?? [];

if (!$category) die('Kategori harus diisi.');
if (!is_array($outlet_names) || count($outlet_names) < 1) die('Minimal 1 outlet harus diisi.');

// reorganize $_FILES['proofs'] into $proofs[outletIndex] = [file, ...]
function reorganizeProofs($files) {
  $result = [];
  if (!isset($files['name']) || !is_array($files['name'])) return $result;
  foreach ($files['name'] as $outletIndex => $names) {
    if (!is_array($names)) continue;
    foreach ($names as $i => $orig) {
      $result[$outletIndex][] = [
        'name' => $orig,
        'type' => $files['type'][$outletIndex][$i] ?? '',
        'tmp_name' => $files['tmp_name'][$outletIndex][$i] ?? '',
        'error' => $files['error'][$outletIndex][$i] ?? 4,
        'size' => $files['size'][$outletIndex][$i] ?? 0,
      ];
    }
  }
  return $result;
}

$proofs = reorganizeProofs($_FILES['proofs'] ?? []);

// config
$allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
$max_size = 5 * 1024 * 1024;
$upload_dir = __DIR__ . '/uploads';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

try {
  $pdo->beginTransaction();

  // insert parcel
  $stmt = $pdo->prepare("INSERT INTO parcels (user_id, nik, category, created_at) VALUES (?, ?, ?, NOW())");
  $stmt->execute([$user_id, $nik, $category]);
  $parcel_id = $pdo->lastInsertId();

  foreach ($outlet_names as $idx => $rawName) {
    $name = trim((string)$rawName);
    if ($name === '') continue;

    $raw_amount = $outlet_amounts[$idx] ?? null;
    $amount = null;
    if ($raw_amount !== null && $raw_amount !== '') {
      // normalize: remove thousand separators if accidentally present
      $norm = str_replace([',', '.'], '', $raw_amount);
      $amount = is_numeric($norm) ? (float)$norm : null;
      if ($amount !== null && $amount < 0) $amount = null;
    }

    // insert outlet
    $stmt = $pdo->prepare("INSERT INTO parcel_outlets (parcel_id, outlet_name, amount, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$parcel_id, $name, $amount]);
    $outlet_id = $pdo->lastInsertId();

    // handle files for this outlet
    if (!isset($proofs[$idx]) || !is_array($proofs[$idx])) continue;

    foreach ($proofs[$idx] as $file) {
      if ($file['error'] !== UPLOAD_ERR_OK) continue;
      if ($file['size'] > $max_size) continue;

      // mime check
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime = finfo_file($finfo, $file['tmp_name']);
      finfo_close($finfo);

      if (!array_key_exists($mime, $allowed_mimes)) continue;

      $ext = $allowed_mimes[$mime];
      $safe = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
      $dest = $upload_dir . '/' . $safe;

      if (!move_uploaded_file($file['tmp_name'], $dest)) {
        // gagal move => skip file
        continue;
      }

      // insert file record
      $stmt = $pdo->prepare("INSERT INTO parcel_files (outlet_id, filename, original_name, mime, size, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
      $stmt->execute([$outlet_id, $safe, $file['name'], $mime, $file['size']]);
    }
  }

  $pdo->commit();

  // redirect ke view
  header("Location: parcel_view.php?id=" . $parcel_id);
  exit;

} catch (Exception $ex) {
  $pdo->rollBack();
  // log and show message (for dev show actual error)
  error_log("parcel_store error: " . $ex->getMessage());
  // Untuk debugging sementara tampilkan pesan error lengkap:
  die('Terjadi kesalahan saat menyimpan: ' . $ex->getMessage());
}