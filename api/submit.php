<?php
// api/submit.php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if (!function_exists('jsonResponse')) {
    function jsonResponse(array $data, int $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}

function uploadErrorMessage($code) {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:   return 'File melebihi batas upload_max_filesize di php.ini';
        case UPLOAD_ERR_FORM_SIZE:  return 'File melebihi batas MAX_FILE_SIZE yang ditentukan form';
        case UPLOAD_ERR_PARTIAL:    return 'File hanya terupload sebagian';
        case UPLOAD_ERR_NO_FILE:    return 'Tidak ada file yang diupload';
        case UPLOAD_ERR_NO_TMP_DIR: return 'Folder temporary tidak ditemukan';
        case UPLOAD_ERR_CANT_WRITE: return 'Gagal menulis file ke disk';
        case UPLOAD_ERR_EXTENSION:  return 'Upload dihentikan oleh ekstensi PHP';
        default: return "Kode error tidak dikenal: $code";
    }
}

try {
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method tidak diizinkan. Gunakan POST.'], 405);
    }

    if (!isset($pdo) || !$pdo) {
        throw new Exception('Koneksi database tidak tersedia');
    }

    // Buat tabel submission_items jika belum ada
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS submission_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            submission_id INT NOT NULL,
            category VARCHAR(50) NOT NULL,
            biaya DECIMAL(10,2) DEFAULT 0,
            keterangan TEXT,
            INDEX (submission_id)
        )
    ");

    $config = require __DIR__ . '/../config.php';
    $maxFileMB = floatval($config['max_file_size_mb'] ?? 10);
    $maxTotalMB = floatval($config['max_total_size_mb'] ?? 40);

    $nama = trim($_POST['nama'] ?? '');
    $nik  = trim($_POST['nik'] ?? '');
    $dept = trim($_POST['dept'] ?? '');
    $tanggal = normalizeDate($_POST['tanggal'] ?? '');
    $perjalanan = trim($_POST['perjalananDinas'] ?? ($_POST['perjalanan'] ?? 'Dalam Kota'));
    $tujuan = trim($_POST['tujuan'] ?? '-') ?: '-';

    if (!$nama || !$tanggal) {
        jsonResponse(['success' => false, 'message' => 'Nama dan Tanggal wajib diisi'], 400);
    }

    // Cek apakah sudah ada submission
    $stmt = $pdo->prepare("SELECT id FROM submissions WHERE nama = ? AND tanggal = ? AND tujuan = ? LIMIT 1");
    $stmt->execute([$nama, $tanggal, $tujuan]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $submission_id = (int)$row['id'];
        // Hapus semua item sebelumnya
        $pdo->prepare("DELETE FROM submission_items WHERE submission_id = ?")->execute([$submission_id]);
    } else {
        // Insert baru
        $ins = $pdo->prepare("INSERT INTO submissions (tanggal, nama, nik, departemen, perjalanan_dinas, tujuan, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, 'SUBMITTED', NOW())");
        $ins->execute([$tanggal, $nama, $nik, $dept, $perjalanan, $tujuan]);
        $submission_id = (int)$pdo->lastInsertId();
    }

    // ------------------------------------------------------------------
    // Proses setiap kategori yang mungkin ada datanya
    // ------------------------------------------------------------------
    $kategoriList = ['tol', 'bbm', 'hotel', 'makan', 'parkir', 'entertain', 'lain'];
    $kolomMap = [
        'tol' => 'biaya_tol',
        'bbm' => 'biaya_bensin',
        'hotel' => 'biaya_hotel',
        'makan' => 'biaya_makan',
        'parkir' => 'biaya_parkir',
        'entertain' => 'biaya_entertain',
        'lain' => 'total_biaya_lain'
    ];

    foreach ($kategoriList as $cat) {
        $biayaField = '';
        if ($cat === 'tol') $biayaField = 'biayaTol';
        elseif ($cat === 'bbm') $biayaField = 'biayaBensin';
        elseif ($cat === 'hotel') $biayaField = 'biayaHotel';
        elseif ($cat === 'makan') $biayaField = 'biayaMakan';
        elseif ($cat === 'parkir') $biayaField = 'biayaParkir';
        elseif ($cat === 'entertain') $biayaField = 'biayaEntertain';
        elseif ($cat === 'lain') $biayaField = 'totalBiayaLain';

        $biayaArray = $_POST[$biayaField] ?? [];
        if (!is_array($biayaArray)) $biayaArray = [$biayaArray];
        if (empty($biayaArray) || (count($biayaArray) == 1 && $biayaArray[0] === '')) continue;

        if ($cat === 'bbm') {
            $platArray = $_POST['platNumber'] ?? [];
            if (!is_array($platArray)) $platArray = array_fill(0, count($biayaArray), $platArray);
            $kmAwalArray = $_POST['km_awal'] ?? [];
            if (!is_array($kmAwalArray)) $kmAwalArray = array_fill(0, count($biayaArray), $kmAwalArray);
            $kmAkhirArray = $_POST['km_akhir'] ?? [];
            if (!is_array($kmAkhirArray)) $kmAkhirArray = array_fill(0, count($biayaArray), $kmAkhirArray);
            $hargaLtrArray = $_POST['harga_ltr'] ?? $_POST['hargaPerLiter'] ?? $_POST['harga_per_liter'] ?? $_POST['harga'] ?? [];
            if (!is_array($hargaLtrArray)) $hargaLtrArray = array_fill(0, count($biayaArray), $hargaLtrArray);
            $literArray = $_POST['liter'] ?? $_POST['bbm_liter'] ?? [];
            if (!is_array($literArray)) $literArray = array_fill(0, count($biayaArray), $literArray);
            $tglAwalArray = $_POST['tgl_awal'] ?? $_POST['tanggal_awal'] ?? $_POST['tglMulai'] ?? [];
            if (!is_array($tglAwalArray)) $tglAwalArray = array_fill(0, count($biayaArray), $tglAwalArray);
            $tglAkhirArray = $_POST['tgl_akhir'] ?? $_POST['tanggal_akhir'] ?? $_POST['tglSelesai'] ?? [];
            if (!is_array($tglAkhirArray)) $tglAkhirArray = array_fill(0, count($biayaArray), $tglAkhirArray);

            $count = count($biayaArray);
            for ($i = 0; $i < $count; $i++) {
                $biaya = isset($biayaArray[$i]) ? floatval(formatNumberInput($biayaArray[$i])) : 0;
                $plat = $platArray[$i] ?? '';
                $kmAwal = isset($kmAwalArray[$i]) ? floatval(formatNumberInput($kmAwalArray[$i])) : 0;
                $kmAkhir = isset($kmAkhirArray[$i]) ? floatval(formatNumberInput($kmAkhirArray[$i])) : 0;
                $hargaLtr = isset($hargaLtrArray[$i]) ? floatval(formatNumberInput($hargaLtrArray[$i])) : 0;
                $liter = isset($literArray[$i]) ? floatval(formatNumberInput($literArray[$i])) : 0;
                $tglAwal = $tglAwalArray[$i] ?? '';
                $tglAkhir = $tglAkhirArray[$i] ?? '';

                $kmTerpakai = ($kmAkhir > $kmAwal) ? ($kmAkhir - $kmAwal) : 0;
                $literFinal = ($liter > 0) ? $liter : (($hargaLtr > 0 && $biaya > 0) ? ($biaya / $hargaLtr) : 0);
                $realisasi = ($literFinal > 0) ? ($kmTerpakai / $literFinal) : 0;

                $keterangan = json_encode([
                    'plat' => $plat,
                    'km_awal' => $kmAwal,
                    'km_akhir' => $kmAkhir,
                    'km_terpakai' => $kmTerpakai,
                    'harga_per_liter' => $hargaLtr,
                    'liter' => $literFinal,
                    'realisasi_km_per_l' => $realisasi,
                    'tgl_awal' => $tglAwal,
                    'tgl_akhir' => $tglAkhir,
                ]);

                $stmt = $pdo->prepare("INSERT INTO submission_items (submission_id, category, biaya, keterangan) VALUES (?, ?, ?, ?)");
                $stmt->execute([$submission_id, $cat, $biaya, $keterangan]);
            }

            // ========== UPDATE PLAT NUMBER KE TABEL SUBMISSIONS ==========
            if (!empty($platArray)) {
                $platList = array_filter($platArray, function($p) { return trim($p) !== ''; });
                $platString = !empty($platList) ? implode(', ', $platList) : '';
                $pdo->prepare("UPDATE submissions SET plat_number = ? WHERE id = ?")->execute([$platString, $submission_id]);
            } else {
                // Jika tidak ada plat sama sekali, kosongkan kolom
                $pdo->prepare("UPDATE submissions SET plat_number = '' WHERE id = ?")->execute([$submission_id]);
            }
            // ==============================================================

        } elseif ($cat === 'entertain') {
            $denganArray = $_POST['entertainDengan'] ?? [];
            if (!is_array($denganArray)) $denganArray = array_fill(0, count($biayaArray), $denganArray);
            $count = count($biayaArray);
            for ($i = 0; $i < $count; $i++) {
                $biaya = isset($biayaArray[$i]) ? floatval(formatNumberInput($biayaArray[$i])) : 0;
                $dengan = $denganArray[$i] ?? '';
                $keterangan = json_encode(['dengan' => $dengan]);
                $stmt = $pdo->prepare("INSERT INTO submission_items (submission_id, category, biaya, keterangan) VALUES (?, ?, ?, ?)");
                $stmt->execute([$submission_id, $cat, $biaya, $keterangan]);
            }
        } elseif ($cat === 'lain') {
            $ketArray = $_POST['keterangan'] ?? [];
            if (!is_array($ketArray)) $ketArray = array_fill(0, count($biayaArray), $ketArray);
            $count = count($biayaArray);
            for ($i = 0; $i < $count; $i++) {
                $biaya = isset($biayaArray[$i]) ? floatval(formatNumberInput($biayaArray[$i])) : 0;
                $ket = $ketArray[$i] ?? '';
                $keterangan = json_encode(['keterangan' => $ket]);
                $stmt = $pdo->prepare("INSERT INTO submission_items (submission_id, category, biaya, keterangan) VALUES (?, ?, ?, ?)");
                $stmt->execute([$submission_id, $cat, $biaya, $keterangan]);
            }
        } elseif ($cat === 'makan') {
            // ===== PERUBAHAN: Ambil makanNama[] =====
            $namaArray = $_POST['makanNama'] ?? [];
            if (!is_array($namaArray)) $namaArray = array_fill(0, count($biayaArray), $namaArray);
            $count = count($biayaArray);
            for ($i = 0; $i < $count; $i++) {
                $biaya = isset($biayaArray[$i]) ? floatval(formatNumberInput($biayaArray[$i])) : 0;
                $namaOrang = $namaArray[$i] ?? '';
                $keterangan = json_encode(['nama' => $namaOrang]);
                $stmt = $pdo->prepare("INSERT INTO submission_items (submission_id, category, biaya, keterangan) VALUES (?, ?, ?, ?)");
                $stmt->execute([$submission_id, $cat, $biaya, $keterangan]);
            }
        } else {
            // tol, hotel, parkir
            foreach ($biayaArray as $biaya) {
                $biayaVal = floatval(formatNumberInput($biaya));
                $stmt = $pdo->prepare("INSERT INTO submission_items (submission_id, category, biaya) VALUES (?, ?, ?)");
                $stmt->execute([$submission_id, $cat, $biayaVal]);
            }
        }

        // Hitung total untuk kategori ini
        $stmt = $pdo->prepare("SELECT SUM(biaya) FROM submission_items WHERE submission_id = ? AND category = ?");
        $stmt->execute([$submission_id, $cat]);
        $total = $stmt->fetchColumn() ?: 0;
        $pdo->prepare("UPDATE submissions SET {$kolomMap[$cat]} = ? WHERE id = ?")->execute([$total, $submission_id]);
    }

    // ------------------------------------------------------------------
    // Proses upload file (sama seperti save_draft)
    // ------------------------------------------------------------------
    // Hitung total ukuran file
    $totalBytes = 0;
    foreach ($_FILES as $field => $fileData) {
        if (is_array($fileData['error'] ?? null)) {
            foreach ($fileData['error'] as $i => $err) {
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                $totalBytes += intval($fileData['size'][$i] ?? 0);
            }
        } else {
            if (($fileData['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $totalBytes += intval($fileData['size'] ?? 0);
        }
    }
    $totalMB = $totalBytes / (1024 * 1024);
    if ($totalMB > $maxTotalMB) {
        jsonResponse(['success' => false, 'message' => "Total ukuran file {$totalMB}MB melebihi batas {$maxTotalMB}MB"], 400);
    }

    // Buat folder upload
    $dateFolder = date('Ymd');
    $safeUser = preg_replace('/[^a-z0-9_\-]/i', '_', substr($nama, 0, 50));
    $subdir = "{$safeUser}_{$tanggal}_{$dateFolder}_{$submission_id}";
    $uploadDir = ensureUploadDir($subdir);
    if (!$uploadDir || !is_dir($uploadDir)) {
        throw new Exception('Gagal membuat atau mengakses folder upload.');
    }

    $categoryMap = [
        'tol_file' => 'tol', 'fileTol' => 'tol', 'file_tol' => 'tol',
        'bbm_file' => 'bbm', 'fileBensin' => 'bbm', 'fileBbm' => 'bbm',
        'hotel_file' => 'hotel', 'fileHotel' => 'hotel',
        'makan_file' => 'makan', 'fileMakan' => 'makan',
        'entertain_file' => 'entertain', 'fileEntertain' => 'entertain',
        'ent_photo' => 'entertain', 'entertainPhoto' => 'entertain',
        'parkir_file' => 'parkir', 'fileParkir' => 'parkir',
        'lain_file' => 'lain', 'fileLain' => 'lain'
    ];

    $savedFiles = [];

    $isMultiple = function($fileArray) {
        return isset($fileArray['name']) && is_array($fileArray['name']);
    };

    foreach ($_FILES as $field => $fileData) {
        if (!isset($fileData['error'])) continue;

        if ($isMultiple($fileData)) {
            $count = count($fileData['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($fileData['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                if ($fileData['error'][$i] !== UPLOAD_ERR_OK) {
                    jsonResponse(['success' => false, 'message' => "Upload error pada field {$field}[{$i}]: " . uploadErrorMessage($fileData['error'][$i])], 400);
                }
                if (!is_uploaded_file($fileData['tmp_name'][$i])) {
                    jsonResponse(['success' => false, 'message' => "File tidak valid pada field {$field}[{$i}]."], 400);
                }

                $sizeMB = $fileData['size'][$i] / (1024 * 1024);
                if ($sizeMB > $maxFileMB) {
                    jsonResponse(['success' => false, 'message' => "File {$field}[{$i}] terlalu besar (max {$maxFileMB}MB)"], 400);
                }

                $fileInfo = [
                    'tmp_name' => $fileData['tmp_name'][$i],
                    'name'     => $fileData['name'][$i],
                    'type'     => $fileData['type'][$i],
                    'size'     => $fileData['size'][$i],
                    'error'    => $fileData['error'][$i],
                ];

                $originalName = pathinfo($fileInfo['name'], PATHINFO_FILENAME);
                $basename = strtoupper(preg_replace('/[^a-z0-9_\-]/i', '_', $originalName)) . '_' . uniqid();
                $saved = saveUploadedFile($fileInfo, $uploadDir, $basename);
                if (!$saved || empty($saved['path'])) {
                    jsonResponse(['success' => false, 'message' => "Gagal menyimpan file {$field}[{$i}]."], 500);
                }

                $cat = $categoryMap[$field] ?? $field;
                $stmt = $pdo->prepare("INSERT INTO files (submission_id, category, path, original_name, mime, size_bytes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$submission_id, $cat, $saved['path'], $saved['name'], $saved['mime'], $saved['size']]);

                $savedFiles[] = ['field' => $field, 'category' => $cat, 'path' => $saved['path']];
            }
        } else {
            if ($fileData['error'] === UPLOAD_ERR_NO_FILE) continue;
            if ($fileData['error'] !== UPLOAD_ERR_OK) {
                jsonResponse(['success' => false, 'message' => "Upload error pada field {$field}: " . uploadErrorMessage($fileData['error'])], 400);
            }
            if (!is_uploaded_file($fileData['tmp_name'])) {
                jsonResponse(['success' => false, 'message' => "File tidak valid pada field {$field}."], 400);
            }

            $sizeMB = $fileData['size'] / (1024 * 1024);
            if ($sizeMB > $maxFileMB) {
                jsonResponse(['success' => false, 'message' => "File {$field} terlalu besar (max {$maxFileMB}MB)"], 400);
            }

            $originalName = pathinfo($fileData['name'], PATHINFO_FILENAME);
            $basename = strtoupper(preg_replace('/[^a-z0-9_\-]/i', '_', $originalName)) . '_' . uniqid();
            $saved = saveUploadedFile($fileData, $uploadDir, $basename);
            if (!$saved || empty($saved['path'])) {
                jsonResponse(['success' => false, 'message' => "Gagal menyimpan file {$field}."], 500);
            }

            $cat = $categoryMap[$field] ?? $field;
            $stmt = $pdo->prepare("INSERT INTO files (submission_id, category, path, original_name, mime, size_bytes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$submission_id, $cat, $saved['path'], $saved['name'], $saved['mime'], $saved['size']]);

            $savedFiles[] = ['field' => $field, 'category' => $cat, 'path' => $saved['path']];
        }
    }

    // Update status dan total_all
    $pdo->prepare("UPDATE submissions SET status = 'SUBMITTED', submitted_at = NOW(), nik = ?, departemen = ? WHERE id = ?")
        ->execute([$nik, $dept, $submission_id]);

    $stmt = $pdo->prepare("SELECT SUM(biaya) FROM submission_items WHERE submission_id = ?");
    $stmt->execute([$submission_id]);
    $total_all = $stmt->fetchColumn() ?: 0;
    $pdo->prepare("UPDATE submissions SET total_all = ? WHERE id = ?")->execute([$total_all, $submission_id]);

    jsonResponse([
        'success' => true,
        'message' => 'Pengajuan berhasil dikirim',
        'submission_id' => $submission_id,
        'files' => $savedFiles
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}