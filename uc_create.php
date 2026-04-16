<?php
// uc_create.php (with left sidebar layout + mobile off-canvas sidebar)
// REVISI: hapus panel kanan Data Pemohon, dan Periode otomatis dari trip_date[] (outlets)

session_start();

if (empty($_SESSION['nik']) && empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

require __DIR__ . '/db.php';

$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $edit_id > 0;

$user_id   = $_SESSION['user_id'] ?? null;
$user_nik  = $_SESSION['nik'] ?? $_SESSION['NIK'] ?? null;
$user_name = $_SESSION['name'] ?? '';
$user_dept = $_SESSION['departemen'] ?? '';

if(!$user_dept && $user_id){
  $s = $pdo->prepare("SELECT departemen FROM users WHERE id=?");
  $s->execute([$user_id]);
  $u = $s->fetch();
  $user_dept = $u['departemen'] ?? '';
}

$form = [
  'requester_name' => $user_name,
  'request_date'   => date('Y-m-d'),
  'branch'         => '',
  'month_label'    => '',
  'start_date'     => '',
  'end_date'       => '',
  'hotel_per_day'  => 0,
  'hotel_nights'   => 0,
  'meal_per_day'   => 0,
  'meal_days'      => 0,
  'fuel_amount'    => 0,
  'other_amount'   => 0,
];

$outlets_rows = [];

if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM uc_requests WHERE id = ? LIMIT 1");
    $stmt->execute([$edit_id]);
    $r = $stmt->fetch();

    if ($r) {
        foreach ($form as $k => $v) {
            if (isset($r[$k]) && $r[$k] !== null) $form[$k] = $r[$k];
        }
        if (!empty($r['requester_name'])) $form['requester_name'] = $r['requester_name'];
        if (!empty($r['request_date'])) $form['request_date'] = $r['request_date'];
        if (!empty($r['branch'])) $form['branch'] = $r['branch'];
        if (!empty($r['month_label'])) $form['month_label'] = $r['month_label'];
        if (!empty($r['start_date'])) $form['start_date'] = $r['start_date'];
        if (!empty($r['end_date'])) $form['end_date'] = $r['end_date'];
        $form['hotel_per_day'] = $r['hotel_per_day'] ?? 0;
        $form['hotel_nights']  = $r['hotel_nights'] ?? 0;
        $form['meal_per_day']  = $r['meal_per_day'] ?? 0;
        $form['meal_days']     = $r['meal_days'] ?? 0;
        $form['fuel_amount']   = $r['fuel_amount'] ?? 0;
        $form['other_amount']  = $r['other_amount'] ?? 0;
    } else {
        header('Location: uc_create.php');
        exit;
    }

    $oStmt = $pdo->prepare("SELECT * FROM uc_outlets WHERE request_id = ? ORDER BY trip_date, id");
    $oStmt->execute([$edit_id]);
    $outlets_rows = $oStmt->fetchAll();

    // Jika ada outlet rows, hitung periode (min/max trip_date) server-side untuk prefill
    $dates = array_values(array_filter(array_map(function($r){ return $r['trip_date'] ?? ''; }, $outlets_rows)));
    if (!empty($dates)) {
        sort($dates); // format YYYY-MM-DD => lexicographic sort works
        $form['start_date'] = $dates[0];
        $form['end_date']   = end($dates);
    }
}

$token = bin2hex(random_bytes(16));
$_SESSION['form_token'] = $token;

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $editing ? 'Edit' : 'Buat' ?> Pengajuan UC / Kasbon</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root{
      --primary-red:#a71d2a;
      --muted-bg:#f7f7f9;
      --card-radius:12px;
      --sidebar-w: 240px;
      --sidebar-z: 1050;
    }
    html,body { height:100%; }
    body{
      background:var(--muted-bg);
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
      margin:0;
      font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
    }

    /* Layout grid: sidebar + content */
    .app { display: flex; min-height: 100vh; gap: 24px; }

    /* SIDEBAR (desktop) */
    .sidebar {
      width: var(--sidebar-w);
      background: #fff;
      border-radius: 16px;
      padding: 18px;
      margin: 20px;
      box-shadow: 0 10px 30px rgba(20,20,20,0.04);
      display: flex;
      flex-direction: column;
      gap: 14px;
      flex-shrink: 0;
      position: sticky;
      top: 20px;
      height: calc(100vh - 40px);
    }
    .sidebar .brand { display:flex; align-items:center; gap:12px; }
    .sidebar .logo { background: var(--primary-red); color:#fff; width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:700; }
    .sidebar .title { font-weight:700; font-size:0.95rem; }
    .sidebar .sub { font-size:0.85rem; color:#6c6c72; }

    .nav-list { margin-top:6px; display:flex; flex-direction:column; gap:6px; }
    .nav-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; cursor:pointer; color:#333; text-decoration:none; }
    .nav-item .ic { width:28px; height:28px; display:inline-grid; place-items:center; border-radius:6px; background:#fafafa; }
    .nav-item.active { background: rgba(167,29,42,0.09); color:var(--primary-red); font-weight:600; }
    .nav-item:hover { background:#fbf2f2; color:var(--primary-red); }

    .sidebar .version { margin-top:auto; font-size:0.82rem; color:#9b9b9f; text-align:center; }

    /* MAIN */
    .main { flex:1 1 auto; margin: 20px 20px 40px 0; max-width: calc(100% - var(--sidebar-w) - 40px); }

    .topbar{ display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px; flex-wrap:wrap; }
    .brand-inline{ display:flex; align-items:center; gap:12px; min-width:0; }
    .brand-inline .logo{ width:44px; height:44px; border-radius:8px; background:var(--primary-red); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; flex:0 0 auto; }
    .actions { display:flex; gap:10px; align-items:center; }

    .card-plain{ border-radius:var(--card-radius); box-shadow:0 6px 18px rgba(20,20,20,0.04); overflow: visible; background: #fff; }

    .section-title{ color:var(--primary-red); font-weight:600; margin-bottom:8px; }
    .readonly-input{ background:#f5f5f7; border:1px solid #e9e9ec; }
    .small-muted{ color:#6c6c72; font-size:0.9rem; }

    .outlet-row{ margin-bottom:8px; align-items:center; }

    .total-box{ background:#fff; padding:12px; border-radius:8px; border:1px solid #f0f0f2; }

    .remove-row { white-space:nowrap; }

    /* Table styling inside total-box */
    .cost-table { width:100%; border-collapse:collapse; margin-bottom:12px; }
    .cost-table th, .cost-table td { padding:8px; vertical-align:middle; border:1px solid #f0f0f2; }
    .cost-table th { background: #fafafa; color:#333; font-weight:600; width:1%; white-space:nowrap; }
    .cost-table .grow { width:100%; }

    /* responsive */
    @media (max-width: 992px) {
      .sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: var(--sidebar-w); margin: 0; height: 100vh; border-radius: 0; transform: translateX(-110%); z-index: var(--sidebar-z); transition: transform .25s ease; box-shadow: 0 20px 40px rgba(20,20,20,0.18); }
      .sidebar.open { transform: translateX(0); }
      .sidebar-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.36); z-index: calc(var(--sidebar-z) - 1); display: none; }
      .sidebar-backdrop.show { display:block; }
      .main { max-width: 100%; margin-right:12px; margin-left:12px; }
      .sidebar-toggle { display: inline-flex; align-items:center; justify-content:center; width:40px; height:40px; border-radius:8px; background:#fff; box-shadow: 0 4px 12px rgba(20,20,20,0.06); border:1px solid #eee; }
      .brand-inline .logo { width:40px; height:40px; }
    }
    @media (max-width: 576px) {
      .topbar { gap:8px; }
      .brand-inline .logo { width:36px; height:36px; }
      .actions .btn { font-size:0.9rem; padding:6px 10px; }
      body { padding-bottom: 40px; }
    }

    .no-scroll { overflow: hidden; }
    .form-card { padding: 22px; }
    .btn-primary { background:var(--primary-red); border-color:var(--primary-red); }
  </style>
</head>
<body>
  <div class="app">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar" aria-hidden="false" aria-labelledby="sidebarLabel">
      <div class="brand">
        <div class="logo">RB</div>
        <div>
          <div class="title">Form Reimbursement</div>
          <div class="sub">Isi pengajuan biaya & nota</div>
        </div>
      </div>

      <nav class="nav-list" role="navigation" aria-label="Main navigation">
        <a href="index.php" class="nav-item ">
          <div class="ic" aria-hidden="true">🏠</div>
          <div>Dashboard</div>
        </a>

        <a href="uc_create.php" class="nav-item active">
          <div class="ic" aria-hidden="true">📄</div>
          <div>UC / Kasbon</div>
        </a>

        <a href="parcel_create.php" class="nav-item">
          <div class="ic" aria-hidden="true">📦</div>
          <div>Parcel</div>
        </a>
      </nav>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop" tabindex="-1" aria-hidden="true"></div>

    <!-- MAIN -->
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
            <div class="logo">UC</div>
            <div class="meta">
              <div class="title"><?= $editing ? 'Edit' : 'Form' ?> Pengajuan UC / Kasbon</div>
              <div class="small-muted">Isi pengajuan biaya &amp; nota — user: <?= e($form['requester_name'] ?: 'Unknown') ?></div>
            </div>
          </div>
        </div>

        <div class="actions">
          <a href="logout.php" class="btn btn-outline-secondary">Logout</a>
          <a href="uc_create.php" class="btn btn-outline-danger">Reset Form</a>
        </div>
      </div>

      <div class="card card-plain form-card">
        <form id="ucForm" action="uc_store.php" method="post" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <input type="hidden" name="user_id" value="<?= e($user_id) ?>">
          <input type="hidden" name="nik" value="<?= e($user_nik) ?>">
          <input type="hidden" name="departemen" value="<?= e($user_dept) ?>">
          <?php if ($editing): ?><input type="hidden" name="request_id" value="<?= $edit_id ?>"><?php endif; ?>

          <!-- Data Pemohon (kiri saja; panel kanan dihilangkan sesuai revisi) -->
          <div class="mb-4 row gx-3 gy-2">
            <div class="col-12 col-md-12">
              <label class="form-label section-title">Data Pemohon</label>

              <div class="mb-2">
                <label class="form-label">Nama</label>
                <input name="requester_name" class="form-control readonly-input" value="<?= e($form['requester_name']) ?>" readonly>
              </div>

              <div class="row g-2">
                <div class="col-12 col-sm-6 col-md-4">
                  <label class="form-label">NIK</label>
                  <input class="form-control readonly-input" value="<?= e($user_nik) ?>" readonly>
                </div>
                <div class="col-12 col-sm-6 col-md-4">
                  <label class="form-label">Departemen</label>
                  <input class="form-control readonly-input" value="<?= e($user_dept) ?>" readonly>
                </div>
                <div class="col-12 col-sm-6 col-md-4">
                  <label class="form-label">Tanggal</label>
                  <input type="date" name="request_date" class="form-control" value="<?= e($form['request_date']) ?>">
                </div>
              </div>
            </div>
          </div>

          <hr>

          <!-- Period (OTOMATIS dari outlet dates; readonly) -->
          <div class="row mb-3 gx-3 gy-2">
            <div class="col-12 col-sm-6 col-md-4">
              <label class="form-label">Cabang</label>
              <input name="branch" class="form-control" placeholder="Cabang" value="<?= e($form['branch']) ?>">
            </div>
            <div class="col-12 col-sm-6 col-md-4">
              <label class="form-label">Bulan (label)</label>
              <input name="month_label" class="form-control" placeholder="Februari 2026" value="<?= e($form['month_label']) ?>">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Periode (otomatis dari Daftar Tujuan)</label>
              <div class="d-flex gap-2">
                <input type="date" name="start_date" class="form-control" value="<?= e($form['start_date']) ?>" readonly title="Terisi otomatis dari tanggal outlet terawal">
                <input type="date" name="end_date" class="form-control" value="<?= e($form['end_date']) ?>" readonly title="Terisi otomatis dari tanggal outlet terakhir">
              </div>
            </div>
          </div>

          <hr>

          <!-- Daftar Tujuan / Outlet -->
          <h5 class="section-title">Daftar Tujuan / Outlet</h5>
          <div id="outlets">
            <?php
            if (!empty($outlets_rows)) {
              foreach ($outlets_rows as $row) {
                $td = e($row['trip_date'] ?? '');
                $dest = e($row['destination'] ?? '');
                $oname = e($row['outlet_name'] ?? '');
                $es = e($row['est_sales'] ?? '');
                $oid = (int)$row['id'];
                echo "<div class=\"row outlet-row gx-2 gy-2 align-items-center\">";
                echo "<input type=\"hidden\" name=\"outlet_id[]\" value=\"{$oid}\">";
                echo "<div class=\"col-12 col-sm-4 col-md-2\"><input type=\"date\" name=\"trip_date[]\" class=\"form-control\" value=\"{$td}\"></div>";
                echo "<div class=\"col-12 col-sm-8 col-md-3\"><input name=\"destination[]\" class=\"form-control\" placeholder=\"Tujuan\" value=\"{$dest}\"></div>";
                echo "<div class=\"col-12 col-md-4\"><input name=\"outlet_name[]\" class=\"form-control\" placeholder=\"Nama Outlet\" value=\"{$oname}\"></div>";
                echo "<div class=\"col-8 col-sm-6 col-md-2\"><input name=\"est_sales[]\" class=\"form-control\" placeholder=\"Estimasi Sales\" value=\"{$es}\"></div>";
                echo "<div class=\"col-4 col-sm-2 col-md-1\"><button type=\"button\" class=\"btn btn-danger btn-sm remove-row w-100\">-</button></div>";
                echo "</div>";
              }
            } else {
              ?>
              <div class="row outlet-row gx-2 gy-2 align-items-center">
                <div class="col-12 col-sm-4 col-md-2"><input type="date" name="trip_date[]" class="form-control"></div>
                <div class="col-12 col-sm-8 col-md-3"><input name="destination[]" class="form-control" placeholder="Tujuan"></div>
                <div class="col-12 col-md-4"><input name="outlet_name[]" class="form-control" placeholder="Nama Outlet (satu row = 1 outlet)"></div>
                <div class="col-8 col-sm-6 col-md-2"><input name="est_sales[]" class="form-control" placeholder="Estimasi Sales (angka)"></div>
                <div class="col-4 col-sm-2 col-md-1"><button type="button" class="btn btn-danger btn-sm remove-row w-100">-</button></div>
              </div>
            <?php } ?>
          </div>

          <div class="mb-3 mt-2">
            <button type="button" id="addOutlet" class="btn btn-outline-primary btn-sm">Tambah Outlet</button>
          </div>

          <!-- Rincian biaya (tabel bertumpuk) -->
          <h5 class="section-title">Rincian Biaya</h5>
          <div class="total-box mb-3">
            <!-- TABEL 1: Biaya Hotel -->
            <table class="cost-table">
              <thead>
                <tr>
                  <th>Biaya Hotel /hari</th>
                  <th>Jumlah Malam</th>
                  <th>Total Hotel</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <input type="number" step="0.01" id="hotel_per_day" name="hotel_per_day" class="form-control" value="<?= e($form['hotel_per_day']) ?>">
                  </td>
                  <td>
                    <input type="number" id="hotel_nights" name="hotel_nights" class="form-control" value="<?= e($form['hotel_nights']) ?>">
                  </td>
                  <td>
                    <input readonly id="hotel_total" class="form-control" aria-label="Total Hotel">
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- TABEL 2: Biaya Makan -->
            <table class="cost-table">
              <thead>
                <tr>
                  <th>Biaya Makan /hari</th>
                  <th>Jumlah Hari</th>
                  <th>Total Makan</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <input type="number" step="0.01" id="meal_per_day" name="meal_per_day" class="form-control" value="<?= e($form['meal_per_day']) ?>">
                  </td>
                  <td>
                    <input type="number" id="meal_days" name="meal_days" class="form-control" value="<?= e($form['meal_days']) ?>">
                  </td>
                  <td>
                    <input readonly id="meal_total" class="form-control" aria-label="Total Makan">
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- TABEL 3: BBM / Transport -->
            <table class="cost-table">
              <thead>
                <tr>
                  <th class="grow">BBM / Transport (estimasi)</th>
                  <th>Total BBM</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <input type="number" step="0.01" id="fuel_amount" name="fuel_amount" class="form-control" value="<?= e($form['fuel_amount']) ?>">
                  </td>
                  <td>
                    <input readonly id="fuel_total" class="form-control" aria-label="Total BBM">
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- TABEL 4: Lain-lain -->
            <table class="cost-table">
              <thead>
                <tr>
                  <th class="grow">Lain-lain (keterangan singkat)</th>
                  <th>Total Lain-lain</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <input type="text" name="other_note" class="form-control" placeholder="Keterangan (opsional)">
                  </td>
                  <td>
                    <input type="number" step="0.01" id="other_amount" name="other_amount" class="form-control" value="<?= e($form['other_amount']) ?>">
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- GRAND TOTAL -->
            <div class="d-flex justify-content-end align-items-center mt-2">
              <div style="width:320px;">
                <label class="form-label">Grand Total</label>
                <input readonly id="grand_total" name="grand_total_display" class="form-control form-control-lg" style="font-weight:600;">
                <!-- hidden numeric value for server processing -->
                <input type="hidden" id="grand_total_raw" name="grand_total" value="">
              </div>
            </div>
          </div>

          <hr>

          <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
            <a href="index.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Update & Lihat PDF' : 'Simpan & Lihat PDF' ?></button>
          </div>
        </form>
      </div>
    </main>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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

    // close sidebar when a navigation link is clicked (mobile)
    document.querySelectorAll('.nav-list a').forEach(a=>{
      a.addEventListener('click', function(){
        if(window.innerWidth < 992) closeSidebar();
      });
    });

    // close sidebar on escape
    document.addEventListener('keydown', (e)=>{
      if(e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
    });
  })();

  // add/remove outlets
  document.getElementById('addOutlet').addEventListener('click', function(){
    const container = document.getElementById('outlets');
    const row = document.createElement('div');
    row.className = 'row outlet-row gx-2 gy-2 align-items-center';
    row.innerHTML = `
      <div class="col-12 col-sm-4 col-md-2"><input type="date" name="trip_date[]" class="form-control"></div>
      <div class="col-12 col-sm-8 col-md-3"><input name="destination[]" class="form-control" placeholder="Tujuan"></div>
      <div class="col-12 col-md-4"><input name="outlet_name[]" class="form-control" placeholder="Nama Outlet"></div>
      <div class="col-8 col-sm-6 col-md-2"><input name="est_sales[]" class="form-control" placeholder="Estimasi Sales"></div>
      <div class="col-4 col-sm-2 col-md-1"><button type="button" class="btn btn-danger btn-sm remove-row w-100">-</button></div>
    `;
    container.appendChild(row);

    // attach listener to the newly added date input
    const dateInput = row.querySelector('input[name="trip_date[]"]');
    if (dateInput) dateInput.addEventListener('input', updatePeriodFromOutlets);

    row.scrollIntoView({behavior:'smooth', block:'center'});
    updatePeriodFromOutlets();
  });

  document.addEventListener('click', function(e){
    if(e.target && e.target.classList.contains('remove-row')){
      const row = e.target.closest('.outlet-row');
      if(row) {
        row.remove();
        updatePeriodFromOutlets();
      }
    }
  });

  // update period from trip_date[] inputs
  function updatePeriodFromOutlets(){
    const dateInputs = Array.from(document.querySelectorAll('input[name="trip_date[]"]'));
    const dates = dateInputs.map(i=>i.value).filter(v=>v);
    if (dates.length === 0) {
      const start = document.querySelector('input[name="start_date"]');
      const end = document.querySelector('input[name="end_date"]');
      if (start) start.value = '';
      if (end) end.value = '';
      return;
    }
    dates.sort(); // 'YYYY-MM-DD' sorts lexicographically
    const min = dates[0];
    const max = dates[dates.length - 1];
    const start = document.querySelector('input[name="start_date"]');
    const end = document.querySelector('input[name="end_date"]');
    if (start) start.value = min;
    if (end) end.value = max;
  }

  // listen for manual changes to any existing trip_date[] (delegation)
  document.addEventListener('input', function(e){
    const name = e.target && e.target.getAttribute ? e.target.getAttribute('name') : null;
    if (name === 'trip_date[]') updatePeriodFromOutlets();
  });

  // cost calculations (tabel model)
  function calcTotals(){
    const hotel = parseFloat(document.getElementById('hotel_per_day').value||0);
    const nights = parseFloat(document.getElementById('hotel_nights').value||0);
    const meal = parseFloat(document.getElementById('meal_per_day').value||0);
    const days = parseFloat(document.getElementById('meal_days').value||0);
    const fuel = parseFloat(document.getElementById('fuel_amount').value||0);
    const other = parseFloat(document.getElementById('other_amount').value||0);

    const hotelTotal = (Number.isFinite(hotel) && Number.isFinite(nights)) ? hotel * nights : 0;
    const mealTotal = (Number.isFinite(meal) && Number.isFinite(days)) ? meal * days : 0;
    const fuelTotal = Number.isFinite(fuel) ? fuel : 0;
    const otherTotal = Number.isFinite(other) ? other : 0;
    const grand = hotelTotal + mealTotal + fuelTotal + otherTotal;

    const opts = { minimumFractionDigits: 0, maximumFractionDigits: 2 };
    const htEl = document.getElementById('hotel_total');
    const mtEl = document.getElementById('meal_total');
    const ftEl = document.getElementById('fuel_total');
    const gtEl = document.getElementById('grand_total');
    const gtRaw = document.getElementById('grand_total_raw');

    if (htEl) htEl.value = hotelTotal.toLocaleString('id-ID', opts);
    if (mtEl) mtEl.value = mealTotal.toLocaleString('id-ID', opts);
    if (ftEl) ftEl.value = fuelTotal.toLocaleString('id-ID', opts);
    if (gtEl) gtEl.value = grand.toLocaleString('id-ID', opts);
    if (gtRaw) gtRaw.value = String(Math.round((grand + Number.EPSILON) * 100) / 100);
  }

  ['hotel_per_day','hotel_nights','meal_per_day','meal_days','fuel_amount','other_amount'].forEach(id=>{
    const el = document.getElementById(id);
    if(el) el.addEventListener('input', calcTotals);
  });

  // init on load
  window.addEventListener('load', function(){
    calcTotals();
    updatePeriodFromOutlets();
  });

  // ensure numeric inputs don't produce NaN on submit (basic guard)
  document.getElementById('ucForm').addEventListener('submit', function(e){
    const raw = document.getElementById('grand_total_raw');
    if(raw && raw.value === '') calcTotals();
    // periode sudah diisi otomatis; tapi jika kosong dan ada outlet date, update lagi
    updatePeriodFromOutlets();
  });
</script>
</body>
</html>