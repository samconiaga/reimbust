<?php
// print.php - printable preview gabungan (multiple submissions -> satu form, tanpa lampiran, tanda tangan satu kali)
session_start();
if (empty($_SESSION['nik'])) { header('Location: login.php'); exit; }
if (file_exists(__DIR__.'/config.php')) include_once __DIR__.'/config.php';

// DB config (override via config.php)
$DB_HOST = defined('DB_HOST')?DB_HOST:'127.0.0.1';
$DB_USER = defined('DB_USER')?DB_USER:'root';
$DB_PASS = defined('DB_PASS')?DB_PASS:'';
$DB_NAME = defined('DB_NAME')?DB_NAME:'reimb_db';

$conn = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($conn->connect_error) die("DB: ".$conn->connect_error);
$conn->set_charset('utf8mb4');

function db_fetch_all($conn,$sql,$params=[]){
    $stmt = $conn->prepare($sql);
    if ($stmt === false) throw new Exception($conn->error);
    if ($params) {
        $types='';
        foreach($params as $p){
            if (is_int($p)) $types.='i';
            elseif (is_float($p)) $types.='d';
            else $types.='s';
        }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// format helpers (sama persis dengan preview.php)
function rupiah($n){
    return "Rp " . number_format((float)$n,0,",",".");
}
function money_html($n){
    $n = (int)$n;
    $neg = $n < 0;
    $abs = number_format(abs($n), 0, ',', '.');
    $sign = $neg ? '-' : '';
    return '<span class="money"><span class="curr">Rp</span><span class="num">'. $sign . $abs .'</span></span>';
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

function fmt_km($n){
    if ($n === null || $n === '') return '-';
    $n = floatval($n);
    if ($n == 0) return '-';
    return number_format($n,0,',','.');
}
function fmt_liter($n){
    if ($n === null || $n === '') return '-';
    $n = floatval($n);
    if ($n == 0) return '-';
    return number_format($n,2,',','.');
}

// ---------------------------
// Determine what to load (follow preview filters)
// ---------------------------
$preview_submissions = [];

// If explicit ids provided -> load exactly those (preview may pass ids)
if (!empty($_GET['ids'])) {
    $ids = $_GET['ids'];
    $ids_arr = array_filter(array_map('intval', explode(',', $ids)));
    if ($ids_arr) {
        $safe = implode(',', $ids_arr);
        $preview_submissions = db_fetch_all($conn, "SELECT * FROM submissions WHERE id IN ($safe) ORDER BY FIELD(id,$safe)", []);
    }
}
// single id
elseif (!empty($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id > 0) $preview_submissions = db_fetch_all($conn, "SELECT * FROM submissions WHERE id = ? LIMIT 1", [$id]);
}
// all with filters (this is the common preview flow)
elseif (isset($_GET['all']) && (string)$_GET['all'] === '1') {
    $where = [];
    $params = [];

    // preview usually shows only current user - keep that behavior
    if (!empty($_SESSION['nik'])) {
        $where[] = "nik = ?";
        $params[] = (string)$_SESSION['nik'];
    }

    // date range filters (preview may send start/end)
    $start = !empty($_GET['start']) ? trim($_GET['start']) : null;
    $end   = !empty($_GET['end']) ? trim($_GET['end']) : null;
    if ($start) {
        $where[] = "(DATE(submitted_at) >= ? OR DATE(tanggal) >= ?)";
        $params[] = $start;
        $params[] = $start;
    }
    if ($end) {
        $where[] = "(DATE(submitted_at) <= ? OR DATE(tanggal) <= ?)";
        $params[] = $end;
        $params[] = $end;
    }

    // perjalanan filter (Dalam Kota / Luar Kota) - follow preview dropdown
    if (!empty($_GET['perjalanan'])) {
        $per = trim($_GET['perjalanan']);
        $where[] = "LOWER(perjalanan_dinas) = LOWER(?)";
        $params[] = $per;
    }

    // nama search (preview has a name input) -> substring match
    if (!empty($_GET['nama'])) {
        $nm = trim($_GET['nama']);
        $where[] = "nama LIKE ?";
        $params[] = "%{$nm}%";
    }

    // plat filter (optional)
    if (!empty($_GET['plat'])) {
        $pl = trim($_GET['plat']);
        $where[] = "plat_number LIKE ?";
        $params[] = "%{$pl}%";
    }

    // Additional safe ordering - preview may sort; allow limited fields only
    $allowedSort = ['submitted_at','tanggal','id','nama'];
    $sort = 'submitted_at';
    $dir  = 'DESC';
    if (!empty($_GET['sort'])) {
        $s = trim($_GET['sort']);
        $sParts = explode(':',$s);
        $field = $sParts[0] ?? '';
        $d = strtoupper($sParts[1] ?? '');
        if (in_array($field, $allowedSort)) $sort = $field;
        if ($d === 'ASC' || $d === 'DESC') $dir = $d;
    }
    $order_sql = "{$sort} {$dir}, id DESC";

    if (empty($where)) $where[] = '1';
    $sql = "SELECT * FROM submissions WHERE " . implode(' AND ', $where) . " ORDER BY " . $order_sql;
    $preview_submissions = db_fetch_all($conn, $sql, $params);
}
// fallback: nothing matched -> empty
else {
    $preview_submissions = [];
}

// printed by info
$printed_by_name = trim((string)($_SESSION['name'] ?? ''));
$printed_by_nik  = trim((string)($_SESSION['nik'] ?? ''));
$printed_at = date('d M Y, H:i');

// ------------------------------------------------------------
// Load submission_items untuk semua submission yang ditemukan
// ------------------------------------------------------------
$items_by_submission = [];
if (!empty($preview_submissions)) {
    $submissionIds = array_column($preview_submissions, 'id');
    $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
    $typesItems = str_repeat('i', count($submissionIds));
    $sqlItems = "SELECT * FROM submission_items WHERE submission_id IN ($placeholders) ORDER BY id ASC";
    $stmtItems = $conn->prepare($sqlItems);
    if ($stmtItems) {
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
        // fallback manual
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

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Preview & Approval - Expense Reimbursement (Gabungan)</title>
<style>
:root{
    --maroon:#7a0004;
    --dark-maroon:#6b0000;
    --accent:#b20000;
    --muted:#666;
    --light-blue:#dff3fb;
    --card-bg:#fff;
}
*{box-sizing:border-box}
html,body{height:100%}
body{font-family:Segoe UI,Roboto,Arial,sans-serif;color:#222;background:#f2f4f6;margin:0;padding:18px}
.wrapper{max-width:1200px;margin:0 auto}
.modal-header{background:var(--maroon);color:#fff;padding:14px 18px;font-weight:700;border-radius:6px 6px 0 0}
.card{background:var(--card-bg);border:1px solid #e2e2e2;border-radius:6px;padding:20px;margin-top:0;box-shadow:0 2px 6px rgba(0,0,0,0.04)}
.title-bar{background:var(--dark-maroon);color:#fff;padding:12px 16px;border-radius:4px;font-weight:700;margin-bottom:14px;text-align:center}
.meta{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.meta .col{flex:1;min-width:220px}
.meta b{display:inline-block;width:125px}
.right-block{text-align:right}
.table-exp{width:100%;border-collapse:collapse;margin-top:8px;font-size:13px}
.table-exp th, .table-exp td{border:1px solid #e8e8e8;padding:10px;vertical-align:top}
.table-exp th{background:#fafafa;font-weight:700}
.table-right{text-align:right}
.table-exp .amount{white-space:nowrap}
.total-row{font-weight:700;background:#fbfbfb}
.entertain-subrow {
    background-color: #fcfcfc;
    font-size: 12px;
}
.entertain-subrow td:first-child {
    color: #999;
    text-align: center;
}
.entertain-subrow .td-keterangan {
    padding-left: 24px !important;
    font-style: italic;
    color: #555;
}
.summary-wrap{display:flex;justify-content:flex-end;margin-top:12px}
.summary-box{width:320px;border:1px solid #ddd;padding:0;background:#fff}
.summary-box table{width:100%;border-collapse:collapse}
.summary-box td{padding:10px;border-bottom:1px solid #eee}
.summary-box .label{font-size:13px}
.summary-box .value{font-weight:700;text-align:right}
.summary-box .highlight{background:transparent} /* no yellow highlight; left transparent */

/* money styling: Rp on left, amount on right */
.money{display:inline-flex;align-items:center;gap:6px;white-space:nowrap;justify-content:flex-end}
.money .curr{font-weight:700;white-space:nowrap;margin-right:6px}
.money .num{min-width:64px;text-align:right;font-variant-numeric:tabular-nums;display:inline-block}

/* SIGNATURE area */
.signs-wrap{
    margin-top:26px;
    display:grid;
    grid-template-columns: repeat(5, 1fr);
    gap:12px;
    align-items:stretch;
    justify-items:stretch;
    break-inside: avoid;
    -webkit-column-break-inside: avoid;
    page-break-inside: avoid;
    width:100%;
}
.sign-block{ padding:0 6px; min-width:0; background:transparent; border:0; }
.sign-inner{ display:grid; grid-template-rows: auto 1fr 40px auto; row-gap:6px; height:220px; align-items:start; }
.sig-title{font-size:13px;color:#444;margin:0 0 4px 0;text-align:center}
.sign-mid{ font-size:11px;color:#999; line-height:1.1; text-align:center; }
.sig-line{ justify-self:center; width:92%; border-top:3px solid #222; height:0; align-self:center; box-sizing:content-box; margin-top:8px; }
.sign-bottom{ display:flex;flex-direction:column;align-items:center;justify-content:flex-start; text-align:center; padding-top:6px; }
.sig-name{font-weight:700;margin-top:0}
.sig-role{font-size:12px;color:#666;margin-top:6px}

/* LEFT column tweaks */
.sign-block.left-info .sig-title{ text-align:left; padding-left:6px; }
.sign-block.left-info .sign-mid{ text-align:left; padding-left:6px; }
.sign-block.left-info .sig-line{ justify-self:start; width:100%; margin-left:6px; max-width:260px; border-top-width:3px; }
.sign-block.left-info .sign-bottom{ align-items:flex-start; padding-left:6px; text-align:left; }

/* fuel report */
.fuel-title{background:#7a0004;color:#fff;padding:10px 12px;border-radius:4px;font-weight:700;margin-top:20px;margin-bottom:10px}
.table-fuel{width:100%;border-collapse:collapse;font-size:13px;margin-top:6px}
.table-fuel th, .table-fuel td{border:1px solid #e8e8e8;padding:8px;text-align:left}
.table-fuel th{background:#fafafa;font-weight:700}
.table-fuel .r{text-align:right}

/* print tweaks */
@media print{
    .controls, .modal-header{display:none}
    body{background:#fff;padding:0; -webkit-print-color-adjust: exact; print-color-adjust: exact;}
    .wrapper{max-width:100%;margin:0}
    .signs-wrap{ grid-auto-rows: auto !important; grid-template-columns: repeat(5, 1fr) !important; gap:12px !important; }
    .sign-inner{ height:220px !important; grid-template-rows: auto 1fr 40px auto !important; }
    .sig-line{ border-top-width:3px !important; }
    .card{box-shadow:none;border-color:#e5e5e5;}
    .title-bar{background:var(--dark-maroon); color:#fff !important;}
    .footer-note{display:none !important}
    .controls{display:none !important}
}

/* page size */
@page { margin: 20mm; }

.footer-note{background:var(--light-blue);padding:12px;border-radius:6px;margin-top:16px;border:1px solid #bfeaf5;color:#0b5568}
.controls{display:flex;justify-content:center;gap:12px;margin-top:16px}
.btn{padding:10px 18px;border-radius:6px;border:0;cursor:pointer;font-weight:700;text-decoration:none;display:inline-block;text-align:center}
.btn-print{background:#2d9cff;color:#fff}
.btn-close{background:#6c757d;color:#fff}

@media (max-width:1100px){
    .signs-wrap{grid-template-columns: 1fr; gap:18px;}
    .sign-block{min-width:0; padding:0}
    .sign-block.left-info .sig-line{ width:80%; margin-left:0; justify-self:start; }
}
</style>
</head>
<body>
<div class="wrapper">

<?php if (empty($preview_submissions)): ?>
    <div class="card">
        <div>Tidak ada data untuk dicetak (cocokkan filter di halaman preview lalu klik Print/Preview kembali).</div>
    </div>
<?php else: ?>

    <!-- Header hanya 1x -->
    <div class="modal-header">Preview & Approval</div>

    <?php
    // Prepare aggregated info
    $first = $preview_submissions[0];
    $display_name = htmlspecialchars($first['nama'] ?? '-');
    $display_nik  = htmlspecialchars($first['nik'] ?? '-');

    // date range (periode) - get min/max of tanggal/submitted_at across all submissions
    $dates = [];
    foreach ($preview_submissions as $s) {
        $t = ($s['tanggal'] && $s['tanggal'] !== '0000-00-00') ? $s['tanggal'] : ($s['submitted_at'] ?? ($s['created_at'] ?? null));
        if ($t) {
            $d = date('Y-m-d', strtotime($t));
            $dates[] = $d;
        }
    }
    if (!empty($dates)) {
        sort($dates);
        $min_d = $dates[0];
        $max_d = end($dates);
        if ($min_d === $max_d) {
            $periode_text = date('d-m-Y', strtotime($min_d));
        } else {
            $periode_text = date('d-m-Y', strtotime($min_d)) . ' s/d ' . date('d-m-Y', strtotime($max_d));
        }
    } else {
        $periode_text = '-';
    }

    // Aggregated totals (hanya dari submission totals, akan ditambahkan dengan sub‑rows jika ada)
    $agg = [
        'ent' => 0.0,
        'hotel' => 0.0,
        'makan' => 0.0,
        'bensin' => 0.0,
        'tol' => 0.0,
        'parkir' => 0.0,
        'lain' => 0.0,
        'total' => 0.0,
        'kasbon' => 0.0
    ];

    // Prepare rows for expense table (including possible sub‑rows)
    $expense_rows = []; // each element is [ 'type'=>'main' or 'sub', 'data'=>... ]

    foreach ($preview_submissions as $s) {
        $sid = $s['id'];
        $items = $items_by_submission[$sid] ?? [];

        // ambil biaya dari submission (sudah termasuk semua item)
        $biaya_ent = floatval($s['biaya_entertain'] ?? 0);
        $biaya_hotel = floatval($s['biaya_hotel'] ?? 0);
        $biaya_makan = floatval($s['biaya_makan'] ?? 0);
        $biaya_bensin = floatval($s['biaya_bensin'] ?? 0);
        $biaya_tol = floatval($s['biaya_tol'] ?? 0);
        $biaya_parkir = floatval($s['biaya_parkir'] ?? 0);
        $biaya_lain = floatval($s['total_biaya_lain'] ?? 0);
        $total = $biaya_ent + $biaya_hotel + $biaya_makan + $biaya_bensin + $biaya_tol + $biaya_parkir + $biaya_lain;
        $kasbon = floatval($s['kasbon'] ?? 0);

        $tanggal_display = ($s['tanggal'] && $s['tanggal'] !== '0000-00-00') ? $s['tanggal'] : ($s['submitted_at'] ?? ($s['created_at'] ?? date('Y-m-d')));
        $tanggal_label = date('d M Y', strtotime($tanggal_display));

        // Buat keterangan dari items
        if (!empty($items)) {
            $aggK = buildKeteranganAggregate($items);
            $keterangan_main = $aggK['main'];
            $additional_ent = $aggK['entertains']; // array entertain tambahan (index 1+)
        } else {
            $keterangan_main = $s['tujuan'] ?: '-';
            $additional_ent = [];
        }

        // Main row
        $expense_rows[] = [
            'type' => 'main',
            'tanggal' => $tanggal_label,
            'keterangan' => $keterangan_main,
            'ent' => $biaya_ent,
            'hotel' => $biaya_hotel,
            'makan' => $biaya_makan,
            'bensin' => $biaya_bensin,
            'tol' => $biaya_tol,
            'parkir' => $biaya_parkir,
            'lain' => $biaya_lain,
            'total' => $total,
            'kasbon' => $kasbon,
            'plat' => trim($s['plat_number'] ?? ''),
            'km_awal' => $s['km_awal'] ?? null,
            'km_akhir' => $s['km_akhir'] ?? null,
            'km_terpakai' => $s['km_terpakai'] ?? null,
            'harga_per_liter' => $s['harga_per_liter'] ?? null,
            'liter' => $s['liter'] ?? null,
            'tgl_awal' => $s['tgl_awal'] ?? $s['tanggal'] ?? null,
            'tgl_akhir' => $s['tgl_akhir'] ?? $s['tanggal'] ?? null
        ];

        // Sub‑rows for additional entertains
        foreach ($additional_ent as $ae) {
            $expense_rows[] = [
                'type' => 'sub',
                'tanggal' => '', // kosong
                'keterangan' => 'Entertain: ' . ($ae['label'] ?: 'Entertain'),
                'ent' => floatval($ae['biaya']),
                'hotel' => 0,
                'makan' => 0,
                'bensin' => 0,
                'tol' => 0,
                'parkir' => 0,
                'lain' => 0,
                'total' => floatval($ae['biaya']),
                'kasbon' => 0,
                'plat' => '',
                'km_awal' => null,
                'km_akhir' => null,
                'km_terpakai' => null,
                'harga_per_liter' => null,
                'liter' => null,
                'tgl_awal' => null,
                'tgl_akhir' => null
            ];
        }

        // accumulate totals (hanya main rows, sub‑rows sudah included dalam main row's ent)
        $agg['ent'] += $biaya_ent;
        $agg['hotel'] += $biaya_hotel;
        $agg['makan'] += $biaya_makan;
        $agg['bensin'] += $biaya_bensin;
        $agg['tol'] += $biaya_tol;
        $agg['parkir'] += $biaya_parkir;
        $agg['lain'] += $biaya_lain;
        $agg['total'] += $total;
        $agg['kasbon'] += $kasbon;
    }

    // -----------------------
    // Build Fuel Usage Aggregation (per plat_number) - sama persis seperti preview.php
    // -----------------------
    // Kumpulkan semua data yang diperlukan dari submissions dan items
    $fuel_raw_rows = [];
    foreach ($preview_submissions as $s) {
        $plat = trim($s['plat_number'] ?? '');
        if ($plat === '') $plat = 'TANPA PLAT';
        $sid = $s['id'];
        $items = $items_by_submission[$sid] ?? [];

        // Ambil data dari submission
        $fuel_raw_rows[] = [
            'submission_id' => $sid,
            'tanggal' => $s['tanggal'] ?? '',
            'plat_number' => $plat,
            'biaya_bensin' => floatval($s['biaya_bensin'] ?? 0),
            'km_awal' => is_numeric($s['km_awal'] ?? null) ? intval($s['km_awal']) : null,
            'km_akhir' => is_numeric($s['km_akhir'] ?? null) ? intval($s['km_akhir']) : null,
            'km_terpakai' => is_numeric($s['km_terpakai'] ?? null) ? intval($s['km_terpakai']) : null,
            'liter' => is_numeric($s['liter'] ?? null) ? floatval($s['liter']) : null,
            'harga_ltr' => is_numeric($s['harga_per_liter'] ?? null) ? floatval($s['harga_per_liter']) : null,
            'realisasi_km_per_l' => is_numeric($s['realisasi_km_per_l'] ?? null) ? floatval($s['realisasi_km_per_l']) : null,
            'tgl_awal' => $s['tgl_awal'] ?? null,
            'tgl_akhir' => $s['tgl_akhir'] ?? null,
            'items' => $items
        ];

        // Tambahkan juga data dari items yang berkategori BBM (opsional)
        foreach ($items as $it) {
            $cat = strtolower(trim($it['category'] ?? ''));
            if (strpos($cat, 'bbm') !== false || strpos($cat, 'bensin') !== false) {
                // Jika item bensin memiliki informasi tambahan, bisa dimasukkan sebagai baris terpisah? 
                // Untuk sederhananya, kita gunakan data submission saja. Tapi preview menggunakan data dari items untuk estimasi.
                // Agar sama persis, kita perlu memasukkan setiap item bensin sebagai baris terpisah.
                $parsed = parse_item_label_and_amount($it);
                $biaya_item = $parsed['biaya'];
                $ket = $it['keterangan'] ?? '';
                $decoded_ket = @json_decode($ket, true);
                $plat_item = '';
                if (is_array($decoded_ket) && !empty($decoded_ket['plat'])) {
                    $plat_item = $decoded_ket['plat'];
                } else {
                    $plat_item = $plat; // fallback ke plat submission
                }
                $fuel_raw_rows[] = [
                    'submission_id' => $sid,
                    'tanggal' => $s['tanggal'] ?? '',
                    'plat_number' => $plat_item,
                    'biaya_bensin' => $biaya_item,
                    'km_awal' => null,
                    'km_akhir' => null,
                    'km_terpakai' => null,
                    'liter' => null,
                    'harga_ltr' => null,
                    'realisasi_km_per_l' => null,
                    'tgl_awal' => null,
                    'tgl_akhir' => null,
                    'items' => []
                ];
            }
        }
    }

    // Bangun fuel_by_plat dengan logika preview
    $fuel_by_plat = [];
    $rows_by_plate_date = [];

    // Pertama, kumpulkan semua baris bensin berdasarkan plat + tanggal
    foreach ($fuel_raw_rows as $rr) {
        $plat = trim($rr['plat_number'] ?: 'TANPA PLAT');
        if ($plat === '') $plat = 'TANPA PLAT';
        $date = $rr['tanggal'] ?: '';
        $key = $plat . '||' . $date;
        if (!isset($rows_by_plate_date[$key])) $rows_by_plate_date[$key] = [];
        $rows_by_plate_date[$key][] = $rr;
    }

    // isi km_awal/km_akhir yang hilang dari baris lain pada plat+date yang sama
    foreach ($fuel_raw_rows as &$rr) {
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

    // Sekarang akumulasi per plat
    foreach ($fuel_raw_rows as $rr) {
        $hasFuel = ($rr['biaya_bensin'] ?? 0) > 0 || ($rr['liter'] !== null && $rr['liter'] > 0) || ($rr['harga_ltr'] !== null && $rr['harga_ltr'] > 0) || ($rr['km_awal'] !== null && $rr['km_akhir'] !== null) || ($rr['km_terpakai'] !== null);
        if (!$hasFuel) continue;

        $plat = trim($rr['plat_number'] ?: 'TANPA PLAT');
        if ($plat === '') $plat = 'TANPA PLAT';

        if (!isset($fuel_by_plat[$plat])) {
            $fuel_by_plat[$plat] = [
                'plat' => $plat,
                'total_bensin' => 0.0,
                'tgl_awal' => $rr['tanggal'] ?: null,
                'tgl_akhir' => $rr['tanggal'] ?: null,
                'km_awal' => $rr['km_awal'],
                'km_akhir' => $rr['km_akhir'],
                'km_terpakai' => $rr['km_terpakai'],
                'liter' => 0.0,
                'harga_ltr' => $rr['harga_ltr'] ?: null,
                'realisasi_km_per_l' => $rr['realisasi_km_per_l'] ?: null,
                'rows' => []
            ];
        }

        $fb = &$fuel_by_plat[$plat];
        $fb['total_bensin'] += floatval($rr['biaya_bensin'] ?? 0);
        if ($rr['liter']) $fb['liter'] += floatval($rr['liter']);
        if ($rr['harga_ltr']) $fb['harga_ltr'] = floatval($rr['harga_ltr']); // ambil harga terakhir

        // km min/max
        if ($rr['km_awal'] !== null) {
            if (!isset($fb['km_awal']) || $fb['km_awal'] === null) $fb['km_awal'] = $rr['km_awal'];
            else $fb['km_awal'] = min($fb['km_awal'], $rr['km_awal']);
        }
        if ($rr['km_akhir'] !== null) {
            if (!isset($fb['km_akhir']) || $fb['km_akhir'] === null) $fb['km_akhir'] = $rr['km_akhir'];
            else $fb['km_akhir'] = max($fb['km_akhir'], $rr['km_akhir']);
        }
        if ($rr['km_terpakai'] !== null) {
            if (!isset($fb['km_terpakai']) || $fb['km_terpakai'] === null) $fb['km_terpakai'] = 0;
            $fb['km_terpakai'] += intval($rr['km_terpakai']);
        }

        // tanggal range
        $ta = $rr['tgl_awal'] ?: $rr['tanggal'];
        $tb = $rr['tgl_akhir'] ?: $rr['tanggal'];
        if ($ta && strtotime($ta) < strtotime($fb['tgl_awal'])) $fb['tgl_awal'] = $ta;
        if ($tb && strtotime($tb) > strtotime($fb['tgl_akhir'])) $fb['tgl_akhir'] = $tb;

        $fb['rows'][] = $rr;
        unset($fb);
    }

    // Setelah akumulasi, lakukan estimasi/penyesuaian seperti preview
    foreach ($fuel_by_plat as $plat => &$fb) {
        // default harga
        if (empty($fb['harga_ltr']) || !is_numeric($fb['harga_ltr']) || floatval($fb['harga_ltr']) <= 0) {
            $fb['harga_ltr'] = 10000.0;
        } else {
            $fb['harga_ltr'] = floatval($fb['harga_ltr']);
        }

        $fb['total_bensin'] = floatval($fb['total_bensin'] ?? 0.0);
        if ((empty($fb['liter']) || !is_numeric($fb['liter']) || $fb['liter'] <= 0) && $fb['harga_ltr'] > 0) {
            $fb['liter'] = $fb['total_bensin'] / $fb['harga_ltr'];
        } else {
            $fb['liter'] = floatval($fb['liter'] ?? 0.0);
        }

        // kumpulkan nilai realisasi dari rows
        $realisasi_values = [];
        foreach ($fb['rows'] as $rr) {
            if (!empty($rr['realisasi_km_per_l']) && is_numeric($rr['realisasi_km_per_l']) && floatval($rr['realisasi_km_per_l']) > 0) {
                $realisasi_values[] = floatval($rr['realisasi_km_per_l']);
            } elseif (!empty($rr['km_terpakai']) && !empty($rr['liter']) && $rr['liter'] > 0) {
                $realisasi_values[] = $rr['km_terpakai'] / $rr['liter'];
            }
        }

        // coba ambil rata‑rata historis dari database (opsional, untuk menyamai preview)
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

        if (empty($realisasi_values) && $hist_realisasi_avg !== null) {
            $fb['realisasi_km_per_l'] = round($hist_realisasi_avg, 2);
        } elseif (!empty($realisasi_values)) {
            $fb['realisasi_km_per_l'] = round(array_sum($realisasi_values) / count($realisasi_values), 2);
        } else {
            $fb['realisasi_km_per_l'] = null;
        }

        // Urutkan rows by tanggal
        if (!empty($fb['rows'])) {
            usort($fb['rows'], function($a,$b){
                return strtotime($a['tanggal']) - strtotime($b['tanggal']);
            });
            $earliestRow = $fb['rows'][0];
        } else {
            $earliestRow = null;
        }

        // Estimasi jarak jika masih ada yang null
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

        // cari data sebelum/sesudah di database (jika ada)
        $minDate = $fb['tgl_awal'];
        $maxDate = $fb['tgl_akhir'];
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

        // hitung hari
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
        }

        $fb['total_bensin'] = floatval($fb['total_bensin'] ?? 0.0);
        $fb['liter'] = floatval($fb['liter'] ?? 0.0);
        $fb['harga_ltr'] = floatval($fb['harga_ltr'] ?? 10000.0);
        if (isset($fb['km_terpakai'])) $fb['km_terpakai'] = is_numeric($fb['km_terpakai']) ? intval($fb['km_terpakai']) : null;
        if (isset($fb['km_awal']) && $fb['km_awal'] !== null) $fb['km_awal'] = intval($fb['km_awal']);
        if (isset($fb['km_akhir']) && $fb['km_akhir'] !== null) $fb['km_akhir'] = intval($fb['km_akhir']);
    }
    unset($fb);

    // Siapkan array untuk fuel table
    $fuelRows = [];
    foreach ($fuel_by_plat as $k => $e) {
        $total_bensin = $e['total_bensin'];
        $tgl_awal = !empty($e['tgl_awal']) ? date('d M Y', strtotime($e['tgl_awal'])) : '-';
        $tgl_akhir = !empty($e['tgl_akhir']) ? date('d M Y', strtotime($e['tgl_akhir'])) : '-';
        $km_awal = $e['km_awal'] ?? null;
        $km_akhir = $e['km_akhir'] ?? null;
        $km_terpakai = $e['km_terpakai'] ?? null;
        $km_per_hari = $e['km_per_hari'] ?? null;
        $harga_ltr = $e['harga_ltr'] ?? null;
        $liter = $e['liter'] ?? null;
        $realisasi = $e['realisasi_km_per_l'] ?? null;

        $fuelRows[] = [
            'plat' => $e['plat'],
            'total_bensin' => $total_bensin,
            'tgl_awal' => $tgl_awal,
            'tgl_akhir' => $tgl_akhir,
            'km_awal' => $km_awal,
            'km_akhir' => $km_akhir,
            'km_terpakai' => $km_terpakai,
            'km_per_hari' => $km_per_hari,
            'harga_per_liter' => $harga_ltr,
            'liter' => $liter,
            'realisasi' => $realisasi
        ];
    }
    usort($fuelRows, function($a,$b){ return strnatcasecmp($a['plat'],$b['plat']); });
    ?>

    <div class="card" role="document" style="page-break-inside:avoid; margin-bottom:22px;">
        <div class="title-bar">EXPENSE REIMBURSEMENT FORM</div>

        <div class="meta">
            <div class="col">
                <div><b>Nama</b> : <?= $display_name ?></div>
                <div><b>NIK</b> : <?= $display_nik ?></div>
                <div><b>Perjalanan Dinas</b> : <?= htmlspecialchars($first['perjalanan_dinas'] ?? '-') ?></div>
            </div>
            <div class="col">
                <div><b>Periode</b> : <?= htmlspecialchars($periode_text) ?></div>
            </div>
            <div class="col right-block">
                <div style="font-weight:700;">SUBMITTED</div>
                <div class="small-muted">Dicetak: <?= htmlspecialchars($printed_at) ?></div>
            </div>
        </div>

        <!-- Expense table dengan sub‑rows entertain -->
        <table class="table-exp" aria-describedby="detail-pengeluaran">
            <thead>
                <tr>
                    <th style="width:120px">TGL</th>
                    <th>KETERANGAN</th>
                    <th style="width:110px">Entertain</th>
                    <th style="width:110px">HOTEL</th>
                    <th style="width:110px">MAKAN</th>
                    <th style="width:110px">BENSIN</th>
                    <th style="width:110px">TOL</th>
                    <th style="width:110px">PARKIR</th>
                    <th style="width:120px">LAIN-LAIN</th>
                    <th style="width:150px">JUMLAH</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expense_rows as $r): ?>
                    <?php if ($r['type'] === 'main'): ?>
                        <tr>
                            <td><?= $r['tanggal'] ?></td>
                            <td><?= h($r['keterangan']) ?></td>
                            <td class="table-right amount"><?= money_html($r['ent']) ?></td>
                            <td class="table-right amount"><?= money_html($r['hotel']) ?></td>
                            <td class="table-right amount"><?= money_html($r['makan']) ?></td>
                            <td class="table-right amount"><?= money_html($r['bensin']) ?></td>
                            <td class="table-right amount"><?= money_html($r['tol']) ?></td>
                            <td class="table-right amount"><?= money_html($r['parkir']) ?></td>
                            <td class="table-right amount"><?= money_html($r['lain']) ?></td>
                            <td class="table-right amount" style="background:var(--dark-maroon);color:#fff;font-weight:700"><?= money_html($r['total']) ?></td>
                        </tr>
                    <?php else: ?>
                        <tr class="entertain-subrow">
                            <td style="color:#999;">—</td>
                            <td class="td-keterangan" style="padding-left:20px;"><?= h($r['keterangan']) ?></td>
                            <td class="table-right amount"><?= money_html($r['ent']) ?></td>
                            <td class="table-right amount"><?= money_html(0) ?></td>
                            <td class="table-right amount"><?= money_html(0) ?></td>
                            <td class="table-right amount"><?= money_html(0) ?></td>
                            <td class="table-right amount"><?= money_html(0) ?></td>
                            <td class="table-right amount"><?= money_html(0) ?></td>
                            <td class="table-right amount"><?= money_html(0) ?></td>
                            <td class="table-right amount" style="font-weight:700"><?= money_html($r['total']) ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>

                <tr class="total-row">
                    <td colspan="2">TOTAL</td>
                    <td class="table-right amount"><?= money_html($agg['ent']) ?></td>
                    <td class="table-right amount"><?= money_html($agg['hotel']) ?></td>
                    <td class="table-right amount"><?= money_html($agg['makan']) ?></td>
                    <td class="table-right amount"><?= money_html($agg['bensin']) ?></td>
                    <td class="table-right amount"><?= money_html($agg['tol']) ?></td>
                    <td class="table-right amount"><?= money_html($agg['parkir']) ?></td>
                    <td class="table-right amount"><?= money_html($agg['lain']) ?></td>
                    <td class="table-right amount"><?= money_html($agg['total']) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="summary-wrap">
            <div class="summary-box" role="region" aria-label="Ringkasan">
                <table>
                    <tr>
                        <td class="label">Total Pengeluaran</td>
                        <td class="value"><?= money_html($agg['total']) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Kasbon diterima (gabungan)</td>
                        <td class="value"></td> <!-- dikosongkan untuk diisi manual saat print -->
                    </tr>
                    <tr class="highlight">
                        <td class="label"><strong>Sisa (Kurang/Lebih)</strong></td>
                        <td class="value"><strong></strong></td> <!-- dikosongkan untuk diisi manual -->
                    </tr>
                </table>
            </div>
        </div>

        <div class="separator" aria-hidden="true"></div>

        <!-- FUEL USAGE REPORT (sama persis dengan preview) -->
        <div class="fuel-title">LAPORAN PEMAKAIAN BENSIN (UNIT)</div>
        <table class="table-fuel" role="table" aria-label="Laporan pemakaian bensin">
            <thead>
                <tr>
                    <th>Plat Number</th>
                    <th>Total Bensin</th>
                    <th>Tgl Awal</th>
                    <th>Tgl Akhir</th>
                    <th class="r">KM Awal</th>
                    <th class="r">KM Akhir</th>
                    <th class="r">KM Terpakai</th>
                    <th class="r">KM/Hari</th>
                    <th class="r">Harga/Ltr</th>
                    <th class="r">Liter</th>
                    <th class="r">Realisasi (KM/L)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fuelRows)): ?>
                    <tr><td colspan="11" style="text-align:center;color:#666;padding:14px">Tidak ada data pemakaian bensin untuk submission yang dipilih.</td></tr>
                <?php else: ?>
                    <?php foreach ($fuelRows as $fr): ?>
                        <tr>
                            <td><?= htmlspecialchars($fr['plat']) ?></td>
                            <td><?= money_html($fr['total_bensin']) ?></td>
                            <td><?= htmlspecialchars($fr['tgl_awal']) ?></td>
                            <td><?= htmlspecialchars($fr['tgl_akhir']) ?></td>
                            <td class="r"><?= fmt_km($fr['km_awal']) ?></td>
                            <td class="r"><?= fmt_km($fr['km_akhir']) ?></td>
                            <td class="r"><?= fmt_km($fr['km_terpakai']) ?></td>
                            <td class="r"><?= $fr['km_per_hari'] ? number_format($fr['km_per_hari'],0,',','.') : '-' ?></td>
                            <td class="r"><?= $fr['harga_per_liter'] ? money_html($fr['harga_per_liter']) : '-' ?></td>
                            <td class="r"><?= fmt_liter($fr['liter']) ?></td>
                            <td class="r"><?= $fr['realisasi'] !== null ? number_format($fr['realisasi'],2,',','.') : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="separator" aria-hidden="true"></div>

        <!-- SINGLE signature area for the combined document -->
        <div class="signs-wrap" role="group" aria-label="Persetujuan dan Tanda Tangan">
            <?php
            // signature names - prefer DB values from first submission or fallback defaults
            $sign1_name = $printed_by_name ?: ($first['nama'] ?? '-'); // pembuat
            $sign2_name = $first['approver_1_name'] ?? 'Bp. Fatur Rakhman';
            $sign2_role = $first['approver_1_role'] ?? 'National Sales Dept Head';
            $sign3_name = $first['approver_2_name'] ?? 'Bp. Yudhi Sunaryo';
            $sign3_role = $first['approver_2_role'] ?? 'Fin Acc Div Head';
            $sign4_name = $first['checker_name'] ?? 'Muhammad Adieb';
            $sign4_role = $first['checker_role'] ?? 'HR & GA Dept Head';
            $sign5_name = $first['gm_name'] ?? 'Kreshna Advagrha';
            $sign5_role = $first['gm_role'] ?? 'General Manager';
            $creator_job = htmlspecialchars($first['jabatan'] ?? $first['position'] ?? $first['role'] ?? 'Sales Promotion');
            ?>
            <div class="sign-block left-info">
                <div class="sign-inner">
                    <div class="sig-title">Dibuat oleh,</div>
                    <div class="sign-mid">
                        <div style="margin-bottom:6px">Dokumen dibuat oleh:</div>
                        <div class="sig-name"><?= htmlspecialchars($sign1_name) ?></div>
                        <div style="font-size:11px;color:#999;margin-top:6px;">Dicetak: <?= htmlspecialchars($printed_at) ?></div>
                    </div>
                    <div class="sig-line" aria-hidden="true"></div>
                    <div class="sign-bottom">
                        <div class="sig-name"><?= htmlspecialchars($sign1_name) ?></div>
                        <div class="sig-role"><?= $creator_job ?></div>
                    </div>
                </div>
            </div>

            <div class="sign-block">
                <div class="sign-inner">
                    <div class="sig-title">Menyetujui,</div>
                    <div class="sign-mid">&nbsp;</div>
                    <div class="sig-line" aria-hidden="true"></div>
                    <div class="sign-bottom">
                        <div class="sig-name"><?= htmlspecialchars($sign2_name) ?></div>
                        <div class="sig-role"><?= htmlspecialchars($sign2_role) ?></div>
                    </div>
                </div>
            </div>

            <div class="sign-block">
                <div class="sign-inner">
                    <div class="sig-title">Menyetujui,</div>
                    <div class="sign-mid">&nbsp;</div>
                    <div class="sig-line" aria-hidden="true"></div>
                    <div class="sign-bottom">
                        <div class="sig-name"><?= htmlspecialchars($sign3_name) ?></div>
                        <div class="sig-role"><?= htmlspecialchars($sign3_role) ?></div>
                    </div>
                </div>
            </div>

            <div class="sign-block">
                <div class="sign-inner">
                    <div class="sig-title">Diperiksa,</div>
                    <div class="sign-mid">&nbsp;</div>
                    <div class="sig-line" aria-hidden="true"></div>
                    <div class="sign-bottom">
                        <div class="sig-name"><?= htmlspecialchars($sign4_name) ?></div>
                        <div class="sig-role"><?= htmlspecialchars($sign4_role) ?></div>
                    </div>
                </div>
            </div>

            <div class="sign-block">
                <div class="sign-inner">
                    <div class="sig-title">Mengetahui,</div>
                    <div class="sign-mid">&nbsp;</div>
                    <div class="sig-line" aria-hidden="true"></div>
                    <div class="sign-bottom">
                        <div class="sig-name"><?= htmlspecialchars($sign5_name) ?></div>
                        <div class="sig-role"><?= htmlspecialchars($sign5_role) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-note">
            <strong>Catatan:</strong> Dokumen gabungan di atas menampilkan semua submission yang dipilih / sesuai filter di preview. Jika sudah sesuai, klik tombol cetak.
        </div>

        <div class="controls">
            <button class="btn btn-print" onclick="window.print();">🖨 Cetak</button>
            <button class="btn btn-close" onclick="window.close();">Tutup</button>
        </div>

    </div> <!-- .card -->

<?php endif; ?>

</div> <!-- .wrapper -->

<script>
// optional: auto-print when ?autoprint=1
(function(){
    const url = new URL(window.location.href);
    if (url.searchParams.get('autoprint') === '1') {
        setTimeout(()=>window.print(), 300);
    }
})();
</script>
</body>
</html>