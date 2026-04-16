<?php
// uc_view.php
require __DIR__ . '/db.php';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo "Invalid id"; exit; }

$stmt = $pdo->prepare("SELECT * FROM uc_requests WHERE id = ?");
$stmt->execute([$id]);
$req = $stmt->fetch();
if (!$req) { echo "Not found"; exit; }

$out = $pdo->prepare("SELECT * FROM uc_outlets WHERE request_id = ? ORDER BY trip_date, id");
$out->execute([$id]);
$outlets = $out->fetchAll();

function hariIndo($tanggal) {
    if (empty($tanggal)) return '';
    $hari = date('N', strtotime($tanggal));
    $daftar_hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    return $daftar_hari[$hari - 1] ?? '';
}

$total_hotel = ($req['hotel_per_day'] ?? 0) * ($req['hotel_nights'] ?? 0);
$total_meal  = ($req['meal_per_day'] ?? 0) * ($req['meal_days'] ?? 0);
$total = $total_hotel + $total_meal + ($req['fuel_amount'] ?? 0) + ($req['other_amount'] ?? 0);

// Group outlet berdasarkan tanggal & tujuan
$grouped = [];
foreach ($outlets as $row) {
    $trip_date = $row['trip_date'] ?? '';
    $destination = $row['destination'] ?? '';
    $key = $trip_date . '|' . $destination;
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'trip_date' => $trip_date,
            'destination' => $destination,
            'outlets' => []
        ];
    }
    $grouped[$key]['outlets'][] = $row;
}
ksort($grouped);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>View UC #<?= $id ?></title>
<style>
    body { font-family: Arial, sans-serif; background:#f4f6f9; margin:20px; }
    .container { max-width:1000px; background:#fff; padding:35px; margin:auto; box-shadow:0 0 10px rgba(0,0,0,0.08); }

    .header { display:flex; align-items:center; margin-bottom:15px; }
    .logo { width:80px; }
    .logo img { max-width:100%; }
    .company-name { flex:1; text-align:center; font-size:22px; font-weight:bold; }

    .title { text-align:center; font-size:17px; font-weight:bold; margin:20px 0; }

    .info-row { display:flex; justify-content:space-between; margin-bottom:8px; font-size:14px; }
    .info-label { font-weight:bold; }

    table { width:100%; border-collapse:collapse; margin-bottom:20px; font-size:13px; }
    th, td { border:1px solid #000; padding:7px; vertical-align:middle; }
    th { background:#f2f2f2; text-align:center; }
    .text-right { text-align:right; }

    .biaya { width:55%; margin-left:auto; border:none; }
    .biaya td { border:none; padding:4px 8px; }
    .biaya .total td { font-weight:bold; }

    /* ===== TTD AREA ===== */
    .signature-wrapper {
        width:50%;
        margin-left:auto;
        margin-top:60px;
    }
    .signature-table {
        width:100%;
        border-collapse:collapse;
    }
    .signature-table td {
        text-align:center;
        vertical-align:bottom;
        padding:8px;
    }
    .ttd-space {
        height:80px; /* ruang untuk tanda tangan basah */
    }

    .btn { display:inline-block; padding:8px 16px; margin:8px 6px 0 0; text-decoration:none; background:#007bff; color:#fff; border-radius:4px; }
    .btn-secondary { background:#6c757d; color:#fff; }
    .btn-edit { background:#28a745; color:#fff; }
    .text-center { text-align:center; }
    .small-muted { color:#666; font-size:0.9rem; margin-top:6px; }
</style>
</head>

<body>
<div class="container">

    <div class="header">
        <div class="logo">
            <img src="logo/logo.png" onerror="this.style.display='none'">
        </div>
        <div class="company-name">PT SAMCO FARMA</div>
    </div>

    <div class="title">Pengajuan / Realisasi UC</div>

    <div class="info-row">
        <div><span class="info-label">Nama :</span> <?= htmlspecialchars($req['requester_name']) ?></div>
        <div><span class="info-label">Cabang :</span> <?= htmlspecialchars($req['branch']) ?></div>
        <div><span class="info-label">Bulan :</span> <?= !empty($req['start_date']) ? date('F Y', strtotime($req['start_date'])) : '' ?></div>
    </div>

    <div class="info-row">
        <div><span class="info-label">Periode :</span>
            <?= !empty($req['start_date']) ? date('d M', strtotime($req['start_date'])) : '' ?> 
            s/d 
            <?= !empty($req['end_date']) ? date('d M Y', strtotime($req['end_date'])) : '' ?>
        </div>
    </div>

    <h4>Rincian Tujuan / Outlet</h4>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Hari</th>
                <th>Tanggal</th>
                <th>Tujuan</th>
                <th>Outlet</th>
                <th>Estimasi Sales (Rp)</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $no = 1;
        if (!empty($grouped)):
            foreach ($grouped as $group):
                $rowspan = count($group['outlets']);
                $hari = hariIndo($group['trip_date']);
                $tgl = !empty($group['trip_date']) ? date('d-m-Y', strtotime($group['trip_date'])) : '';
                $first = true;
                foreach ($group['outlets'] as $outlet):
        ?>
            <tr>
                <?php if ($first): ?>
                    <td rowspan="<?= $rowspan ?>"><?= $no++ ?></td>
                    <td rowspan="<?= $rowspan ?>"><?= $hari ?></td>
                    <td rowspan="<?= $rowspan ?>"><?= $tgl ?></td>
                    <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($group['destination']) ?></td>
                <?php endif; ?>
                <td><?= htmlspecialchars($outlet['outlet_name']) ?></td>
                <td class="text-right"><?= number_format($outlet['est_sales'] ?? 0, 0, ',', '.') ?></td>
            </tr>
        <?php 
                $first = false;
                endforeach;
            endforeach; 
        else: ?>
            <tr><td colspan="6" class="text-center small-muted">Belum ada data outlet</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h4>Rincian Biaya</h4>
    <table class="biaya">
        <tr>
            <td>1. Biaya hotel</td>
            <td><?= number_format($req['hotel_per_day'] ?? 0, 0, ',', '.') ?> x <?= intval($req['hotel_nights'] ?? 0) ?> malam</td>
            <td class="text-right">Rp <?= number_format($total_hotel, 0, ',', '.') ?></td>
        </tr>
        <tr>
            <td>2. Biaya makan</td>
            <td><?= number_format($req['meal_per_day'] ?? 0, 0, ',', '.') ?> x <?= intval($req['meal_days'] ?? 0) ?> hari</td>
            <td class="text-right">Rp <?= number_format($total_meal, 0, ',', '.') ?></td>
        </tr>
        <tr>
            <td>3. Biaya BBM/Transport</td>
            <td></td>
            <td class="text-right">Rp <?= number_format($req['fuel_amount'] ?? 0, 0, ',', '.') ?></td>
        </tr>
        <tr>
            <td>4. Lain-lain</td>
            <td></td>
            <td class="text-right">Rp <?= number_format($req['other_amount'] ?? 0, 0, ',', '.') ?></td>
        </tr>
        <tr class="total">
            <td colspan="2" class="text-right">Total</td>
            <td class="text-right">Rp <?= number_format($total, 0, ',', '.') ?></td>
        </tr>
    </table>

    <!-- ===== TANDA TANGAN (2 TEMPLATE SAJA) ===== -->
    <div class="signature-wrapper">
        <table class="signature-table">
            <tr>
                <td>Diajukan,</td>
                <td>Diketahui,</td>
            </tr>
            <tr>
                <td class="ttd-space"></td>
                <td class="ttd-space"></td>
            </tr>
            <tr>
                <td><strong>Fatur Rahman</strong><br>Sales Dept Head</td>
                <td><strong>Mukhammad Adieb</strong><br>HR&GA Dept Head</td>
            </tr>
        </table>
    </div>

    <div class="text-center" style="margin-top:20px;">
        <a class="btn" href="uc_pdf.php?id=<?= $id ?>" target="_blank">Download / Cetak PDF</a>
        <a class="btn btn-secondary" href="uc_create.php">Buat Baru</a>

        <!-- tombol EDIT sesuai permintaan (kembali ke uc_create dengan id untuk prefill/edit) -->
        <a class="btn btn-edit" href="uc_create.php?id=<?= $id ?>">Edit Form</a>
    </div>

</div>
</body>
</html>