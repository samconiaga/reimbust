<?php
// approve.php - generate PDF server-side using Dompdf (TTD sejajar ke samping)
// Salin-tempel file ini menggantikan approve.php lama
session_start();
if (empty($_SESSION['nik'])) { header('Location: login.php'); exit; }

if (file_exists(__DIR__.'/config.php')) include_once __DIR__.'/config.php';

// require autoload for dompdf (install via composer)
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// DB config (same as print.php)
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

function rupiah($n){
    $n = $n === null || $n === '' ? 0 : $n;
    return 'Rp ' . number_format((float)$n,0,',','.');
}

// determine ids (same as print.php)
$preview_submissions = [];
if (!empty($_GET['ids'])) {
    $ids = $_GET['ids'];
    $ids_arr = array_filter(array_map('intval', explode(',', $ids)));
    if ($ids_arr) {
        $safe = implode(',', $ids_arr);
        $preview_submissions = db_fetch_all($conn, "SELECT * FROM submissions WHERE id IN ($safe) ORDER BY FIELD(id,$safe)", []);
    }
} elseif (!empty($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id > 0) $preview_submissions = db_fetch_all($conn, "SELECT * FROM submissions WHERE id = ? LIMIT 1", [$id]);
}

if (empty($preview_submissions)) {
    echo "Tidak ada data untuk dibuat PDF.";
    exit;
}

// build HTML (string)
ob_start();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Expense Reimbursement - PDF</title>
<style>
/* font-family default; Dompdf mendukung Arial/Helvetica dengan baik */
body{font-family: Arial, Helvetica, sans-serif; font-size:13px; color:#222; margin:20mm;}
.header{background:#7a0004;color:#fff;padding:10px 14px;text-align:center;font-weight:700;border-radius:4px}
.title{background:#6b0000;color:#fff;padding:10px;text-align:center;font-weight:700;margin-top:12px;border-radius:3px}
.table-exp{width:100%;border-collapse:collapse;margin-top:12px;font-size:12px}
.table-exp th, .table-exp td{border:1px solid #ddd;padding:8px;vertical-align:top}
.table-exp th{background:#f6f6f6;font-weight:700}
.table-right{text-align:right}
.summary-box{width:300px;border:1px solid #ddd;padding:6px;background:#fff; float:right; margin-top:8px;}
.summary-box table{width:100%;border-collapse:collapse}
.summary-box td{padding:6px}
.separator{height:1px;background:#b20000;margin:18px 0;border-radius:2px}

/* Signature area: use table for consistent horizontal alignment in Dompdf.
   Each cell contains a fixed-height wrapper (.sig-cell) so the line and name positions are stable. */
.sig-table { width:100%; border-collapse:collapse; margin-top:26px; table-layout:fixed; }
.sig-table td { vertical-align:top; padding:8px; }

/* column widths tuned similar to the web preview */
.sig-left { width:28%; }
.sig-col { width:18%; }

/* cell internal layout */
.sig-cell { position:relative; height:120px; }

/* small top meta (above the line) */
.sig-top { font-size:11px; color:#666; line-height:1.1; }

/* signature horizontal line placed at absolute vertical position so all lines align */
.sig-line { position:absolute; left:8%; right:8%; top:52px; border-top:2px solid #222; height:0; }

/* left cell line slightly offset to left */
.sig-left .sig-line { left:6%; right:32%; }

/* name & role placed below the line */
.sig-bottom { position:absolute; left:0; right:0; top:66px; text-align:center; }
.sig-left .sig-bottom { text-align:left; padding-left:6px; }

/* look of name and role */
.sig-name { font-weight:700; margin:0 0 4px 0; font-size:13px; }
.sig-role { font-size:12px; color:#666; margin:0; }

/* small helper so top title (Menyetujui etc) appears above the line */
.sig-title { font-size:12px; color:#444; margin-bottom:6px; text-align:center; }
.sig-left .sig-title { text-align:left; padding-left:6px; }

/* prevent signature table splitting across pages */
.sig-table, .sig-table tr, .sig-table td {
    page-break-inside: avoid;
    break-inside: avoid;
    -webkit-column-break-inside: avoid;
}

/* small note box */
.note{background:#dff3fb;border:1px solid #bfeaf5;padding:10px;border-radius:6px;margin-top:12px}

/* page margin - also ok to be set by Dompdf setPaper, kept for safety */
@page { margin: 20mm; }
</style>
</head>
<body>
<?php
foreach($preview_submissions as $idx => $s):
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
    $periode_text = ($s['periode_mulai'] ?? '') || ($s['periode_selesai'] ?? '') ? ( ($s['periode_mulai']?date('d-m-Y',strtotime($s['periode_mulai'])):'' ) . (($s['periode_selesai']) ? ' s/d ' . date('d-m-Y',strtotime($s['periode_selesai'])) : '') ) : date('d-m-Y',strtotime($tanggal_display));

    // signature names
    $printed_by_name = $_SESSION['name'] ?? ($s['nama'] ?? '');
    $printed_by_nik  = $_SESSION['nik'] ?? ($s['nik'] ?? '');
    $sign2_name = $s['approver_1_name'] ?? 'Bp. Fatur Rakhman';
    $sign2_role = $s['approver_1_role'] ?? 'National Sales Dept Head';
    $sign3_name = $s['approver_2_name'] ?? 'Bp. Yudhi Sunaryo';
    $sign3_role = $s['approver_2_role'] ?? 'Fin Acc Div Head';
    $sign4_name = $s['checker_name'] ?? 'Muhammad Adieb';
    $sign4_role = $s['checker_role'] ?? 'HR & GA Dept Head';
    $sign5_name = $s['gm_name'] ?? 'Kreshna Advagrha';
    $sign5_role = $s['gm_role'] ?? 'General Manager';
    // creator small info
    $creator_initials = htmlspecialchars($s['dibuat_oleh_initials'] ?? $s['created_by_initials'] ?? 'DAL');
    $creator_job = htmlspecialchars($s['jabatan'] ?? $s['position'] ?? $s['role'] ?? 'Sales Promotion');
?>
<div class="header">Preview & Approval</div>
<div class="title">EXPENSE REIMBURSEMENT FORM</div>

<div style="margin-top:8px">
    <div style="float:left; width:60%">
        <div><strong>Nama</strong>: <?= htmlspecialchars($s['nama'] ?? '-') ?></div>
        <div><strong>Cabang</strong>: <?= htmlspecialchars($s['cabang'] ?? '-') ?></div>
        <div><strong>Bagian</strong>: <?= htmlspecialchars($s['department'] ?? '') ?></div>
    </div>
    <div style="float:right; width:35%; text-align:right;">
        <div><strong>Periode</strong>: <?= htmlspecialchars($periode_text) ?></div>
        <div><strong>Perjalanan Dinas</strong>: <?= htmlspecialchars($s['perjalanan_dinas'] ?? '-') ?></div>
        <div><strong>No. Pengajuan</strong>: <?= htmlspecialchars($s['nomor'] ?? $s['id']) ?></div>
    </div>
    <div style="clear:both"></div>
</div>

<table class="table-exp" role="table" aria-label="detail">
    <thead>
        <tr>
            <th style="width:110px">TGL</th>
            <th>KETERANGAN</th>
            <th style="width:90px">Entertain</th>
            <th style="width:90px">HOTEL</th>
            <th style="width:90px">MAKAN</th>
            <th style="width:90px">BENSIN</th>
            <th style="width:90px">TOL</th>
            <th style="width:90px">PARKIR</th>
            <th style="width:100px">LAIN-LAIN</th>
            <th style="width:120px">JUMLAH</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><?= date('d M Y', strtotime($tanggal_display)) ?></td>
            <td><?= nl2br(htmlspecialchars($s['keterangan_lain'] ?? $s['tujuan'] ?? '-')) ?></td>
            <td class="table-right"><?= rupiah($biaya_ent) ?></td>
            <td class="table-right"><?= rupiah($biaya_hotel) ?></td>
            <td class="table-right"><?= rupiah($biaya_makan) ?></td>
            <td class="table-right"><?= rupiah($biaya_bensin) ?></td>
            <td class="table-right"><?= rupiah($biaya_tol) ?></td>
            <td class="table-right"><?= rupiah($biaya_parkir) ?></td>
            <td class="table-right"><?= rupiah($biaya_lain) ?></td>
            <td class="table-right" style="background:#6b0000;color:#fff;font-weight:700"><?= rupiah($total) ?></td>
        </tr>
        <tr class="total-row">
            <td colspan="2">TOTAL</td>
            <td class="table-right"><?= rupiah($biaya_ent) ?></td>
            <td class="table-right"><?= rupiah($biaya_hotel) ?></td>
            <td class="table-right"><?= rupiah($biaya_makan) ?></td>
            <td class="table-right"><?= rupiah($biaya_bensin) ?></td>
            <td class="table-right"><?= rupiah($biaya_tol) ?></td>
            <td class="table-right"><?= rupiah($biaya_parkir) ?></td>
            <td class="table-right"><?= rupiah($biaya_lain) ?></td>
            <td class="table-right"><?= rupiah($total) ?></td>
        </tr>
    </tbody>
</table>

<div class="summary-box">
    <table>
        <tr><td>Total Pengeluaran</td><td style="text-align:right"><?= rupiah($total) ?></td></tr>
        <tr><td>Kasbon diterima</td><td style="text-align:right"><?= rupiah($kasbon) ?></td></tr>
        <tr><td><strong>Sisa (Kurang/Lebih)</strong></td><td style="text-align:right"><strong><?= rupiah($kasbon - $total) ?></strong></td></tr>
    </table>
</div>

<div style="clear:both"></div>

<div class="separator"></div>

<!-- Signature table: fixed-height cell with absolute-positioned line + bottom name -->
<table class="sig-table" role="presentation" aria-label="Tanda Tangan">
    <tr>
        <!-- Left (creator) -->
        <td class="sig-left">
            <div class="sig-cell">
                <div class="sig-title">Dibuat oleh,</div>
                <div class="sig-top">
                    <div>Dokumen telah dibuat oleh:</div>
                    <div style="font-weight:700; margin-top:4px;"><?= $creator_initials ?></div>
                    <div style="font-size:11px;color:#999;margin-top:6px;">Dicetak: <?= date('d M Y, H:i') ?></div>
                </div>
                <div class="sig-line" aria-hidden="true"></div>
                <div class="sig-bottom">
                    <div class="sig-name"><?= htmlspecialchars($printed_by_name ?: ($s['nama'] ?? '-')) ?></div>
                    <div class="sig-role"><?= $creator_job ?></div>
                </div>
            </div>
        </td>

        <!-- Approver 1 -->
        <td class="sig-col">
            <div class="sig-cell">
                <div class="sig-title">Menyetujui,</div>
                <div class="sig-top">&nbsp;</div>
                <div class="sig-line" aria-hidden="true"></div>
                <div class="sig-bottom">
                    <div class="sig-name"><?= htmlspecialchars($sign2_name) ?></div>
                    <div class="sig-role"><?= htmlspecialchars($sign2_role) ?></div>
                </div>
            </div>
        </td>

        <!-- Approver 2 -->
        <td class="sig-col">
            <div class="sig-cell">
                <div class="sig-title">Menyetujui,</div>
                <div class="sig-top">&nbsp;</div>
                <div class="sig-line" aria-hidden="true"></div>
                <div class="sig-bottom">
                    <div class="sig-name"><?= htmlspecialchars($sign3_name) ?></div>
                    <div class="sig-role"><?= htmlspecialchars($sign3_role) ?></div>
                </div>
            </div>
        </td>

        <!-- Checker -->
        <td class="sig-col">
            <div class="sig-cell">
                <div class="sig-title">Diperiksa,</div>
                <div class="sig-top">&nbsp;</div>
                <div class="sig-line" aria-hidden="true"></div>
                <div class="sig-bottom">
                    <div class="sig-name"><?= htmlspecialchars($sign4_name) ?></div>
                    <div class="sig-role"><?= htmlspecialchars($sign4_role) ?></div>
                </div>
            </div>
        </td>

        <!-- GM -->
        <td class="sig-col">
            <div class="sig-cell">
                <div class="sig-title">Mengetahui,</div>
                <div class="sig-top">&nbsp;</div>
                <div class="sig-line" aria-hidden="true"></div>
                <div class="sig-bottom">
                    <div class="sig-name"><?= htmlspecialchars($sign5_name) ?></div>
                    <div class="sig-role"><?= htmlspecialchars($sign5_role) ?></div>
                </div>
            </div>
        </td>
    </tr>
</table>

<div class="note">
    <strong>Catatan:</strong> Tinjau dokumen di atas. Jika sudah sesuai, dokumen ini dianggap final.
</div>

<?php
    // page-break between multiple submissions
    if ($idx < count($preview_submissions)-1) {
        echo '<div style="page-break-after:always"></div>';
    }
endforeach;
?>
</body>
</html>
<?php
$html = ob_get_clean();

// Dompdf options
$options = new Options();
$options->set('isRemoteEnabled', true); // jika ada gambar remote
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// ukuran: A4 portrait
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// stream pdf to browser (inline)
$filename = 'reimbursement_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ["Attachment" => false]);
exit;