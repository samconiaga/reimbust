<?php
// parcel_list.php
session_start();
require __DIR__ . '/db.php'; // harus menyediakan $pdo (PDO instance)

// pastikan login
if (empty($_SESSION['user_id']) && empty($_SESSION['nik'])) {
  header('Location: login.php');
  exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = strtolower((string)($_SESSION['role'] ?? 'user'));

// CSRF token
if (empty($_SESSION['parcel_csrf_token'])) $_SESSION['parcel_csrf_token'] = bin2hex(random_bytes(16));
$token = $_SESSION['parcel_csrf_token'];

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// upload helper
$upload_base = 'uploads';
function resolve_relpath($filename, $upload_base='uploads') {
  if (!$filename) return null;
  if (strpos($filename, '://') !== false) return $filename;
  if (strpos($filename, $upload_base . '/') === 0) return $filename;
  return $upload_base . '/' . ltrim($filename, '/');
}
function file_exists_on_disk($relpath) {
  if (!$relpath) return false;
  $full = __DIR__ . '/' . ltrim($relpath, '/');
  return is_file($full);
}

// ---- prepare users list for admin filter ----
$users = [];
if ($user_role === 'admin') {
    $q = $pdo->query("SELECT id, nik, nama FROM users ORDER BY nama ASC");
    if ($q) $users = $q->fetchAll(PDO::FETCH_ASSOC);
}

// Get optional filter_user from GET (admin dropdown); sanitize to int
$filter_user = 0;
if ($user_role === 'admin' && isset($_GET['filter_user'])) {
    $filter_user = (int)$_GET['filter_user'];
}

// -----------------------------
// Handle admin AJAX update (JSON response)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_total') {
  header('Content-Type: application/json; charset=utf-8');
  $resp = ['ok' => false, 'msg' => 'Unknown error'];

  // check token & role
  $tok = $_POST['token'] ?? '';
  if (!$tok || !hash_equals($_SESSION['parcel_csrf_token'] ?? '', $tok)) {
    http_response_code(400);
    $resp['msg'] = 'Token invalid.';
    echo json_encode($resp); exit;
  }
  if ($user_role !== 'admin') {
    http_response_code(403);
    $resp['msg'] = 'Hanya admin yang bisa mengubah total.';
    echo json_encode($resp); exit;
  }

  $parcel_id = (int)($_POST['parcel_id'] ?? 0);
  $new_total_raw = trim((string)($_POST['new_total'] ?? ''));
  $clean = preg_replace('/[^\d\.,-]/', '', $new_total_raw);
  $clean = str_replace(',', '.', $clean);
  $new_total = (float)$clean;

  if ($parcel_id <= 0) {
    http_response_code(400);
    $resp['msg'] = 'Parcel ID invalid.';
    echo json_encode($resp); exit;
  }

  try {
    // Ensure column admin_total exists (safe check via information_schema)
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parcels' AND COLUMN_NAME = 'admin_total'");
    $stmtCheck->execute();
    $c = (int)$stmtCheck->fetchColumn();
    if ($c === 0) {
      // add column
      $pdo->exec("ALTER TABLE parcels ADD COLUMN admin_total DECIMAL(14,2) NULL DEFAULT NULL");
    }

    // update admin_total on parcels
    $stmt = $pdo->prepare("UPDATE parcels SET admin_total = :val, updated_at = NOW() WHERE id = :id LIMIT 1");
    $stmt->execute([':val' => $new_total, ':id' => $parcel_id]);

    $resp['ok'] = true;
    $resp['msg'] = 'Total berhasil diperbarui.';
    $resp['new_total'] = number_format($new_total,0,',','.');
    echo json_encode($resp); exit;
  } catch (Exception $ex) {
    http_response_code(500);
    $resp['msg'] = 'Server error: ' . $ex->getMessage();
    echo json_encode($resp); exit;
  }
}

// -----------------------------
// Fetch parcel rows for listing
// If admin -> show all parcels or filter by selected user; otherwise show only user parcels
// -----------------------------
if ($user_role === 'admin') {
    if ($filter_user > 0) {
        $stmt = $pdo->prepare("SELECT p.*, u.nama AS user_name, u.nik AS user_nik FROM parcels p LEFT JOIN users u ON p.user_id = u.id WHERE p.user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$filter_user]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("SELECT p.*, u.nama AS user_name, u.nik AS user_nik FROM parcels p LEFT JOIN users u ON p.user_id = u.id ORDER BY created_at DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    $stmt = $pdo->prepare("SELECT p.*, u.nama AS user_name, u.nik AS user_nik FROM parcels p LEFT JOIN users u ON p.user_id = u.id WHERE p.user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// prepared statements for parcel details (reused)
$ps_sum = $pdo->prepare("
  SELECT COALESCE(SUM(amount),0) AS total,
         SUM(CASE WHEN amount IS NOT NULL THEN 1 ELSE 0 END) AS cnt
  FROM parcel_outlets
  WHERE parcel_id = ?
");
$ps_file = $pdo->prepare("
  SELECT pf.filename, pf.original_name 
  FROM parcel_files pf
  WHERE pf.outlet_id IN (SELECT id FROM parcel_outlets WHERE parcel_id = ?)
  ORDER BY pf.id
  LIMIT 1
");
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Daftar Parcel - Rekap</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root { --accent: #990000; }
    body { font-family: Inter, system-ui, Arial, Helvetica, sans-serif; background:#f5f6f8; color:#111; }
    .wrap { max-width:1200px; margin:24px auto; background:#fff; padding:18px; border-radius:10px; box-shadow:0 6px 24px rgba(0,0,0,.06); }
    .title-bar { background:var(--accent); color:#fff; padding:12px 16px; font-weight:700; border-radius:6px; margin-bottom:12px; display:flex; align-items:center; gap:12px; }
    .rekap { width:100%; border-collapse:collapse; border:1px solid #e5e7eb; font-size:14px; }
    .rekap th, .rekap td { border:1px solid #eef2f6; padding:10px; vertical-align:middle; }
    .rekap th { background:#fbfbfd; text-align:left; font-weight:600; color:#333; }
    .thumb { max-width:120px; max-height:80px; object-fit:cover; display:block; margin:0 auto; border:1px solid #ececec; background:#fff; padding:4px; border-radius:6px; }
    .status-badge { font-size:0.82rem; padding:.25rem .45rem; border-radius:6px; display:inline-block; }
    .status-draft { background:#fff3cd; color:#856404; border:1px solid #ffeeba; }
    .status-submitted { background:#d1e7dd; color:#0f5132; border:1px solid #badbcc; }
    .no-data { padding:18px; background:#fff; border:1px solid #e9ecef; border-radius:8px; text-align:center; }
    .small-muted { font-size:13px; color:#6b7280; }
    .actions .btn { margin-right:6px; }
    @media (max-width:900px){
      .wrap{ padding:12px; margin:10px; }
      .rekap th, .rekap td { font-size:13px; padding:8px; }
      .thumb{ max-width:90px; max-height:64px; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="title-bar">
      <div style="font-size:1.05rem">Daftar Parcel</div>
      <div class="ms-auto small-muted">User: <?= e($_SESSION['nik'] ?? $user_id) ?> &nbsp;|&nbsp; Role: <?= e($user_role) ?></div>
    </div>

    <div class="mb-3 d-flex gap-2 align-items-center">
      <a class="btn btn-outline-primary btn-sm" href="parcel_create.php">Buat Pengajuan Baru</a>
      <button class="btn btn-primary btn-sm" onclick="window.print()">Print / Save PDF</button>

      <?php if ($user_role === 'admin'): ?>
        <!-- Admin filter: dropdown of users -->
        <div class="ms-auto d-flex align-items-center" style="gap:8px;">
          <label class="small-muted mb-0">Filter user:</label>
          <select id="filterUser" class="form-select form-select-sm" style="width:220px;">
            <option value="0">-- Semua user --</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= e($u['id']) ?>" <?= $filter_user === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['nama'] . ' — ' . $u['nik']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php else: ?>
      <?php endif; ?>
    </div>

    <div class="mb-3">
      <input id="q" class="form-control form-control-sm" placeholder="Cari ID, kategori, tanggal, user, atau nama file..." style="max-width:720px;">
    </div>

    <?php if (empty($rows)): ?>
      <div class="no-data">Belum ada parcel. Klik "Buat Pengajuan Baru" untuk menambahkan.</div>
    <?php else: ?>
      <div class="table-responsive">
      <table class="rekap table-sm">
        <thead>
          <tr>
            <th style="width:4%;">No</th>
            <th style="width:6%;">ID</th>
            <th style="width:14%;">Tanggal</th>
            <th style="width:14%;">Kategori</th>
            <th style="width:12%;">User</th>
            <th style="width:12%;">Total Kasbon</th>
            <th style="width:14%;">Lampiran</th>
            <th style="width:8%;">Status</th>
            <th style="width:16%;" class="no-print">Aksi</th>
          </tr>
        </thead>
        <tbody id="table-body">
        <?php $no = 1; foreach ($rows as $r):
          // total per parcel + cnt (jumlah outlet yang punya amount not null)
          $ps_sum->execute([$r['id']]);
          $sumRow = $ps_sum->fetch(PDO::FETCH_ASSOC);
          $calc_total = $sumRow ? (float)$sumRow['total'] : 0.0;
          $cnt = $sumRow ? (int)($sumRow['cnt'] ?? 0) : 0;

          // admin override (tampilkan walau cnt==0)
          $display_total = $calc_total;
          if (array_key_exists('admin_total', $r) && $r['admin_total'] !== null && $r['admin_total'] !== '') {
            $display_total = (float)$r['admin_total'];
          }

          // ambil satu file pertama (jika ada)
          $ps_file->execute([$r['id']]);
          $file = $ps_file->fetch(PDO::FETCH_ASSOC);
          $thumb_rel = $file && !empty($file['filename']) ? resolve_relpath($file['filename'], $upload_base) : null;
          $thumb_exists = $thumb_rel ? file_exists_on_disk($thumb_rel) : false;

          // data-search includes user name & nik for search box
          $data_search = trim($r['id'].' '.$r['created_at'].' '.$r['category'].' '.($file['original_name'] ?? '').' '.($r['user_name'] ?? $r['user_nik'] ?? ''));
        ?>
          <tr data-search="<?= e(strtolower($data_search)) ?>" data-user="<?= e($r['user_id'] ?? '') ?>">
            <td class="text-center"><?= $no ?></td>
            <td class="text-center"><?= e($r['id']) ?></td>
            <td><?= e(date('Y-m-d H:i', strtotime($r['created_at']))) ?></td>
            <td><?= e($r['category']) ?></td>
            <td><?= e($r['user_name'] ?? $r['user_nik'] ?? '-') ?></td>
            <td class="text-end">
              <?php
                // tampilkan admin_total bila ada; jika tidak ada dan cnt==0, tampilkan '-'
                if ((isset($r['admin_total']) && $r['admin_total'] !== null && $r['admin_total'] !== '') || $cnt > 0) {
                  echo '<span class="fw-semibold">' . e('Rp ' . number_format($display_total,0,',','.')) . '</span>';
                } else {
                  echo '<span class="small-muted">-</span>';
                }
              ?>
            </td>
            <td class="text-center">
              <?php if ($thumb_rel && $thumb_exists): ?>
                <a href="parcel_view.php?id=<?= e($r['id']) ?>" title="<?= e($file['original_name'] ?? '') ?>">
                  <img src="<?= e($thumb_rel) ?>" class="thumb" alt="<?= e($file['original_name'] ?? '') ?>">
                </a>
              <?php elseif ($thumb_rel): ?>
                <div class="small text-danger">File tidak ditemukan</div>
              <?php else: ?>
                <div class="small text-muted">-</div>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?php $status = $r['status'] ?? 'submitted'; ?>
              <?php if (strtolower($status) === 'draft'): ?>
                <span class="status-badge status-draft">Draft</span>
              <?php else: ?>
                <span class="status-badge status-submitted"><?= e(ucfirst($status)) ?></span>
              <?php endif; ?>
            </td>
            <td class="actions text-center no-print">
              <!-- Lihat: semua bisa lihat -->
              <a class="btn btn-sm btn-outline-secondary" href="parcel_view.php?id=<?= e($r['id']) ?>">Lihat</a>

              <?php
                // siapa boleh Edit?
                $canEdit = false;
                if ($user_role === 'admin') $canEdit = true; // admin boleh edit semua
                else {
                  // pemilik boleh edit
                  if ((int)$r['user_id'] === (int)$user_id) {
                    $canEdit = true;
                  }
                }
              ?>
              <?php if ($canEdit): ?>
                <!-- NOTE: parcel_create.php harus diubah agar menerima admin (mis. cek $_SESSION['role']=='admin' atau param as_admin=1)
                     agar admin tidak mendapatkan "Anda tidak berhak mengedit pengajuan ini." -->
                <a class="btn btn-sm btn-primary" href="parcel_create.php?id=<?= e($r['id']) ?>&as_admin=<?= $user_role === 'admin' ? '1' : '0' ?>">Edit</a>
              <?php endif; ?>

              <?php if ($user_role === 'admin'): ?>
                <!-- admin edit total -->
                <button class="btn btn-sm btn-warning" onclick="editTotal(<?= e($r['id']) ?>, '<?= e(number_format($display_total,0,',','.')) ?>')">Edit Total</button>
              <?php endif; ?>

              <?php
                // siapa boleh Hapus?
                $canDelete = false;
                if ($user_role === 'admin') $canDelete = true;
                else if ((int)$r['user_id'] === (int)$user_id) $canDelete = true;
              ?>
              <?php if ($canDelete): ?>
                <a class="btn btn-sm btn-danger" href="parcel_delete.php?id=<?= e($r['id']) ?>" onclick="return confirm('Hapus parcel ini?')">Hapus</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php $no++; endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>

  </div>

<script>
  // search filter (works with server-side user filter)
  (function(){
    const q = document.getElementById('q');
    const tbody = document.getElementById('table-body');
    if (!q || !tbody) return;
    q.addEventListener('input', function(){
      const v = this.value.trim().toLowerCase();
      const rows = tbody.querySelectorAll('tr');
      rows.forEach(r => {
        const s = (r.getAttribute('data-search') || '').toLowerCase();
        r.style.display = s.indexOf(v) !== -1 ? '' : 'none';
      });
    });
  })();

  // admin user filter: reload page with filter_user param
  (function(){
    const sel = document.getElementById('filterUser');
    if (!sel) return;
    sel.addEventListener('change', function(){
      const v = this.value || 0;
      // preserve q (search box) in query string if present
      const q = document.getElementById('q') ? document.getElementById('q').value.trim() : '';
      const params = new URLSearchParams(window.location.search);
      if (v && parseInt(v) > 0) params.set('filter_user', v);
      else params.delete('filter_user');
      if (q) params.set('q', q);
      else params.delete('q');
      // reload with params
      window.location = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
    });

    // if page loaded with q param, populate search box
    (function populateFromQS(){
      const params = new URLSearchParams(window.location.search);
      const qv = params.get('q') || '';
      if (qv && document.getElementById('q')) document.getElementById('q').value = qv;
    })();
  })();

  // Edit total (admin)
  async function editTotal(parcelId, currentFormatted) {
    const cleaned = (currentFormatted||'').replace(/\./g,'').replace(/[^0-9]/g,'');
    const msg = prompt('Masukkan total baru (angka saja). Contoh: 250000', cleaned);
    if (msg === null) return;
    let newTotal = msg.toString().trim();
    if (!newTotal) { alert('Nilai tidak boleh kosong.'); return; }
    if (!/^[0-9\.\,]+$/.test(newTotal)) { alert('Format angka tidak valid.'); return; }

    try {
      const fd = new FormData();
      fd.append('action', 'update_total');
      fd.append('token', '<?= e($token) ?>');
      fd.append('parcel_id', parcelId);
      fd.append('new_total', newTotal);

      const res = await fetch(location.href, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      const j = await res.json();
      if (j.ok) {
        alert(j.msg || 'OK');
        location.reload();
      } else {
        alert(j.msg || 'Gagal memperbarui total.');
      }
    } catch (err) {
      console.error(err);
      alert('Terjadi kesalahan saat menghubungi server.');
    }
  }
</script>

</body>
</html>