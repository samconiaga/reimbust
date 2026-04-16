<?php
session_start();

/*
  admin_monitoring_parcel.php
  - Monitoring Parcel (UI disamakan)
  - Perbaikan: aman bila parcel_files tidak punya kolom 'path'
*/

if (empty($_SESSION['user_id']) || (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin')) {
    header('Location: login.php');
    exit;
}

if (file_exists(__DIR__ . '/db.php')) include_once __DIR__ . '/db.php';
if (!isset($conn) && file_exists(__DIR__ . '/config.php')) include_once __DIR__ . '/config.php';
if (!isset($conn)) {
    $conn = new mysqli('127.0.0.1', 'root', '', 'reimb_db');
    if ($conn->connect_errno) die("Koneksi DB gagal: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function is_image_ext($fn){ $ext = strtolower(pathinfo((string)$fn, PATHINFO_EXTENSION)); return in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg']); }

/**
 * Cari atribut file yang mungkin ada di record parcel_files.
 * Prioritas: filename, file, file_path, filepath, path, original_name
 */
function file_record_to_path($rec) {
    if (!$rec || !is_array($rec)) return null;
    $candidates = ['filename','file','file_path','filepath','path','file_name','original_name','name'];
    foreach ($candidates as $k) {
        if (!empty($rec[$k])) return $rec[$k];
    }
    // jika ada kolom 'url' misalnya
    if (!empty($rec['url'])) return $rec['url'];
    return null;
}

/**
 * Resolve path/url jika file ada di server
 * - Jika sudah URL => return
 * - Jika path relatif dan file ada di project => return relatif
 * - Jika file ada di uploads/ atau public/ => return that relative path
 */
function path_to_url_if_exists($path) {
    $path = trim((string)$path);
    if ($path === '') return null;
    if (preg_match('#^https?://#i', $path)) return $path;

    // normalisasi
    $path2 = str_replace(['\\'], '/', $path);
    $try = __DIR__ . '/' . ltrim($path2, '/');
    if (file_exists($try)) return ltrim($path2, '/');

    $base = basename($path2);
    if (file_exists(__DIR__ . '/uploads/' . $base)) return 'uploads/' . $base;
    if (file_exists(__DIR__ . '/public/' . $base)) return 'public/' . $base;
    if (file_exists($path2)) return $path2;

    return null;
}

// filter tanggal server-side (parcels.created_at)
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$where = [];
if ($from !== '') {
    $f = $conn->real_escape_string($from);
    $where[] = "(COALESCE(created_at, tanggal) >= '$f')";
}
if ($to !== '') {
    $t = $conn->real_escape_string($to);
    $where[] = "(COALESCE(created_at, tanggal) <= '$t')";
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ambil parcels
$rows = [];
$error_db = null;
$sql = "SELECT * FROM parcels $where_sql ORDER BY id DESC LIMIT 1000";
$res = $conn->query($sql);
if ($res === false) {
    $error_db = $conn->error;
} else {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
}

// prefetch parcel_outlets and parcel_files (safe)
$outlets_by_parcel = [];
$files_by_parcel = []; // keyed by parcel_id => array of file records
if (!empty($rows)) {
    $ids = array_column($rows, 'id');
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids);
    if (!empty($ids)) {
        $ids_in = implode(',', $ids);

        // prefetch outlets
        $qo = "SELECT * FROM parcel_outlets WHERE parcel_id IN ($ids_in) ORDER BY id ASC";
        $ro = $conn->query($qo);
        if ($ro) {
            while ($o = $ro->fetch_assoc()) {
                $pid = (int)($o['parcel_id'] ?? 0);
                if (!isset($outlets_by_parcel[$pid])) $outlets_by_parcel[$pid] = [];
                $outlets_by_parcel[$pid][] = $o;
            }
        }

        // prefetch files: select * so we don't request non-existing columns
        $qf = "SELECT pf.* FROM parcel_files pf WHERE pf.outlet_id IN (SELECT id FROM parcel_outlets WHERE parcel_id IN ($ids_in)) ORDER BY pf.id ASC";
        $rf = $conn->query($qf);
        if ($rf) {
            while ($ff = $rf->fetch_assoc()) {
                // try to attach to parcel_id if present in file record
                $pid = (int)($ff['parcel_id'] ?? 0);
                if ($pid) {
                    if (!isset($files_by_parcel[$pid])) $files_by_parcel[$pid] = [];
                    $files_by_parcel[$pid][] = $ff;
                } else {
                    // if parcel_id not present, try to attach by outlet_id -> map outlet to parcel
                    $outlet_id = (int)($ff['outlet_id'] ?? 0);
                    if ($outlet_id) {
                        // find parcel via outlets_by_parcel (cheap map)
                        foreach ($outlets_by_parcel as $p_id => $olist) {
                            foreach ($olist as $o) {
                                if ((int)($o['id'] ?? 0) === $outlet_id) {
                                    if (!isset($files_by_parcel[$p_id])) $files_by_parcel[$p_id] = [];
                                    $files_by_parcel[$p_id][] = $ff;
                                    // break both loops
                                    break 2;
                                }
                            }
                        }
                    }
                }
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
<title>Admin Monitoring — Parcel</title>
<style>
:root{--accent:#7a0004;--muted:#6b7280;--bg:#f4f6f8;--card:#fff;--radius:12px;--shadow:0 8px 30px rgba(0,0,0,0.06);--max-width:1400px}
*{box-sizing:border-box}
body{font-family:Inter, Arial, sans-serif;background:var(--bg);margin:0;color:#222}
.container{max-width:var(--max-width);margin:18px auto;padding:12px}
.header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
.brand{display:flex;align-items:center;gap:12px}
.logo{width:52px;height:52px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700}
.card{background:var(--card);padding:14px;border-radius:var(--radius);box-shadow:var(--shadow)}
select,input[type="date"],input[type="search"]{padding:8px;border-radius:8px;border:1px solid #e6e6e6}
.table-wrap{width:100%;overflow-x:auto;border-radius:10px}
.table{width:100%;border-collapse:collapse;font-size:14px;min-width:900px}
.table thead th{background:var(--accent);color:#fff;padding:12px 14px;text-align:left;position:sticky;top:0;z-index:4}
.table tbody td{background:#fff;padding:12px;border-bottom:1px solid #eef2f6}
.img-thumb{width:120px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #e6e6e6;cursor:pointer}
@media (max-width:900px){
  .table, .table thead, .table tbody, .table th, .table td, .table tr {display:block}
  .table thead{display:none}
  .table tbody tr{margin-bottom:14px;border-radius:10px;overflow:hidden;border:1px solid #eef2f6;background:#fff}
  .table tbody td{display:flex;justify-content:space-between;padding:12px 14px;background:transparent;border:none;border-bottom:1px solid #f1f5f9}
}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,0.75);display:none;align-items:center;justify-content:center;z-index:9999;padding:20px}
.modal-backdrop.show{display:flex}
.modal-img{max-width:100%;height:auto;border-radius:8px;display:block}
.modal-close{position:fixed;right:22px;top:18px;background:#fff;border-radius:50%;width:44px;height:44px;border:none}
.small-muted{color:var(--muted)}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="brand">
      <div class="logo">RB</div>
      <div>
        <h1 class="title">Admin Monitoring — Parcel</h1>
        <div class="meta">Login sebagai: <strong><?= e($_SESSION['name'] ?? $_SESSION['username'] ?? 'Administrator') ?></strong></div>
      </div>
    </div>

    <div style="display:flex;gap:10px;align-items:center">
      <select onchange="if(this.value) window.location.href=this.value" aria-label="Pilih monitoring">
        <option value="admin_monitoring.php">Reimbursement</option>
        <option value="admin_monitoring_kasbon.php">UC Kasbon</option>
        <option value="admin_monitoring_parcel.php" selected>Parcel</option>
            <option value="admin_categories.php">Akses Admin</option>   
      </select>

      <form method="get" style="margin:0">
        <input type="date" name="from" value="<?= e($from) ?>">
        <input type="date" name="to" value="<?= e($to) ?>">
        <button type="submit" style="padding:8px;background:#7a0004;color:#fff;border:none;border-radius:8px">Filter</button>
      </form>

      <form action="logout.php" method="post" style="margin:0">
        <button style="background:#dc2626;color:#fff;padding:8px 10px;border-radius:8px;border:none">Logout</button>
      </form>
    </div>
  </div>

  <div class="card" role="main">
    <?php if ($error_db): ?>
      <div style="color:#a94442;background:#fff6f6;padding:10px;border-radius:8px;margin-bottom:12px">Error mengambil data: <?= e($error_db) ?></div>
    <?php endif; ?>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap">
      <div class="meta">Menampilkan <strong><?= count($rows) ?></strong> parcel</div>
      <div style="margin-left:auto;font-size:13px;color:var(--muted)">Tip: klik thumbnail untuk preview.</div>
    </div>

    <div class="table-wrap">
      <table class="table" role="table">
        <thead>
          <tr>
            <th>No</th>
            <th>ID</th>
            <th>Tanggal</th>
            <th>Kategori</th>
            <th>Total Kasbon</th>
            <th>Lampiran</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody id="bodyList">
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" style="text-align:center;padding:18px;color:#666">Tidak ada parcel.</td></tr>
          <?php else:
            $no = 1;
            foreach ($rows as $r):
              $pid = (int)$r['id'];
              $created = $r['created_at'] ?? $r['tanggal'] ?? '';
              $cat = $r['category'] ?? '-';
              $total = 0;
              if (isset($outlets_by_parcel[$pid])) {
                  foreach ($outlets_by_parcel[$pid] as $o) $total += (float)($o['amount'] ?? $o['nominal'] ?? 0);
              } else {
                  $qr = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM parcel_outlets WHERE parcel_id = " . intval($pid));
                  if ($qr) { $rr = $qr->fetch_assoc(); $total = (float)($rr['total'] ?? 0); }
              }

              // pick a file thumbnail (from prefetched files_by_parcel) or fallback to per-outlet lookup
              $thumb = null;
              $caption = '';
              if (isset($files_by_parcel[$pid]) && !empty($files_by_parcel[$pid])) {
                  $ff = $files_by_parcel[$pid][0];
                  $path = file_record_to_path($ff);
                  $thumb = $path ? path_to_url_if_exists($path) : null;
                  $caption = $ff['original_name'] ?? $ff['name'] ?? $ff['filename'] ?? '';
              } else {
                  // fallback: check first outlet's file with SELECT * (safe)
                  if (!empty($outlets_by_parcel[$pid])) {
                      $oid = (int)($outlets_by_parcel[$pid][0]['id'] ?? 0);
                      if ($oid) {
                          $qf = $conn->query("SELECT * FROM parcel_files WHERE outlet_id = " . intval($oid) . " LIMIT 1");
                          if ($qf && ($frow = $qf->fetch_assoc())) {
                              $path = file_record_to_path($frow);
                              $thumb = $path ? path_to_url_if_exists($path) : null;
                              $caption = $frow['original_name'] ?? $frow['name'] ?? $frow['filename'] ?? '';
                          }
                      }
                  }
              }
          ?>
          <tr data-search="<?= e($r['id'].' '.$created.' '.$cat) ?>">
            <td class="text-center"><?= $no ?></td>
            <td class="text-center"><?= e($pid) ?></td>
            <td><?= e($created) ?></td>
            <td><?= e($cat) ?></td>
            <td style="text-align:right"><?= $total>0 ? 'Rp '.number_format($total,0,',','.') : '-' ?></td>
            <td style="text-align:center">
              <?php if ($thumb): ?>
                <img class="img-thumb" src="<?= e($thumb) ?>" data-src="<?= e($thumb) ?>" data-caption="<?= e($caption) ?>" alt="lampiran">
              <?php else: ?>
                <span style="color:#888">-</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center">
              <a class="btn" href="parcel_view.php?id=<?= e($pid) ?>" style="background:#7a0004;color:#fff;padding:6px 10px;border-radius:6px;text-decoration:none">Lihat</a>
              <a class="btn" href="parcel_delete.php?id=<?= e($pid) ?>" style="background:#dc2626;color:#fff;padding:6px 10px;border-radius:6px;text-decoration:none" onclick="return confirm('Hapus parcel ini?')">Hapus</a>
            </td>
          </tr>
          <?php $no++; endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="imgModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-hidden="true">
  <button id="modalClose" class="modal-close">✕</button>
  <div><img id="modalImage" class="modal-img" src="" alt="preview"></div>
</div>

<script>
(function(){
  const modal = document.getElementById('imgModal');
  const modalImg = document.getElementById('modalImage');
  const modalClose = document.getElementById('modalClose');
  function openModal(url){ modalImg.src = url; modal.classList.add('show'); modal.style.display='flex'; document.body.style.overflow='hidden'; }
  function closeModal(){ modal.classList.remove('show'); modal.style.display='none'; modalImg.src=''; document.body.style.overflow=''; }
  document.body.addEventListener('click', function(e){
    const t = e.target;
    if (t && t.classList && t.classList.contains('img-thumb')) {
      const url = t.getAttribute('data-src') || t.src;
      openModal(url);
    }
  });
  modalClose.addEventListener('click', closeModal);
  modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });
})();
</script>
</body>
</html>