<?php
session_start();
/*
 admin_monitoring_kasbon.php
 - Menampilkan NIK dari tabel users
 - Menampilkan destination, outlet_name, dan est_sales (dari uc_outlets)
 - Menghilangkan kolom lampiran/tanda tangan
 - Aman terhadap perubahan nama kolom (pakai fallback)
 - Tampilan rapi & responsive
*/

// ----------------------
// cek sesi admin
// ----------------------
if (empty($_SESSION['user_id']) || (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin')) {
    header('Location: login.php');
    exit;
}

// ----------------------
// koneksi DB (sesuaikan bila perlu)
// ----------------------
if (file_exists(__DIR__ . '/db.php')) include_once __DIR__ . '/db.php';
if (!isset($conn) && file_exists(__DIR__ . '/config.php')) include_once __DIR__ . '/config.php';
if (!isset($conn)) {
    $conn = new mysqli('127.0.0.1', 'root', '', 'reimb_db');
    if ($conn->connect_errno) die("Koneksi DB gagal: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ----------------------
// helpers
// ----------------------
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function rupiah($n){
    $n = (float)$n;
    if ($n == 0) return '0';
    return 'Rp ' . number_format($n,0,',','.');
}

// ----------------------
// filter tanggal (server-side)
// ----------------------
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$where = [];
if ($from !== '') {
    $f = $conn->real_escape_string($from);
    $where[] = "COALESCE(uc_requests.created_at, uc_requests.start_date, uc_requests.tanggal) >= '$f'";
}
if ($to !== '') {
    $t = $conn->real_escape_string($to);
    $where[] = "COALESCE(uc_requests.created_at, uc_requests.start_date, uc_requests.tanggal) <= '$t'";
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ----------------------
// ambil data requests + gabungkan data dari uc_outlets + join users untuk NIK
// NOTE: hubungkan uc_outlets.request_id => uc_requests.id
// NOTE: asumsikan users.id = uc_requests.user_id; jika berbeda, ubah ON clause
// ----------------------
$rows = [];
$error_db = null;

$sql = "
  SELECT uc_requests.*,
         users.nik AS nik,
         COALESCE(o.destinations, '') AS destinations,
         COALESCE(o.outlet_names, '') AS outlet_names,
         COALESCE(o.est_sales_sum, 0) AS est_sales_sum,
         COALESCE(o.est_sales_list, '') AS est_sales_list
  FROM uc_requests
  LEFT JOIN users
    ON users.id = uc_requests.user_id
  LEFT JOIN (
    SELECT request_id,
           GROUP_CONCAT(DISTINCT destination SEPARATOR ' | ') AS destinations,
           GROUP_CONCAT(DISTINCT outlet_name SEPARATOR ' | ') AS outlet_names,
           -- list est_sales (raw values) jika diperlukan
           GROUP_CONCAT(DISTINCT est_sales SEPARATOR ' | ') AS est_sales_list,
           -- total est_sales (numeric) — gunakan est_sales+0 untuk paksa numeric
           SUM(est_sales+0) AS est_sales_sum
    FROM uc_outlets
    GROUP BY request_id
  ) AS o
    ON o.request_id = uc_requests.id
  $where_sql
  ORDER BY uc_requests.id DESC
  LIMIT 1000
";

$res = $conn->query($sql);
if ($res === false) {
    $error_db = $conn->error;
} else {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Monitoring — Kasbon</title>
<style>
:root{
  --accent:#7a0004; --muted:#6b7280; --bg:#f4f6f8; --card:#fff;
  --radius:10px; --shadow:0 8px 30px rgba(0,0,0,0.06); --max-width:1300px;
  --table-min:1100px;
}
*{box-sizing:border-box}
body{font-family:Inter, Arial, sans-serif;background:var(--bg);margin:0;color:#222}
.container{max-width:var(--max-width);margin:18px auto;padding:12px}
.header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.brand{display:flex;align-items:center;gap:12px}
.logo{width:48px;height:48px;border-radius:8px;background:var(--accent);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700}
.title{margin:0;font-size:18px}
.meta{font-size:13px;color:var(--muted)}
.card{background:var(--card);padding:14px;border-radius:var(--radius);box-shadow:var(--shadow)}
.controls{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
select,input[type="date"],input[type="search"]{padding:8px;border-radius:8px;border:1px solid #e6e6e6}
.table-wrap{width:100%;overflow-x:auto;border-radius:8px;margin-top:8px}
.table{width:100%;border-collapse:collapse;font-size:13px;min-width:var(--table-min)}
.table thead th{background:var(--accent);color:#fff;padding:10px 12px;text-align:left;position:sticky;top:0;z-index:4;white-space:nowrap}
.table tbody td{background:#fff;padding:8px 12px;border-bottom:1px solid #eef2f6;vertical-align:middle;white-space:nowrap}
.small-muted{color:var(--muted);font-size:13px}
@media (max-width:900px){ .table{min-width:900px} }
button.btn{background:var(--accent);color:#fff;border:none;padding:8px 12px;border-radius:8px;cursor:pointer}
button.btn-ghost{background:transparent;border:1px solid #e6e6e6;padding:8px 10px;border-radius:8px}
.count-badge{background:#eef3f5;padding:6px 10px;border-radius:999px;color:#333;font-weight:600}
.row-right{margin-left:auto}
.small-note{font-size:12px;color:var(--muted)}
.col-narrow{max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.col-medium{max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bad-amount{color:#b91c1c;font-weight:600}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="brand">
      <div class="logo">RB</div>
      <div>
        <h1 class="title">Monitoring Pengajuan Kasbon</h1>
        <div class="meta">Login sebagai: <strong><?= e($_SESSION['name'] ?? $_SESSION['username'] ?? 'Administrator') ?></strong></div>
      </div>
    </div>

    <div class="controls" aria-hidden="false">
      <select onchange="if(this.value) window.location.href=this.value" aria-label="Pilih monitoring">
        <option value="admin_monitoring.php">Reimbursement</option>
        <option value="admin_monitoring_kasbon.php" selected>UC Kasbon</option>
        <option value="admin_monitoring_parcel.php">Parcel</option>
            <option value="admin_categories.php">Akses Admin</option>
      </select>

      <form method="get" style="margin:0;display:flex;gap:8px;align-items:center">
        <input type="date" name="from" value="<?= e($from) ?>" aria-label="Dari tanggal">
        <input type="date" name="to" value="<?= e($to) ?>" aria-label="Sampai tanggal">
        <button type="submit" class="btn">Filter</button>
        <a class="btn-ghost" href="admin_monitoring_kasbon.php" style="text-decoration:none;color:inherit;padding:8px 10px;border-radius:8px">Reset</a>
      </form>

      <form action="logout.php" method="post" style="margin:0">
        <button type="submit" style="background:#dc2626;color:#fff;padding:8px 10px;border-radius:8px;border:none">Logout</button>
      </form>
    </div>
  </div>

  <div class="card" role="main">
    <?php if ($error_db): ?>
      <div style="color:#a94442;background:#fff6f6;padding:10px;border-radius:8px;margin-bottom:12px">Error mengambil data: <?= e($error_db) ?></div>
    <?php endif; ?>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap">
      <div class="count-badge">Menampilkan <strong><?= count($rows) ?></strong> pengajuan</div>
      <div class="row-right small-note">Tampilan: <strong>Kasbon</strong></div>
    </div>

    <div class="table-wrap" role="region" aria-label="Daftar Pengajuan Kasbon">
      <table class="table" role="table" aria-label="Daftar Pengajuan Kasbon">
        <thead>
          <tr>
            <th class="col-narrow">ID</th>
            <th class="col-narrow">NIK</th>
            <th>Nama Pemohon</th>
            <th class="col-medium">Cabang</th>
            <th class="col-narrow">Tgl Mulai</th>
            <th class="col-narrow">Tgl Selesai</th>
            <th class="col-narrow">Bulan</th>
            <th class="col-narrow">Hotel / Hari</th>
            <th class="col-narrow">Malam</th>
            <th class="col-narrow">Makan / Hari</th>
            <th class="col-narrow">Hari Makan</th>
            <th class="col-narrow">Bensin (Rp)</th>
            <th class="col-narrow">Lain-lain (Rp)</th>
            <th class="col-narrow">Total</th>
            <th class="col-narrow">Est. Sales (Rp)</th>
            <th>Tujuan (Destination)</th>
            <th>Nama Outlet</th>
            <th class="col-narrow">Dibuat Pada</th>
          </tr>
        </thead>
        <tbody id="bodyList">
          <?php if (empty($rows)): ?>
            <tr><td colspan="19" style="text-align:center;padding:18px;color:#666">Tidak ada data pengajuan.</td></tr>
          <?php else: foreach ($rows as $r):
              // fallback jika nama kolom beda
              $id = (int)($r['id'] ?? 0);
              $nik = isset($r['nik']) ? e($r['nik']) : (isset($r['user_id']) ? e($r['user_id']) : '-');
              $requester_name = isset($r['requester_name']) ? e($r['requester_name']) : (isset($r['nama']) ? e($r['nama']) : '-');
              $branch = isset($r['branch']) ? e($r['branch']) : (isset($r['cabang']) ? e($r['cabang']) : '');
              $start_date = isset($r['start_date']) ? e($r['start_date']) : (isset($r['tanggal']) ? e($r['tanggal']) : '');
              $end_date = isset($r['end_date']) ? e($r['end_date']) : '';
              $month_label = isset($r['month_label']) ? e($r['month_label']) : '';
              $title = isset($r['title']) ? e($r['title']) : (isset($r['tujuan']) ? e($r['tujuan']) : '');
              $hotel_per_day = isset($r['hotel_per_day']) ? (float)$r['hotel_per_day'] : 0;
              $hotel_nights = isset($r['hotel_nights']) ? (int)$r['hotel_nights'] : 0;
              $meal_per_day = isset($r['meal_per_day']) ? (float)$r['meal_per_day'] : 0;
              $meal_days = isset($r['meal_days']) ? (int)$r['meal_days'] : 0;
              $fuel_amount = isset($r['fuel_amount']) ? (float)$r['fuel_amount'] : 0;
              $other_amount = isset($r['other_amount']) ? (float)$r['other_amount'] : 0;
              $total_amount = isset($r['total_amount']) ? (float)$r['total_amount'] : null;
              $created_at = isset($r['created_at']) ? e($r['created_at']) : (isset($r['created']) ? e($r['created']) : '');

              if ($total_amount === null || $total_amount == 0.0) {
                  $computed = ($hotel_per_day * $hotel_nights) + ($meal_per_day * $meal_days) + $fuel_amount + $other_amount;
                  $total_amount = $computed;
              }

              // data dari uc_outlets (GROUP_CONCAT & SUM)
              $destinations = isset($r['destinations']) ? e($r['destinations']) : '';
              $outlet_names = isset($r['outlet_names']) ? e($r['outlet_names']) : '';
              $est_sales_sum = isset($r['est_sales_sum']) ? (float)$r['est_sales_sum'] : 0;
              $est_sales_list = isset($r['est_sales_list']) ? e($r['est_sales_list']) : '';
          ?>
          <tr>
            <td class="col-narrow"><?= e($id) ?></td>
            <td class="col-narrow"><?= $nik ?></td>
            <td><?= $requester_name ?></td>
            <td class="col-medium"><?= $branch ?></td>
            <td class="col-narrow"><?= $start_date ?></td>
            <td class="col-narrow"><?= $end_date ?></td>
            <td class="col-narrow"><?= $month_label ?></td>
            <td style="text-align:right"><?= $hotel_per_day>0 ? number_format($hotel_per_day,2,',','.') : '0' ?></td>
            <td style="text-align:right"><?= $hotel_nights ?></td>
            <td style="text-align:right"><?= $meal_per_day>0 ? number_format($meal_per_day,2,',','.') : '0' ?></td>
            <td style="text-align:right"><?= $meal_days ?></td>
            <td style="text-align:right"><?= $fuel_amount>0 ? number_format($fuel_amount,0,',','.') : '0' ?></td>
            <td style="text-align:right"><?= $other_amount>0 ? number_format($other_amount,0,',','.') : '0' ?></td>
            <td style="text-align:right"><strong><?= $total_amount>0 ? rupiah($total_amount) : '-' ?></strong></td>
            <td style="text-align:right"><?= $est_sales_sum>0 ? rupiah($est_sales_sum) : '<span style=\"color:#888\">-</span>' ?></td>
            <td><?= $destinations ?: '<span style="color:#888">-</span>' ?></td>
            <td><?= $outlet_names ?: '<span style="color:#888">-</span>' ?></td>
            <td class="col-narrow"><?= $created_at ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div style="text-align:center;margin-top:10px;" class="small-muted">Perlu kolom lain? sebutkan nama kolomnya — saya tambahkan.</div>
</div>
</body>
</html>