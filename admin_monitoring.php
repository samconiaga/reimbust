<?php
session_start();

/*
  admin_monitoring.php (revisi)
  - Responsive desktop & mobile
  - Logout: tombol merah
  - Table dengan sticky header di desktop, stacked cards di mobile
  - Client-side instant search (tetap mempertahankan server-side filtering untuk tanggal)
*/

// ----------------------
// cek session admin
// ----------------------
if (empty($_SESSION['user_id']) || (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin')) {
    header('Location: login.php');
    exit;
}

// ----------------------
// load DB (db.php / config.php) atau fallback mysqli
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
function is_image_ext($fn){
    $ext = strtolower(pathinfo((string)$fn, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg','jpeg','png','gif','webp','bmp']);
}

/**
 * Convert stored path (absolute or relative) into web-accessible relative URL if possible.
 */
function path_to_url_if_exists($path) {
    $path = trim((string)$path);
    if ($path === '') return null;
    if (preg_match('#^https?://#i', $path)) return $path;

    $norm = str_replace(['\\','/'], DIRECTORY_SEPARATOR, $path);

    if (file_exists($norm)) {
        $project = realpath(__DIR__);
        $real = realpath($norm);
        if ($real !== false && strpos($real, $project) === 0) {
            $rel = substr($real, strlen($project));
            $rel = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $rel), '/');
            return $rel;
        }
        return null;
    }

    $try1 = __DIR__ . DIRECTORY_SEPARATOR . $path;
    if (file_exists($try1)) {
        $real = realpath($try1);
        $rel = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen(realpath(__DIR__)))), '/');
        return $rel;
    }

    $base = basename($path);
    $try2 = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $base;
    if (file_exists($try2)) return 'uploads/' . $base;

    return null;
}

// ----------------------
// kategori & kata kunci
// ----------------------
$categories = [
    'tol'      => 'Tol',
    'bensin'   => 'Bensin / BBM',
    'hotel'    => 'Hotel',
    'makan'    => 'Makan',
    'entertain'=> 'Entertain',
    'parkir'   => 'Parkir',
    'lain'     => 'Lain-lain',
    'plat'     => 'Plat Number'
];

$category_keywords = [
    'tol'      => ['tol'],
    'bensin'   => ['bbm','bensin','fuel','petrol'],
    'hotel'    => ['hotel'],
    'makan'    => ['makan','meal','food','restaurant','lunch','dinner','breakfast'],
    'entertain'=> ['entertain','hiburan','enter'],
    'parkir'   => ['parkir','park'],
    'plat'     => ['plat','plate']
];

// ----------------------
// filter (only date server-side; quick q is client-side for safety)
// ----------------------
$q = trim($_GET['q'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$where = [];
if ($from !== '') {
    $f = $conn->real_escape_string($from);
    // compare against created_at OR tanggal if available
    $where[] = "(COALESCE(tanggal, created_at) >= '$f')";
}
if ($to !== '') {
    $t = $conn->real_escape_string($to);
    $where[] = "(COALESCE(tanggal, created_at) <= '$t')";
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ----------------------
// ambil submissions
// ----------------------
$rows = [];
$error_db = null;
$sql = "SELECT * FROM submissions $where_sql ORDER BY id DESC LIMIT 1000";
$res = $conn->query($sql);
if ($res === false) {
    $error_db = $conn->error;
} else {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
}

// ----------------------
// ambil files (group by submission_id)
// ----------------------
$files_by_submission = [];
if (!empty($rows)) {
    $ids = array_column($rows, 'id');
    $ids_escaped = array_map('intval', $ids);
    $in = implode(',', $ids_escaped);
    $sqlf = "SELECT * FROM files WHERE submission_id IN ($in) ORDER BY uploaded_at ASC";
    $rf = $conn->query($sqlf);
    if ($rf) {
        while ($fr = $rf->fetch_assoc()) {
            $sid = (int)($fr['submission_id'] ?? 0);
            if (!isset($files_by_submission[$sid])) $files_by_submission[$sid] = [];
            $files_by_submission[$sid][] = $fr;
        }
    }
}

// ----------------------
// HTML output
// ---------------------- 
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Monitoring — Reimbursement</title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<style>
:root{
  --accent:#7a0004;
  --accent-strong: #5b0202;
  --danger:#dc2626;
  --muted:#6b7280;
  --card:#fff;
  --bg:#f4f6f8;
  --radius:12px;
  --shadow: 0 8px 30px rgba(0,0,0,0.06);
  --max-width:1400px;
}
*{box-sizing:border-box}
html,body{height:100%}
body{font-family:Inter, "Segoe UI", Arial, Helvetica, sans-serif;background:var(--bg);margin:0;color:#222;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.container{max-width:var(--max-width);margin:18px auto;padding:12px}
.header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
.brand{display:flex;align-items:center;gap:12px}
.logo{width:52px;height:52px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:18px}
.title{margin:0;font-size:18px}
.meta{font-size:13px;color:var(--muted)}
.controls{display:flex;gap:12px;align-items:center}

/* Buttons */
.btn{background:var(--accent);color:#fff;border:none;padding:9px 14px;border-radius:10px;cursor:pointer;font-weight:600}
.btn:hover{filter:brightness(.95)}
.btn-ghost{background:transparent;border:1px solid #e6e6e6;color:#333;padding:8px 12px;border-radius:10px}
.btn-logout{background:var(--danger);color:#fff;border:none;padding:8px 12px;border-radius:10px;cursor:pointer;font-weight:600;display:inline-flex;align-items:center;gap:8px}
.btn-logout:hover{filter:brightness(.95)}
.card{background:var(--card);padding:14px;border-radius:var(--radius);box-shadow:var(--shadow)}
.filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
input[type="search"], input[type="date"], .select, .input-small{padding:8px;border-radius:8px;border:1px solid #e6e6e6;font-size:14px}
.input-small{padding:6px 8px;font-size:13px}
.table-wrap{width:100%;overflow-x:auto;border-radius:10px}
.table{width:100%;border-collapse:collapse;font-size:14px;min-width:1100px}
.table thead th{background:var(--accent);color:#fff;padding:12px 14px;text-align:left;white-space:nowrap;position:sticky;top:0;z-index:4}
.table tbody td{background:#fff;padding:12px;border-bottom:1px solid #eef2f6;vertical-align:top;white-space:nowrap}
.table tbody tr:hover td{background:#fbfbfb}
.col-id{width:72px}
.col-name{width:260px;min-width:200px}
.col-date{width:120px}
.col-target{width:180px;min-width:140px}
.col-cat-val{min-width:150px;max-width:220px;vertical-align:top}
.col-cat-file{min-width:180px;max-width:260px;vertical-align:top}
.col-cost{width:160px;text-align:right}
.file-grid{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;justify-content:flex-start}
.file-item{min-width:86px;max-width:140px;text-align:center}
.file-label{font-size:12px;color:#666;margin-bottom:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.img-thumb{width:120px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #e6e6e6;cursor:pointer;transition:transform .18s ease}
.img-thumb:hover{transform:scale(1.03)}
.value-top{margin-bottom:6px}
.empty-sign{color:#999;font-style:italic}

/* Desktop enhancements */
.header-right{display:flex;gap:12px;align-items:center}
.search-form{display:flex;gap:8px;align-items:center}

/* Mobile: stacked cards */
@media (max-width:900px){
  .table, .table thead, .table tbody, .table th, .table td, .table tr {display:block}
  .table thead{display:none}
  .table tbody tr{margin-bottom:14px;border-radius:10px;overflow:hidden;border:1px solid #eef2f6;background:#fff}
  .table tbody td{display:flex;justify-content:space-between;padding:12px 14px;background:transparent;border:none;border-bottom:1px solid #f1f5f9}
  .table tbody td .label{font-weight:600;color:#333;min-width:40%;font-size:14px}
  .table tbody td .value{flex:1;text-align:right}
  .file-grid{justify-content:flex-end}
  .col-cost{display:block;text-align:left;padding:12px}
  .file-item .img-thumb{width:110px;height:72px}
  .container{padding:10px}
}

/* Small phones: compacting */
@media (max-width:420px){
  .img-thumb{width:88px;height:60px}
  .file-item{min-width:72px}
  .title{font-size:16px}
}

/* Modal */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,0.75);display:none;align-items:center;justify-content:center;z-index:9999;padding:20px}
.modal-backdrop.show{display:flex}
.modal-content{max-width:100%;max-height:100%;border-radius:12px;overflow:auto}
.modal-img{max-width:100%;height:auto;border-radius:8px;display:block;box-shadow:0 8px 40px rgba(0,0,0,0.6)}
.modal-close{position:fixed;right:22px;top:18px;background:#fff;border-radius:50%;width:44px;height:44px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:10000}
.modal-caption{margin-top:8px;color:#fff;text-align:center;font-size:13px}

/* accessibility focus */
a:focus, button:focus, input:focus {outline:3px solid rgba(122,0,4,0.12);outline-offset:2px}
</style>
</head>
<body>
<div class="container">
  <div class="header" role="banner">
    <div class="brand">
      <div class="logo" aria-hidden="true">RB</div>
      <div>
        <h1 class="title">Admin Monitoring — Reimbursement</h1>
        <div class="meta">Login sebagai: <strong><?= e($_SESSION['name'] ?? $_SESSION['username'] ?? 'Administrator') ?></strong></div>
      </div>
    </div>

    <div class="header-right" role="navigation" aria-label="Admin controls">
      <!-- Dropdown untuk switch halaman monitoring -->
      <select class="select" onchange="if(this.value) window.location.href=this.value" aria-label="Pilih monitoring">
        <option value="admin_monitoring.php">Reimbursement</option>
        <option value="admin_monitoring_kasbon.php">UC Kasbon</option>
        <option value="admin_monitoring_parcel.php">Parcel</option>
          <option value="admin_categories.php">Akses Admin</option>
      </select>

      <!-- Search: server-side date filter + client-side quick search -->
      <form method="get" class="search-form" style="margin:0" aria-label="Filter submissions">
        <input type="search" id="globalSearch" name="q" placeholder="Cari nama / tujuan / NIK..." value="<?= e($q) ?>" aria-label="Cari">
        <input type="date" name="from" class="input-small" value="<?= e($from) ?>" aria-label="Dari tanggal">
        <input type="date" name="to" class="input-small" value="<?= e($to) ?>" aria-label="Sampai tanggal">
        <button class="btn" type="submit" title="Filter server-side">Filter</button>
        <a href="admin_monitoring.php" class="btn-ghost" style="text-decoration:none;color:inherit" title="Reset filter">Reset</a>
      </form>

      <!-- Logout form -->
      <form action="logout.php" method="post" style="margin:0">
        <button type="submit" class="btn-logout" aria-label="Logout">Logout</button>
      </form>
    </div>
  </div>

  <div class="card" role="main" aria-live="polite">
    <?php if ($error_db): ?>
      <div style="color:#a94442;background:#fff6f6;padding:10px;border-radius:8px;margin-bottom:12px">Error mengambil data: <?= e($error_db) ?></div>
    <?php endif; ?>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap">
      <div class="meta">Menampilkan <strong><?= count($rows) ?></strong> pengajuan</div>
      <div style="margin-left:auto;font-size:13px;color:var(--muted)">Tip: ketik pada kotak pencarian untuk memfilter cepat (client-side)</div>
    </div>

    <div class="table-wrap" role="region" aria-label="Daftar pengajuan">
      <table class="table" role="table" aria-label="Submissions table">
        <thead>
          <tr>
            <th class="col-id">ID</th>
            <th class="col-name">Nama</th>
            <th class="col-date">Tanggal</th>
            <th class="col-target">Tujuan</th>

            <!-- untuk setiap kategori: 2 header (Value | Bukti X) -->
            <?php foreach ($categories as $k => $label): ?>
              <th class="col-cat-val"><?= e($label) ?></th>
              <th class="col-cat-file"><?= 'Bukti ' . e($label) ?></th>
            <?php endforeach; ?>

            <th class="col-cost">Total Biaya</th>
          </tr>
        </thead>
        <tbody id="submissionsBody">
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="<?= 4 + (count($categories) * 2) + 1 ?>" style="text-align:center;color:#666;padding:24px">Tidak ada data pengajuan.</td>
            </tr>
          <?php else: foreach ($rows as $row):
              $id = (int)($row['id'] ?? 0);
              $nama = $row['nama'] ?? ($row['name'] ?? '');
              $nik = $row['nik'] ?? '';
              $dept = $row['departemen'] ?? $row['cabang'] ?? '';
              $tanggal = $row['tanggal'] ?? ($row['created_at'] ?? $row['timestamp'] ?? '');
              $tujuan = $row['tujuan'] ?? ($row['destination'] ?? '');

              // biaya per kategori (ambil dari submissions)
              $vals = [
                  'tol' => (float)($row['biaya_tol'] ?? 0),
                  'bensin' => (float)($row['biaya_bensin'] ?? 0),
                  'hotel' => (float)($row['biaya_hotel'] ?? 0),
                  'makan' => (float)($row['biaya_makan'] ?? 0),
                  'entertain' => (float)($row['biaya_entertain'] ?? 0),
                  'parkir' => (float)($row['biaya_parkir'] ?? 0),
                  'lain' => (float)($row['total_biaya_lain'] ?? $row['total_all'] ?? 0),
                  'plat' => $row['plat_number'] ?? ($row['plate'] ?? ''),
              ];
              // total (simple sum numeric)
              $totalCalc = 0;
              foreach ($vals as $k=>$v) if (is_numeric($v)) $totalCalc += (float)$v;

              // files for this submission
              $files = $files_by_submission[$id] ?? [];

              // group files into categories using strict mapping first (category column), then keywords
              $files_grouped = [];
              foreach ($files as $f) {
                  $raw_cat = strtolower(trim($f['category'] ?? ''));
                  $path = $f['path'] ?? ($f['filename'] ?? '');
                  $orig = $f['original_name'] ?? basename($path);
                  $assigned = null;

                  // 1) if category exactly matches one of our keys
                  foreach (array_keys($categories) as $ck) {
                      if ($raw_cat === $ck) { $assigned = $ck; break; }
                  }

                  // 2) if not exact, check if raw_cat contains a keyword
                  if (!$assigned && $raw_cat !== '') {
                      foreach ($category_keywords as $ck => $kws) {
                          foreach ($kws as $kw) {
                              if ($kw !== '' && strpos($raw_cat, $kw) !== false) {
                                  $assigned = $ck; break 2;
                              }
                          }
                      }
                  }

                  // 3) fallback: check original_name or path for keywords
                  if (!$assigned) {
                      $low = strtolower($path . ' ' . $orig);
                      foreach ($category_keywords as $ck => $kws) {
                          foreach ($kws as $kw) {
                              if ($kw !== '' && strpos($low, $kw) !== false) {
                                  $assigned = $ck; break 2;
                              }
                          }
                      }
                  }

                  if (!$assigned) $assigned = 'lain';

                  if (!isset($files_grouped[$assigned])) $files_grouped[$assigned] = [];
                  $files_grouped[$assigned][] = [
                      'path' => $path,
                      'orig' => $orig,
                      'mime' => $f['mime'] ?? '',
                      'size_bytes' => $f['size_bytes'] ?? 0,
                      'uploaded_at' => $f['uploaded_at'] ?? null
                  ];
              }
          ?>
          <tr class="row-item" data-nama="<?= e(strtolower($nama)) ?>" data-nik="<?= e($nik) ?>" data-tujuan="<?= e(strtolower($tujuan)) ?>">
            <td class="col-id"><?= e($id) ?></td>
            <td class="col-name"><strong><?= e($nama) ?></strong><div class="meta">NIK: <?= e($nik) ?> • Dept: <?= e($dept) ?></div></td>
            <td class="col-date"><?= e($tanggal) ?></td>
            <td class="col-target"><?= e($tujuan) ?></td>

            <!-- untuk setiap kategori: 2 kolom (value | bukti) -->
            <?php foreach ($categories as $key => $label): ?>
              <td class="col-cat-val" data-label="<?= e($label) ?>">
                <?php
                  $val = $vals[$key] ?? null;
                  if ($key === 'plat') {
                      if ($val) echo '<div class="value-top"><strong>'.e($val).'</strong></div>';
                      else echo '<div class="value-top empty-sign">-</div>';
                  } else {
                      if ($val && is_numeric($val) && floatval($val) != 0) {
                          echo '<div class="value-top"><strong>Rp ' . number_format($val,0,',','.') . '</strong></div>';
                      } else {
                          echo '<div class="value-top empty-sign">-</div>';
                      }
                  }
                ?>
              </td>

              <td class="col-cat-file" data-label="Bukti <?= e($label) ?>">
                <?php
                  echo '<div class="file-grid" aria-hidden="false">';
                  $fg = $files_grouped[$key] ?? [];
                  if (!empty($fg)) {
                      $counter = 1;
                      foreach ($fg as $f) {
                          $path = $f['path'];
                          $orig = $f['orig'];
                          $url = path_to_url_if_exists($path);
                          echo '<div class="file-item">';
                          echo '<div class="file-label" title="'.e($orig).'">Bukti ' . $counter . '</div>';
                          if ($url) {
                              if (is_image_ext($url)) {
                                  echo '<img class="img-thumb" src="'.e($url).'" data-src="'.e($url).'" data-caption="'.e($orig).'" alt="preview" loading="lazy">';
                              } else {
                                  echo '<div class="meta"><a href="'.e($url).'" target="_blank" rel="noopener noreferrer">Lihat File</a></div>';
                              }
                          } else {
                              $base = basename($path);
                              $try = 'uploads/' . $base;
                              if (file_exists(__DIR__ . '/uploads/' . $base)) {
                                  if (is_image_ext($try)) {
                                      echo '<img class="img-thumb" src="'.e($try).'" data-src="'.e($try).'" data-caption="'.e($orig).'" alt="preview" loading="lazy">';
                                  } else {
                                      echo '<div class="meta"><a href="'.e($try).'" target="_blank" rel="noopener noreferrer">Lihat File</a></div>';
                                  }
                              } else {
                                  echo '<div class="meta">' . e($path ?: $orig) . '</div>';
                              }
                          }
                          echo '</div>';
                          $counter++;
                      }
                  } else {
                      echo '<div class="meta empty-sign">-</div>';
                  }
                  echo '</div>';
                ?>
              </td>
            <?php endforeach; ?>

            <td class="col-cost"><strong>Rp <?= number_format($totalCalc,0,',','.') ?></strong></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal backdrop -->
<div id="imgModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-hidden="true">
  <button id="modalClose" class="modal-close" aria-label="Close image">✕</button>
  <div class="modal-content" id="modalContent">
    <img id="modalImage" class="modal-img" src="" alt="">
    <div id="modalCaption" class="modal-caption"></div>
  </div>
</div>

<script>
/* Modal image preview + keyboard close */
(function(){
  const modal = document.getElementById('imgModal');
  const modalImg = document.getElementById('modalImage');
  const modalCaption = document.getElementById('modalCaption');
  const modalClose = document.getElementById('modalClose');

  function openModal(url, caption) {
    modalImg.src = url;
    modalImg.alt = caption || 'Preview image';
    modalCaption.textContent = caption || '';
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    modalClose.focus();
  }

  function closeModal() {
    modal.classList.remove('show');
    modalImg.src = '';
    modalCaption.textContent = '';
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  document.body.addEventListener('click', function(e){
    const t = e.target;
    if (t && t.classList && t.classList.contains('img-thumb')) {
      const url = t.getAttribute('data-src') || t.src;
      const caption = t.getAttribute('data-caption') || t.alt || '';
      e.preventDefault();
      openModal(url, caption);
    }
  }, false);

  modalClose.addEventListener('click', closeModal);
  modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.classList.contains('show')) closeModal(); });
})();

/* Client-side quick search (instant filtering) */
(function(){
  const input = document.getElementById('globalSearch');
  const tbody = document.getElementById('submissionsBody');
  if (!input || !tbody) return;

  function normalize(s){
    return (s || '').toString().trim().toLowerCase();
  }

  input.addEventListener('input', function(){
    const q = normalize(this.value);
    const rows = tbody.querySelectorAll('.row-item');
    rows.forEach(function(r){
      if (!q) {
        r.style.display = '';
        return;
      }
      const nama = normalize(r.getAttribute('data-nama'));
      const nik = normalize(r.getAttribute('data-nik'));
      const tujuan = normalize(r.getAttribute('data-tujuan'));
      if (nama.indexOf(q) !== -1 || nik.indexOf(q) !== -1 || tujuan.indexOf(q) !== -1) {
        r.style.display = '';
      } else {
        r.style.display = 'none';
      }
    });
  });
})();
</script>
</body>
</html>