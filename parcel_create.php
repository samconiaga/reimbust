<?php
// parcel_create.php (dengan sidebar desktop + off-canvas mobile)
session_start();
require __DIR__ . '/db.php'; // harus menghasilkan $pdo (PDO instance)

// pastikan user login
if (empty($_SESSION['user_id']) && empty($_SESSION['nik'])) {
  header('Location: login.php');
  exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$nik     = $_SESSION['nik'] ?? null;
$display_nik = $nik;
$me_name = $_SESSION['name'] ?? $_SESSION['nama'] ?? $_SESSION['user_name'] ?? '';
$departemen = $_SESSION['departemen'] ?? 'Sales';
$user_role = strtolower((string)($_SESSION['role'] ?? 'user'));

// CSRF token
if (empty($_SESSION['parcel_token'])) {
  $_SESSION['parcel_token'] = bin2hex(random_bytes(16));
}
$token = $_SESSION['parcel_token'];

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ====== Mode edit (jika ?id=...) ======
$editing = false;
$edit_parcel = null;
$edit_outlets = [];
$owner_display_name = $me_name; // nama pemilik pengajuan (untuk header)

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
  // ambil parcel (tanpa membatasi user di query) lalu cek izin
  $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = ? LIMIT 1");
  $stmt->execute([$id]);
  $p = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($p) {
    // izinkan edit oleh pemilik OR admin
    $is_owner = ((int)$p['user_id'] === (int)$user_id);
    $is_admin = ($user_role === 'admin');
    if ($is_owner || $is_admin) {
      $editing = true;
      $edit_parcel = $p;

      // jika admin mengedit pengajuan orang lain, ambil nama pemilik untuk ditampilkan
      if (!$is_owner && $is_admin) {
        try {
          $sq = $pdo->prepare("SELECT nama, nik FROM users WHERE id = ? LIMIT 1");
          $sq->execute([(int)$p['user_id']]);
          $urow = $sq->fetch(PDO::FETCH_ASSOC);
          if ($urow) {
            $owner_display_name = $urow['nama'] . ' (' . ($urow['nik'] ?? '') . ')';
            if (!empty($urow['nik'])) $display_nik = $urow['nik'];
          }
        } catch (Exception $ex) {
          // fallback ke nama session admin agar tidak error
          $owner_display_name = $me_name;
        }
      } else {
        // pemilik sedang edit -> gunakan nama sendiri
        $owner_display_name = $me_name;
      }

      // ambil outlets + files
      $stmt2 = $pdo->prepare("SELECT * FROM parcel_outlets WHERE parcel_id = ? ORDER BY id");
      $stmt2->execute([$id]);
      $outs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
      foreach ($outs as $o) {
        // ambil semua file untuk outlet
        $stmtf = $pdo->prepare("SELECT id, filename, original_name, size FROM parcel_files WHERE outlet_id = ? ORDER BY id");
        $stmtf->execute([$o['id']]);
        $files = $stmtf->fetchAll(PDO::FETCH_ASSOC);
        $o['files'] = $files;
        $edit_outlets[] = $o;
      }
    } else {
      // bukan pemilik & bukan admin => tolak
      http_response_code(403);
      die('Anda tidak berhak mengedit pengajuan ini.');
    }
  } else {
    http_response_code(404);
    die('Data pengajuan tidak ditemukan.');
  }
}

// ambil kategori aktif dari tabel parcel_categories (yang enabled = 1)
$categories = [];
try {
  $qc = $pdo->query("SELECT id, name, slug FROM parcel_categories WHERE enabled = 1 ORDER BY name");
  $categories = $qc->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
  // kalau tabel tidak ada atau query error, fallback ke list statis minimal
  $categories = [
    ['id'=>0,'name'=>'Idul Fitri','slug'=>'idul_fitri'],
    ['id'=>1,'name'=>'Imlek','slug'=>'imlek'],
    ['id'=>2,'name'=>'Natal','slug'=>'natal'],
    ['id'=>3,'name'=>'Lainnya','slug'=>'lainnya'],
  ];
}

$upload_base = 'uploads';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title><?= $editing ? 'Edit' : 'Form Pengajuan' ?> Parcel</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --primary-red:#a71d2a;
      --muted-bg:#f7f7f9;
      --sidebar-w: 240px;
      --sidebar-z:1050;
    }
    body { background:var(--muted-bg); font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial; }
    .card { border-radius: 0.6rem; }
    .outlet-card { background:#fff; border:1px solid #e9e9e9; padding:12px; border-radius:8px; margin-bottom:10px; position:relative; }
    .remove-outlet { cursor:pointer; color:#c82333; }
    .existing-file { font-size:0.9rem; display:flex; gap:8px; align-items:center; margin-bottom:6px; }
    .existing-file .fname { max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:inline-block; }
    .badge-doc { font-size:0.75rem; background:#eef; color:#113; padding:0.25rem 0.4rem; border-radius:4px; }

    /* Layout */
    .app { display:flex; gap:24px; min-height:100vh; }
    .sidebar {
      width: var(--sidebar-w);
      background:#fff;
      border-radius: 16px;
      padding:18px;
      margin:20px;
      box-shadow: 0 10px 30px rgba(20,20,20,0.04);
      display:flex; flex-direction:column; gap:14px; flex-shrink:0;
      position:sticky; top:20px; height: calc(100vh - 40px);
    }
    .sidebar .logo { background:var(--primary-red); color:#fff; width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:700; }
    .nav-list { margin-top:6px; display:flex; flex-direction:column; gap:6px; }
    .nav-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; cursor:pointer; color:#333; text-decoration:none; }
    .nav-item .ic { width:28px; height:28px; display:inline-grid; place-items:center; border-radius:6px; background:#fafafa; }
    .nav-item.active { background: rgba(167,29,42,0.09); color:var(--primary-red); font-weight:600; }
    .nav-item:hover { background:#fbf2f2; color:var(--primary-red); }

    /* main */
    .main { flex:1 1 auto; margin:20px 20px 40px 0; max-width: calc(100% - var(--sidebar-w) - 40px); }
    .topbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px; flex-wrap:wrap; }
    .brand-inline { display:flex; align-items:center; gap:12px; }
    .brand-inline .logo { width:44px; height:44px; border-radius:8px; background:var(--primary-red); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; }
    .sidebar .version { margin-top:auto; font-size:0.82rem; color:#9b9b9f; text-align:center; }

    /* responsive: off-canvas sidebar on smaller screens */
    @media (max-width: 992px) {
      .sidebar {
        position: fixed; left:0; top:0; bottom:0; width:var(--sidebar-w); margin:0; height:100vh; border-radius:0;
        transform: translateX(-110%); z-index: var(--sidebar-z); transition: transform .25s ease; box-shadow: 0 20px 40px rgba(20,20,20,0.18);
      }
      .sidebar.open { transform: translateX(0); }
      .sidebar-backdrop { position:fixed; inset:0; background: rgba(0,0,0,0.36); z-index: calc(var(--sidebar-z) - 1); display:none; }
      .sidebar-backdrop.show { display:block; }
      .main { max-width:100%; margin-left:12px; margin-right:12px; }
      .sidebar-toggle { display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; border-radius:8px; background:#fff; border:1px solid #eee; box-shadow:0 4px 12px rgba(20,20,20,0.06); }
    }
    @media (max-width: 576px) {
      .brand-inline .logo { width:36px; height:36px; }
      body { padding-bottom:20px; }
    }

    .no-scroll { overflow:hidden; }
  </style>
</head>
<body>
  <div class="app">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar" aria-hidden="false">
      <div style="display:flex; align-items:center; gap:12px;">
        <div class="logo">RB</div>
        <div>
          <div style="font-weight:700;">Form Reimbursement</div>
          <div style="font-size:0.85rem; color:#6c6c72;">Isi pengajuan biaya & nota</div>
        </div>
      </div>

      <nav class="nav-list" role="navigation" aria-label="Main navigation">
        <a href="index.php" class="nav-item">
          <div class="ic" aria-hidden="true">🏠</div><div>Dashboard</div>
        </a>

        <a href="uc_create.php" class="nav-item">
          <div class="ic" aria-hidden="true">📄</div><div>UC / Kasbon</div>
        </a>

        <a href="parcel_create.php" class="nav-item active">
          <div class="ic" aria-hidden="true">📦</div><div>Parcel</div>
        </a>

      </nav>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop" tabindex="-1" aria-hidden="true"></div>

    <!-- MAIN CONTENT -->
    <main class="main" id="main">
      <div class="topbar">
        <div style="display:flex; align-items:center; gap:10px;">
          <button id="sidebarToggle" class="sidebar-toggle d-lg-none" aria-expanded="false" aria-controls="sidebar" title="Menu">
            <svg width="18" height="12" viewBox="0 0 18 12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <rect y="0" width="18" height="2" rx="1" fill="#6c6c72"/>
              <rect y="5" width="18" height="2" rx="1" fill="#6c6c72"/>
              <rect y="10" width="18" height="2" rx="1" fill="#6c6c72"/>
            </svg>
          </button>

          <div class="brand-inline">
            <div class="logo" aria-hidden="true">PC</div>
            <div>
              <div style="font-weight:700;"><?= $editing ? 'Edit' : 'Form' ?> Pengajuan Parcel</div>
              <div style="color:#6c6c72; font-size:0.9rem;"><?= $editing ? 'Edit draft/pengajuan' : 'Isi pengajuan parcel' ?> — user: <?= e($owner_display_name) ?></div>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2">
          <a href="parcel_list.php" class="btn btn-outline-secondary">Daftar Parcel</a>
            <a href="logout.php" class="btn btn-outline-secondary">Logout</a>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-9">
          <div class="card p-4 mb-4">
            <h5 class="text-danger">Data Pemohon</h5>

            <div class="mb-3">
              <label class="form-label">Nama</label>
              <input type="text" class="form-control" value="<?= e($owner_display_name) ?>" readonly>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">NIK</label>
                <?php
                  // jika admin mengedit milik orang lain dan kita punya nik pemilik di paket? coba tampilkan
                  if ($editing && !empty($edit_parcel['user_id']) && $user_role === 'admin' && (int)$edit_parcel['user_id'] !== (int)$user_id) {
                    // get owner's nik if available via join would be better, but fallback
                    try {
                      $sq = $pdo->prepare("SELECT nik FROM users WHERE id = ? LIMIT 1");
                      $sq->execute([(int)$edit_parcel['user_id']]);
                      $ur = $sq->fetch(PDO::FETCH_ASSOC);
                      if ($ur && !empty($ur['nik'])) $display_nik = $ur['nik'];
                    } catch (Exception $xx) { /* ignore */ }
                  }
                ?>
                <input type="text" class="form-control" value="<?= e($display_nik) ?>" readonly>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Departemen</label>
                <input type="text" class="form-control" value="<?= e($departemen) ?>" readonly>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Tanggal</label>
                <input type="date" class="form-control" value="<?= e(date('Y-m-d', strtotime($edit_parcel['created_at'] ?? date('Y-m-d')))) ?>" readonly>
              </div>
            </div>

            <hr>

            <form action="parcel_store.php" method="post" enctype="multipart/form-data" id="parcelForm">
              <input type="hidden" name="token" value="<?= e($token) ?>">
              <?php if ($editing): ?>
                <input type="hidden" name="parcel_id" value="<?= e($edit_parcel['id']) ?>">
              <?php endif; ?>
              <input type="hidden" name="status" id="statusField" value="<?= e($edit_parcel['status'] ?? 'submitted') ?>">

              <div class="mb-3">
                <label class="form-label">Kategori</label>
                <select name="category" class="form-select" id="category" required>
                  <option value="">-- Pilih Kategori --</option>
                  <?php foreach ($categories as $c):
                    $sel = '';
                    if ($editing) {
                      $curCat = $edit_parcel['category'] ?? '';
                      if ($curCat !== '' && ($curCat == ($c['slug'] ?? $c['name']) || $curCat == ($c['name'] ?? ''))) $sel = 'selected';
                    }
                  ?>
                    <option value="<?= e($c['slug'] ?? $c['name']) ?>" <?= $sel ?>><?= e($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <h6>Outlet / Tujuan</h6>
              <div id="outletsContainer">
                <?php if ($editing && !empty($edit_outlets)): ?>
                  <?php $idx = 0; foreach ($edit_outlets as $o): ?>
                    <div class="outlet-card" data-index="<?= $idx ?>">
                      <div class="d-flex justify-content-between align-items-start">
                        <div><strong>Outlet #<?= $idx+1 ?></strong></div>
                        <div class="remove-outlet" onclick="removeOutlet(this)">Hapus</div>
                      </div>

                      <div class="mb-2 mt-2">
                        <label class="form-label">Nama Outlet</label>
                        <input type="text" name="outlet_name[]" class="form-control" placeholder="Contoh: PT. TJAKRINDO" value="<?= e($o['outlet_name'] ?? '') ?>" required>
                        <input type="hidden" name="outlet_id_existing[]" value="<?= e($o['id']) ?>">
                      </div>

                      <div class="mb-2">
                        <label class="form-label">File Lampiran (sudah ada)</label>
                        <div>
                          <?php if (!empty($o['files'])): foreach ($o['files'] as $f): ?>
                            <div class="existing-file">
                              <span class="fname"><?= e($f['original_name'] ?? $f['filename']) ?></span>
                              <span class="small text-muted">(<?= e(round(($f['size'] ?? 0)/1024)) ?> KB)</span>
                              <label class="ms-auto mb-0"><input type="checkbox" name="delete_files[]" value="<?= e($f['id']) ?>"> Hapus</label>
                            </div>
                          <?php endforeach; else: ?>
                            <div class="small text-muted">Belum ada file.</div>
                          <?php endif; ?>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-12 mb-2">
                          <label class="form-label">Upload File Baru (opsional)</label>
                          <input type="file" name="proofs[<?= $idx ?>][]" accept="image/jpeg,image/png" class="form-control proof-input" multiple>
                          <div class="form-text">Format: jpg/png. Ukuran max ~5 MB per file. (Tidak wajib jika sudah ada file)</div>
                        </div>
                      </div>
                    </div>
                    <?php $idx++; endforeach; ?>
                <?php else: ?>
                  <div class="outlet-card" data-index="0">
                    <div class="d-flex justify-content-between align-items-start">
                      <div><strong>Outlet #1</strong></div>
                      <div class="remove-outlet" onclick="removeOutlet(this)" style="display:none;">Hapus</div>
                    </div>

                    <div class="mb-2 mt-2">
                      <label class="form-label">Nama Outlet</label>
                      <input type="text" name="outlet_name[]" class="form-control" placeholder="Contoh: PT. TJAKRINDO" required>
                    </div>

                    <div class="row">
                      <div class="col-md-12 mb-2">
                        <label class="form-label">Bukti (gambar)</label>
                        <input type="file" name="proofs[0][]" accept="image/jpeg,image/png" class="form-control proof-input" multiple required>
                        <div class="form-text">Format: jpg/png. Ukuran max ~5 MB per file.</div>
                      </div>
                    </div>

                  </div>
                <?php endif; ?>
              </div>

              <div class="mt-2 mb-3">
                <button type="button" id="addOutletBtn" class="btn btn-sm btn-outline-primary">+ Tambah Outlet</button>
              </div>

              <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="index.php" class="btn btn-secondary">Batal / Kembali</a>
                <button type="button" class="btn btn-outline-secondary" id="saveDraftBtn">Save Draft</button>
                <button class="btn btn-primary" type="submit" id="submitBtn">Simpan & Lihat PDF</button>
              </div>
            </form>
          </div>
        </div>

        <div class="col-lg-3">
          <div class="card p-3">
            <div class="text-center">
              <div class="mb-2"><div class="badge bg-danger">Parcel</div></div>
              <div class="fw-semibold"><?= e($owner_display_name) ?></div>
              <div class="small">NIK: <?= e($display_nik ?? $nik) ?></div>
              <div class="small mt-2"><?= e($departemen) ?></div>
            </div>
            <hr>
            <div>
              <h6 class="mb-2">Petunjuk</h6>
              <ul class="small mb-0">
                <li>Isi kategori dan nama outlet. Upload bukti berupa JPG/PNG (max 5MB).</li>
                <li>Save Draft untuk menyimpan tanpa upload file lengkap.</li>
                <li>Klik "Simpan & Lihat PDF" untuk menyimpan final dan generate preview.</li>
              </ul>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

<script>
// Sidebar toggle (mobile)
(function(){
  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('sidebarToggle');
  const backdrop = document.getElementById('sidebarBackdrop');

  function openSidebar(){
    sidebar.classList.add('open');
    backdrop.classList.add('show');
    document.body.classList.add('no-scroll');
    if(toggle) toggle.setAttribute('aria-expanded','true');
  }
  function closeSidebar(){
    sidebar.classList.remove('open');
    backdrop.classList.remove('show');
    document.body.classList.remove('no-scroll');
    if(toggle) toggle.setAttribute('aria-expanded','false');
  }

  if(toggle){
    toggle.addEventListener('click', function(e){
      if(sidebar.classList.contains('open')) closeSidebar();
      else openSidebar();
    });
  }
  if(backdrop){
    backdrop.addEventListener('click', closeSidebar);
  }
  document.querySelectorAll('.nav-list a').forEach(a=>{
    a.addEventListener('click', function(){
      if(window.innerWidth < 992) closeSidebar();
    });
  });
  document.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
  });
})();
</script>

<script>
// Outlet dynamic
let nextIndex = (function(){
  const nodes = document.querySelectorAll('#outletsContainer .outlet-card');
  let max = 0;
  nodes.forEach(n => {
    const i = parseInt(n.getAttribute('data-index')||0,10);
    if (!isNaN(i) && i >= max) max = i+1;
  });
  return max || 1;
})();

function addOutlet() {
  const container = document.getElementById('outletsContainer');
  const idx = nextIndex++;
  const card = document.createElement('div');
  card.className = 'outlet-card';
  card.setAttribute('data-index', idx);
  card.innerHTML = `
    <div class="d-flex justify-content-between align-items-start">
      <div><strong>Outlet #${idx+1}</strong></div>
      <div class="remove-outlet" onclick="removeOutlet(this)">Hapus</div>
    </div>

    <div class="mb-2 mt-2">
      <label class="form-label">Nama Outlet</label>
      <input type="text" name="outlet_name[]" class="form-control" placeholder="Contoh: PT. TJAKRINDO" required>
    </div>

    <div class="mb-2">
      <label class="form-label">File Lampiran (sudah ada)</label>
      <div class="small text-muted">Belum ada file.</div>
    </div>

    <div class="row">
      <div class="col-md-12 mb-2">
        <label class="form-label">Upload File Baru (opsional)</label>
        <input type="file" name="proofs[${idx}][]" accept="image/jpeg,image/png" class="form-control proof-input" multiple>
        <div class="form-text">Format: jpg/png. Ukuran max ~5 MB per file.</div>
      </div>
    </div>
  `;
  container.appendChild(card);
}

function removeOutlet(el) {
  const card = el.closest('.outlet-card');
  if (!card) return;
  const all = document.querySelectorAll('#outletsContainer .outlet-card');
  if (all.length <= 1) {
    alert('Minimal harus ada 1 outlet.');
    return;
  }
  card.remove();
}

document.getElementById('addOutletBtn').addEventListener('click', addOutlet);

// Save Draft
document.getElementById('saveDraftBtn').addEventListener('click', function(){
  if (!confirm('Simpan sebagai draft? Anda bisa mengeditnya kembali nanti.')) return;
  document.getElementById('statusField').value = 'draft';
  document.getElementById('parcelForm').submit();
});

// Submit validation
document.getElementById('parcelForm').addEventListener('submit', function(ev){
  const status = document.getElementById('statusField').value || 'submitted';
  const files = document.querySelectorAll('.proof-input');
  for (let i=0;i<files.length;i++){
    const fList = files[i].files;
    for (let j=0;j<fList.length;j++){
      if (fList[j].size > 5 * 1024 * 1024) {
        ev.preventDefault();
        alert('Satu atau lebih file melebihi limit 5MB. Periksa kembali.');
        return false;
      }
      const t = fList[j].type;
      if (!(t === 'image/jpeg' || t === 'image/png')) {
        ev.preventDefault();
        alert('Hanya file JPG/PNG yang diperbolehkan.');
        return false;
      }
    }
  }

  if (status === 'submitted') {
    const outletCards = document.querySelectorAll('#outletsContainer .outlet-card');
    let okOutlet = false;
    outletCards.forEach(card => {
      const existingFiles = card.querySelectorAll('.existing-file');
      let hasExisting = false;
      existingFiles.forEach(fn => {
        const chk = fn.querySelector('input[type="checkbox"]');
        if (!chk || (chk && !chk.checked)) hasExisting = true;
      });
      const inputs = card.querySelectorAll('input[type="file"]');
      let hasNew = false;
      inputs.forEach(inp => { if (inp.files && inp.files.length>0) hasNew = true; });

      if (hasExisting || hasNew) okOutlet = true;
    });

    if (!okOutlet) {
      ev.preventDefault();
      alert('Untuk menyimpan final, minimal satu outlet harus memiliki lampiran (file) — baik sudah ada atau diupload sekarang. Jika belum lengkap, simpan sebagai Draft dulu.');
      return false;
    }
  }
  document.getElementById('statusField').value = 'submitted';
  return true;
});

const catEl = document.getElementById('category');
if (catEl) catEl.addEventListener('change', function(){ document.getElementById('statusField').value = 'submitted'; });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>