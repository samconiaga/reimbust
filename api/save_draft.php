<?php
// api/save_draft.php
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

    $category    = trim($_POST['category'] ?? '');
    $nama        = trim($_POST['nama'] ?? '');
    $nik         = trim($_POST['nik'] ?? '');
    $dept        = trim($_POST['dept'] ?? '');
    $tanggal     = normalizeDate($_POST['tanggal'] ?? '');
    $perjalanan  = trim($_POST['perjalananDinas'] ?? ($_POST['perjalanan'] ?? 'Dalam Kota'));
    $tujuan      = trim($_POST['tujuan'] ?? '-') ?: '-';

    if ($category === '' || $nama === '' || $tanggal === '') {
        jsonResponse(['success' => false, 'message' => 'Kolom category, nama, dan tanggal wajib diisi.'], 400);
    }

    // Cari atau buat submission
    $stmt = $pdo->prepare("SELECT id FROM submissions WHERE nama = ? AND tanggal = ? AND tujuan = ? LIMIT 1");
    $stmt->execute([$nama, $tanggal, $tujuan]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $submission_id = (int)$row['id'];
        $pdo->prepare("UPDATE submissions SET status = 'DRAFT' WHERE id = ?")->execute([$submission_id]);
    } else {
        $ins = $pdo->prepare("INSERT INTO submissions (tanggal, nama, nik, departemen, perjalanan_dinas, tujuan, status) VALUES (?, ?, ?, ?, ?, ?, 'DRAFT')");
        $ins->execute([$tanggal, $nama, $nik, $dept, $perjalanan, $tujuan]);
        $submission_id = (int)$pdo->lastInsertId();
    }

    // Hapus item lama untuk kategori ini (agar update)
    $pdo->prepare("DELETE FROM submission_items WHERE submission_id = ? AND category = ?")->execute([$submission_id, $category]);

    // ------------------------------------------------------------------
    // 1. Simpan setiap entri ke tabel submission_items
    // ------------------------------------------------------------------
    if ($category === 'tol') {
        $biayaArray = $_POST['biayaTol'] ?? [];
        if (!is_array($biayaArray)) $biayaArray = [$biayaArray];
        foreach ($biayaArray as $biaya) {
            $biayaVal = floatval(formatNumberInput($biaya));
            $stmt = $pdo->prepare("INSERT INTO submission_items (submission_id, category, biaya) VALUES (?, ?, ?)");
            $stmt->execute([$submission_id, $category, $biayaVal]);
        }
    } elseif ($category === 'bbm') {
        // Ambil semua field array
        $biayaArray = $_POST['biayaBensin'] ?? [];
        if (!is_array($biayaArray)) $biayaArray = [$biayaArray];

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

            // Hitung km terpakai, realisasi
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
            $stmt->execute([$submission_id, $category, $biaya, $keterangan]);
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

    } elseif ($category === 'hotel') {
        $biayaArray = $_POST['biayaHotel'] ?? [];
        if (!is_array($biayaArray)) $biayaArray = [$biayaArray];
        foreach ($biayaArray as $biaya) {
            $biayaVal = floatval(formatNumberInput($biaya));
            $stmt = $pdo->prepare("INSERT INTO submission_items (submission_id, category, biaya) VALUES (?, ?, ?)");
            $stmt->execute([$submission_id, $category, $biayaVal]);
        }
    } elseif ($category === 'makan') {
        // ===== PERUBAHAN: Ambil biayaMakan[] dan makanNama[] =====
        $biayaArray = $_POST['biayaMakan'] ?? [];
        if (!is_array($biayaArray)) $biayaArray = [$biayaArray];

        $namaArray = $_POST['makanNama'] ?? [];
        if (!is_array($namaArray)) $namaArray = array_fill(0, count($biayaArray), $namaArray);

        $count = count($biayaArray);
        for ($i = 0; $i < $count; $i++) {
            $biaya = isset($biayaArray[$i]) ? floatval(formatNumberInput($biayaArray[$i])) : 0;
            $namaOrang = $namaArray[$i] ?? '';

            $keterangan = json_encode(['nama' => $namaOrang]);

            $stmt = $pdo->prepare("INSERT INTO submission_items (submission_id, category, biaya, keterangan) VALUES (?, ?, ?, ?)");
            $stmt->execute([$submission_id, $category, $biaya, $keterangan]);
        }
    } elseif ($category === 'parkir') {
        $biayaArray = $_POST['biayaParkir'] ?? [];
        if (!is_array($biayaArray)) $biayaArray = [$biayaArray];
        foreach ($biayaArray as $biaya) {
            $biayaVal = floatval(formatNumberInput($biaya));
            $stmt = $pdo->prepare("INSERT INTO submission_items (submission_id, category, biaya) VALUES (?, ?, ?)");
            $stmt->execute([$submission_id, $category, $biayaVal]);
        }
    } elseif ($category === 'entertain') {
        $biayaArray = $_POST['biayaEntertain'] ?? [];
        if (!is_array($biayaArray)) $biayaArray = [$biayaArray];
        $denganArray = $_POST['entertainDengan'] ?? [];
        if (!is_array($denganArray)) $denganArray = array_fill(0, count($biayaArray), $denganArray);
        $count = count($biayaArray);
        for ($i = 0; $i < $count; $i++) {
            $biaya = isset($biayaArray[$i]) ? floatval(formatNumberInput($biayaArray[$i])) : 0;
            $dengan = $denganArray[$i] ?? '';
            $keterangan = json_encode(['dengan' => $dengan]);
            $stmt = $pdo->prepare("INSERT INTO submission_items (submission_id, category, biaya, keterangan) VALUES (?, ?, ?, ?)");
            $stmt->execute([$submission_id, $category, $biaya, $keterangan]);
        }
    } elseif ($category === 'lain') {
        $biayaArray = $_POST['totalBiayaLain'] ?? $_POST['biaya'] ?? [];
        if (!is_array($biayaArray)) $biayaArray = [$biayaArray];
        $ketArray = $_POST['keterangan'] ?? [];
        if (!is_array($ketArray)) $ketArray = array_fill(0, count($biayaArray), $ketArray);
        $count = count($biayaArray);
        for ($i = 0; $i < $count; $i++) {
            $biaya = isset($biayaArray[$i]) ? floatval(formatNumberInput($biayaArray[$i])) : 0;
            $ket = $ketArray[$i] ?? '';
            $keterangan = json_encode(['keterangan' => $ket]);
            $stmt = $pdo->prepare("INSERT INTO submission_items (submission_id, category, biaya, keterangan) VALUES (?, ?, ?, ?)");
            $stmt->execute([$submission_id, $category, $biaya, $keterangan]);
        }
    }

    // Hitung total per kategori dari submission_items dan update kolom submissions
    $stmt = $pdo->prepare("SELECT SUM(biaya) as total FROM submission_items WHERE submission_id = ? AND category = ?");
    $stmt->execute([$submission_id, $category]);
    $total = $stmt->fetchColumn() ?: 0;

    $kolomMap = [
        'tol' => 'biaya_tol',
        'bbm' => 'biaya_bensin',
        'hotel' => 'biaya_hotel',
        'makan' => 'biaya_makan',
        'parkir' => 'biaya_parkir',
        'entertain' => 'biaya_entertain',
        'lain' => 'total_biaya_lain'
    ];
    if (isset($kolomMap[$category])) {
        $col = $kolomMap[$category];
        $pdo->prepare("UPDATE submissions SET $col = ? WHERE id = ?")->execute([$total, $submission_id]);
    }

    // ------------------------------------------------------------------
    // 2. Proses upload file (mendukung multiple file)
    // ------------------------------------------------------------------
    $dateFolder = date('Ymd');
    $safeUser = preg_replace('/[^a-z0-9_\-]/i', '_', substr($nama, 0, 50));
    $subdir = "{$safeUser}_{$tanggal}_{$dateFolder}_{$submission_id}";
    $uploadDir = ensureUploadDir($subdir);
    if (!$uploadDir || !is_dir($uploadDir)) {
        throw new Exception('Gagal membuat atau mengakses folder upload.');
    }

    $categoryMap = [
        'tol_file'       => 'tol',
        'fileTol'        => 'tol',
        'file_tol'       => 'tol',
        'bbm_file'       => 'bbm',
        'fileBensin'     => 'bbm',
        'fileBbm'        => 'bbm',
        'hotel_file'     => 'hotel',
        'fileHotel'      => 'hotel',
        'makan_file'     => 'makan',
        'fileMakan'      => 'makan',
        'entertain_file' => 'entertain',
        'fileEntertain'  => 'entertain',
        'ent_photo'      => 'entertain',
        'entertainPhoto' => 'entertain',
        'entertain_photo'=> 'entertain',
        'parkir_file'    => 'parkir',
        'fileParkir'     => 'parkir',
        'lain_file'      => 'lain',
        'fileLain'       => 'lain',
        'file'           => $category,
    ];

    $possibleFields = [];
    if ($category === 'tol') {
        $possibleFields = ['tol_file', 'fileTol', 'file_tol', 'file'];
    } elseif ($category === 'bbm') {
        $possibleFields = ['bbm_file', 'fileBensin', 'fileBbm', 'file'];
    } elseif ($category === 'hotel') {
        $possibleFields = ['hotel_file', 'fileHotel', 'file'];
    } elseif ($category === 'makan') {
        $possibleFields = ['makan_file', 'fileMakan', 'file'];
    } elseif ($category === 'parkir') {
        $possibleFields = ['parkir_file', 'fileParkir', 'file'];
    } elseif ($category === 'entertain') {
        $possibleFields = ['entertain_file', 'fileEntertain', 'file', 'ent_photo', 'entertainPhoto', 'entertain_photo'];
    } elseif ($category === 'lain') {
        $possibleFields = ['lain_file', 'fileLain', 'file'];
    }

    $savedFiles = [];

    $isMultiple = function($fileArray) {
        return isset($fileArray['name']) && is_array($fileArray['name']);
    };

    foreach ($possibleFields as $fieldName) {
        if (empty($_FILES[$fieldName])) continue;

        $fileData = $_FILES[$fieldName];

        if ($isMultiple($fileData)) {
            $total = count($fileData['name']);
            for ($i = 0; $i < $total; $i++) {
                if ($fileData['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                if ($fileData['error'][$i] !== UPLOAD_ERR_OK) {
                    throw new Exception("Upload error pada field {$fieldName}[{$i}]: " . uploadErrorMessage($fileData['error'][$i]));
                }
                if (!is_uploaded_file($fileData['tmp_name'][$i])) {
                    throw new Exception("File tidak valid pada field {$fieldName}[{$i}].");
                }

                $sizeMB = $fileData['size'][$i] / (1024 * 1024);
                if ($sizeMB > $maxFileMB) {
                    throw new Exception("File {$fieldName}[{$i}] terlalu besar (max {$maxFileMB}MB).");
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
                    throw new Exception("Gagal menyimpan file {$fieldName}[{$i}].");
                }

                $catSaved = $categoryMap[$fieldName] ?? $category;
                $stmt = $pdo->prepare("INSERT INTO files (submission_id, category, path, original_name, mime, size_bytes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$submission_id, $catSaved, $saved['path'], $saved['name'], $saved['mime'], $saved['size']]);

                $savedFiles[] = [
                    'field'    => $fieldName,
                    'category' => $catSaved,
                    'path'     => $saved['path'],
                    'name'     => $saved['name'],
                ];
            }
        } else {
            if ($fileData['error'] === UPLOAD_ERR_NO_FILE) continue;
            if ($fileData['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Upload error pada field {$fieldName}: " . uploadErrorMessage($fileData['error']));
            }
            if (!is_uploaded_file($fileData['tmp_name'])) {
                throw new Exception("File tidak valid pada field {$fieldName}.");
            }

            $sizeMB = $fileData['size'] / (1024 * 1024);
            if ($sizeMB > $maxFileMB) {
                throw new Exception("File {$fieldName} terlalu besar (max {$maxFileMB}MB).");
            }

            $originalName = pathinfo($fileData['name'], PATHINFO_FILENAME);
            $basename = strtoupper(preg_replace('/[^a-z0-9_\-]/i', '_', $originalName)) . '_' . uniqid();
            $saved = saveUploadedFile($fileData, $uploadDir, $basename);
            if (!$saved || empty($saved['path'])) {
                throw new Exception("Gagal menyimpan file {$fieldName}.");
            }

            $catSaved = $categoryMap[$fieldName] ?? $category;
            $stmt = $pdo->prepare("INSERT INTO files (submission_id, category, path, original_name, mime, size_bytes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$submission_id, $catSaved, $saved['path'], $saved['name'], $saved['mime'], $saved['size']]);

            $savedFiles[] = [
                'field'    => $fieldName,
                'category' => $catSaved,
                'path'     => $saved['path'],
                'name'     => $saved['name'],
            ];
        }
    }

    jsonResponse([
        'success'       => true,
        'message'       => 'Draft berhasil disimpan.',
        'submission_id' => $submission_id,
        'files'         => $savedFiles,
    ]);
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}