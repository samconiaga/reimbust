<?php
  session_start();

  // kalau belum login, redirect ke login
  if (empty($_SESSION['user_id'])) {
      header('Location: login.php?next=' . urlencode('index.php'));
      exit;
  }

  // ==================
  // koneksi + config
  // ==================
  require_once __DIR__ . '/db.php';
  require_once __DIR__ . '/helpers.php';
  $config = require __DIR__ . '/config.php';

  // ambil users (tetap ambil untuk kompatibilitas)
  $users = [];
  try {
      if (isset($pdo)) {
          $stmtU = $pdo->query("SELECT nik, nama, departemen, uid FROM users ORDER BY nama ASC");
          $users = $stmtU->fetchAll(PDO::FETCH_ASSOC);
      } elseif (isset($conn)) {
          $result = $conn->query("SELECT nik, nama, departemen, uid FROM users ORDER BY nama ASC");
          if ($result) {
              while ($row = $result->fetch_assoc()) {
                  $users[] = $row;
              }
          }
      }
  } catch (Exception $e) {
      $users = [];
  }

  // Ambil informasi login dari session (prioritas)
  $session_nik  = $_SESSION['selected_nik'] ?? $_SESSION['nik'] ?? '';
  $session_name = $_SESSION['selected_name'] ?? $_SESSION['name'] ?? ($_SESSION['nama'] ?? '');
  $session_dept = $_SESSION['cabang'] ?? $_SESSION['jabatan'] ?? '';
  $session_uid  = $_SESSION['selected_user_id'] ?? ($_SESSION['uid'] ?? '');

  // Normalisasi user (untuk tampilan)
  $nama = $nik = $dept = $uid = '';
  $lockUser = "true";

  if (!empty($users)) {
      foreach ($users as $u) {
          if ($session_uid !== '' && ((string)($u['uid'] ?? '') === (string)$session_uid || (string)($u['nik'] ?? '') === (string)$session_uid)) {
              $nama = $u['nama'] ?? $session_name;
              $nik  = $u['nik'] ?? $session_nik;
              $dept = $u['departemen'] ?? $session_dept;
              $uid  = $u['uid'] ?? $session_uid;
              break;
          }
          if ($session_nik !== '' && ((string)($u['nik'] ?? '') === (string)$session_nik)) {
              $nama = $u['nama'] ?? $session_name;
              $nik  = $u['nik'] ?? $session_nik;
              $dept = $u['departemen'] ?? $session_dept;
              $uid  = $u['uid'] ?? $session_uid;
              break;
          }
          if ($session_name !== '' && mb_strtolower(trim($u['nama'] ?? '')) === mb_strtolower(trim($session_name))) {
              $nama = $u['nama'] ?? $session_name;
              $nik  = $u['nik'] ?? $session_nik;
              $dept = $u['departemen'] ?? $session_dept;
              $uid  = $u['uid'] ?? $session_uid;
              break;
          }
      }
  }

  if ($nama === '' && $session_name !== '') $nama = $session_name;
  if ($nik === '' && $session_nik !== '') $nik = $session_nik;
  if ($dept === '' && $session_dept !== '') $dept = $session_dept;
  if ($uid === '' && $session_uid !== '') $uid = $session_uid;

  $usersJson = json_encode($users, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $templateObj = [
      'nama' => $nama,
      'nik'  => $nik,
      'dept' => $dept,
      'uid'  => $uid,
      'lockUser' => $lockUser,
      'webAppUrl' => ''
  ];
  $maxFileSizeMb = intval($config['max_file_size_mb'] ?? 10);
  $maxTotalSizeMb = intval($config['max_total_size_mb'] ?? 40);
  ?>
  <!DOCTYPE html>
  <html lang="id">
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <title>Form Reimbursement</title>
    <style>
      :root{
        --brand: #aa1e2c;
        --muted: #6b7280;
        --bg: #f6f7fb;
        --card: #ffffff;
        --radius: 12px;
        --glass: rgba(255,255,255,0.7);
        --max-width: 1100px;
        --sidebar-width: 220px;
        --sidebar-collapsed: 72px;
      }
      *{box-sizing:border-box;margin:0;padding:0}
      html,body{height:100%;font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale}
      body{background:var(--bg);padding:16px;color:#111;font-size:14px;line-height:1.4}
      a{color:inherit;text-decoration:none}
      .app { display:flex; gap:16px; max-width:1200px; margin:0 auto; align-items:flex-start; }
      .sidebar { width:var(--sidebar-width); min-width:var(--sidebar-width); background:#fff; border-radius:12px; padding:12px; box-shadow:0 6px 18px rgba(16,24,40,0.06); height:calc(100vh - 32px); position:sticky; top:16px; transition: width .22s ease, min-width .22s ease; overflow:hidden; }
      .sidebar.collapsed { width:var(--sidebar-collapsed); min-width:var(--sidebar-collapsed); }
      .sidebar .brand { display:flex; align-items:center; gap:10px; margin-bottom:14px }
      .sidebar .brand .logo { width:44px;height:44px;border-radius:10px;background:var(--brand);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700 }
      .sidebar .brand .title { font-weight:800; font-size:15px }
      .nav { margin-top:8px; display:flex; flex-direction:column; gap:6px }
      .nav a.item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; color:#222; font-weight:700; border:1px solid transparent; background:transparent; transition: background .15s ease, color .15s ease; }
      .nav a.item:hover { background: #fafafa; }
      .nav a.item.active { background: rgba(170,30,44,0.08); border-color: rgba(170,30,44,0.08); color:var(--brand) }
      .nav a.item .ico { width:30px; text-align:center; font-size:16px }
      .sidebar .toggle { display:flex; justify-content:center; margin-top:10px; }
      .sidebar .toggle button { padding:8px 10px; border-radius:999px; border:0; background:#f4f4f5; cursor:pointer; font-weight:700 }
      .main { flex:1; min-width:0; }
      .container { width:100%; max-width:var(--max-width); margin:0 auto }
      .card{background:var(--card);border-radius:var(--radius);padding:18px;box-shadow:0 6px 18px rgba(16,24,40,0.06);margin-bottom:16px}
      .header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px;flex-wrap:wrap}
      .logo-wrapper{display:flex;align-items:center;gap:12px;min-width:0}
      .logo{width:56px;height:56px;border-radius:10px;background:var(--brand);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:18px;flex-shrink:0}
      h1{font-size:clamp(16px, 1.6vw, 20px);margin:0;line-height:1.2}
      .lead{margin-top:4px;color:var(--muted);font-size:clamp(12px,1.2vw,14px)}
      .header-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
      .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 14px;border-radius:10px;border:0;font-weight:600;cursor:pointer;font-size:14px}
      .btn.primary{background:var(--brand);color:#fff}
      .btn.ghost{background:#fff;border:1px solid #e8e8e8;color:#333}
      .btn.reset{background:#fff;border:1px solid #f3d2d2;color:#aa1e2c}
      .stepper-wrap{margin:10px 0;overflow:hidden}
      .stepper{display:flex;gap:8px;padding:6px;align-items:center}
      .step{flex:0 0 auto;padding:10px 12px;border-radius:10px;background:#fff;border:1px solid #f0f0f0;text-align:center;font-size:13px;color:var(--muted);white-space:nowrap}
      .step.active{border-color:var(--brand);color:var(--brand);font-weight:700;box-shadow:0 4px 12px rgba(170,30,44,0.06)}
      .pages{display:block}
      .page{display:none}
      .page.active{display:block}
      .section-title{display:flex;align-items:center;gap:12px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #f0f0f0}
      label{display:block;font-weight:700;margin-top:12px;color:#222;font-size:13px}
      input[type="text"],input[type="date"],input[type="number"],select,textarea{ width:100%;padding:12px;border-radius:10px;border:1px solid #e6e6e6;margin-top:8px;font-size:14px;font-family:inherit;background:#fff;}
      input[readonly]{background:#f8f9fa;color:#444}
      .grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:12px;margin-top:12px}
      .cat{display:flex;align-items:center;gap:12px;padding:12px;border-radius:10px;border:1px solid rgba(170,30,44,0.08);background:#fff;cursor:pointer;position:relative;min-height:64px}
      .cat .icon{font-size:20px;width:44px;height:44px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:rgba(170,30,44,0.06);color:var(--brand);flex-shrink:0}
      .cat strong{display:block;font-size:14px}
      .cat small{color:var(--muted);display:block;font-size:12px}
      .cat.has-data{border-color:var(--brand);background:rgba(170,30,44,0.03)}
      .cat-data-badge{position:absolute;top:-8px;right:-8px;background:var(--brand);color:white;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700}
      .cat.hidden{display:none!important}
      .actions{display:flex;gap:10px;margin-top:18px}
      .actions .btn{flex:1}
      .actions .btn.reset{flex:0}
      .summary .row{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f0f0f0}
      .summary .row:last-child{border-bottom:0}
      .summary .total{font-size:16px;font-weight:800;color:var(--brand);margin-top:12px;text-align:right}
      .spinner{display:inline-block;width:16px;height:16px;border:2px solid #fff;border-radius:50%;border-top-color:transparent;animation:spin 0.8s linear infinite;margin-right:8px}
      @keyframes spin{to{transform:rotate(360deg)}}
      .attachments{margin-top:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
      .att-card{border:1px solid #eee;border-radius:12px;background:#fff;overflow:hidden;display:flex;flex-direction:column}
      .att-head{padding:10px 12px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;gap:10px}
      .att-title{font-weight:700;font-size:13px}
      .att-meta{color:var(--muted);font-size:12px;margin-top:2px}
      .att-body{padding:10px 12px;flex:1;display:flex;flex-direction:column;gap:8px}
      .att-preview{width:100%;border-radius:10px;border:1px solid #f0f0f0;background:#fafafa;overflow:hidden;display:block}
      .att-preview img{width:100%;max-height:260px;object-fit:cover;display:block}
      .att-preview iframe{width:100%;height:260px;border:0;display:block}
      .att-actions{margin-top:8px;display:flex;gap:8px}
      .att-actions a{flex:1;text-align:center;text-decoration:none;padding:10px 12px;border-radius:10px;font-weight:700;font-size:13px}
      .att-actions a.open{background:var(--brand);color:#fff}
      .att-actions a.dl{background:#fff;border:1px solid #e8e8e8;color:#333}
      .reset-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px}
      .reset-modal.active{display:flex}
      .reset-content{background:white;border-radius:12px;width:100%;max-width:460px;overflow:hidden}
      .reset-header{padding:20px;border-bottom:1px solid #e9ecef;text-align:center}
      .reset-body{padding:20px;text-align:center}
      .reset-actions{padding:16px;border-top:1px solid #e9ecef;display:flex;gap:8px;justify-content:center}
      .hint{margin-top:10px;color:var(--muted);font-size:13px;line-height:1.4}
      .pill{display:inline-block;padding:6px 10px;border:1px solid #eee;border-radius:999px;background:#fafafa;font-size:12px;color:#333}
      .file-size-warning{color:#dc2626;font-size:12px;margin-top:6px;display:none}
      .compression-progress{background:#f0f0f0;border-radius:4px;height:4px;margin-top:8px;overflow:hidden;display:none}
      .compression-progress .bar{height:100%;background:var(--brand);width:0%;transition:width 0.3s}
      .file-info{font-size:12px;color:#666;margin-top:6px}
      /* ========== SIDEBAR IMPROVEMENTS ========== */
      .sidebar.collapsed .nav .item .txt,
      .sidebar.collapsed .brand .title,
      .sidebar.collapsed .brand div:last-child {
        display: none;
      }
      .sidebar.collapsed .nav .item {
        justify-content: center;
        padding: 10px 0;
      }
      .sidebar.collapsed .nav .item .ico {
        width: auto;
        margin: 0;
      }
      .sidebar.collapsed .brand {
        justify-content: center;
      }
      .sidebar .toggle button {
        width: 100%;
        background: transparent;
        border: 1px solid #eee;
        border-radius: 999px;
        padding: 8px;
        font-size: 18px;
        transition: background 0.2s;
      }
      .sidebar .toggle button:hover {
        background: #f0f0f0;
      }
      .sidebar.collapsed .toggle button {
        width: auto;
        padding: 8px 12px;
        margin: 0 auto;
      }
      .sidebar.collapsed .nav .item {
        position: relative;
      }
      .sidebar.collapsed .nav .item:hover::after {
        content: attr(title);
        position: absolute;
        left: 70px;
        top: 50%;
        transform: translateY(-50%);
        background: #333;
        color: #fff;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 13px;
        white-space: nowrap;
        z-index: 1000;
        pointer-events: none;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
      }
      body {
        padding: 20px;
      }
      .header {
        margin-bottom: 20px;
      }
      .logo-wrapper {
        gap: 16px;
      }
      .logo-wrapper button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        padding: 0;
        font-size: 20px;
      }
      .stepper-wrap {
        background: transparent;
        box-shadow: none;
        padding: 0;
      }
      .stepper {
        gap: 12px;
      }
      .step {
        flex: 1;
        text-align: center;
        background: #f8f9fa;
        border: none;
        border-radius: 30px;
        padding: 10px 16px;
        font-weight: 500;
        color: #6c757d;
        transition: all 0.2s;
      }
      .step.active {
        background: var(--brand);
        color: white;
        box-shadow: 0 4px 10px rgba(170,30,44,0.2);
      }
      .card {
        border-radius: 16px;
        padding: 22px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.04);
      }
      label {
        font-weight: 600;
        margin-top: 16px;
        color: #2d3748;
      }
      input, select, textarea {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 14px 16px;
        font-size: 15px;
        transition: border 0.2s, box-shadow 0.2s;
      }
      input:focus, select:focus, textarea:focus {
        border-color: var(--brand);
        outline: none;
        box-shadow: 0 0 0 3px rgba(170,30,44,0.1);
      }
      .grid {
        gap: 16px;
      }
      .cat {
        border-radius: 16px;
        padding: 16px;
        border: 1px solid #edf2f7;
        transition: all 0.2s;
      }
      .cat:hover {
        border-color: var(--brand);
        background: rgba(170,30,44,0.02);
      }
      .cat .icon {
        width: 48px;
        height: 48px;
        font-size: 24px;
      }
      .actions .btn {
        padding: 14px 24px;
        font-size: 15px;
        border-radius: 40px;
        font-weight: 600;
      }

      /* ========== RESPONSIVE UNTUK HP ========== */
      @media (max-width: 900px) {
        /* Sidebar off-canvas */
        .sidebar {
          display: block !important;
          position: fixed;
          top: 0;
          left: 0;
          width: 280px;
          height: 100vh;
          z-index: 1000;
          transform: translateX(-100%);
          transition: transform 0.3s ease;
          border-radius: 0;
          box-shadow: 2px 0 10px rgba(0,0,0,0.1);
          padding: 20px;
        }
        .sidebar.open {
          transform: translateX(0);
        }
        .app {
          flex-direction: column;
        }
        body {
          padding: 12px;
        }
        /* overlay */
        .sidebar-overlay {
          display: none;
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0,0,0,0.5);
          z-index: 999;
        }
        .sidebar-overlay.active {
          display: block;
        }
        .stepper {
          overflow-x: auto;
          flex-wrap: nowrap;
          -webkit-overflow-scrolling: touch;
          padding-bottom: 8px;
        }
        .step {
          flex: 0 0 auto;
          white-space: nowrap;
        }
        .grid {
          grid-template-columns: repeat(2, 1fr) !important;
        }
        .cat {
          padding: 10px;
          min-height: auto;
        }
        .cat .icon {
          width: 36px;
          height: 36px;
          font-size: 18px;
        }
        .cat strong {
          font-size: 13px;
        }
        .cat small {
          font-size: 11px;
        }
        .actions {
          flex-direction: column;
        }
        .actions .btn {
          width: 100%;
        }
        .header-actions {
          flex-wrap: wrap;
          justify-content: flex-start;
        }
        .header-actions .btn {
          flex: 1 1 auto;
        }
        .attachments {
          grid-template-columns: 1fr !important;
        }
        /* Grid untuk BBM (3 kolom & 2 kolom) diubah jadi 1 kolom */
        .bbm-grid-3,
        .bbm-grid-2 {
          grid-template-columns: 1fr !important;
        }
        /* Tombol aksi dalam form kategori */
        .cat-form-actions {
          flex-wrap: wrap;
        }
        .cat-form-actions .btn {
          width: 100%;
        }
      }
    </style>
  </head>
  <body>
    <!-- Overlay untuk sidebar mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="app">
      <!-- SIDEBAR -->
      <aside class="sidebar" id="sidebar">
        <div class="brand">
          <div class="logo">RB</div>
          <div>
            <div class="title">Form Reimbursement</div>
            <div style="font-size:12px;color:var(--muted)">Isi pengajuan biaya & nota</div>
          </div>
        </div>

        <nav class="nav" role="navigation" aria-label="Main">
          <a href="preview.php" class="item active" id="nav-dashboard" title="Dashboard">
            <div class="ico">📊</div>
            <div class="txt">Dashboard</div>
          </a>

          <a href="uc_create.php" class="item" id="nav-uc" title="UC / Kasbon">
            <div class="ico">💳</div>
            <div class="txt">UC / Kasbon</div>
          </a>

          <a href="parcel_create.php" class="item" id="nav-kasbon" title="Parcel">
            <div class="ico">💰</div>
            <div class="txt">Parcel</div>
          </a>

          <a href="uc_list.php" class="item" id="nav-uc-list" style="display:none" title="Daftar UC">
            <div class="ico">📁</div>
            <div class="txt">Daftar UC</div>
          </a>
        </nav>
      </aside>

      <!-- MAIN -->
      <main class="main">
        <div class="container">
          <div class="card">
            <div class="header">
              <div class="logo-wrapper">
                <button class="btn ghost" onclick="toggleSidebar()" style="margin-right:6px" title="Buka/Tutup sidebar">☰</button>
                <div class="logo">RB</div>
                <div style="min-width:0">
                  <h1>Form Reimbursement</h1>
                  <p class="lead">Isi pengajuan biaya perjalanan dinas & nota</p>
                </div>
              </div>

              <div class="header-actions">
                <button class="btn ghost" onclick="logoutSPO()">Logout</button>
                <button class="btn reset" onclick="showResetModal()">Reset Form</button>
              </div>
            </div>

            <div class="stepper-wrap card" style="margin-bottom:12px;">
              <div class="stepper" id="stepper">
                <div class="step active" id="step-1">1. Data Karyawan</div>
                <div class="step" id="step-2">2. Nota & Lokasi</div>
                <div class="step" id="step-3">3. Kategori</div>
                <div class="step" id="step-4">4. Review</div>
              </div>
            </div>

            <div class="pages">
              <div class="page active" id="page-1">
                <div class="section-title" style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                  <div class="avatar" style="width:48px;height:48px;border-radius:10px;background:var(--glass);display:flex;align-items:center;justify-content:center;font-size:20px">👤</div>
                  <div>
                    <div style="font-weight:800;font-size:15px" id="display-nama">—</div>
                    <div style="color:var(--muted);font-size:13px" id="display-dept">NIK: —</div>
                  </div>
                </div>

                <div class="card" style="margin-bottom:16px">
                  <div style="font-weight:800;margin-bottom:6px">Pilih Karyawan</div>
                  <div class="hint">Nama dikunci sesuai akun login. Jika perlu ganti akun, logout lalu login dengan user lain.</div>

                  <label>Nama Karyawan <span style="color:#dc2626">*</span></label>
                  <select id="userSelect" onchange="onUserChange()" disabled>
                    <option value="">-- Pilih Nama --</option>
                  </select>

                  <label>NIK</label>
                  <input type="text" id="inp_nik" readonly>

                  <label>Departemen</label>
                  <input type="text" id="inp_dept" readonly>

                  <input type="hidden" id="inp_uid">
                </div>

                <div style="margin-top:12px; padding:14px; background:#f8f9fa; border-radius:10px;">
                  <div style="font-weight:700; margin-bottom:8px;">Data Karyawan</div>
                  <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:8px; font-size:13px;">
                    <div style="color:var(--muted)">Nama:</div>
                    <div id="simple-nama">—</div>
                    <div style="color:var(--muted)">NIK:</div>
                    <div id="simple-nik">—</div>
                    <div style="color:var(--muted)">Departemen:</div>
                    <div id="simple-dept">—</div>
                  </div>
                </div>

                <div class="actions" style="margin-top:18px">
                  <button class="btn primary" onclick="go(2)">Lanjut</button>
                </div>
              </div>

              <div class="page" id="page-2">
                <div class="section-title">
                  <div class="avatar">📝</div>
                  <div>
                    <strong>Informasi Nota & Lokasi</strong>
                    <div class="lead">Tanggal, lokasi dan detail tujuan</div>
                  </div>
                </div>

                <label>Tanggal Nota</label>
                <input type="date" id="tanggal_nota">

                <label>Lokasi</label>
                <select id="lokasi" onchange="onLokasiChange()">
                  <option value="Dalam Kota">Dalam Kota</option>
                  <option value="Luar Kota">Luar Kota</option>
                </select>

                <div id="tujuan_wrap" style="display:none;margin-top:16px">
                  <label>Tujuan Luar Kota</label>
                  <input type="text" id="luar_kota_tujuan" placeholder="Cth: Bandung">
                </div>

                <div class="actions">
                  <button class="btn ghost" onclick="go(1)">Kembali</button>
                  <button class="btn primary" onclick="go(3)">Lanjut</button>
                </div>
              </div>

              <div class="page" id="page-3">
                <div class="section-title">
                  <div class="avatar">📂</div>
                  <div>
                    <strong>Pilih Kategori</strong>
                    <div class="lead">Tambah bukti & jumlah untuk tiap kategori</div>
                  </div>
                </div>

                <div class="grid" id="category-grid" style="margin-bottom:10px">
                  <div class="cat" onclick="openCat('tol')" id="cat-tol"><div class="icon">🚗</div><div><strong>Tol</strong><small>Biaya tol & bukti nota</small></div></div>
                  <div class="cat" onclick="openCat('parkir')" id="cat-parkir"><div class="icon">🅿️</div><div><strong>Parkir</strong><small>Biaya parkir</small></div></div>
                  <div class="cat" onclick="openCat('makan')" id="cat-makan"><div class="icon">🍽</div><div><strong>Makan</strong><small>Nota makan + nama orang</small></div></div>
                  <div class="cat" onclick="openCat('hotel')" id="cat-hotel"><div class="icon">🏨</div><div><strong>Hotel</strong><small>Bukti hotel</small></div></div>
                  <div class="cat" onclick="openCat('bbm')" id="cat-bbm"><div class="icon">⛽</div><div><strong>BBM</strong><small>Plat & KM & Liters</small></div></div>
                  <div class="cat" onclick="openCat('entertain')" id="cat-entertain"><div class="icon">🎉</div><div><strong>Entertain</strong><small>Nota & foto</small></div></div>
                  <div class="cat" onclick="openCat('lain')" id="cat-lain"><div class="icon">📋</div><div><strong>Lain-Lain</strong><small>Kategori lainnya</small></div></div>
                </div>

                <div id="category-form" style="margin-top:8px"></div>

                <div class="actions">
                  <button class="btn ghost" onclick="go(2)">Kembali</button>
                  <button class="btn primary" onclick="go(4)">Review & Submit</button>
                </div>
              </div>

              <div class="page" id="page-4">
                <div class="section-title">
                  <div class="avatar">📄</div>
                  <div>
                    <strong>Review & Submit</strong>
                    <div class="lead">Periksa data sebelum dikirim</div>
                  </div>
                </div>

                <div class="card" style="margin-bottom:16px">
                  <div style="font-weight:700; margin-bottom:12px;">Informasi Nota</div>
                  <div style="color:var(--muted);margin-top:4px">Tanggal: <span id="rev_tanggal"></span></div>
                  <div style="color:var(--muted);margin-top:4px">Lokasi: <span id="rev_lokasi"></span></div>
                  <div style="color:var(--muted);margin-top:4px" id="rev_tujuan_container">Tujuan: <span id="rev_tujuan"></span></div>
                </div>

                <div class="card" style="margin-bottom:16px">
                  <div style="font-weight:700; margin-bottom:8px;">Data Karyawan</div>
                  <div style="color:var(--muted);margin-top:4px">Nama: <span id="rev_nama"></span></div>
                  <div style="color:var(--muted);margin-top:4px">NIK: <span id="rev_nik"></span></div>
                  <div style="color:var(--muted);margin-top:4px">Departemen: <span id="rev_dept"></span></div>
                </div>

                <div class="card summary" id="rev_summary">
                  <div id="rev_rows"></div>
                  <div class="total" id="rev_total"></div>
                  <div id="rev_attachments" class="attachments" style="display:none"></div>
                </div>

                <div class="actions">
                  <button class="btn ghost" onclick="go(3)">Kembali</button>
                  <button class="btn primary" onclick="submitAll()">Submit</button>
                  <button class="btn reset" onclick="showResetModal()">Reset</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <div class="reset-modal" id="resetModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px">
      <div class="reset-content" style="background:white;border-radius:12px;width:100%;max-width:460px;overflow:hidden">
        <div class="reset-header" style="padding:20px;border-bottom:1px solid #e9ecef;text-align:center">
          <h3 style="margin:0;font-size:18px">Reset Form Reimbursement</h3>
        </div>
        <div class="reset-body" style="padding:20px;text-align:center">
          <p style="margin-bottom:16px;color:#666">Apakah Anda yakin ingin mengosongkan semua data yang sudah diisi?</p>
          <p style="font-size:13px;color:#999">Semua data termasuk biaya, keterangan, dan file upload akan dihapus.</p>
        </div>
        <div class="reset-actions" style="padding:16px;border-top:1px solid #e9ecef;display:flex;gap:8px;justify-content:center">
          <button class="btn ghost" onclick="closeResetModal()" style="padding:10px 20px">Batal</button>
          <button class="btn primary" onclick="resetForm()" style="padding:10px 20px;background:#dc2626">Ya, Reset Form</button>
        </div>
      </div>
    </div>

    <script>
      // injected from PHP
      const USERS = <?php echo $usersJson ?: '[]'; ?>;
      const TEMPLATE = <?php echo json_encode($templateObj, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
      const LOCK_USER = (String(TEMPLATE.lockUser).toLowerCase() === 'true');

      const API_SAVE_DRAFT = 'api/save_draft.php';
      const API_SUBMIT = 'api/submit.php';
      const API_GET_USERS = 'api/get_users.php';

      const MAX_FILE_SIZE_MB = <?php echo $maxFileSizeMb; ?>;
      const MAX_TOTAL_SIZE_MB = <?php echo $maxTotalSizeMb; ?>;

      const REQUIRED_SPO = "SPO";

      // --- state helpers ---
      function makeEmptyEntryFor(cat){
        const base = { biaya: '', file: null, fileSize: 0 };
        if(cat === 'bbm'){
          return Object.assign({}, base, { plat:'' }); // hanya plat
        }
        if(cat === 'entertain'){
          return Object.assign({}, base, { dengan:'', photo: null, photoSize: 0 });
        }
        if(cat === 'lain'){
          return Object.assign({}, base, { keterangan:'' });
        }
        // ===== PERUBAHAN: tambahkan field nama untuk kategori Makan =====
        if(cat === 'makan'){
          return Object.assign({}, base, { nama: '' });
        }
        return base;
      }

      function getInitialState() {
        const today = new Date().toISOString().split('T')[0];
        return {
          tanggal_nota: today,
          lokasi: 'Dalam Kota',
          tujuan: '',
          categories: {
            tol: { entries: [] },
            parkir: { entries: [] },
            makan: { entries: [] },
            hotel: { entries: [] },
            bbm: { entries: [] },
            entertain: { entries: [] },
            lain: { entries: [] }
          }
        };
      }

      function migrateLegacyState(s){
        try {
          const cats = ['tol','parkir','makan','hotel','bbm','entertain','lain'];
          cats.forEach(cat=>{
            const d = s.categories[cat];
            if(!d) return;
            if(d.hasOwnProperty('biaya') || d.hasOwnProperty('file') || d.hasOwnProperty('photo') || d.hasOwnProperty('plat')){
              const entry = makeEmptyEntryFor(cat);
              if(d.biaya) entry.biaya = d.biaya;
              if(d.file) { entry.file = d.file; entry.fileSize = d.fileSize || (d.file && d.file.size) || 0; }
              if(cat === 'entertain' && d.photo){ entry.photo = d.photo; entry.photoSize = d.photoSize || (d.photo && d.photo.size) || 0; }
              if(cat === 'bbm'){
                entry.plat = d.plat || '';
                // field lama diabaikan
              }
              if(cat === 'lain'){
                entry.keterangan = d.keterangan || '';
              }
              // ===== PERUBAHAN: untuk legacy state makan, mungkin ada nama? tidak, kita abaikan =====
              s.categories[cat] = { entries: [entry] };
            }
          });
        } catch(e){}
      }

      let STATE = getInitialState();
      migrateLegacyState(STATE);
      let currentCat = null;
      let totalFileSize = 0;

      const CATEGORY_NAMES = {
        tol: 'Tol', parkir: 'Parkir', makan: 'Makan', hotel: 'Hotel',
        bbm: 'BBM', entertain: 'Entertain', lain: 'Lain-Lain'
      };

      document.addEventListener('DOMContentLoaded', function(){
        try {
          if (sessionStorage && sessionStorage.getItem('spoValidated') === '1') {
            const ov = document.getElementById('spoOverlay');
            if (ov) ov.style.display = 'none';
          }
        } catch(e){}

        const sel = document.getElementById('userSelect');
        if(sel){
          sel.innerHTML = '<option value="">-- Pilih Nama --</option>';
          USERS.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.uid || u.nik || u.nama;
            opt.textContent = `${u.nama} (${u.departemen || '-'} • ${u.nik || '-'})`;
            opt.dataset.nik = u.nik || '';
            opt.dataset.dept = u.departemen || '';
            sel.appendChild(opt);
          });

          if (TEMPLATE.uid) {
            const optToSelect = Array.from(sel.options).find(o => o.value === String(TEMPLATE.uid));
            if (optToSelect) sel.value = optToSelect.value; else sel.value = '';
          } else {
            sel.value = '';
          }
          sel.disabled = true;
        }

        document.getElementById('inp_uid').value = TEMPLATE.uid || '';
        document.getElementById('inp_nik').value = TEMPLATE.nik || '';
        document.getElementById('inp_dept').value = TEMPLATE.dept || '';

        document.getElementById('simple-nama').textContent = TEMPLATE.nama || '—';
        document.getElementById('simple-nik').textContent = TEMPLATE.nik || '—';
        document.getElementById('simple-dept').textContent = TEMPLATE.dept || '—';
        document.getElementById('display-nama').textContent = TEMPLATE.nama || '—';
        document.getElementById('display-dept').textContent = TEMPLATE.nik ? `NIK: ${TEMPLATE.nik}` : 'NIK: —';

        document.getElementById('tanggal_nota').value = STATE.tanggal_nota;
        document.getElementById('lokasi').value = STATE.lokasi;
        document.getElementById('tujuan_wrap').style.display = 'none';

        applyLokasiRules();
        renderUser();

        const spoInput = document.getElementById('spoInput');
        if (spoInput) {
          spoInput.addEventListener('keydown', function(e){
            if (e.key === 'Enter') {
              e.preventDefault();
              checkSPO();
            }
          });
        }

        try {
          const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
          if (isCollapsed) document.getElementById('sidebar').classList.add('collapsed');
        } catch (e) {}
      });

      function logoutSPO(){
        if(!confirm("Keluar dari form?")) return;
        try { sessionStorage.removeItem('spoValidated'); } catch(e){}
        const input = document.getElementById("spoInput");
        if(input) input.value = "";
        const ov = document.getElementById("spoOverlay");
        if(ov) ov.style.display = "flex";
        window.location.href = 'login.php?logout=1';
      }

      function getSelectedUser(){
        const uid = (document.getElementById('inp_uid').value || '').trim();
        if(!uid) {
          const name = document.getElementById('display-nama')?.textContent || '';
          const nik = document.getElementById('inp_nik')?.value || '';
          const dept = document.getElementById('inp_dept')?.value || '';
          if(name || nik) return { uid: uid || '', nama: name, nik: nik, dept: dept };
          return null;
        }

        let u = USERS.find(x => String(x.uid) === String(uid) || String(x.nik) === String(uid));
        if(u){
          return { uid: u.uid || '', nama: u.nama || '', nik: u.nik || '', dept: u.departemen || '' };
        }

        const nama = document.getElementById('display-nama')?.textContent || '';
        const nik = document.getElementById('inp_nik')?.value || '';
        const dept = document.getElementById('inp_dept')?.value || '';
        return { uid: uid || '', nama: nama, nik: nik, dept: dept };
      }

      function onUserChange(silent = false) {
        const select = document.getElementById('userSelect');
        const uid = select.value || '';
        const opt = select.options[select.selectedIndex];

        const nama = opt ? opt.textContent.split(' (')[0] : '';
        const nik = opt ? (opt.dataset.nik || '') : '';
        const dept = opt ? (opt.dataset.dept || '') : '';

        document.getElementById('inp_uid').value = uid;
        document.getElementById('inp_nik').value = nik;
        document.getElementById('inp_dept').value = dept;

        document.getElementById('simple-nama').textContent = nama || '—';
        document.getElementById('simple-nik').textContent = nik || '—';
        document.getElementById('simple-dept').textContent = dept || '—';

        document.getElementById('display-nama').textContent = nama || '—';
        document.getElementById('display-dept').textContent = nik ? `NIK: ${nik}` : 'NIK: —';

        if (!silent && uid) console.log("Selected UID:", uid);
      }

      function renderUser(){
        const u = getSelectedUser();
        const nama = u?.nama || '—';
        const nik  = u?.nik  || '—';
        const dept = u?.dept || '—';
        document.getElementById('display-nama').textContent = nama;
        document.getElementById('display-dept').textContent = (dept !== '—') ? `${dept} • NIK: ${nik}` : `NIK: ${nik}`;
        document.getElementById('simple-nama').textContent = nama;
        document.getElementById('simple-nik').textContent  = nik;
        document.getElementById('simple-dept').textContent = dept;
        document.getElementById('inp_nik').value = nik;
        document.getElementById('inp_dept').value = dept;
      }

      function toggleSidebar(){
        const sb = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (window.innerWidth <= 900) {
          sb.classList.toggle('open');
          if (overlay) overlay.classList.toggle('active', sb.classList.contains('open'));
        } else {
          // desktop toggle collapsed
          sb.classList.toggle('collapsed');
          try { localStorage.setItem('sidebarCollapsed', sb.classList.contains('collapsed')); } catch (e) {}
        }
      }

      function closeSidebar() {
        const sb = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sb.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
      }

      function go(step){
        if(step > 1){
          const u = getSelectedUser();
          if(!u){
            alert('Data user tidak ditemukan. Silakan logout dan login kembali.');
            return;
          }
        }
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        document.getElementById('page-'+step).classList.add('active');
        document.querySelectorAll('.step').forEach((s, i) => {
          s.classList.toggle('active', (i + 1) === step);
        });
        if(step === 4) buildReview();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }

      function onLokasiChange(){
        const v = document.getElementById('lokasi').value;
        STATE.lokasi = v;
        const wrap = document.getElementById('tujuan_wrap');
        wrap.style.display = v === 'Luar Kota' ? 'block' : 'none';
        if (v !== 'Luar Kota') document.getElementById('luar_kota_tujuan').value = '';
        applyLokasiRules();
        saveFormData();
      }
      function saveFormData() {
        STATE.tanggal_nota = document.getElementById('tanggal_nota').value || '';
        STATE.lokasi = document.getElementById('lokasi').value || 'Dalam Kota';
        STATE.tujuan = document.getElementById('luar_kota_tujuan')?.value || '';
      }

      function applyLokasiRules(){
        const isDalam = (STATE.lokasi === 'Dalam Kota');
        document.getElementById('cat-makan')?.classList.toggle('hidden', isDalam);
        document.getElementById('cat-hotel')?.classList.toggle('hidden', isDalam);
        if(isDalam){
          STATE.categories.makan = { entries: [] };
          STATE.categories.hotel = { entries: [] };
          clearCategoryBadge('makan');
          clearCategoryBadge('hotel');
          if(currentCat === 'makan' || currentCat === 'hotel') closeCatForm();
        }
      }

      function clearCategoryBadge(cat){
        const el = document.getElementById('cat-' + cat);
        if(!el) return;
        el.classList.remove('has-data');
        const badge = el.querySelector('.cat-data-badge');
        if(badge) badge.remove();
      }

      // ================== FILE HANDLING ==================
      async function handleFileUpload(inputId, category, index, field = 'file', isPhoto = false) {
        const input = document.getElementById(inputId);
        if (!input || !input.files || !input.files[0]) {
          console.warn('No file selected');
          return;
        }
        const file = input.files[0];
        const fileSizeMB = file.size / (1024 * 1024);
        
        // Hapus warning lama jika ada
        const warningDiv = document.createElement('div');
        warningDiv.className = 'file-size-warning';
        warningDiv.style.display = 'block';
        const oldWarning = input.parentNode.querySelector('.file-size-warning');
        if (oldWarning) oldWarning.remove();
        input.parentNode.appendChild(warningDiv);

        if (fileSizeMB > MAX_FILE_SIZE_MB) {
          if (!file.type.startsWith('image/')) {
            warningDiv.textContent = `❌ File terlalu besar: ${fileSizeMB.toFixed(2)}MB (maks ${MAX_FILE_SIZE_MB}MB). File bukan gambar tidak bisa dikompres.`;
            warningDiv.style.color = '#dc2626';
            input.value = '';
            return;
          }
          warningDiv.textContent = `⏳ Mengkompres gambar ${fileSizeMB.toFixed(2)}MB...`;
          warningDiv.style.color = '#f59e0b';
          try {
            const compressedFile = await compressImage(file, MAX_FILE_SIZE_MB);
            const newSizeMB = compressedFile.size / (1024 * 1024);
            warningDiv.textContent = `✓ Berhasil dikompres: ${newSizeMB.toFixed(2)}MB`;
            warningDiv.style.color = '#10b981';
            
            if(!STATE.categories[category] || !Array.isArray(STATE.categories[category].entries)) 
              STATE.categories[category] = { entries: [] };
            const entries = STATE.categories[category].entries;
            if(!entries[index]) entries[index] = makeEmptyEntryFor(category);
            if(isPhoto){
              entries[index].photo = compressedFile;
              entries[index].photoSize = compressedFile.size;
            } else {
              entries[index].file = compressedFile;
              entries[index].fileSize = compressedFile.size;
            }
          } catch (error) {
            warningDiv.textContent = `❌ Gagal kompres: ${error.message}`;
            warningDiv.style.color = '#dc2626';
            input.value = '';
          }
        } else {
          warningDiv.textContent = `✓ File OK: ${fileSizeMB.toFixed(2)}MB`;
          warningDiv.style.color = '#10b981';
          if(!STATE.categories[category] || !Array.isArray(STATE.categories[category].entries)) 
            STATE.categories[category] = { entries: [] };
          const entries = STATE.categories[category].entries;
          if(!entries[index]) entries[index] = makeEmptyEntryFor(category);
          if(isPhoto){
            entries[index].photo = file;
            entries[index].photoSize = file.size;
          } else {
            entries[index].file = file;
            entries[index].fileSize = file.size;
          }
        }
        updateTotalFileSize();
        setTimeout(() => { if (warningDiv.parentNode) warningDiv.remove(); }, 3000);
        updateCategoryBadge(category);
        renderCategoryForm(currentCat); // re-render untuk tampilkan nama file
      }

      function compressImage(file, maxSizeMB) {
        return new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
              const canvas = document.createElement('canvas');
              let width = img.width;
              let height = img.height;
              const maxDimension = 1200;
              if (width > maxDimension || height > maxDimension) {
                if (width > height) {
                  height = Math.round(height * maxDimension / width);
                  width = maxDimension;
                } else {
                  width = Math.round(width * maxDimension / height);
                  height = maxDimension;
                }
              }
              canvas.width = width;
              canvas.height = height;
              const ctx = canvas.getContext('2d');
              ctx.drawImage(img, 0, 0, width, height);
              let quality = 0.9;
              function tryCompress() {
                canvas.toBlob((blob) => {
                  const sizeMB = blob.size / (1024 * 1024);
                  if (sizeMB <= maxSizeMB || quality <= 0.3) {
                    const compressedFile = new File([blob], file.name, { type: file.type, lastModified: Date.now() });
                    resolve(compressedFile);
                  } else {
                    quality -= 0.1;
                    tryCompress();
                  }
                }, file.type, quality);
              }
              tryCompress();
            };
            img.onerror = () => reject(new Error('Gagal memuat gambar untuk kompresi'));
            img.src = e.target.result;
          };
          reader.onerror = () => reject(new Error('Gagal membaca file'));
          reader.readAsDataURL(file);
        });
      }

      function updateTotalFileSize() {
        totalFileSize = 0;
        Object.keys(STATE.categories).forEach(cat => {
          const catObj = STATE.categories[cat];
          if(!catObj || !Array.isArray(catObj.entries)) return;
          catObj.entries.forEach(ent=>{
            if(ent.fileSize) totalFileSize += ent.fileSize;
            if(ent.photoSize) totalFileSize += ent.photoSize;
          });
        });
        return totalFileSize;
      }

      function showResetModal(){ document.getElementById('resetModal').classList.add('active'); document.body.style.overflow='hidden'; }
      function closeResetModal(){ document.getElementById('resetModal').classList.remove('active'); document.body.style.overflow='auto'; }
      function resetForm(){
        try { revokeAllPreviewUrls(); } catch(e){}
        STATE = getInitialState();
        document.getElementById('tanggal_nota').value = STATE.tanggal_nota;
        document.getElementById('lokasi').value = STATE.lokasi;
        document.getElementById('luar_kota_tujuan').value = '';
        document.getElementById('tujuan_wrap').style.display = 'none';
        document.getElementById('category-form').innerHTML = '';
        currentCat = null;
        Object.keys(STATE.categories).forEach(cat => {
          const el = document.getElementById('cat-' + cat);
          if(!el) return;
          el.classList.remove('has-data','hidden');
          const badge = el.querySelector('.cat-data-badge');
          if(badge) badge.remove();
        });
        applyLokasiRules();
        closeResetModal();
        go(1);
        alert('Form telah direset.');
      }

      function validateNumberInput(input) {
        const value = input.value;
        const errorId = input.id + '_error';
        let errorElement = document.getElementById(errorId);
        if (!errorElement) {
          errorElement = document.createElement('div');
          errorElement.id = errorId;
          errorElement.style.color = '#dc2626';
          errorElement.style.fontSize = '12px';
          errorElement.style.marginTop = '6px';
          errorElement.style.display = 'none';
          input.parentNode.insertBefore(errorElement, input.nextSibling);
        }
        if (value === '') {
          input.style.borderColor = '#e6e6e6';
          errorElement.style.display = 'none';
          return true;
        }
        const normalized = String(value).replace(/[.,\s]/g, '');
        if (isNaN(normalized) || normalized.trim() === '') {
          input.style.borderColor = '#dc2626';
          errorElement.textContent = 'Harus berupa angka';
          errorElement.style.display = 'block';
          return false;
        }
        if (parseFloat(normalized) < 0) {
          input.style.borderColor = '#dc2626';
          errorElement.textContent = 'Tidak boleh negatif';
          errorElement.style.display = 'block';
          return false;
        }
        input.style.borderColor = '#e6e6e6';
        errorElement.style.display = 'none';
        return true;
      }

      // ----- Category modal: add/remove entries + render -----
      function openCat(cat){
        if(STATE.lokasi === 'Dalam Kota' && (cat === 'makan' || cat === 'hotel')) return alert('Untuk Dalam Kota, kategori Makan & Hotel tidak tersedia.');
        currentCat = cat;
        if(!STATE.categories[cat] || !Array.isArray(STATE.categories[cat].entries)) STATE.categories[cat] = { entries: [] };
        if(STATE.categories[cat].entries.length === 0) STATE.categories[cat].entries.push(makeEmptyEntryFor(cat));
        renderCategoryForm(cat);
        setTimeout(()=> document.getElementById('category-form').scrollIntoView({behavior:'smooth'}), 50);
      }

      function addEntry(cat){
        if(!STATE.categories[cat]) STATE.categories[cat] = { entries: [] };
        STATE.categories[cat].entries.push(makeEmptyEntryFor(cat));
        renderCategoryForm(cat);
      }
      function removeEntry(cat, idx){
        if(!confirm('Hapus entri ini?')) return;
        if(!STATE.categories[cat]) return;
        STATE.categories[cat].entries.splice(idx,1);
        if(STATE.categories[cat].entries.length === 0) STATE.categories[cat].entries.push(makeEmptyEntryFor(cat));
        renderCategoryForm(cat);
        updateCategoryBadge(cat);
      }

      function renderSelectedFileHintInline(file, size){
        const name = file && file.name ? file.name : 'File dipilih';
        const fileSize = size ? formatBytes(size) : '';
        return `<div style="margin-top:6px;font-size:13px;color:var(--muted)">${escapeHtml(name)} ${fileSize? '• '+fileSize : ''}</div>`;
      }

      function renderCategoryForm(cat){
        const data = STATE.categories[cat];
        const catName = CATEGORY_NAMES[cat] || cat;
        let html = `
          <div class="section-title" style="margin-bottom:12px">
            <div class="avatar">${getCategoryIcon(cat)}</div>
            <div>
              <strong>${catName}</strong>
              <div class="lead">Isi biaya dan upload bukti. Bisa menambah beberapa entri.</div>
            </div>
          </div>
          <div class="card" style="margin-top:12px">
        `;
        // Tambahkan class untuk tombol aksi agar responsif
        html += `<div class="cat-form-actions" style="display:flex;gap:8px;margin-bottom:12px"><button class="btn ghost" onclick="addEntry('${cat}')">+ Tambah Entri</button><button class="btn" onclick="saveCat(event)">Simpan Semua Entri</button><button class="btn primary" onclick="closeCatForm()">Tutup</button></div>`;
        (data.entries || []).forEach((entry, idx) => {
          html += `<div style="border:1px dashed #eee;padding:12px;border-radius:10px;margin-bottom:10px">`;
          html += `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><div style="font-weight:700">Entri ke-${idx+1}</div><div><button class="btn ghost" onclick="removeEntry('${cat}',${idx})">Hapus</button></div></div>`;

          html += `<label>Biaya (IDR)</label>`;
          html += `<input type="text" id="${cat}_biaya_${idx}" value="${escapeHtml(entry.biaya||'')}" placeholder="Masukkan jumlah biaya" oninput="validateNumberInput(this)" onblur="validateNumberInput(this)" onchange="STATE.categories['${cat}'].entries[${idx}].biaya=this.value">`;

          html += `<label style="margin-top:8px">Upload File Bukti (max ${MAX_FILE_SIZE_MB}MB)</label>`;
          html += `<input type="file" id="${cat}_file_${idx}" accept=".jpg,.jpeg,.png,.pdf" onchange="handleFileUpload('${cat}_file_${idx}','${cat}',${idx},'file', false)">`;
          if(entry.file) html += renderSelectedFileHintInline(entry.file, entry.fileSize);

          // ===== PERUBAHAN: input untuk Nama Orang pada kategori Makan =====
          if(cat === 'makan'){
            html += `<label style="margin-top:8px">Nama Orang (yang dijamu / rekan)</label>`;
            html += `<input type="text" id="makan_nama_${idx}" value="${escapeHtml(entry.nama||'')}" placeholder="Masukkan nama" onchange="STATE.categories['makan'].entries[${idx}].nama=this.value">`;
          }

          if(cat === 'bbm'){
            html += `<label style="margin-top:8px">Plat Nomor</label>`;
            html += `<input type="text" id="bbm_plat_${idx}" value="${escapeHtml(entry.plat||'')}" placeholder="B 1234 ABC" onchange="STATE.categories['bbm'].entries[${idx}].plat=this.value">`;
            // Tidak ada field KM, Liter, Harga, Realisasi
          }

          if(cat === 'entertain'){
            html += `<label style="margin-top:8px">Entertain Dengan</label>`;
            html += `<input type="text" id="ent_with_${idx}" value="${escapeHtml(entry.dengan||'')}" onchange="STATE.categories['entertain'].entries[${idx}].dengan=this.value">`;

            html += `<label style="margin-top:8px">Foto Lokasi (opsional, max ${MAX_FILE_SIZE_MB}MB)</label>`;
            html += `<input type="file" id="ent_photo_${idx}" accept=".jpg,.jpeg,.png" onchange="handleFileUpload('ent_photo_${idx}', 'entertain', ${idx}, 'photo', true)">`;
            if(entry.photo) html += renderSelectedFileHintInline(entry.photo, entry.photoSize);
          }

          if(cat === 'lain'){
            html += `<label style="margin-top:8px">Keterangan (wajib)</label>`;
            html += `<textarea id="lain_ket_${idx}" placeholder="Isi keterangan pengeluaran lain-lain" rows="2" onchange="STATE.categories['lain'].entries[${idx}].keterangan=this.value">${escapeHtml(entry.keterangan||'')}</textarea>`;
          }

          html += `</div>`;
        });

        html += `</div>`;
        document.getElementById('category-form').innerHTML = html;

        // Tidak ada perhitungan otomatis untuk BBM
      }

      function getCategoryIcon(cat){
        return ({tol:'🚗',parkir:'🅿️',makan:'🍽',hotel:'🏨',bbm:'⛽',entertain:'🎉',lain:'📋'})[cat] || '📄';
      }

      function closeCatForm(){ document.getElementById('category-form').innerHTML=''; currentCat=null; }

      async function saveCat(e){
        if (e && e.preventDefault) e.preventDefault();
        const cat = currentCat;
        if(!cat) return;
        const u = getSelectedUser();
        if(!u){ alert("Data user tidak ditemukan"); return; }
        if(!STATE.tanggal_nota){ alert("Tanggal wajib diisi dulu"); return; }
        const catObj = STATE.categories[cat];
        if(!catObj || !Array.isArray(catObj.entries) || catObj.entries.length === 0) { alert('Belum ada entri untuk disimpan'); return; }

        for(let i=0;i<catObj.entries.length;i++){
          const ent = catObj.entries[i];
          if(cat === 'lain' && (!ent.keterangan || ent.keterangan.trim()==='')) { alert('Keterangan wajib diisi untuk Lain-Lain'); return; }
          if(ent.biaya && !/^[\d\.,\s]+$/.test(String(ent.biaya))) { alert('Biaya harus berupa angka (atau kosong)'); return; }
        }

        const formData = new FormData();
        formData.append('category', cat);
        formData.append('nama', u.nama || '');
        formData.append('nik', u.nik || '');
        formData.append('dept', u.dept || '');
        formData.append('tanggal', STATE.tanggal_nota || '');
        formData.append('perjalananDinas', STATE.lokasi || '');
        formData.append('tujuan', STATE.tujuan || '-');

        catObj.entries.forEach((ent, idx)=>{
          if(cat === 'tol') formData.append('biayaTol[]', ent.biaya || '');
          if(cat === 'parkir') formData.append('biayaParkir[]', ent.biaya || '');
          if(cat === 'makan') {
            formData.append('biayaMakan[]', ent.biaya || '');
            // ===== PERUBAHAN: kirim nama makan =====
            formData.append('makanNama[]', ent.nama || '');
          }
          if(cat === 'hotel') formData.append('biayaHotel[]', ent.biaya || '');
          if(cat === 'bbm') {
            formData.append('biayaBensin[]', ent.biaya || '');
            formData.append('platNumber[]', ent.plat || '');
            // field km, liter, harga, tgl dihapus
          }
          if(cat === 'entertain'){
            formData.append('biayaEntertain[]', ent.biaya || '');
            formData.append('entertainDengan[]', ent.dengan || '');
          }
          if(cat === 'lain'){
            formData.append('totalBiayaLain[]', ent.biaya || '');
            formData.append('keterangan[]', ent.keterangan || '');
          }

          if(ent.file) {
            const fieldName = (cat === 'tol') ? 'tol_file[]' :
                              (cat === 'bbm') ? 'bbm_file[]' :
                              (cat === 'hotel') ? 'hotel_file[]' :
                              (cat === 'makan') ? 'makan_file[]' :
                              (cat === 'parkir') ? 'parkir_file[]' :
                              (cat === 'entertain') ? 'entertain_file[]' :
                              (cat === 'lain') ? 'lain_file[]' : `${cat}_file[]`;
            formData.append(fieldName, ent.file, ent.file.name);
          }
          if(cat === 'entertain' && ent.photo){
            formData.append('ent_photo[]', ent.photo, ent.photo.name);
          }
        });

        try {
          const res = await fetch(API_SAVE_DRAFT, { method: 'POST', body: formData });
          const json = await res.json();
          if (json && json.success) {
            alert("✅ Draft + File berhasil disimpan");
          } else {
            alert("Gagal simpan: " + (json?.message || 'Unknown'));
          }
        } catch (err) {
          alert("Gagal simpan: " + err.message);
        }
        closeCatForm();
        updateCategoryBadge(cat);
      }

      function lockTanggalIfNeeded(){ return; }

      function updateCategoryBadge(cat){
        const catEl = document.getElementById('cat-' + cat);
        const d = STATE.categories[cat];
        const hasData = d && Array.isArray(d.entries) && d.entries.some(ent => {
          if(!ent) return false;
          if((ent.biaya||'').toString().trim() !== '') return true;
          if(ent.file) return true;
          if(cat === 'entertain' && ent.photo) return true;
          if(cat === 'lain' && (ent.keterangan||'').trim() !== '') return true;
          if(cat === 'bbm' && (ent.plat||'').trim() !== '') return true;
          // ===== PERUBAHAN: nama makan dianggap sebagai data =====
          if(cat === 'makan' && ent.nama && ent.nama.trim() !== '') return true;
          return false;
        });
        const badge = catEl.querySelector('.cat-data-badge');
        if(hasData && !badge){
          catEl.classList.add('has-data');
          const b = document.createElement('div');
          b.className = 'cat-data-badge';
          b.innerHTML = '✓';
          catEl.appendChild(b);
        } else if(!hasData && badge){
          catEl.classList.remove('has-data');
          if(badge) badge.remove();
        }
      }

      const PREVIEW_URLS = new Set();
      function revokeAllPreviewUrls(){ PREVIEW_URLS.forEach(url => { try{ URL.revokeObjectURL(url); } catch(e){} }); PREVIEW_URLS.clear(); }

      function buildReview(){
        saveFormData();
        applyLokasiRules();
        revokeAllPreviewUrls();
        document.getElementById('rev_tanggal').textContent = formatDate(STATE.tanggal_nota);
        document.getElementById('rev_lokasi').textContent  = STATE.lokasi;
        if(STATE.lokasi === 'Luar Kota' && STATE.tujuan){
          document.getElementById('rev_tujuan').textContent = STATE.tujuan;
          document.getElementById('rev_tujuan_container').style.display = 'block';
        } else {
          document.getElementById('rev_tujuan_container').style.display = 'none';
        }
        const u = getSelectedUser();
        document.getElementById('rev_nama').textContent = u?.nama || '-';
        document.getElementById('rev_nik').textContent  = u?.nik  || '-';
        document.getElementById('rev_dept').textContent = u?.dept || '-';
        const container = document.getElementById('rev_rows');
        container.innerHTML = '';
        let total = 0;
        const rows = [];

        Object.keys(STATE.categories).forEach(cat => {
          if(STATE.lokasi === 'Dalam Kota' && (cat === 'makan' || cat === 'hotel')) return;
          const catObj = STATE.categories[cat];
          if(!catObj || !Array.isArray(catObj.entries)) return;
          catObj.entries.forEach(ent => {
            if(!ent) return;
            const biaya = ent.biaya ? (parseFloat(String(ent.biaya).replace(/[^\d.-]/g,'')) || 0) : 0;
            let info = '';
            if(cat === 'lain' && ent.keterangan) info = ent.keterangan;
            if(cat === 'bbm') {
              info = `Plat: ${ent.plat || '-'}`; // hanya plat
            }
            if(cat === 'entertain' && ent.dengan) info = `Dengan: ${ent.dengan}`;
            // ===== PERUBAHAN: tampilkan nama orang untuk Makan =====
            if(cat === 'makan' && ent.nama) info = `Nama: ${ent.nama}`;
            const hasAny = biaya>0 || ent.file || (cat==='entertain' && ent.photo) || (cat==='lain' && ent.keterangan && ent.keterangan.trim()!=='') || (cat==='makan' && ent.nama && ent.nama.trim()!=='');
            if(hasAny){
              rows.push({cat, label: CATEGORY_NAMES[cat], info, biaya, file: ent.file, photo: ent.photo});
              total += biaya;
            }
          });
        });

        if(rows.length === 0){
          container.innerHTML = '<div style="text-align:center;color:var(--muted);padding:20px">Belum ada data</div>';
        } else {
          rows.forEach(r => {
            const div = document.createElement('div');
            div.className = 'row';
            div.innerHTML = `
              <div>
                <div style="font-weight:700">${escapeHtml(r.label)}</div>
                ${r.info ? `<div style="color:var(--muted);font-size:12px;margin-top:2px">${escapeHtml(r.info)}</div>` : ''}
              </div>
              <div style="font-weight:700">${formatIDR(r.biaya)}</div>
            `;
            container.appendChild(div);
          });
        }
        document.getElementById('rev_total').textContent = rows.length ? `Total: ${formatIDR(total)}` : '';
        const attWrap = document.getElementById('rev_attachments');
        attWrap.innerHTML = '';
        let attCount = 0;
        Object.keys(STATE.categories).forEach(cat => {
          if(STATE.lokasi === 'Dalam Kota' && (cat === 'makan' || cat === 'hotel')) return;
          const catObj = STATE.categories[cat];
          if(!catObj || !Array.isArray(catObj.entries)) return;
          catObj.entries.forEach(ent=>{
            if(ent && ent.file){
              attWrap.appendChild(makeAttachmentCard(`${CATEGORY_NAMES[cat]} - Bukti`, ent.file));
              attCount++;
            }
            if(cat === 'entertain' && ent && ent.photo){
              attWrap.appendChild(makeAttachmentCard(`Entertain - Foto Lokasi`, ent.photo));
              attCount++;
            }
          });
        });
        attWrap.style.display = attCount ? 'grid' : 'none';
      }

      function makeAttachmentCard(title, file){
        const name = file?.name || 'file';
        const type = (file?.type || '').toLowerCase();
        const url = URL.createObjectURL(file);
        PREVIEW_URLS.add(url);
        const size = file?.size ? formatBytes(file.size) : '';
        const isImg = type.startsWith('image/');
        const isPdf = type === 'application/pdf' || name.toLowerCase().endsWith('.pdf');
        let preview = `<div style="color:var(--muted);font-size:12px">Preview tidak tersedia.</div>`;
        if (isImg){
          preview = `<div class="att-preview"><img src="${url}" alt="${escapeHtml(name)}"></div>`;
        } else if (isPdf){
          preview = `<div class="att-preview"><iframe src="${url}#view=FitH"></iframe></div>`;
        } else {
          preview = `
            <div class="att-preview" style="padding:14px">
              <div style="font-weight:700;font-size:13px">📎 ${escapeHtml(name)}</div>
              <div style="color:var(--muted);font-size:12px;margin-top:6px">${escapeHtml(type || 'unknown')} ${size ? '• ' + size : ''}</div>
            </div>
          `;
        }
        const card = document.createElement('div');
        card.className = 'att-card';
        card.innerHTML = `
          <div class="att-head">
            <div>
              <div class="att-title">${escapeHtml(title)}</div>
              <div class="att-meta">${escapeHtml(name)} ${size ? '• ' + size : ''}</div>
            </div>
          </div>
          <div class="att-body">
            ${preview}
            <div class="att-actions">
              <a class="open" href="${url}" target="_blank" rel="noopener">Buka</a>
              <a class="dl" href="${url}" download="${escapeHtml(name)}">Download</a>
            </div>
          </div>
        `;
        return card;
      }

      function formatBytes(bytes){
        const b = Number(bytes) || 0;
        if(!b) return '';
        const units = ['B','KB','MB','GB'];
        let i = 0, val = b;
        while(val >= 1024 && i < units.length-1){ val /= 1024; i++; }
        return `${val.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
      }

      function fileToDataUrl(file){
        return new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onload = () => resolve(reader.result);
          reader.onerror = reject;
          reader.readAsDataURL(file);
        });
      }

      // ================== SUBMIT ALL (dengan debugging) ==================
      async function submitAll(){
        const submitBtn = document.querySelector('#page-4 .btn.primary');
        const originalText = submitBtn ? submitBtn.textContent : 'Kirim';
        if(submitBtn){ submitBtn.innerHTML = '<span class="spinner"></span> Mengirim...'; submitBtn.disabled = true; }
        try {
          saveFormData();
          applyLokasiRules();
          const u = getSelectedUser();
          if(!u){
            alert('Data user tidak ditemukan. Silakan logout dan login kembali.');
            if(submitBtn){ submitBtn.innerHTML = originalText; submitBtn.disabled = false; }
            go(1);
            return;
          }
          if(!STATE.tanggal_nota){
            alert('Tanggal nota harus diisi');
            if(submitBtn){ submitBtn.innerHTML = originalText; submitBtn.disabled = false; }
            return;
          }
          const totalSize = updateTotalFileSize();
          const totalSizeMB = totalSize / (1024 * 1024);
          if (totalSizeMB > MAX_TOTAL_SIZE_MB) {
            if (!confirm(`⚠️ Total ukuran file ${totalSizeMB.toFixed(2)}MB melebihi batas ${MAX_TOTAL_SIZE_MB}MB. Lanjutkan?`)) {
              if(submitBtn){ submitBtn.innerHTML = originalText; submitBtn.disabled = false; }
              return;
            }
          }

          const c = STATE.categories;
          let hasData = false;
          Object.keys(c).forEach(cat=>{
            if(STATE.lokasi === 'Dalam Kota' && (cat === 'makan' || cat === 'hotel')) return;
            const catObj = c[cat];
            if(!catObj || !Array.isArray(catObj.entries)) return;
            catObj.entries.forEach(ent=>{
              const biayaNum = parseFloat(String(ent.biaya || "0").replace(/[^\d.-]/g,'')) || 0;
              if (biayaNum > 0) hasData = true;
              if (ent.file) hasData = true;
              if (cat === 'entertain' && ent.photo) hasData = true;
              if (cat === 'lain' && ent.keterangan && ent.keterangan.trim() !== '') hasData = true;
              // ===== PERUBAHAN: nama makan juga dianggap data =====
              if (cat === 'makan' && ent.nama && ent.nama.trim() !== '') hasData = true;
            });
          });

          if(!hasData){
            alert('Minimal satu kategori harus diisi (biaya atau file atau nama).');
            if(submitBtn){ submitBtn.innerHTML = originalText; submitBtn.disabled = false; }
            return;
          }
          if(!confirm('Kirim pengajuan ini?')){
            if(submitBtn){ submitBtn.innerHTML = originalText; submitBtn.disabled = false; }
            return;
          }

          const formData = new FormData();
          formData.append('uid', u.uid || '');
          formData.append('nama', u.nama || '');
          formData.append('namaLengkap', u.nama || '');
          formData.append('nik', u.nik || '');
          formData.append('dept', u.dept || '');
          formData.append('tanggal', STATE.tanggal_nota || '');
          formData.append('perjalananDinas', (STATE.lokasi === 'Luar Kota' ? 'Luar Kota' : 'Dalam Kota'));
          formData.append('tujuan', STATE.tujuan && STATE.tujuan.trim() !== '' ? STATE.tujuan : '-');

          Object.keys(STATE.categories).forEach(cat=>{
            if(STATE.lokasi === 'Dalam Kota' && (cat === 'makan' || cat === 'hotel')) return;
            const catObj = STATE.categories[cat];
            if(!catObj || !Array.isArray(catObj.entries)) return;
            catObj.entries.forEach(ent=>{
              if(cat === 'tol') {
                if(ent.biaya) formData.append('biayaTol[]', ent.biaya);
                if(ent.file) formData.append('tol_file[]', ent.file, ent.file.name);
              }
              if(cat === 'bbm'){
                if(ent.biaya) formData.append('biayaBensin[]', ent.biaya);
                if(ent.plat) formData.append('platNumber[]', ent.plat);
                if(ent.file) formData.append('bbm_file[]', ent.file, ent.file.name);
              }
              if(cat === 'hotel'){
                if(ent.biaya) formData.append('biayaHotel[]', ent.biaya);
                if(ent.file) formData.append('hotel_file[]', ent.file, ent.file.name);
              }
              if(cat === 'makan'){
                if(ent.biaya) formData.append('biayaMakan[]', ent.biaya);
                // ===== PERUBAHAN: kirim nama makan =====
                if(ent.nama) formData.append('makanNama[]', ent.nama);
                if(ent.file) formData.append('makan_file[]', ent.file, ent.file.name);
              }
              if(cat === 'parkir'){
                if(ent.biaya) formData.append('biayaParkir[]', ent.biaya);
                if(ent.file) {
                  if (ent.file instanceof File) {
                    formData.append('parkir_file[]', ent.file, ent.file.name);
                  } else {
                    console.warn('File untuk parkir entri tidak valid:', ent.file);
                  }
                }
              }
              if(cat === 'entertain'){
                if(ent.dengan) formData.append('entertainDengan[]', ent.dengan);
                if(ent.biaya) formData.append('biayaEntertain[]', ent.biaya);
                if(ent.file) formData.append('entertain_file[]', ent.file, ent.file.name);
                if(ent.photo) formData.append('ent_photo[]', ent.photo, ent.photo.name);
              }
              if(cat === 'lain'){
                if(ent.biaya) formData.append('totalBiayaLain[]', ent.biaya);
                if(ent.keterangan) formData.append('keterangan[]', ent.keterangan);
                if(ent.file) formData.append('lain_file[]', ent.file, ent.file.name);
              }
            });
          });

          // Debug: log daftar file yang dikirim
          console.log('Mengirim file:');
          for (let pair of formData.entries()) {
            if (pair[1] instanceof File) {
              console.log(pair[0], pair[1].name, pair[1].size);
            }
          }

          const res = await fetch(API_SUBMIT, { method: 'POST', body: formData });
          
          // Baca respons sebagai teks dulu untuk debugging
          const responseText = await res.text();
          console.log('Respons server (mentah):', responseText);
          
          // Coba parse JSON
          try {
            const json = JSON.parse(responseText);
            if (json && json.success) {
              alert('✅ Pengajuan berhasil dikirim!');
              resetForm();
            } else {
              alert('❌ Gagal: ' + (json?.message || 'Unknown error'));
            }
          } catch (e) {
            // Jika bukan JSON, tampilkan teks mentah
            alert('❌ Gagal: respons server bukan JSON. Lihat console untuk detail.');
            console.error('Respons tidak valid:', responseText);
          }

        } catch (err) {
          alert('❌ Error: ' + err.message);
        } finally {
          if(submitBtn){ submitBtn.innerHTML = originalText; submitBtn.disabled = false; }
        }
      }

      function checkSPO(){
        const input = document.getElementById("spoInput");
        if(!input) return;
        const val = input.value.trim();
        if(!val){ alert("SPO wajib diisi"); return; }
        if(val !== REQUIRED_SPO){ alert("SPO salah!"); return; }
        try { sessionStorage.setItem('spoValidated','1'); } catch(e){}
        const ov = document.getElementById('spoOverlay');
        if(ov) ov.style.display = 'none';
      }

      function formatDate(dateStr){
        if(!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('id-ID', { day:'2-digit', month:'long', year:'numeric' });
      }
      function formatIDR(n){
        const num = Number(n) || 0;
        return new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', minimumFractionDigits:0 }).format(num);
      }
      function escapeHtml(str){
        if(str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, s => ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[s]));
      }
    </script>
  </body>
  </html>