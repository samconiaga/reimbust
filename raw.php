<?php
session_start();
if (empty($_SESSION['nik'])) {
    header("Location: login.php");
    exit;
}

if (file_exists(__DIR__.'/config.php')) include_once __DIR__.'/config.php';

$DB_HOST = defined('DB_HOST')?DB_HOST:'127.0.0.1';
$DB_USER = defined('DB_USER')?DB_USER:'root';
$DB_PASS = defined('DB_PASS')?DB_PASS:'';
$DB_NAME = defined('DB_NAME')?DB_NAME:'reimb_db';

$conn = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($conn->connect_error) die("DB error");
$conn->set_charset('utf8mb4');

function db_fetch_all($conn,$sql,$params=[]){
    $stmt=$conn->prepare($sql);
    if($params){
        $types='';
        foreach($params as $p){ $types.=is_int($p)?'i':'s'; }
        $stmt->bind_param($types,...$params);
    }
    $stmt->execute();
    $res=$stmt->get_result();
    $rows=$res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function rupiah($n){
    return number_format((float)$n,0,',','.');
}

/* ================= FILTER ================= */

$start=$_GET['start']??'';
$end=$_GET['end']??'';
$dinas=$_GET['perjalanan']??'';

$where=[];
$params=[];

if($start){
    $where[]="DATE(tanggal)>=?";
    $params[]=$start;
}
if($end){
    $where[]="DATE(tanggal)<=?";
    $params[]=$end;
}
if($dinas){
    $where[]="perjalanan_dinas=?";
    $params[]=$dinas;
}

$where[]="nik=?";
$params[]=$_SESSION['nik'];

$sql="SELECT * FROM submissions WHERE ".implode(' AND ',$where)." ORDER BY submitted_at DESC";
$rows=db_fetch_all($conn,$sql,$params);
$totalData=count($rows);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Raw Data</title>

<style>
body{margin:0;font-family:Arial;background:#f3f4f6}

/* ================= HEADER ================= */
.topbar{
    background:linear-gradient(to right,#8b0000,#b30000);
    color:#fff;
    padding:15px 20px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.top-left{
    display:flex;
    align-items:center;
    gap:15px;
}
.back-btn{
    background:rgba(255,255,255,.15);
    padding:6px 12px;
    border-radius:6px;
    text-decoration:none;
    color:#fff;
    font-size:14px;
}
.title{
    font-size:18px;
    font-weight:bold;
}
.badge{
    background:#fff;
    color:#8b0000;
    padding:3px 10px;
    border-radius:12px;
    font-size:12px;
    font-weight:bold;
}

/* ================= FILTER ================= */
.filter-box{
    background:#fff;
    margin:15px;
    padding:12px 15px;
    border-radius:8px;
    display:flex;
    justify-content:flex-end;
    align-items:center;
    gap:10px;
    box-shadow:0 3px 10px rgba(0,0,0,.05);
}
.filter-box select,
.filter-box input{
    padding:6px;
}
.btn{
    padding:6px 12px;
    border-radius:5px;
    border:none;
    cursor:pointer;
}
.btn-refresh{background:#b30000;color:#fff;}
.btn-reset{background:#ddd;}

/* ================= TABLE ================= */
.table-wrapper{
    margin:15px;
    background:#fff;
    border-radius:8px;
    overflow:auto;
    box-shadow:0 3px 10px rgba(0,0,0,.05);
}
table{
    border-collapse:collapse;
    width:100%;
    min-width:1600px;
}
th{
    background:#b30000;
    color:#fff;
    padding:10px;
    font-size:13px;
    text-align:left;
    position:sticky;
    top:0;
}
td{
    padding:9px;
    font-size:13px;
    border-bottom:1px solid #eee;
}
tr:nth-child(even){background:#fafafa}
.btn-del{
    background:#ff4d4d;
    color:#fff;
    border:none;
    padding:4px 10px;
    border-radius:4px;
    cursor:pointer;
}

/* ================= FOOTER ================= */
.footer{
    background:#fff;
    margin:15px;
    padding:10px 15px;
    border-radius:8px;
    display:flex;
    justify-content:space-between;
    font-size:13px;
    box-shadow:0 3px 10px rgba(0,0,0,.05);
}
</style>
</head>
<body>

<!-- ================= HEADER ================= -->

<div class="topbar">
    <div class="top-left">
        <a href="preview.php" class="back-btn">← Kembali</a>
        <div class="title">Data Reimbursement - <?=htmlspecialchars($_SESSION['name'])?></div>
        <div class="badge">USER</div>
    </div>
</div>

<!-- ================= FILTER ================= -->

<form>
<div class="filter-box">
    Dinas:
    <select name="perjalanan">
        <option value="">Semua Perjalanan</option>
        <option value="Dalam Kota" <?=$dinas=='Dalam Kota'?'selected':''?>>Dalam Kota</option>
        <option value="Luar Kota" <?=$dinas=='Luar Kota'?'selected':''?>>Luar Kota</option>
    </select>

    Periode:
    <input type="date" name="start" value="<?=htmlspecialchars($start)?>">
    s/d
    <input type="date" name="end" value="<?=htmlspecialchars($end)?>">

    <button class="btn btn-refresh">Refresh</button>
    <a href="raw.php" class="btn btn-reset">Reset</a>
</div>
</form>

<!-- ================= TABLE ================= -->

<div class="table-wrapper">
<table>
<thead>
<tr>
<th>TIMESTAMP</th>
<th>TANGGAL</th>
<th>NAMA LENGKAP</th>
<th>PERJALANAN DINAS</th>
<th>TUJUAN</th>
<th>BIAYA HOTEL</th>
<th>BIAYA TOL</th>
<th>PLAT</th>
<th>KM</th>
<th>BIAYA BENSIN</th>
<th>BIAYA MAKAN</th>
<th>ENTERTAIN</th>
<th>AKSI</th>
</tr>
</thead>
<tbody>

<?php if($totalData==0): ?>
<tr><td colspan="13" style="text-align:center;padding:20px;">Tidak ada data</td></tr>
<?php else: ?>
<?php foreach($rows as $r): ?>
<tr>
<td><?=htmlspecialchars($r['submitted_at']??'-')?></td>
<td><?=htmlspecialchars($r['tanggal']??'-')?></td>
<td><?=htmlspecialchars($r['nama']??'-')?></td>
<td><?=htmlspecialchars($r['perjalanan_dinas']??'-')?></td>
<td><?=htmlspecialchars($r['tujuan']??'-')?></td>
<td><?=rupiah($r['biaya_hotel']??0)?></td>
<td><?=rupiah($r['biaya_tol']??0)?></td>
<td><?=htmlspecialchars($r['plat_number']??'-')?></td>
<td><?=htmlspecialchars($r['km']??'-')?></td>
<td><?=rupiah($r['biaya_bensin']??0)?></td>
<td><?=rupiah($r['biaya_makan']??0)?></td>
<td><?=rupiah($r['biaya_entertain']??0)?></td>
<td><button class="btn-del">Del</button></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>

</tbody>
</table>
</div>

<!-- ================= FOOTER ================= -->

<div class="footer">
<div>Total Data: <b><?=$totalData?></b></div>
<div>User: <b><?=htmlspecialchars($_SESSION['name'])?></b></div>
<div><?=date('H:i:s')?></div>
</div>

</body>
</html>