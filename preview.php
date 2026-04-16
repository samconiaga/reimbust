<?php
session_start();
if (empty($_SESSION['nik'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("127.0.0.1","root","","reimb_db");
$conn->set_charset("utf8mb4");

if (!function_exists('rupiah')) {
    function rupiah($n){
        return "Rp " . number_format((float)$n,0,",",".");
    }
}

if (!function_exists('money_html')) {
    function money_html($n){
        $n = (int)$n;
        $neg = $n < 0;
        $abs = number_format(abs($n), 0, ',', '.');
        $sign = $neg ? '-' : '';
        return '<span class="money"><span class="curr">Rp</span><span class="num">'. $sign . $abs .'</span></span>';
    }
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function human_size($b){
    if (!$b && $b !== 0) return '-';
    if ($b < 1024) return $b . ' B';
    if ($b < 1024*1024) return round($b/1024) . ' KB';
    return round($b/(1024*1024), 2) . ' MB';
}
function truncate($s, $len=80){
    if ($s === null) return '';
    if (strlen($s) <= $len) return $s;
    return substr($s,0,$len-3) . '...';
}

function first_field($row, $names, $default = null){
    foreach ((array)$names as $n){
        if (array_key_exists($n, $row) && $row[$n] !== null) return $row[$n];
    }
    return $default;
}

/**
 * Parse item field yang mungkin berisi JSON -> kembalikan array berisi label & biaya numeric
 */
function parse_item_label_and_amount($item) {
    $category = strtolower(trim($item['category'] ?? ''));
    $ket = $item['keterangan'] ?? '';
    $nama_orang = $item['nama_orang'] ?? '';
    $biaya = intval($item['biaya'] ?? 0);

    $label = '';
    $meta = [];

    // decode nama_orang bila JSON
    $decoded_name = null;
    if (is_string($nama_orang) && $nama_orang !== '') {
        $tmp = @json_decode($nama_orang, true);
        if (is_array($tmp)) $decoded_name = $tmp;
    }

    // decode keterangan bila JSON
    $decoded_ket = null;
    if (is_string($ket) && $ket !== '') {
        $tmp2 = @json_decode($ket, true);
        if (is_array($tmp2)) $decoded_ket = $tmp2;
    }

    // helper to pick best 'person' name
    $pick_person = function() use ($decoded_name, $decoded_ket, $nama_orang, $ket) {
        if (is_array($decoded_name)) {
            if (!empty($decoded_name['dengan'])) return $decoded_name['dengan'];
            if (!empty($decoded_name['nama'])) return $decoded_name['nama'];
            if (!empty($decoded_name['name'])) return $decoded_name['name'];
        }
        if (is_array($decoded_ket)) {
            if (!empty($decoded_ket['dengan'])) return $decoded_ket['dengan'];
            if (!empty($decoded_ket['nama'])) return $decoded_ket['nama'];
            if (!empty($decoded_ket['name'])) return $decoded_ket['name'];
        }
        // fallback to raw strings (but only if not JSON-like)
        if (!empty($nama_orang) && !is_array(@json_decode($nama_orang, true))) return $nama_orang;
        if (!empty($ket) && !is_array(@json_decode($ket, true))) return $ket;
        return '';
    };

    $person = $pick_person();

    // Build a clean label based on category
    if (strpos($category, 'enter') !== false || $category === 'entertain') {
        // entertain
        if ($person) {
            $label = $person;
        } else {
            $label = 'Entertain';
        }

    } elseif (strpos($category, 'makan') !== false || strpos($category, 'mkn') !== false) {
        $label = 'Makan' . ($person ? ': ' . $person : '');

    } elseif (strpos($category, 'hotel') !== false || strpos($category, 'htl') !== false) {
        $label = 'Hotel' . ($person ? ': ' . $person : '');

    } elseif (strpos($category, 'tol') !== false) {
        $label = 'Tol' . ($person ? ': ' . $person : '');

    } elseif (strpos($category, 'park') !== false) {
        $label = 'Parkir' . ($person ? ': ' . $person : '');

    } elseif (strpos($category, 'bbm') !== false || strpos($category, 'bensin') !== false) {

        // if decoded_ket has 'plat' show plat
        if (is_array($decoded_ket) && !empty($decoded_ket['plat'])) {
            $label = 'BBM (Plat: ' . $decoded_ket['plat'] . ')';
        } else {
            $label = 'BBM' . ($person ? ': ' . $person : '');
        }

    } else {

        // other / fallback
        if (is_array($decoded_ket) && !empty($decoded_ket['plat'])) {

            $label = 'Plat: ' . $decoded_ket['plat'];

        } elseif ($person) {

            $label = ucfirst($category ?: 'Item') . ': ' . $person;

        } elseif (!empty($ket)) {

            // ensure not raw JSON shown
            if (is_string($ket) && (strpos($ket,'{') === false && strpos($ket,'[') === false)) {
                $label = $ket;
            } else {
                $label = ucfirst($category ?: 'Item');
            }

        } else {

            $label = ucfirst($category ?: 'Item');

        }
    }

    return [
        'label' => $label,
        'biaya' => $biaya,
        'category' => $category
    ];
}

/**
 * Build aggregate keterangan untuk satu submission (items = array dari submission_items)
 * Mengembalikan:
 *   - main: string (keterangan baris utama)
 *   - entertains: array of ['label','biaya'] untuk entertain tambahan (index 0 juga bisa dipakai pada main)
 */
function buildKeteranganAggregate($items) {
    if (empty($items)) return ['main'=>'-','entertains'=>[]];

    $other_labels = [];
    $entertains = [];

    foreach ($items as $it) {
        $parsed = parse_item_label_and_amount($it);
        $cat = $parsed['category'];
        if (strpos($cat, 'enter') !== false || $cat === 'entertain') {
            $entertains[] = ['label' => $parsed['label'], 'biaya' => $parsed['biaya']];
        } else {
            // avoid duplicates
            if ($parsed['label'] && !in_array($parsed['label'], $other_labels)) $other_labels[] = $parsed['label'];
        }
    }

    // build main text:
    $main = '';
    if (!empty($other_labels)) $main = implode(', ', $other_labels);

    if (!empty($entertains)) {
        // jika hanya 1 entertain -> gabungkan ke main
        if (count($entertains) === 1) {
            $entLabel = $entertains[0]['label'] ?: 'Entertain';
            if ($main !== '') $main .= ', ';
            $main .= 'Entertain: ' . $entLabel;
            // entertains array kept as-is (will not render extra line)
            return ['main' => $main, 'entertains' => $entertains];
        } else {
            // lebih dari 1 entertain -> main gunakan entertain pertama (gabung), sisanya return sebagai tambahan
            $entLabel0 = $entertains[0]['label'] ?: 'Entertain';
            if ($main !== '') $main .= ', ';
            $main .= 'Entertain: ' . $entLabel0;
            // additional entertains are from index 1..
            $additional = array_slice($entertains, 1);
            return ['main' => $main, 'entertains' => $additional];
        }
    } else {
        if ($main === '') $main = '-';
        return ['main' => $main, 'entertains' => []];
    }
}

// --- Serve file jika diminta via ?file_id= (sama seperti asli) ---
if (isset($_GET['file_id'])) {
    $file_id = (int)$_GET['file_id'];

    $sql = "SELECT f.path, f.original_name, f.mime, f.size_bytes, s.nik
            FROM files f
            JOIN submissions s ON f.submission_id = s.id
            WHERE f.id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        http_response_code(500);
        echo "Query error.";
        exit;
    }
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        http_response_code(404);
        echo "File tidak ditemukan.";
        exit;
    }
    $file = $res->fetch_assoc();

    if ($file['nik'] !== $_SESSION['nik']) {
        http_response_code(403);
        echo "Akses ditolak.";
        exit;
    }

    $candidates = [];
    $rawPath = $file['path'];
    $candidates[] = $rawPath;
    $candidates[] = str_replace('\\', '/', $rawPath);
    $candidates[] = __DIR__ . '/' . ltrim($rawPath, '/\\');
    $candidates[] = __DIR__ . '/uploads/' . basename($rawPath);
    $candidates[] = __DIR__ . '/' . preg_replace('#^.*htdocs[\\/]+#i', '', $rawPath);

    $found = false;
    foreach ($candidates as $p) {
        if (!$p) continue;
        $p2 = str_replace('\\', '/', $p);
        if (file_exists($p2)) { $fsPath = $p2; $found = true; break; }
        $rp = realpath($p);
        if ($rp && file_exists($rp)) { $fsPath = $rp; $found = true; break; }
    }

    if (!$found) {
        http_response_code(404);
        echo "File fisik tidak ditemukan di server.";
        exit;
    }

    $mime = $file['mime'] ?: mime_content_type($fsPath);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($fsPath));
    header('Content-Disposition: inline; filename="' . basename($file['original_name']) . '"');
    readfile($fsPath);
    exit;
}

// ---------------------------------------------------------

$nik = $_SESSION['nik'];
$name = $_SESSION['name'] ?? '';

$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';
$perjalanan = $_GET['perjalanan'] ?? '';
$search = isset($_GET['search']);

$data = [];
$groups = [];
$filesAll = [];
$fuel_by_plat = [];
$groupByPerjalanan = false;
$items_by_submission = []; // untuk menyimpan item per submission

if ($search) {
    $where = "WHERE nik = ?";
    $params = [$nik];
    $types = "s";

    if($start){
        $where .= " AND tanggal >= ?";
        $params[] = $start;
        $types .= "s";
    }
    if($end){
        $where .= " AND tanggal <= ?";
        $params[] = $end;
        $types .= "s";
    }
    if($perjalanan !== null && $perjalanan !== ''){
        $where .= " AND perjalanan_dinas = ?";
        $params[] = $perjalanan;
        $types .= "s";
    }

    $query = "SELECT * FROM submissions $where ORDER BY tanggal ASC";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Ambil semua submission_items untuk submission yang ditemukan
    if (!empty($data)) {
        $submissionIds = array_column($data, 'id');
        // build placeholders
        $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
        $typesItems = str_repeat('i', count($submissionIds));
        $sqlItems = "SELECT * FROM submission_items WHERE submission_id IN ($placeholders) ORDER BY id ASC";
        $stmtItems = $conn->prepare($sqlItems);
        if ($stmtItems) {
            // bind params dynamically (mysqli requires references)
            $refs = [];
            $refs[] = & $typesItems;
            foreach ($submissionIds as $k => $idv) {
                $refs[] = & $submissionIds[$k];
            }
            call_user_func_array([$stmtItems, 'bind_param'], $refs);
            $stmtItems->execute();
            $resItems = $stmtItems->get_result();
            while ($item = $resItems->fetch_assoc()) {
                $sid = $item['submission_id'];
                $items_by_submission[$sid][] = $item;
            }
            $stmtItems->close();
        } else {
            // fallback: ambil per submission (lebih lambat, tapi aman)
            foreach ($submissionIds as $sid) {
                $st = $conn->prepare("SELECT * FROM submission_items WHERE submission_id = ?");
                if ($st) {
                    $st->bind_param('i', $sid);
                    $st->execute();
                    $res = $st->get_result();
                    while ($it = $res->fetch_assoc()) $items_by_submission[$sid][] = $it;
                    $st->close();
                }
            }
        }
    }

    $groupByPerjalanan = ($perjalanan !== null && $perjalanan !== '');

    $groups = [];
    $groupIndex = 0;
    foreach ($data as $r) {
        if ($groupByPerjalanan) {
            $key = trim($r['nama'] ?? '') . '||' . trim($r['departemen'] ?? '') . '||' . trim($r['perjalanan_dinas'] ?? '');
            $labelPerjalanan = $r['perjalanan_dinas'] ?? '';
        } else {
            $key = trim($r['nama'] ?? '') . '||' . trim($r['departemen'] ?? '');
            $labelPerjalanan = 'Semua Perjalanan';
        }

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'id' => ++$groupIndex,
                'nama' => $r['nama'] ?? '',
                'departemen' => $r['departemen'] ?? '',
                'perjalanan_dinas' => $labelPerjalanan,
                'rows' => [],
                'total' => 0,
                'kasbon_total' => 0
            ];
        }

        $biaya_entertain = intval(first_field($r, ['biaya_entertain','biaya_enter','biaya_ent'], 0));
        $biaya_hotel = intval(first_field($r, ['biaya_hotel','biaya_htl'], 0));
        $biaya_makan = intval(first_field($r, ['biaya_makan','biaya_mkn'], 0));
        $biaya_bensin = intval(first_field($r, ['biaya_bensin','biaya_bbm','biaya_bensin'], 0));
        $biaya_tol = intval(first_field($r, ['biaya_tol','biaya_toll'], 0));
        $biaya_parkir = intval(first_field($r, ['biaya_parkir','biaya_parking'], 0));
        $total_biaya_lain = intval(first_field($r, ['total_biaya_lain','total_lain','total_biaya_lain'], 0));

        $rowTotal = $biaya_entertain + $biaya_hotel + $biaya_makan + $biaya_bensin + $biaya_tol + $biaya_parkir + $total_biaya_lain;

        $plat_number = first_field($r, ['plat_number','plat','no_pol'], '');
        $km_awal = first_field($r, ['km_awal'], null);
        $km_akhir = first_field($r, ['km_akhir'], null);
        $km_terpakai = first_field($r, ['km_terpakai'], null);
        $km_per_hari = first_field($r, ['km_per_hari'], null);
        $liter = first_field($r, ['liter','liter_ltr','liters'], null);
        $harga_per_liter = first_field($r, ['harga_per_liter','harga_ltr','harga_liter'], null);
        $realisasi_km_per_l = first_field($r, ['realisasi_km_per_l'], null);
        $tgl_awal = first_field($r, ['tgl_awal','tanggal_awal'], null);
        $tgl_akhir = first_field($r, ['tgl_akhir','tanggal_akhir'], null);

        $groups[$key]['rows'][] = [
            'submission_id' => intval($r['id']),
            'tanggal' => $r['tanggal'],
            'tujuan' => $r['tujuan'] ?? '',
            'biaya_entertain' => $biaya_entertain,
            'biaya_hotel' => $biaya_hotel,
            'biaya_makan' => $biaya_makan,
            'biaya_bensin' => $biaya_bensin,
            'biaya_tol' => $biaya_tol,
            'biaya_parkir' => $biaya_parkir,
            'total_biaya_lain' => $total_biaya_lain,
            'row_total' => $rowTotal,
            'plat_number' => $plat_number,
            'km_awal' => is_numeric($km_awal) ? intval($km_awal) : null,
            'km_akhir' => is_numeric($km_akhir) ? intval($km_akhir) : null,
            'km_terpakai' => is_numeric($km_terpakai) ? intval($km_terpakai) : null,
            'km_per_hari' => is_numeric($km_per_hari) ? floatval($km_per_hari) : null,
            'liter' => is_numeric($liter) ? floatval($liter) : null,
            'harga_ltr' => is_numeric($harga_per_liter) ? floatval($harga_per_liter) : null,
            'realisasi_km_per_l' => is_numeric($realisasi_km_per_l) ? floatval($realisasi_km_per_l) : null,
            'tgl_awal' => $tgl_awal ?: null,
            'tgl_akhir' => $tgl_akhir ?: null,
        ];
        $groups[$key]['total'] += $rowTotal;
        $groups[$key]['kasbon_total'] += intval($r['kasbon'] ?? 0);
    }

    // fetch all files (sama seperti asli)
    $submissionIds = array_column($data, 'id');
    $filesAll = [];
    if (!empty($submissionIds)) {
        $ids_safe = implode(',', array_map('intval', $submissionIds));
        $sqlFAll = "SELECT f.*, s.tanggal as submission_tanggal, s.tujuan as submission_tujuan, s.id as submission_id, f.category
                    FROM files f
                    JOIN submissions s ON f.submission_id = s.id
                    WHERE f.submission_id IN ($ids_safe)
                    ORDER BY f.uploaded_at ASC";
        $resFA = $conn->query($sqlFAll);
        if ($resFA) {
            while ($rowF = $resFA->fetch_assoc()) $filesAll[] = $rowF;
        }
    }

    // ==================== BAGIAN FUEL BY PLAT (sama persis seperti asli) ====================
    // build quick lookup of rows by plate+date
    $rows_by_plate_date = [];
    foreach ($groups as $g) {
        foreach ($g['rows'] as $rr) {
            $plat = trim($rr['plat_number'] ?: 'TANPA PLAT');
            if ($plat === '') $plat = 'TANPA PLAT';
            $date = $rr['tanggal'] ?: '';
            $key = $plat . '||' . $date;
            if (!isset($rows_by_plate_date[$key])) $rows_by_plate_date[$key] = [];
            $rows_by_plate_date[$key][] = $rr;
        }
    }

    // try fill missing km_awal/km_akhir from same-plate same-date entries
    foreach ($groups as &$g) {
        foreach ($g['rows'] as &$rr) {
            $plat = trim($rr['plat_number'] ?: 'TANPA PLAT');
            if ($plat === '') $plat = 'TANPA PLAT';
            $date = $rr['tanggal'] ?: '';
            $key = $plat . '||' . $date;

            if ((!isset($rr['km_awal']) || $rr['km_awal'] === null) && isset($rows_by_plate_date[$key])) {
                foreach ($rows_by_plate_date[$key] as $cand) {
                    if (isset($cand['km_awal']) && $cand['km_awal'] !== null) { $rr['km_awal'] = intval($cand['km_awal']); break; }
                    if (isset($cand['km_akhir']) && $cand['km_akhir'] !== null) { $rr['km_awal'] = intval($cand['km_akhir']); break; }
                }
            }
            if ((!isset($rr['km_akhir']) || $rr['km_akhir'] === null) && isset($rows_by_plate_date[$key])) {
                foreach ($rows_by_plate_date[$key] as $cand) {
                    if (isset($cand['km_akhir']) && $cand['km_akhir'] !== null) { $rr['km_akhir'] = intval($cand['km_akhir']); break; }
                    if (isset($cand['km_awal']) && $cand['km_awal'] !== null) { $rr['km_akhir'] = intval($cand['km_awal']); break; }
                }
            }
        }
        unset($rr);
    }
    unset($g);

    // Build fuel_by_plat after the initial autofill
    $fuel_by_plat = [];
    foreach ($groups as $g) {
        foreach ($g['rows'] as $r) {
            $hasFuel = ($r['biaya_bensin'] ?? 0) > 0 || ($r['liter'] !== null && $r['liter'] > 0) || ($r['harga_ltr'] !== null && $r['harga_ltr'] > 0) || ($r['km_awal'] !== null && $r['km_akhir'] !== null) || ($r['km_terpakai'] !== null);
            if (!$hasFuel) continue;
            $plat = trim($r['plat_number'] ?: 'TANPA PLAT');
            if ($plat === '') $plat = 'TANPA PLAT';
            if (!isset($fuel_by_plat[$plat])) {
                $fuel_by_plat[$plat] = [
                    'plat' => $plat,
                    'total_bensin' => 0.0,
                    'tgl_awal' => $r['tgl_awal'] ?: $r['tanggal'],
                    'tgl_akhir' => $r['tgl_akhir'] ?: $r['tanggal'],
                    'km_awal' => $r['km_awal'],
                    'km_akhir' => $r['km_akhir'],
                    'km_terpakai' => $r['km_terpakai'],
                    'liter' => 0.0,
                    'harga_ltr' => $r['harga_ltr'] ?: null,
                    'realisasi_km_per_l' => $r['realisasi_km_per_l'] ?: null,
                    'rows' => []
                ];
            }

            // accumulate totals & ranges
            $fuel_by_plat[$plat]['total_bensin'] += floatval($r['biaya_bensin'] ?? 0);
            if ($r['liter']) $fuel_by_plat[$plat]['liter'] += floatval($r['liter']);
            if ($r['harga_ltr']) $fuel_by_plat[$plat]['harga_ltr'] = floatval($r['harga_ltr']); // take last known price

            // km min/max
            if ($r['km_awal'] !== null) {
                if (!isset($fuel_by_plat[$plat]['km_awal']) || $fuel_by_plat[$plat]['km_awal'] === null) $fuel_by_plat[$plat]['km_awal'] = $r['km_awal'];
                else $fuel_by_plat[$plat]['km_awal'] = min($fuel_by_plat[$plat]['km_awal'], $r['km_awal']);
            }
            if ($r['km_akhir'] !== null) {
                if (!isset($fuel_by_plat[$plat]['km_akhir']) || $fuel_by_plat[$plat]['km_akhir'] === null) $fuel_by_plat[$plat]['km_akhir'] = $r['km_akhir'];
                else $fuel_by_plat[$plat]['km_akhir'] = max($fuel_by_plat[$plat]['km_akhir'], $r['km_akhir']);
            }
            // accumulate explicit km_terpakai if provided (summation)
            if ($r['km_terpakai'] !== null) {
                if (!isset($fuel_by_plat[$plat]['km_terpakai']) || $fuel_by_plat[$plat]['km_terpakai'] === null) $fuel_by_plat[$plat]['km_terpakai'] = 0;
                $fuel_by_plat[$plat]['km_terpakai'] += intval($r['km_terpakai']);
            }

            // adjust tgl range using provided tgl_awal/tgl_akhir or submission tanggal
            $ta = $r['tgl_awal'] ?: $r['tanggal'];
            $tb = $r['tgl_akhir'] ?: $r['tanggal'];
            if ($ta && strtotime($ta) < strtotime($fuel_by_plat[$plat]['tgl_awal'])) $fuel_by_plat[$plat]['tgl_awal'] = $ta;
            if ($tb && strtotime($tb) > strtotime($fuel_by_plat[$plat]['tgl_akhir'])) $fuel_by_plat[$plat]['tgl_akhir'] = $tb;

            $fuel_by_plat[$plat]['rows'][] = $r;
        }
    }

    // For each plate, compute historical average realisasi_km_per_l if available
    foreach ($fuel_by_plat as $plat => &$fb) {
        // default harga per liter placeholder
        if (empty($fb['harga_ltr']) || !is_numeric($fb['harga_ltr']) || floatval($fb['harga_ltr']) <= 0) {
            $fb['harga_ltr'] = 10000.0;
        } else {
            $fb['harga_ltr'] = floatval($fb['harga_ltr']);
        }

        // Compute liters from total_bensin if liter not provided
        $fb['total_bensin'] = floatval($fb['total_bensin'] ?? 0.0);
        if ((empty($fb['liter']) || !is_numeric($fb['liter']) || $fb['liter'] <= 0) && $fb['harga_ltr'] > 0) {
            $fb['liter'] = $fb['total_bensin'] / $fb['harga_ltr'];
        } else {
            $fb['liter'] = floatval($fb['liter'] ?? 0.0);
        }

        // compute basic stats from rows
        $dates = array_filter(array_map(function($rr){ return $rr['tanggal'] ?? null; }, $fb['rows']));
        $minDate = !empty($dates) ? min($dates) : null;
        $maxDate = !empty($dates) ? max($dates) : null;

        // gather candidate realisasi values from rows
        $realisasi_values = [];
        foreach ($fb['rows'] as $rr) {
            if (!empty($rr['realisasi_km_per_l']) && is_numeric($rr['realisasi_km_per_l']) && floatval($rr['realisasi_km_per_l']) > 0) {
                $realisasi_values[] = floatval($rr['realisasi_km_per_l']);
            } elseif (!empty($rr['km_terpakai']) && !empty($rr['liter']) && $rr['liter'] > 0) {
                $realisasi_values[] = $rr['km_terpakai'] / $rr['liter'];
            }
        }

        // Try read historical realisasi for this plate (last 50) to compute average
        $hist_realisasi_avg = null;
        $stmtHist = $conn->prepare("SELECT km_terpakai, liter, realisasi_km_per_l FROM submissions WHERE plat_number = ? AND (realisasi_km_per_l IS NOT NULL OR (km_terpakai IS NOT NULL AND liter IS NOT NULL AND liter > 0)) ORDER BY tanggal DESC LIMIT 50");
        if ($stmtHist) {
            $stmtHist->bind_param('s', $plat);
            $stmtHist->execute();
            $resH = $stmtHist->get_result();
            $hist_vals = [];
            while ($rowH = $resH->fetch_assoc()) {
                if (!empty($rowH['realisasi_km_per_l'])) $hist_vals[] = floatval($rowH['realisasi_km_per_l']);
                elseif (!empty($rowH['km_terpakai']) && !empty($rowH['liter']) && floatval($rowH['liter']) > 0) $hist_vals[] = floatval($rowH['km_terpakai']) / floatval($rowH['liter']);
            }
            if (!empty($hist_vals)) $hist_realisasi_avg = array_sum($hist_vals) / count($hist_vals);
            $stmtHist->close();
        }

        // combine available realisasi sources to choose a fallback
        if (empty($realisasi_values) && $hist_realisasi_avg !== null) {
            $fb['realisasi_km_per_l'] = round($hist_realisasi_avg, 2);
        } elseif (!empty($realisasi_values)) {
            $fb['realisasi_km_per_l'] = round(array_sum($realisasi_values) / count($realisasi_values), 2);
        } else {
            $fb['realisasi_km_per_l'] = null;
        }

        // pick earliest row by date (already sorted? ensure)
        $earliestRow = null;
        if (!empty($fb['rows'])) {
            usort($fb['rows'], function($a,$b){
                return strtotime($a['tanggal']) - strtotime($b['tanggal']);
            });
            $earliestRow = $fb['rows'][0];
        }

        // Try estimate distance from earliest BBM entry in range if realisasi known
        if ($earliestRow) {
            $row_biaya = floatval($earliestRow['biaya_bensin'] ?? 0.0);
            $row_liter = floatval($earliestRow['liter'] ?? 0.0);
            $row_harga = floatval($earliestRow['harga_ltr'] ?? $fb['harga_ltr']);
            $row_realisasi = isset($earliestRow['realisasi_km_per_l']) && is_numeric($earliestRow['realisasi_km_per_l']) ? floatval($earliestRow['realisasi_km_per_l']) : null;

            if ($row_liter <= 0 && $row_biaya > 0 && $row_harga > 0) {
                $row_liter = $row_biaya / $row_harga;
            }

            if (empty($row_realisasi) || $row_realisasi <= 0) {
                if (!empty($fb['realisasi_km_per_l']) && is_numeric($fb['realisasi_km_per_l'])) {
                    $row_realisasi = floatval($fb['realisasi_km_per_l']);
                } elseif (!empty($hist_realisasi_avg)) {
                    $row_realisasi = floatval($hist_realisasi_avg);
                } else {
                    $row_realisasi = 12.0;
                }
            }

            if ($row_liter > 0 && $row_realisasi > 0) {
                $estimated_distance = intval(round($row_liter * $row_realisasi));
                $fb['__earliest_estimated_distance'] = $estimated_distance;
            } elseif ($fb['liter'] > 0 && isset($fb['realisasi_km_per_l']) && $fb['realisasi_km_per_l'] > 0) {
                $fb['__earliest_estimated_distance'] = intval(round($fb['liter'] * $fb['realisasi_km_per_l']));
                $estimated_distance = $fb['__earliest_estimated_distance'];
            } else {
                $estimated_distance = null;
            }

            if (isset($estimated_distance) && $estimated_distance !== null) {
                if ((!isset($fb['km_awal']) || $fb['km_awal'] === null) && isset($fb['km_akhir']) && $fb['km_akhir'] !== null) {
                    $computed = intval(round($fb['km_akhir'] - $estimated_distance));
                    if ($computed >= 0) $fb['km_awal'] = $computed;
                }

                if ((!isset($fb['km_awal']) || $fb['km_awal'] === null) && (!isset($fb['km_akhir']) || $fb['km_akhir'] === null)) {
                    $fb['km_awal'] = intval($estimated_distance);
                }

                if ((!isset($fb['km_akhir']) || $fb['km_akhir'] === null) && isset($fb['km_awal']) && $fb['km_awal'] !== null) {
                    $fb['km_akhir'] = intval(round($fb['km_awal'] + $estimated_distance));
                }

                if ((!isset($fb['km_terpakai']) || $fb['km_terpakai'] === null) && isset($fb['km_awal']) && isset($fb['km_akhir']) && $fb['km_awal'] !== null && $fb['km_akhir'] !== null) {
                    $fb['km_terpakai'] = intval($fb['km_akhir']) - intval($fb['km_awal']);
                    if ($fb['km_terpakai'] < 0) $fb['km_terpakai'] = null;
                }

                if ((!isset($fb['km_terpakai']) || $fb['km_terpakai'] === null) && isset($estimated_distance)) {
                    $fb['km_terpakai'] = intval($estimated_distance);
                }
            }
        }

        // If km_awal or km_akhir still empty, attempt DB lookups:
        if ($minDate) {
            $sqlPrev = "SELECT km_akhir, km_awal, tanggal FROM submissions WHERE plat_number = ? AND tanggal < ? AND (km_akhir IS NOT NULL OR km_awal IS NOT NULL) ORDER BY tanggal DESC LIMIT 1";
            $stPrev = $conn->prepare($sqlPrev);
            if ($stPrev) {
                $stPrev->bind_param('ss', $plat, $minDate);
                $stPrev->execute();
                $resPrev = $stPrev->get_result();
                if ($resPrev && $resPrev->num_rows > 0) {
                    $rp = $resPrev->fetch_assoc();
                    if (!isset($fb['km_awal']) || $fb['km_awal'] === null) {
                        if (!empty($rp['km_akhir'])) $fb['km_awal'] = intval($rp['km_akhir']);
                        elseif (!empty($rp['km_awal'])) $fb['km_awal'] = intval($rp['km_awal']);
                    }
                }
                $stPrev->close();
            }
        }

        if ($maxDate) {
            $sqlNext = "SELECT km_awal, km_akhir, tanggal FROM submissions WHERE plat_number = ? AND tanggal > ? AND (km_awal IS NOT NULL OR km_akhir IS NOT NULL) ORDER BY tanggal ASC LIMIT 1";
            $stNext = $conn->prepare($sqlNext);
            if ($stNext) {
                $stNext->bind_param('ss', $plat, $maxDate);
                $stNext->execute();
                $resNext = $stNext->get_result();
                if ($resNext && $resNext->num_rows > 0) {
                    $rn = $resNext->fetch_assoc();
                    if (!isset($fb['km_akhir']) || $fb['km_akhir'] === null) {
                        if (!empty($rn['km_awal'])) $fb['km_akhir'] = intval($rn['km_awal']);
                        elseif (!empty($rn['km_akhir'])) $fb['km_akhir'] = intval($rn['km_akhir']);
                    }
                }
                $stNext->close();
            }
        }

        // After DB lookups, if km_awal still missing but we have __earliest_estimated_distance and km_akhir -> compute km_awal
        if ((!isset($fb['km_awal']) || $fb['km_awal'] === null) && isset($fb['__earliest_estimated_distance']) && isset($fb['km_akhir']) && $fb['km_akhir'] !== null) {
            $computed = intval(round($fb['km_akhir'] - $fb['__earliest_estimated_distance']));
            if ($computed >= 0) $fb['km_awal'] = $computed;
        }

        if ((!isset($fb['km_akhir']) || $fb['km_akhir'] === null) && isset($fb['__earliest_estimated_distance']) && isset($fb['km_awal']) && $fb['km_awal'] !== null) {
            $computed = intval(round($fb['km_awal'] + $fb['__earliest_estimated_distance']));
            if ($computed >= 0) $fb['km_akhir'] = $computed;
        }

        if ((!isset($fb['km_terpakai']) || $fb['km_terpakai'] === null) && $fb['km_awal'] !== null && $fb['km_akhir'] !== null) {
            $fb['km_terpakai'] = intval($fb['km_akhir']) - intval($fb['km_awal']);
            if ($fb['km_terpakai'] < 0) $fb['km_terpakai'] = null;
        }

        if ((!isset($fb['km_terpakai']) || $fb['km_terpakai'] === null) && isset($fb['liter']) && $fb['liter'] > 0 && isset($fb['realisasi_km_per_l']) && $fb['realisasi_km_per_l'] > 0) {
            $fb['km_terpakai'] = intval(round($fb['liter'] * $fb['realisasi_km_per_l']));
        }

        // Compute days
        $days = 0;
        if (!empty($fb['tgl_awal']) && !empty($fb['tgl_akhir'])) {
            $days = max(1, (int)floor((strtotime($fb['tgl_akhir']) - strtotime($fb['tgl_awal'])) / 86400) + 1);
        } elseif (!empty($fb['rows'])) {
            $dates2 = array_map(function($rr){ return $rr['tanggal'] ?? null; }, $fb['rows']);
            $dates2 = array_filter($dates2);
            if (!empty($dates2)) {
                $min = min($dates2); $max = max($dates2);
                if ($min && $max) $days = max(1, (int)floor((strtotime($max) - strtotime($min)) / 86400) + 1);
            }
        }

        if (isset($fb['km_terpakai']) && $fb['km_terpakai'] !== null && $days > 0) {
            $fb['km_per_hari'] = round($fb['km_terpakai'] / $days, 2);
        } else {
            $fb['km_per_hari'] = null;
        }

        if ((!isset($fb['realisasi_km_per_l']) || $fb['realisasi_km_per_l'] === null) && isset($fb['liter']) && $fb['liter'] > 0 && isset($fb['km_terpakai']) && $fb['km_terpakai'] !== null) {
            $fb['realisasi_km_per_l'] = round($fb['km_terpakai'] / max(0.00001, $fb['liter']), 2);
        } elseif (isset($fb['realisasi_km_per_l'])) {
            $fb['realisasi_km_per_l'] = is_numeric($fb['realisasi_km_per_l']) ? round($fb['realisasi_km_per_l'],2) : null;
        } else {
            $fb['realisasi_km_per_l'] = null;
        }

        $fb['total_bensin'] = floatval($fb['total_bensin'] ?? 0.0);
        $fb['liter'] = floatval($fb['liter'] ?? 0.0);
        $fb['harga_ltr'] = floatval($fb['harga_ltr'] ?? 10000.0);
        if (isset($fb['km_terpakai'])) $fb['km_terpakai'] = is_numeric($fb['km_terpakai']) ? intval($fb['km_terpakai']) : null;
        if (isset($fb['km_awal']) && $fb['km_awal'] !== null) $fb['km_awal'] = intval($fb['km_awal']);
        if (isset($fb['km_akhir']) && $fb['km_akhir'] !== null) $fb['km_akhir'] = intval($fb['km_akhir']);
    }
    unset($fb);
    // ==================== AKHIR BAGIAN FUEL ====================
}

$queryParams = [];
if ($start) $queryParams['start'] = $start;
if ($end) $queryParams['end'] = $end;
if ($perjalanan !== null && $perjalanan !== '') $queryParams['perjalanan'] = $perjalanan;
$queryParams['all'] = '1';
$print_all_url = 'print.php?' . http_build_query($queryParams);

$can_print = $search && (!empty($groups) || !empty($filesAll));
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Expense Reimbursement System</title>
<style>
/* ==========================
   GLOBAL VARIABLES & RESET
   ========================== */
:root{
  --brand: #8b0000;
  --bg: #f3f4f7;
  --card: #fff;
  --muted: #666;
  --radius: 10px;
  --maxwidth: 1100px;
  --table-font: 12px; /* ukuran font tabel */
  --ui-font: 13px;    /* ukuran font body / UI */
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
  background: var(--bg);
  color: #222;
  font-size: var(--ui-font);
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
}

/* ==========================
   TOP NAV, LAYOUT, CARDS
   ========================== */
.topnav{
  background: var(--card);
  padding:10px 14px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:8px;
  box-shadow:0 2px 6px rgba(0,0,0,.06);
  position:sticky;
  top:0;
  z-index:10;
}
.topnav .left, .topnav .right{display:flex;gap:8px;align-items:center}
.topnav a{
  background:var(--brand);
  color:#fff;
  padding:6px 10px;
  border-radius:8px;
  text-decoration:none;
  font-weight:700;
  font-size:13px;
}
.container{
  width:100%;
  max-width:var(--maxwidth);
  margin:12px auto;
  padding:0 12px;
}
.title{text-align:center;font-size:18px;margin:10px 0;font-weight:700}

.card{
  background:var(--card);
  border-radius:var(--radius);
  padding:14px;
  margin-bottom:14px;
  box-shadow:0 6px 18px rgba(0,0,0,.04);
}

/* small section badges */
.section-title{display:inline-block;background:var(--brand);color:#fff;padding:6px 10px;border-radius:6px;font-weight:700;margin-bottom:10px}
.fuel-title{background:#6e0000;color:#fff;padding:8px 10px;border-radius:6px;margin-bottom:8px;font-weight:700}

/* ==========================
   TABLE: RESPONSIVE & GLOBAL
   ========================== */
.table-responsive{
  width:100%;
  overflow:auto;
  margin-top:10px;
}
table{
  width:100%;
  border-collapse:collapse;
  min-width:0;
  font-size: var(--table-font);
  table-layout: auto;
}
th, td{
  border:1px solid #e6e6e6;
  padding:6px 8px;
  text-align:left;
  vertical-align:middle;
  color:#222;
  word-break:break-word;
  line-height:1.25;
}
th{
  background:#fafafa;
  font-weight:700;
  color:#333;
  font-size:11px;
  letter-spacing:0.2px;
}
.right { text-align: right }

/* nicer card-table appearance */
.card .table-responsive table {
  border-collapse: separate;
  border-spacing: 0;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}
.card .table-responsive th {
  background-color: #f5f5f5;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 11px;
  letter-spacing: 0.3px;
  color: #333;
  border-bottom: 2px solid #ddd;
}
.card .table-responsive td { background-color: #fff; }
.card .table-responsive tr:last-child td { border-bottom: none; }
.card .table-responsive tbody tr:hover td { background-color: #f9f9f9; }
.card .table-responsive td:last-child { font-weight: 600; background-color: #fef9e7; }

/* ==========================
   EXPENSE TABLE - LAYOUT FIX
   ========================== */
/* prefer fixed layout for predictable column widths on desktop */
.expense-table {
  table-layout: fixed; /* forces columns to obey widths */
  min-width: 900px;    /* allow horizontal scroll for small screens */
}

/* Column widths: if HTML uses colgroup these will match; otherwise apply to first/nth columns */
.expense-table th:first-child,
.expense-table td:first-child,
.expense-table .col-date,
.expense-table .td-date {
  width: 100px;
  white-space: nowrap;      /* prevent wrap of date */
  text-align: center;
  font-size: 12px;
  padding:6px 8px;
}

/* Keterangan column (allow wrap but limit width) */
.expense-table th:nth-child(2),
.expense-table td.td-keterangan {
  width: 280px;
  max-width: 280px;
  overflow-wrap: break-word;
  word-break: break-word;
  white-space: normal;
  font-size: 12px;
}

/* nominal columns (consistent width) */
.expense-table th:nth-child(3),
.expense-table th:nth-child(4),
.expense-table th:nth-child(5),
.expense-table th:nth-child(6),
.expense-table th:nth-child(7),
.expense-table th:nth-child(8),
.expense-table th:nth-child(9),
.expense-table td:nth-child(n+3):nth-child(-n+9) {
  width: 85px;
  white-space: nowrap; /* keep numeric values in one line */
  text-align: right;
  font-size: 12px;
  padding:6px 8px;
}

/* last column JUMLAH */
.expense-table th:last-child,
.expense-table td:last-child {
  width: 95px;
  text-align: right;
  background: #fef9e7;
  font-weight:700;
  white-space: nowrap;
  font-size: 12px;
}

/* money display */
.money{
  display:inline-flex;
  align-items:center;
  gap:6px;
  white-space:nowrap;
  justify-content:flex-end;
  font-size:12px;
}
.money .curr{font-weight:700;white-space:nowrap;margin-right:6px;font-size:11px}
.money .num{min-width:56px;text-align:right;font-variant-numeric:tabular-nums;display:inline-block}

/* Sub-row entertain (lighter, indented) */
.entertain-subrow {
  background-color: #fcfcfc;
  font-size: 11px;
}
.entertain-subrow td:first-child {
  color: #999;
  font-size: 12px;
  text-align: center;
}
.entertain-subrow .td-keterangan {
  padding-left: 16px !important;
  font-style: italic;
  color: #555;
}

/* ==========================
   FUEL TABLE (LAPORAN BENSIN)
   ========================== */
.fuel-table {
  table-layout: auto;
  min-width: 720px;
  font-size: 12px;
}
.fuel-table th, .fuel-table td {
  padding:6px 8px;
  font-size: 12px;
  white-space: nowrap; /* keep date and small numeric cells single-line */
  text-align: center;
}
.fuel-table td.td-keterangan {
  white-space: normal;
  max-width: 220px;
  word-break:break-word;
  text-align:left;
  padding-left:8px;
}

/* specific fuel date column if exists */
.fuel-table th.col-date,
.fuel-table td.td-date,
.fuel-table td:first-child {
  white-space: nowrap;
  text-align: center;
  width: 95px;
}

/* ==========================
   SUMMARY BOX
   ========================== */
.summary-row{display:flex;justify-content:flex-end;margin-top:12px}
.summary-box {
  width:320px;
  border:1px solid #e0e0e0;
  border-radius:8px;
  overflow:hidden;
  background:#fff;
  box-shadow:0 2px 6px rgba(0,0,0,0.05);
}
.summary-box td {
  padding:10px 12px;
  border-bottom:1px solid #eee;
  font-size:12px;
}
.summary-box tr:last-child td {
  border-bottom:none;
  background-color:#f0f7f0;
  font-weight:700;
}
.summary-box td.right { font-weight:600; text-align:right }

/* ==========================
   FORMS, BUTTONS, UI
   ========================== */
.search-form{display:flex;flex-wrap:wrap;gap:8px;align-items:end}
.search-form .field{flex:1;min-width:140px}
.search-form .small{min-width:120px}
.btn{background:var(--brand);color:#fff;padding:9px 12px;border-radius:8px;border:none;text-decoration:none;font-weight:700;display:inline-block}
.btn.disabled{background:#ccc;color:#666;pointer-events:none;opacity:.8}

/* ==========================
   MOBILE / RESPONSIVE TWEAKS
   ========================== */
@media (max-width:1100px){
  :root { --table-font: 11px; } /* sedikit lebih kecil pada layar lebar menengah */
  .expense-table { min-width: 820px; }
}
@media (max-width:900px){
  .container{padding:0 10px}
  table{min-width:640px}
  .summary-box{width:100%}
  .card .table-responsive th:first-child { width: 80px; }
  .card .table-responsive th:nth-child(2) { width: 200px; }
  .card .table-responsive th:nth-child(3),
  .card .table-responsive th:nth-child(4),
  .card .table-responsive th:nth-child(5),
  .card .table-responsive th:nth-child(6),
  .card .table-responsive th:nth-child(7),
  .card .table-responsive th:nth-child(8),
  .card .table-responsive th:nth-child(9) { width: 75px; }
  .card .table-responsive th:last-child { width: 85px; }
  .card .table-responsive td.td-keterangan { max-width: 200px; }
  .fuel-table th, .fuel-table td { font-size:11px; padding:6px 6px; white-space:nowrap; }
}
@media (max-width:520px){
  .topnav{flex-direction:column;align-items:stretch;gap:6px;padding:8px}
  .title{font-size:16px}
  .card{padding:10px;border-radius:8px}
  .search-form{flex-direction:column;align-items:stretch}
  .search-form .field, .search-form .small, .search-form .btn-wrap{min-width:0;width:100%}
  .search-form button{width:100%}
  .table-responsive{overflow-x:auto}
  table{min-width:600px;font-size:11px}
  th,td{padding:6px}
  .mobile-card{display:block}
  .summary-row{justify-content:flex-start}
  .summary-box{width:100%}
  .panel-body{padding:6px}
  .panel-head{padding:8px}
  .btn{width:100%;padding:10px}
}

/* ==========================
   MODAL / FILE VIEWER
   ========================== */
.viewer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px; }
.viewer-box { background: #fff; border-radius: 8px; max-width: calc(100% - 40px); max-height: calc(100% - 40px); width: 1000px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(0,0,0,.4); }
.viewer-head { background: #f7f7f7; padding: 10px 12px; display:flex; align-items:center; justify-content:space-between; gap:10px; border-bottom:1px solid #eee; }
.viewer-title {font-weight:700;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.viewer-close { background: transparent;border:0;font-size:18px;padding:6px 10px;cursor:pointer;border-radius:6px; }
.viewer-body { padding:10px; display:flex; align-items:center; justify-content:center; background:#fff; min-height:320px; max-height:calc(100vh - 130px); overflow:auto; }
.viewer-body img {max-width:100%; max-height:calc(100vh - 200px); object-fit:contain; display:block;}
.viewer-body iframe {width:100%; height:80vh; border:0;}
.viewer-actions {padding:8px 12px;border-top:1px solid #eee;display:flex;gap:8px;justify-content:flex-end;background:#fafafa}
.viewer-warning {color:#b33;margin-top:6px;font-size:13px}

/* small utility */
.hidden-xs { display:none; }
</style>
    </head>
    <body>

    <div class="topnav">
        <div class="left">
            <a href="index.php">Input</a>
            <a href="raw.php">Raw Data</a>
        </div>
        <div class="right">
            <span style="font-weight:700;color:#333"><?= h($name) ?></span>
            <a href="logout.php" style="background:#777">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="title">Expense Reimbursement System</div>

        <div class="card">
            <form method="get" class="search-form" aria-label="Form filter data">
                <div class="field">
                    <label style="display:block;font-weight:700;margin-bottom:6px">Nama Lengkap</label>
                    <input type="text" value="<?= h($name) ?>" disabled style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd;background:#f7f7f9">
                </div>
                <div class="small">
                    <label style="display:block;font-weight:700;margin-bottom:6px">Mulai</label>
                    <input type="date" name="start" value="<?= h($start) ?>" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd">
                </div>
                <div class="small">
                    <label style="display:block;font-weight:700;margin-bottom:6px">Selesai</label>
                    <input type="date" name="end" value="<?= h($end) ?>" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd">
                </div>
                <div class="small">
                    <label style="display:block;font-weight:700;margin-bottom:6px">Perjalanan</label>
                    <select name="perjalanan" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd">
                        <option value="">Semua Perjalanan</option>
                        <option value="Dalam Kota" <?= $perjalanan === 'Dalam Kota' ? 'selected' : '' ?>>Dalam Kota</option>
                        <option value="Luar Kota" <?= $perjalanan === 'Luar Kota' ? 'selected' : '' ?>>Luar Kota</option>
                    </select>
                </div>
                <div class="btn-wrap" style="min-width:120px">
                    <button class="btn" name="search" type="submit">CARI DATA</button>
                </div>
            </form>
        </div>

        <!-- EXPENSE REIMBURSEMENT FORM -->
        <div class="card" id="expense-section">
            <div class="section-title">EXPENSE REIMBURSEMENT FORM</div>

            <?php if ($search && !empty($groups)): ?>
                <?php foreach($groups as $g): ?>
                    <div style="margin-top:10px;font-size:14px;display:flex;gap:16px;flex-wrap:wrap">
                        <div><strong>Nama:</strong> <?= h($g['nama']) ?></div>
                        <div><strong>Departemen:</strong> <?= h($g['departemen']) ?></div>
                        <div><strong>Perjalanan Dinas:</strong> <?= h($g['perjalanan_dinas']) ?></div>
                    </div>

                    <div class="table-responsive" role="region" aria-label="Detail pengeluaran" style="margin-top:10px">
                        <table>
                            <thead>
                                <tr>
                                    <th>TGL</th>
                                    <th class="col-keterangan">KETERANGAN</th>
                                    <th>ENTERTAIN</th>
                                    <th>HOTEL</th>
                                    <th>MAKAN</th>
                                    <th>BENSIN</th>
                                    <th>TOL</th>
                                    <th>PARKIR</th>
                                    <th>LAIN</th>
                                    <th>JUMLAH</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach($g['rows'] as $r):
                                    $sid = $r['submission_id'];
                                    $items = $items_by_submission[$sid] ?? [];
                                    // jika ada items -> build keterangan dari items (HANYA keterangan)
                                    if (!empty($items)) {
                                        $agg = buildKeteranganAggregate($items);
                                        $keterangan_main = $agg['main'];
                                        $additional_ent = $agg['entertains']; // kalau ada, ini array entertain tambahan (label+biaya)
                                    } else {
                                        // fallback: kalau tidak ada items, buat keterangan dari fields submissions (tujuan / default)
                                        $keterangan_main = $r['tujuan'] ?: '-';
                                        $additional_ent = [];
                                    }
                                    // tampilkan baris utama (nilai biaya tetap dari submissions)
                                ?>
                                    <tr>
                                        <td><?= h($r['tanggal']) ?></td>
                                        <td class="td-keterangan"><?= h($keterangan_main) ?></td>
                                        <td class="right"><?= money_html($r['biaya_entertain']) ?></td>
                                        <td class="right"><?= money_html($r['biaya_hotel']) ?></td>
                                        <td class="right"><?= money_html($r['biaya_makan']) ?></td>
                                        <td class="right"><?= money_html($r['biaya_bensin']) ?></td>
                                        <td class="right"><?= money_html($r['biaya_tol']) ?></td>
                                        <td class="right"><?= money_html($r['biaya_parkir']) ?></td>
                                        <td class="right"><?= money_html($r['total_biaya_lain']) ?></td>
                                        <td class="right" style="font-weight:700"><?= money_html($r['row_total']) ?></td>
                                    </tr>
                                    <?php
                                    // jika ada entertain tambahan, render baris-baris entertain tambahan (tanggal kosong)
                                    if (!empty($additional_ent)) {
                                        foreach ($additional_ent as $ae) {
                                            $lbl = $ae['label'] ?? 'Entertain';
                                            $cost = intval($ae['biaya'] ?? 0);
                                            ?>
                                            <tr class="entertain-subrow">
                                                <td class="right" style="color:#999;">—</td>
                                                <td class="td-keterangan" style="padding-left:20px;"><?= h('Entertain: ' . $lbl) ?></td>
                                                <td class="right"><?= money_html($cost) ?></td>
                                                <td class="right"><?= money_html(0) ?></td>
                                                <td class="right"><?= money_html(0) ?></td>
                                                <td class="right"><?= money_html(0) ?></td>
                                                <td class="right"><?= money_html(0) ?></td>
                                                <td class="right"><?= money_html(0) ?></td>
                                                <td class="right"><?= money_html(0) ?></td>
                                                <td class="right" style="font-weight:700"><?= money_html($cost) ?></td>
                                            </tr>
                                            <?php
                                        }
                                    }
                                endforeach;
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- FIX: Summary box dengan kasbon dan sisa yang dihitung -->
                    <div class="summary-row">
                        <table class="summary-box" data-total="<?= intval($g['total']) ?>" id="group-summary-<?= $g['id'] ?>">
                            <tr><td>Total Pengeluaran</td><td class="right"><?= money_html($g['total']) ?></td></tr>
                            <tr><td>Kasbon diterima (gabungan)</td><td class="right"><?= money_html($g['kasbon_total']) ?></td></tr>
                            <tr>
                                <td>Sisa (Kurang/Lebih)</td>
                                <td class="right"><span class="sisa-value-group" id="sisa-group-<?= $g['id'] ?>"><?= money_html($g['total'] - $g['kasbon_total']) ?></span></td>
                            </tr>
                        </table>
                    </div>
                    <hr style="margin:12px 0;border:none;border-top:1px dashed #eee">
                <?php endforeach; ?>

            <?php elseif ($search && empty($groups)): ?>
                <div style="padding:18px;color:#666;text-align:center">Tidak ditemukan data untuk filter yang dipilih.</div>
            <?php else: ?>
                <div style="padding:18px;color:#777;text-align:center">Belum ada data. Gunakan filter di atas lalu klik <strong>CARI DATA</strong> untuk menampilkan hasil.</div>
            <?php endif; ?>
        </div>

        <!-- LAPORAN PEMAKAIAN BENSIN (UNIT) -->
        <div class="card" id="fuel-section">
            <div class="fuel-title">LAPORAN PEMAKAIAN BENSIN (UNIT)</div>
            <div class="table-responsive">
                <table class="fuel-table" role="table" aria-label="Laporan bensin">
                    <thead>
                        <tr>
                            <th>Plat Number</th>
                            <th>Total Bensin</th>
                            <th>Tgl Awal</th>
                            <th>Tgl Akhir</th>
                            <th>KM Awal</th>
                            <th>KM Akhir</th>
                            <th>KM Terpakai</th>
                            <th>KM/Hari</th>
                            <th>Harga/Ltr</th>
                            <th>Liter</th>
                            <th>Realisasi (KM/L)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($search && !empty($fuel_by_plat)): ?>
                            <?php foreach ($fuel_by_plat as $plat => $fb):
                                $km_awal = is_numeric($fb['km_awal']) ? intval($fb['km_awal']) : null;
                                $km_akhir = is_numeric($fb['km_akhir']) ? intval($fb['km_akhir']) : null;
                                $km_terpakai = isset($fb['km_terpakai']) && $fb['km_terpakai'] !== null ? intval($fb['km_terpakai']) : null;
                                $tgl_awal = $fb['tgl_awal'] ? date('d M Y', strtotime($fb['tgl_awal'])) : '-';
                                $tgl_akhir = $fb['tgl_akhir'] ? date('d M Y', strtotime($fb['tgl_akhir'])) : '-';
                                $days = 0;
                                if (!empty($fb['tgl_awal']) && !empty($fb['tgl_akhir'])) {
                                    $days = max(1, (int)floor((strtotime($fb['tgl_akhir']) - strtotime($fb['tgl_awal'])) / 86400) + 1);
                                } elseif (!empty($fb['rows'])) {
                                    $dates = array_map(function($rr){ return $rr['tanggal']; }, $fb['rows']);
                                    $dates = array_filter($dates);
                                    if (!empty($dates)) { $min = min($dates); $max = max($dates); if ($min && $max) $days = max(1, (int)floor((strtotime($max) - strtotime($min)) / 86400) + 1); }
                                }
                                $km_per_day = ($km_terpakai !== null && $days > 0) ? round($km_terpakai / $days, 2) : '-';
                                $harga_ltr_display = money_html(intval(round($fb['harga_ltr'] ?? 10000)));
                                $liter = $fb['liter'] ? number_format($fb['liter'],2,',','.') : '-';
                                $realisasi = null;
                                if (is_numeric($fb['realisasi_km_per_l'])) {
                                    $realisasi = round($fb['realisasi_km_per_l'], 2);
                                } elseif ($km_terpakai !== null && $fb['liter'] > 0) {
                                    $realisasi = round($km_terpakai / max(0.00001, $fb['liter']), 2);
                                }
                            ?>
                                <tr>
                                    <td><?= h($fb['plat']) ?></td>
                                    <td class="right"><?= money_html($fb['total_bensin']) ?></td>
                                    <td><?= h($tgl_awal) ?></td>
                                    <td><?= h($tgl_akhir) ?></td>
                                    <td class="right"><?= $km_awal !== null ? number_format($km_awal) : '-' ?></td>
                                    <td class="right"><?= $km_akhir !== null ? number_format($km_akhir) : '-' ?></td>
                                    <td class="right"><?= $km_terpakai !== null ? number_format($km_terpakai) : '-' ?></td>
                                    <td class="right"><?= is_numeric($km_per_day) ? $km_per_day : '-' ?></td>
                                    <td class="right"><?= $harga_ltr_display ?></td>
                                    <td class="right"><?= $liter ?></td>
                                    <td class="right"><?= is_numeric($realisasi) ? $realisasi : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php elseif ($search && empty($fuel_by_plat)): ?>
                            <tr><td colspan="11" style="text-align:center;color:#666;padding:18px">Tidak ada data pemakaian bensin untuk filter ini.</td></tr>
                        <?php else: ?>
                            <tr><td colspan="11" style="text-align:center;color:#777;padding:18px">Belum ada data. Gunakan filter di atas lalu klik <strong>CARI DATA</strong>.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- COMBINED ATTACHMENTS -->
        <div class="card" id="attachments-section">
            <div class="section-title">LAMPIRAN BUKTI TRANSAKSI (Gabungan)</div>

            <?php if ($search && !empty($filesAll)): ?>
                <div class="panel">
                    <div class="panel-head" onclick="togglePanel(this)" role="button" aria-expanded="true">
                        <div>Semua Lampiran (<?= count($filesAll) ?> file)</div>
                        <div class="chev">▾</div>
                    </div>
                    <div class="panel-body">
                        <?php foreach ($filesAll as $f):
                            $isExternal = preg_match('#^https?://#i', $f['path']);
                            $link = $isExternal ? $f['path'] : "preview.php?file_id=" . intval($f['id']);
                            $display = $f['original_name'] ?: basename($f['path']);
                            $subDate = !empty($f['submission_tanggal']) ? date('d M Y', strtotime($f['submission_tanggal'])) : '-';
                            $mime = $f['mime'] ?? '';
                        ?>
                            <div class="row-item file-row" style="display:flex;gap:8px;align-items:center;padding:8px 6px;border-bottom:1px solid #f1f1f1">
                                <div style="min-width:110px;font-weight:700;color:#6b0b0b;text-transform:uppercase"><?= h(strtoupper($f['category'] ?: $display)) ?></div>
                                <div style="flex:1;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">
                                    <a href="#" class="open-file"
                                    data-src="<?= h($link) ?>"
                                    data-mime="<?= h($mime) ?>"
                                    data-external="<?= $isExternal ? '1' : '0' ?>"
                                    data-name="<?= h($display) ?>">
                                    <?= h($isExternal ? truncate($f['path'],90) : truncate($display,60)) ?>
                                    </a>
                                    <div style="font-size:12px;color:#777;margin-top:4px">Submission: <?= h($f['submission_id']) ?> — <?= h($subDate) ?> — <?= h($f['submission_tujuan']) ?></div>
                                </div>
                                <div style="min-width:100px;font-size:12px;color:#666;text-align:right"><?= h(human_size($f['size_bytes'])) ?> &nbsp;|&nbsp; <?= h($f['uploaded_at'] ?: '-') ?></div>
                                <div style="width:84px;text-align:right">
                                    <button class="btn open-file-btn"
                                            data-src="<?= h($link) ?>"
                                            data-mime="<?= h($mime) ?>"
                                            data-external="<?= $isExternal ? '1' : '0' ?>"
                                            data-name="<?= h($display) ?>">
                                        Buka
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($search && empty($filesAll)): ?>
                <div style="padding:15px;color:#666;text-align:center">Tidak ada lampiran untuk filter ini.</div>
            <?php else: ?>
                <div style="padding:15px;color:#777;text-align:center">Belum ada lampiran. Gunakan filter di atas lalu klik <strong>CARI DATA</strong>.</div>
            <?php endif; ?>
        </div>

        <!-- PRINT ALL BUTTON -->
        <div class="card" style="display:flex;justify-content:center;align-items:center">
            <a class="btn <?= $can_print ? '' : 'disabled' ?>" href="<?= $can_print ? h($print_all_url) : '#' ?>" target="<?= $can_print ? '_blank' : '_self' ?>" rel="noopener"><?= $can_print ? 'Preview / Print Semua' : 'Preview / Print Semua (nonaktif)' ?></a>
        </div>

    </div>

    <!-- Modal viewer -->
    <div id="viewerOverlay" class="viewer-overlay" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="viewer-box" role="document" aria-labelledby="viewerTitle">
            <div class="viewer-head">
                <div class="viewer-title" id="viewerTitle">Lampiran</div>
                <div style="display:flex;gap:8px;align-items:center">
                    <button id="viewerClose" class="viewer-close" aria-label="Tutup (Esc)">✕</button>
                </div>
            </div>
            <div class="viewer-body" id="viewerBody"></div>
            <div class="viewer-actions">
                <a id="viewerOpenNewTab" href="#" target="_blank" rel="noopener" class="btn" style="display:none">Buka di tab baru</a>
                <div id="viewerWarn" class="viewer-warning" style="display:none"></div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        function attachOpeners() {
            document.querySelectorAll('.open-file, .open-file-btn').forEach(function(el){
                el.addEventListener('click', function(ev){
                    ev.preventDefault();
                    var src = el.getAttribute('data-src');
                    var mime = el.getAttribute('data-mime') || '';
                    var external = el.getAttribute('data-external') === '1';
                    var name = el.getAttribute('data-name') || 'Lampiran';
                    openViewer(src, mime, external, name);
                });
            });
        }
        attachOpeners();

        var isSmall = window.matchMedia && window.matchMedia('(max-width:520px)').matches;
        document.querySelectorAll('.panel .panel-body').forEach(function(b){
            if (isSmall) { b.style.display = 'none'; var head = b.previousElementSibling; if (head) { var chev = head.querySelector('.chev'); if (chev) chev.textContent = '▴'; } }
            else { b.style.display = 'block'; var head = b.previousElementSibling; if (head) { var chev = head.querySelector('.chev'); if (chev) chev.textContent = '▾'; } }
        });

        var overlay = document.getElementById('viewerOverlay');
        var body = document.getElementById('viewerBody');
        var title = document.getElementById('viewerTitle');
        var closeBtn = document.getElementById('viewerClose');
        var openNewTabBtn = document.getElementById('viewerOpenNewTab');
        var warnEl = document.getElementById('viewerWarn');

        function clearViewer(){ body.innerHTML = ''; title.textContent = 'Lampiran'; warnEl.style.display = 'none'; openNewTabBtn.style.display = 'none'; openNewTabBtn.href = '#'; }

        function openViewer(src, mime, external, name) {
            clearViewer();
            title.textContent = name || 'Lampiran';
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden','false');

            var lower = (mime || '').toLowerCase();
            var isImage = false;
            if (lower.startsWith('image/')) isImage = true;
            var ext = (src || '').split('?')[0].split('.').pop().toLowerCase();
            var imageExts = ['png','jpg','jpeg','gif','bmp','webp','svg'];
            if (imageExts.indexOf(ext) !== -1) isImage = true;

            if (isImage) {
                var img = document.createElement('img');
                img.src = src;
                img.alt = name || 'Lampiran';
                img.onerror = function(){ warnEl.textContent = 'Gagal memuat gambar. Coba buka di tab baru.'; warnEl.style.display = 'block'; openNewTabBtn.style.display = 'inline-block'; openNewTabBtn.href = src; };
                body.appendChild(img);
                openNewTabBtn.style.display = 'inline-block';
                openNewTabBtn.href = src;
            } else {
                var iframe = document.createElement('iframe');
                iframe.src = src;
                iframe.setAttribute('sandbox', 'allow-same-origin allow-scripts allow-forms allow-popups');
                iframe.onerror = function(){ warnEl.textContent = 'Tidak dapat menampilkan dokumen di sini. Gunakan \"Buka di tab baru\".'; warnEl.style.display = 'block'; };
                body.appendChild(iframe);
                openNewTabBtn.style.display = 'inline-block';
                openNewTabBtn.href = src;
                if (external) { warnEl.textContent = 'Catatan: resource eksternal mungkin tidak dapat ditampilkan di dalam modal karena aturan keamanan (X-Frame-Options/CORS). Jika tidak muncul, gunakan tombol \"Buka di tab baru\".'; warnEl.style.display = 'block'; }
            }
        }

        function closeViewer(){ overlay.style.display = 'none'; overlay.setAttribute('aria-hidden','true'); clearViewer(); }
        closeBtn.addEventListener('click', closeViewer);
        overlay.addEventListener('click', function(e){ if (e.target === overlay) closeViewer(); });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { if (overlay.style.display === 'flex') closeViewer(); } });
    });

    function togglePanel(head){
        var panel = head.parentElement;
        var body = panel.querySelector('.panel-body');
        var chev = head.querySelector('.chev');
        if (!body) return;
        if (body.style.display === 'none' || body.style.display === '') { body.style.display = 'block'; if (chev) chev.textContent = '▾'; }
        else { body.style.display = 'none'; if (chev) chev.textContent = '▴'; }
    }
    </script>
            
    </body>
    </html>