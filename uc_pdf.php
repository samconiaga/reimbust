<?php 
// uc_pdf.php (revisi: FORM PERMINTAAN boxed signatures + no signatures on Pengajuan page)
// ready to copy-paste — smaller signature boxes
require __DIR__ . '/db.php';
require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

// get user from session (adjust keys if needed)
$currentUserName = '';
$currentUserId = null;
if (!empty($_SESSION['user_id'])) $currentUserId = $_SESSION['user_id'];
if (!empty($_SESSION['user_name'])) $currentUserName = $_SESSION['user_name'];
elseif (!empty($_SESSION['username'])) $currentUserName = $_SESSION['username'];
elseif (!empty($_SESSION['name'])) $currentUserName = $_SESSION['name'];

function singkatanNama($fullname) {
    $fullname = trim($fullname);
    if ($fullname === '') return '';
    $parts = preg_split('/\s+/', $fullname);
    if (count($parts) === 1) return strtoupper(substr($parts[0], 0, min(3, strlen($parts[0]))));
    $inisial = '';
    foreach ($parts as $p) $inisial .= mb_substr($p, 0, 1);
    return strtoupper($inisial);
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo "Invalid"; exit; }

$stmt = $pdo->prepare("SELECT * FROM uc_requests WHERE id = ?");
$stmt->execute([$id]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$req) { echo "Not found"; exit; }

$out = $pdo->prepare("SELECT * FROM uc_outlets WHERE request_id = ? ORDER BY trip_date");
$out->execute([$id]);
$outlets = $out->fetchAll(PDO::FETCH_ASSOC);

function hariIndo($tanggal) {
    $hari = date('N', strtotime($tanggal));
    $daftar_hari = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
    return $daftar_hari[$hari - 1];
}
function bulanIndo($bln) {
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return $bulan[(int)$bln];
}
function formatTanggal($tgl) {
    $d = date('d', strtotime($tgl));
    $m = date('n', strtotime($tgl));
    $y = date('y', strtotime($tgl));
    return $d . '-' . bulanIndo($m) . '-' . $y;
}
function summarizeItinerary($outlets) {
    $dates = [];
    foreach ($outlets as $row) {
        $tgl = $row['trip_date'];
        $kota = $row['destination'];
        if (!isset($dates[$tgl])) $dates[$tgl] = [];
        if (!in_array($kota, $dates[$tgl])) $dates[$tgl][] = $kota;
    }
    ksort($dates);
    $iter = array_keys($dates);
    $i = 0; $groups = [];
    while ($i < count($iter)) {
        $start = $iter[$i]; $end = $start; $j = $i;
        while (isset($iter[$j+1]) && (strtotime($iter[$j+1]) - strtotime($iter[$j])) == 86400) { $end = $iter[$j+1]; $j++; }
        $kotaList = [];
        for ($k=$i;$k<=$j;$k++){
            foreach ($dates[$iter[$k]] as $kota) if (!in_array($kota,$kotaList)) $kotaList[] = $kota;
        }
        $kotaStr = implode(', ',$kotaList);
        if ($start == $end) $groups[] = formatTanggal($start) . ' ' . $kotaStr;
        else {
            $tglAwal = date('j', strtotime($start));
            $blnAwal = bulanIndo(date('n', strtotime($start)));
            $tglAkhir = date('j', strtotime($end));
            $blnAkhir = bulanIndo(date('n', strtotime($end)));
            $tahunAwal = date('y', strtotime($start));
            $tahunAkhir = date('y', strtotime($end));
            if ($blnAwal == $blnAkhir && $tahunAwal == $tahunAkhir) $groups[] = $tglAwal . ' - ' . $tglAkhir . ' ' . $blnAwal . ' ' . $kotaStr;
            else $groups[] = formatTanggal($start) . ' - ' . formatTanggal($end) . ' ' . $kotaStr;
        }
        $i = $j + 1;
    }
    return implode(' ', $groups);
}

// totals
$total_hotel = ($req['hotel_per_day'] ?? 0) * ($req['hotel_nights'] ?? 0);
$total_meal = ($req['meal_per_day'] ?? 0) * ($req['meal_days'] ?? 0);
$total = $total_hotel + $total_meal + ($req['fuel_amount'] ?? 0) + ($req['other_amount'] ?? 0);

$itinerary = summarizeItinerary($outlets);

// group outlets
$grouped = [];
foreach ($outlets as $row) {
    $key = $row['trip_date'] . '|' . $row['destination'];
    if (!isset($grouped[$key])) $grouped[$key] = ['trip_date'=>$row['trip_date'],'destination'=>$row['destination'],'outlets'=>[]];
    $grouped[$key]['outlets'][] = $row;
}
ksort($grouped);

// logo base64
$logoPath = __DIR__ . '/logo/logo.png';
$logoBase64 = '';
if (file_exists($logoPath)) $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));

// Page fixed signers (names & titles)
$page_sign_fatur = 'Fatur Rahman';
$page_sign_fatur_title = 'Sales Dept Head';
$page_sign_adieb = 'Mukhammad Adieb';
$page_sign_adieb_title = 'HR&GA Dept Head';
$finance_default_name = 'Yudhi Sunaryo';
$finance_default_title = 'Finance Acc Div Head';

// --- Yang Meminta name, title, department (try multiple fallbacks):
$yang_meminta_name = $currentUserName ?: ($req['requester_name'] ?? '');
$yang_meminta_title = $req['requester_title'] ?? $req['requester_position'] ?? '';
$yang_meminta_dept = '';

// lookup user by session id for jabatan & departemen if available
if (!empty($currentUserId)) {
    try {
        $uStmt = $pdo->prepare("SELECT nama, name, jabatan, departemen, cabang FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$currentUserId]);
        $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($uRow) {
            if (!empty($uRow['nama'])) $yang_meminta_name = $uRow['nama'];
            elseif (!empty($uRow['name'])) $yang_meminta_name = $uRow['name'];
            if (!empty($uRow['jabatan'])) $yang_meminta_title = $uRow['jabatan'];
            // prefer departemen, fallback to cabang
            if (!empty($uRow['departemen'])) $yang_meminta_dept = $uRow['departemen'];
            elseif (!empty($uRow['cabang'])) $yang_meminta_dept = $uRow['cabang'];
        }
    } catch (Exception $e) {
        // ignore
    }
}

// if department/title still empty, try to find user by the request's requester_name
if (empty($yang_meminta_dept) && !empty($req['requester_name'])) {
    try {
        $q = $pdo->prepare("SELECT nama, name, jabatan, departemen, cabang FROM users WHERE nama = ? LIMIT 1");
        $q->execute([$req['requester_name']]);
        $ru = $q->fetch(PDO::FETCH_ASSOC);
        if ($ru) {
            if (!empty($ru['nama'])) $yang_meminta_name = $ru['nama'];
            elseif (!empty($ru['name'])) $yang_meminta_name = $ru['name'];
            if (!empty($ru['jabatan'])) $yang_meminta_title = $ru['jabatan'];
            if (!empty($ru['departemen'])) $yang_meminta_dept = $ru['departemen'];
            elseif (!empty($ru['cabang'])) $yang_meminta_dept = $ru['cabang'];
        }
    } catch (Exception $e) {
        // ignore
    }
}

$kepala_name = $req['approver_kepala_name'] ?? ($req['approver1_name'] ?? $page_sign_fatur);
$kepala_title = $req['approver_kepala_title'] ?? ($req['approver1_title'] ?? $page_sign_fatur_title);
$finance_name = $req['approver_finance_name'] ?? ($req['approver2_name'] ?? $finance_default_name);
$finance_title = $req['approver_finance_title'] ?? ($req['approver2_title'] ?? $finance_default_title);

$userAbbrev = singkatanNama($yang_meminta_name);
$todayFull = date('d-M-Y');

function e($s){ return htmlspecialchars((string)$s); }

// build HTML (FORM PERMINTAAN first)
$html = '<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans, sans-serif; font-size:12px; margin:18px; color:#000;}
.header{display:flex;align-items:center;margin-bottom:10px;}
.logo{width:80px;} .logo img{max-width:100%;display:block;}
.company-name{font-size:24px;font-weight:bold;text-align:center;flex:1;}
.title{font-size:18px;font-weight:bold;text-align:center;margin-bottom:12px;}
.info-row{display:flex;justify-content:space-between;margin-bottom:6px;font-size:12px;}
.info-label{font-weight:bold;}
table{width:100%;border-collapse:collapse;margin-bottom:12px;font-size:12px;}
th,td{border:1px solid #000;padding:6px;vertical-align:top;}
th{background:#eee;text-align:center;font-weight:bold;}
.text-right{text-align:right;}
.biaya{width:60%;margin-left:auto;border:none;}
.biaya td{border:none;padding:4px 8px;}
/* signature area: made smaller */
.signature-wrapper{width:100%;margin-top:12px;}
.signature-table{width:100%;border-collapse:collapse;}
.signature-table td{padding:4px;text-align:center;vertical-align:bottom;}
.ttd-space{height:90px;border:1px solid #000;}
.sig-line{display:flex;justify-content:center;align-items:center;gap:6px;white-space:nowrap;}
.sig-name{font-weight:600;font-size:12px;}
.sig-title{font-size:11px;color:#333;}
.col-4{width:24%;}
.col-3{width:33.333%;}
.boxed-cell{border:1px solid #000;padding:6px;height:110px;vertical-align:top;}
.name-dept{margin-top:6px;font-size:10px;color:#333;}
</style></head><body>';

// PAGE 1: FORM PERMINTAAN
$html .= '<div class="header">';
if ($logoBase64) $html .= '<div class="logo"><img src="' . $logoBase64 . '"></div>';
else $html .= '<div class="logo"></div>';
$html .= '<div class="company-name">PT SAMCO FARMA</div></div>';
$html .= '<div style="text-align:center;font-weight:bold;font-size:16px;margin-bottom:10px;">FORM PERMINTAAN</div>';
$html .= '<div style="display:flex;justify-content:space-between;margin-bottom:12px;font-size:12px;"><div>Bagian : ' . e($req['branch'] ?? 'Sales') . '</div><div>Tanggal : ' . e(date('d-M-y')) . '</div></div>';

$html .= '<table style="width:100%;border:1px solid #000;border-collapse:collapse;">';
$html .= '<tr><th style="width:70%;padding:8px;">Nama Produk / Barang / Jasa / Dokumen *</th><th style="width:30%;padding:8px;">Jumlah</th></tr>';
$html .= '<tr><td style="padding:8px;">' . e($req['requester_name'] ?? '') . ' Permintaan Kas bon #' . e($req['requester_name'] ?? '') . '</td>';
$html .= '<td style="padding:8px;text-align:right;">Rp ' . number_format($total,0,',','.') . '</td></tr>';
$html .= '<tr><td colspan="2" style="padding:8px;">' . e($itinerary) . '</td></tr>';
$html .= '</table>';

// signature area form - 4 boxed columns: Yang Meminta (abbr+date + dept), Kepala, HR&GA (Adieb), Finance
$html .= '<div class="signature-wrapper">';
$html .= '<table class="signature-table" style="width:100%;">';
$html .= '<tr>';
$html .= '<td class="col-4"><strong>Yang Meminta</strong></td>';
$html .= '<td class="col-4"><strong>Mengetahui<br>Kepala Bagian</strong></td>';
$html .= '<td class="col-4"><strong>Mengetahui<br>HR & GA</strong></td>';
$html .= '<td class="col-4"><strong>Mengetahui<br>Finance</strong></td>';
$html .= '</tr>';

// boxed signature rows
$html .= '<tr>';
$html .= '<td class="boxed-cell" style="text-align:center;">';
$html .= '<div style="font-weight:bold;">(' . e($userAbbrev) . ')<br>' . e($todayFull) . '</div>';
$html .= '<div class="name-dept"><strong>' . e($yang_meminta_name) . '</strong><br>' . e($yang_meminta_dept) . '</div>';
$html .= '</td>';

$html .= '<td class="boxed-cell"></td>';
$html .= '<td class="boxed-cell"></td>';
$html .= '<td class="boxed-cell"></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td style="padding:8px;text-align:center;">';
$html .= '<div class="sig-line"><div class="sig-name">' . e($yang_meminta_name) . '</div>';
if (!empty($yang_meminta_title)) $html .= '<div class="sig-title">' . e($yang_meminta_title) . '</div>';
$html .= '</div></td>';

$html .= '<td style="padding:8px;text-align:center;">';
$html .= '<div class="sig-line"><div class="sig-name">' . e($kepala_name) . '</div>';
if (!empty($kepala_title)) $html .= '<div class="sig-title">' . e($kepala_title) . '</div>';
$html .= '</div></td>';

$html .= '<td style="padding:8px;text-align:center;">';
$html .= '<div class="sig-line"><div class="sig-name">' . e($page_sign_adieb) . '</div>';
if (!empty($page_sign_adieb_title)) $html .= '<div class="sig-title">' . e($page_sign_adieb_title) . '</div>';
$html .= '</div></td>';

$html .= '<td style="padding:8px;text-align:center;">';
$html .= '<div class="sig-line"><div class="sig-name">' . e($finance_name) . '</div>';
if (!empty($finance_title)) $html .= '<div class="sig-title">' . e($finance_title) . '</div>';
$html .= '</div></td>';
$html .= '</tr>';

$html .= '</table></div>'; // end signature-wrapper for form

// page break => next page Pengajuan / Realisasi UC (no signatures)
$html .= '<div style="page-break-before:always;"></div>';

// --- PAGE 2: Pengajuan / Realisasi UC (no signatures)
$html .= '<div class="header">';
if ($logoBase64) $html .= '<div class="logo"><img src="' . $logoBase64 . '"></div>';
else $html .= '<div class="logo"></div>';
$html .= '<div class="company-name">PT SAMCO FARMA</div>';
$html .= '</div>';
$html .= '<div class="title">Pengajuan / Realisasi UC</div>';

$html .= '<div class="info-row">';
$html .= '<div><span class="info-label">Nama :</span> ' . e($req['requester_name'] ?? '') . '</div>';
$html .= '<div><span class="info-label">Cabang :</span> ' . e($req['branch'] ?? '') . '</div>';
$html .= '<div><span class="info-label">Bulan :</span> ' . e(date('F Y', strtotime($req['start_date'] ?? date('Y-m-d')))) . '</div>';
$html .= '</div>';
$html .= '<div class="info-row"><div><span class="info-label">Periode :</span> ' . e(date('d M', strtotime($req['start_date'] ?? date('Y-m-d')))) . ' s/d ' . e(date('d M Y', strtotime($req['end_date'] ?? date('Y-m-d')))) . '</div></div>';

$html .= '<h4>Rincian Tujuan / Outlet</h4>';
$html .= '<table>';
$html .= '<thead><tr><th>No</th><th>Hari</th><th>Tanggal</th><th>Tujuan</th><th>Outlet</th><th>Estimasi Sales (Rp)</th></tr></thead><tbody>';
$no = 1;
foreach ($grouped as $group) {
    $rowspan = count($group['outlets']);
    $hari = hariIndo($group['trip_date']);
    $tgl = date('d-m-Y', strtotime($group['trip_date']));
    $first = true;
    foreach ($group['outlets'] as $outlet) {
        $html .= '<tr>';
        if ($first) {
            $html .= '<td rowspan="' . $rowspan . '">' . $no++ . '</td>';
            $html .= '<td rowspan="' . $rowspan . '">' . e($hari) . '</td>';
            $html .= '<td rowspan="' . $rowspan . '">' . e($tgl) . '</td>';
            $html .= '<td rowspan="' . $rowspan . '">' . e($group['destination']) . '</td>';
        }
        $html .= '<td>' . e($outlet['outlet_name']) . '</td>';
        $html .= '<td class="text-right">' . number_format($outlet['est_sales'] ?? 0,0,',','.') . '</td>';
        $html .= '</tr>';
        $first = false;
    }
}
$html .= '</tbody></table>';

$html .= '<h4>Rincian Biaya</h4>';
$html .= '<table class="biaya">';
$html .= '<tr><td>1. Biaya hotel</td><td>' . number_format($req['hotel_per_day'] ?? 0,0,',','.') . ' x ' . ($req['hotel_nights'] ?? 0) . ' malam</td><td class="text-right">Rp ' . number_format($total_hotel,0,',','.') . '</td></tr>';
$html .= '<tr><td>2. Biaya makan</td><td>' . number_format($req['meal_per_day'] ?? 0,0,',','.') . ' x ' . ($req['meal_days'] ?? 0) . ' hari</td><td class="text-right">Rp ' . number_format($total_meal,0,',','.') . '</td></tr>';
$html .= '<tr><td>3. Biaya BBM/Transport</td><td></td><td class="text-right">Rp ' . number_format($req['fuel_amount'] ?? 0,0,',','.') . '</td></tr>';
$html .= '<tr><td>4. Lain-lain</td><td></td><td class="text-right">Rp ' . number_format($req['other_amount'] ?? 0,0,',','.') . '</td></tr>';
$html .= '<tr class="total"><td colspan="2" class="text-right"><strong>Total</strong></td><td class="text-right"><strong>Rp ' . number_format($total,0,',','.') . '</strong></td></tr>';
$html .= '</table>';

// end document
$html .= '</body></html>';

// generate PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4','portrait');
$dompdf->render();
$dompdf->stream("uc_request_{$id}.pdf", ["Attachment" => false]);
exit;