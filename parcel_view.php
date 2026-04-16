<?php
// parcel_view.php (full) - gabungan draft per tanggal, fallback id/latest/date
session_start();
require __DIR__ . '/db.php'; // pastikan $pdo tersedia (PDO instance)

// akses kontrol
if (empty($_SESSION['user_id']) && empty($_SESSION['nik'])) {
  header('Location: login.php');
  exit;
}

// helper escape
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// helper path/file
$upload_base = 'uploads';
function resolve_relpath($filename, $upload_base='uploads') {
  if (!$filename) return null;
  if (strpos($filename, '://') !== false) return $filename; // external URL
  if (strpos($filename, $upload_base . '/') === 0) return $filename;
  return $upload_base . '/' . ltrim($filename, '/');
}
function file_exists_on_disk($relpath) {
  if (!$relpath) return false;
  $full = __DIR__ . '/' . ltrim($relpath, '/');
  return is_file($full);
}

// redirect helper (simpan pesan singkat ke session)
function redirect_list($msg = '') {
  if ($msg) $_SESSION['flash_msg'] = $msg;
  header('Location: parcel_list.php');
  exit;
}

// ambil param
$user_id = (int)($_SESSION['user_id'] ?? 0);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$dateParam = isset($_GET['date']) ? trim($_GET['date']) : '';

// jika tidak ada id -> fallback: pakai ?date=YYYY-MM-DD jika ada, atau parcel terakhir
if ($id <= 0) {
  if ($dateParam !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $dateParam);
    if ($d && $d->format('Y-m-d') === $dateParam) {
      $stmt = $pdo->prepare("SELECT * FROM parcels WHERE user_id = ? AND DATE(created_at) = ? ORDER BY id LIMIT 1");
      $stmt->execute([$user_id, $dateParam]);
      $p = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($p) $id = (int)$p['id'];
      else redirect_list('Tidak ada pengajuan pada tanggal tersebut.');
    } else {
      redirect_list('Format tanggal tidak valid (gunakan YYYY-MM-DD).');
    }
  } else {
    // ambil parcel terakhir user
    $stmt = $pdo->prepare("SELECT * FROM parcels WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($p) $id = (int)$p['id'];
    else redirect_list('Belum ada pengajuan. Silakan buat pengajuan baru.');
  }
}

// ambil parcel utama
$stmt = $pdo->prepare("SELECT p.*, u.nama AS user_name FROM parcels p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ? LIMIT 1");
$stmt->execute([$id]);
$parcel = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$parcel) {
  redirect_list('Pengajuan tidak ditemukan.');
}

// bangun daftar parcel yang akan ditampilkan (gabungan bila draft di tanggal sama)
$parcels_to_show = [];
$parcels_to_show[] = $parcel;

if (strtolower($parcel['status'] ?? '') === 'draft') {
  $dateOnly = date('Y-m-d', strtotime($parcel['created_at']));
  $stmtp = $pdo->prepare("SELECT * FROM parcels WHERE user_id = ? AND status = 'draft' AND DATE(created_at) = ? ORDER BY id");
  $stmtp->execute([(int)$parcel['user_id'], $dateOnly]);
  $sameDateParcels = $stmtp->fetchAll(PDO::FETCH_ASSOC);

  // unique & sorted
  $map = [];
  foreach ($sameDateParcels as $pp) $map[(int)$pp['id']] = $pp;
  ksort($map);
  $parcels_to_show = array_values($map);
}

// ambil outlets + file untuk setiap parcel
$outlets_grouped = [];
$total = 0.0;
$has_amount = false;

foreach ($parcels_to_show as $p) {
  $pid = (int)$p['id'];
  $stmt = $pdo->prepare("SELECT * FROM parcel_outlets WHERE parcel_id = ? ORDER BY id");
  $stmt->execute([$pid]);
  $outs = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $outs_with_files = [];
  foreach ($outs as $o) {
    // ambil semua file outlet
    $stmtf = $pdo->prepare("SELECT id, filename, original_name, size FROM parcel_files WHERE outlet_id = ? ORDER BY id");
    $stmtf->execute([$o['id']]);
    $files = $stmtf->fetchAll(PDO::FETCH_ASSOC);

    $files_norm = [];
    foreach ($files as $f) {
      $rel = resolve_relpath($f['filename'] ?? '', $upload_base);
      $exists = $rel ? file_exists_on_disk($rel) : false;
      $files_norm[] = [
        'id' => $f['id'] ?? null,
        'original_name' => $f['original_name'] ?? $f['filename'],
        'filename' => $f['filename'] ?? null,
        'relpath' => $rel,
        'exists' => $exists,
        'size' => $f['size'] ?? null
      ];
    }

    if (array_key_exists('amount', $o) && $o['amount'] !== null && $o['amount'] !== '') {
      $total += (float)$o['amount'];
      $has_amount = true;
    }

    $o['files'] = $files_norm;
    $outs_with_files[] = $o;
  }

  $outlets_grouped[] = [
    'parcel' => $p,
    'outlets' => $outs_with_files
  ];
}

// tampilkan halaman
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Rekap Parcel #<?= e($parcel['id']) ?><?= (count($parcels_to_show)>1 ? ' (Gabungan draft per tanggal)' : '') ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family: Arial, Helvetica, sans-serif; color:#111; background:#fff; }
    .wrap { max-width:1100px; margin:14px auto; padding:0 12px; }
    .title-bar { background:#990000; color:#fff; padding:10px 14px; font-weight:700; border-radius:6px 6px 0 0; margin-bottom:8px; }
    table.rekap { width:100%; border-collapse:collapse; border:1px solid #222; }
    table.rekap th, table.rekap td { border:1px solid #222; padding:10px; vertical-align:middle; }
    .lampiran-img { width:140px; height:100px; object-fit:cover; border:1px solid #ccc; display:block; margin:6px auto; }
    .files-row { display:flex; gap:8px; flex-wrap:wrap; justify-content:center; align-items:center; }
    .parcel-badge { font-size:0.8rem; background:#eef; color:#113; padding:4px 8px; border-radius:6px; margin-left:6px; text-decoration:none; }
    .small-muted { color:#666; font-size:0.9rem; }
    .meta { font-size:14px; color:#333; }
    @media print {
      .no-print { display:none; }
      .wrap { max-width:100%; margin:0; }
      table.rekap th, table.rekap td { padding:6px; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <?php
      $titleExtra = count($parcels_to_show) > 1 ? ' (Gabungan draft tanggal: ' . date('Y-m-d', strtotime($parcel['created_at'])) . ')' : '';
    ?>
    <div class="title-bar">Rekap <?= e($parcel['category'] ?? 'Parcel') ?> : <?= e($parcel['user_name'] ?? $parcel['nik']) ?><?= e($titleExtra) ?></div>

    <div class="d-flex justify-content-between align-items-center no-print mb-3">
      <div class="meta">
        <strong>ID utama:</strong> <?= e($parcel['id']) ?> &nbsp; | &nbsp;
        <strong>Tanggal:</strong> <?= e($parcel['created_at']) ?>
        <?php if (count($parcels_to_show) > 1): ?>
          <div class="small-muted mt-1">Gabungan parcel:
            <?php foreach ($parcels_to_show as $pp): ?>
              <?php
                $editLink = ((int)$pp['user_id'] === (int)$_SESSION['user_id'] && strtolower($pp['status'] ?? '') === 'draft')
                          ? ' <a class="parcel-badge" href="parcel_create.php?id=' . e($pp['id']) . '">#' . e($pp['id']) . ' ' . e(date('H:i',strtotime($pp['created_at']))) . '</a>'
                          : '<span class="parcel-badge">#' . e($pp['id']) . ' ' . e(date('H:i',strtotime($pp['created_at']))) . '</span>';
              ?>
              <?= $editLink ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div>
        <a class="btn btn-sm btn-outline-primary" href="parcel_list.php">Semua Pengajuan</a>
        <a class="btn btn-sm btn-secondary" href="parcel_create.php">Buat Pengajuan Baru</a>
        <?php if (strtolower($parcel['status'] ?? '') === 'draft' && (int)$parcel['user_id'] === (int)($_SESSION['user_id'] ?? 0)): ?>
          <a class="btn btn-sm btn-primary" href="parcel_create.php?id=<?= e($parcel['id']) ?>">Edit Draft</a>
        <?php endif; ?>
        <button class="btn btn-sm btn-primary" onclick="window.print()">Print / Save PDF</button>
      </div>
    </div>

    <table class="rekap">
      <thead>
        <tr>
          <th style="width:5%;">No</th>
          <th style="width:45%;">Penerima (Outlet) <br><small class="small-muted">[parcel ID / waktu]</small></th>
          <th style="width:20%;">Nominal Kasbon</th>
          <th style="width:30%;">Lampiran</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $no = 1;
          $anyOutlet = false;
          foreach ($outlets_grouped as $group) {
            $p = $group['parcel'];
            foreach ($group['outlets'] as $o) {
              $anyOutlet = true;
              $penerima = strtoupper($o['outlet_name'] ?? '-');
              $nom = (isset($o['amount']) && $o['amount'] !== null && $o['amount'] !== '') ? $o['amount'] : null;
        ?>
          <tr>
            <td class="text-center"><?= $no ?></td>
            <td>
              <?= e($penerima) ?>
              <?php
                // badge kecil per parcel (dengan link edit jika bisa)
                if ((int)$p['user_id'] === (int)$_SESSION['user_id'] && strtolower($p['status'] ?? '') === 'draft') {
                  echo ' <a class="parcel-badge" href="parcel_create.php?id=' . e($p['id']) . '">#' . e($p['id']) . ' ' . e(date('H:i', strtotime($p['created_at']))) . '</a>';
                } else {
                  echo ' <span class="parcel-badge">#' . e($p['id']) . ' ' . e(date('H:i', strtotime($p['created_at']))) . '</span>';
                }
              ?>
            </td>
            <td class="text-end"><?= $nom !== null ? e('Rp ' . number_format((float)$nom,0,',','.')) : '-' ?></td>
            <td class="text-center">
              <?php if (!empty($o['files'])): ?>
                <div class="files-row">
                  <?php foreach ($o['files'] as $f): ?>
                    <?php if ($f['exists'] && $f['relpath']): ?>
                      <a href="<?= e($f['relpath']) ?>" target="_blank" title="<?= e($f['original_name']) ?>">
                        <img src="<?= e($f['relpath']) ?>" class="lampiran-img" alt="<?= e($f['original_name']) ?>">
                      </a>
                    <?php else: ?>
                      <div class="small text-danger"><?= e($f['original_name'] ?? $f['filename']) ?> (tidak ditemukan)</div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="text-muted small">-</div>
              <?php endif; ?>
            </td>
          </tr>
        <?php
              $no++;
            } // endforeach outlets
          } // endforeach groups

          if (!$anyOutlet) {
            echo '<tr><td colspan="4" class="text-center">Belum ada data outlet untuk pengajuan ini.</td></tr>';
          }
        ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="2" class="text-end">Total</td>
          <td class="text-right"><?= $has_amount ? e('Rp ' . number_format($total,0,',','.')) : '-' ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>

    <?php if (count($parcels_to_show) > 1): ?>
      <div class="mt-3 small-muted">
        Catatan: ini adalah tampilan gabungan untuk semua <strong>draft</strong> pada tanggal <?= e(date('Y-m-d', strtotime($parcel['created_at']))) ?>.
        Untuk mengedit salah satu draft, gunakan link <em>Edit</em> pada badge parcel.
      </div>
    <?php endif; ?>

  </div>
</body>
</html>