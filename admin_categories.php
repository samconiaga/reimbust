<?php
// admin_categories.php
// Admin UI untuk kelola parcel categories dan manajemen users.

session_start();

// Cek session admin
if (empty($_SESSION['user_id']) || (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin')) {
    header('Location: login.php');
    exit;
}

// Koneksi DB
if (file_exists(__DIR__ . '/db.php')) include_once __DIR__ . '/db.php';
if (!isset($conn) && file_exists(__DIR__ . '/config.php')) include_once __DIR__ . '/config.php';
if (!isset($conn)) {
    $conn = new mysqli('127.0.0.1', 'root', '', 'reimb_db');
    if ($conn->connect_errno) die("Koneksi DB gagal: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function slugify($s){
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9\-]+/', '-', $s);
    $s = trim($s, '-');
    return $s ?: substr(bin2hex(random_bytes(4)), 0, 8);
}

// CSRF token
if (empty($_SESSION['admin_token'])) $_SESSION['admin_token'] = bin2hex(random_bytes(16));
$token = $_SESSION['admin_token'];
$flash = '';
$activeTab = $_GET['tab'] ?? 'categories';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $t = $_POST['token'] ?? '';
    
    if ($t !== $token) {
        $flash = 'Token invalid. Coba lagi.';
    } else {
        // ==========================================
        // ACTION: KATEGORI PARCEL
        // ==========================================
        if ($action === 'add_category') {
            $name = trim((string)($_POST['name'] ?? ''));
            $slug = trim((string)($_POST['slug'] ?? ''));
            $enabled = isset($_POST['enabled']) ? 1 : 0;
            
            if ($name === '') {
                $flash = 'Nama kategori tidak boleh kosong.';
            } else {
                if ($slug === '') $slug = slugify($name);
                $stmt = $conn->prepare("INSERT INTO parcel_categories (name, slug, enabled) VALUES (?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('ssi', $name, $slug, $enabled);
                    if ($stmt->execute()) $flash = 'Kategori berhasil ditambahkan.';
                    else $flash = 'Gagal menambahkan: ' . $stmt->error;
                    $stmt->close();
                } else {
                    $flash = 'DB error: ' . $conn->error;
                }
            }
            $activeTab = 'categories';

        } elseif ($action === 'toggle_category') {
            $id = (int)($_POST['id'] ?? 0);
            $enabled = (int)($_POST['enabled'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE parcel_categories SET enabled = ? WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('ii', $enabled, $id);
                    $stmt->execute();
                    $stmt->close();
                    $flash = 'Status kategori diperbarui.';
                } else $flash = 'DB error: ' . $conn->error;
            }
            $activeTab = 'categories';

        } elseif ($action === 'delete_category') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM parcel_categories WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();
                    $flash = 'Kategori dihapus.';
                } else $flash = 'DB error: ' . $conn->error;
            }
            $activeTab = 'categories';
        }
        
        // ==========================================
        // ACTION: MANAJEMEN USER
        // ==========================================
        elseif ($action === 'add_user') {
            $nik = trim($_POST['nik'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $nama = trim($_POST['nama'] ?? '');
            $departemen = trim($_POST['departemen'] ?? '');
            $cabang = trim($_POST['cabang'] ?? '');
            $jabatan = trim($_POST['jabatan'] ?? '');
            $singkatan_nama = trim($_POST['singkatan_nama'] ?? '');
            $password_raw = $_POST['password'] ?? '';
            $role = trim($_POST['role'] ?? 'user');
            
            if ($nik === '' || $username === '' || $password_raw === '') {
                $flash = 'NIK, Username, dan Password wajib diisi.';
            } else {
                $password_hash = hash('sha256', $password_raw); 
                $uid = 'uid-' . $nik;

                $stmt = $conn->prepare("INSERT INTO users (nik, username, nama, departemen, cabang, jabatan, singkatan_nama, password, uid, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('ssssssssss', $nik, $username, $nama, $departemen, $cabang, $jabatan, $singkatan_nama, $password_hash, $uid, $role);
                    if ($stmt->execute()) {
                        $flash = 'User berhasil ditambahkan.';
                    } else {
                        $flash = 'Gagal menambah user: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $flash = 'DB error: ' . $conn->error;
                }
            }
            $activeTab = 'users';

        } elseif ($action === 'delete_user') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                if ($id == $_SESSION['user_id']) {
                    $flash = 'Anda tidak dapat menghapus akun Anda sendiri!';
                } else {
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('i', $id);
                        $stmt->execute();
                        $stmt->close();
                        $flash = 'User berhasil dihapus.';
                    } else $flash = 'DB error: ' . $conn->error;
                }
            }
            $activeTab = 'users';
        }
    }
    
    // Simpan flash message ke session agar tampil setelah redirect
    $_SESSION['flash_msg'] = $flash;
    header("Location: ?tab=" . $activeTab);
    exit;
}

// Ambil flash message dari session jika ada
if (isset($_SESSION['flash_msg'])) {
    $flash = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// FETCH DATA
$cats = [];
$qr_cats = $conn->query("SELECT * FROM parcel_categories ORDER BY created_at DESC, id DESC");
if ($qr_cats) {
    while ($row = $qr_cats->fetch_assoc()) $cats[] = $row;
}

$users = [];
$qr_users = $conn->query("SELECT id, nik, username, nama, departemen, cabang, jabatan, singkatan_nama, role, created_at FROM users ORDER BY id DESC");
if ($qr_users) {
    while ($row = $qr_users->fetch_assoc()) $users[] = $row;
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Panel — Kategori & Users</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background: #f4f6f8; font-size: 0.95rem; }
    .container-custom { max-width: 1200px; margin: 24px auto; padding: 0 15px; }
    .card { border-radius: 10px; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .small-muted { color: #6b7280; font-size: 0.85em; }
    .table-sm th, .table-sm td { padding: 0.5rem; vertical-align: middle; }
    .nav-tabs .nav-link { font-weight: 500; color: #495057; }
    .nav-tabs .nav-link.active { font-weight: bold; color: #0d6efd; }
</style>
</head>
<body>
<div class="container-custom">
  <div class="d-flex mb-4 align-items-center">
    <h3 class="mb-0">Admin Panel</h3>
    <div class="ms-auto">
        <a href="admin_monitoring.php" class="btn btn-outline-secondary">Kembali ke Monitoring</a>
        <a href="parcel_list.php?admin=1" class="btn btn-primary">Kelola Kasbon Parcel</a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <?= e($flash) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- TAB NAVIGATION -->
  <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $activeTab === 'categories' ? 'active' : '' ?>" id="cat-tab" data-bs-toggle="tab" data-bs-target="#cat-pane" type="button" role="tab" aria-selected="<?= $activeTab === 'categories' ? 'true' : 'false' ?>">Kategori Parcel</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $activeTab === 'users' ? 'active' : '' ?>" id="user-tab" data-bs-toggle="tab" data-bs-target="#user-pane" type="button" role="tab" aria-selected="<?= $activeTab === 'users' ? 'true' : 'false' ?>">Manajemen User</button>
    </li>
  </ul>

  <div class="tab-content" id="adminTabsContent">
    
    <!-- TAB 1: KATEGORI PARCEL -->
    <div class="tab-pane fade <?= $activeTab === 'categories' ? 'show active' : '' ?>" id="cat-pane" role="tabpanel" tabindex="0">
      
      <div class="card p-4 mb-4">
        <h5 class="mb-3">Tambah Kategori Baru</h5>
        <form method="post" class="row g-3">
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <input type="hidden" name="action" value="add_category">
          <div class="col-md-5">
            <label class="form-label">Nama Kategori</label>
            <input type="text" name="name" class="form-control" placeholder="Contoh: Idul Fitri" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Slug (opsional)</label>
            <input type="text" name="slug" class="form-control" placeholder="opsional, auto dibuat jika kosong">
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <div class="form-check form-switch me-3 mb-2">
              <input class="form-check-input" type="checkbox" id="enabled" name="enabled" checked>
              <label class="form-check-label small-muted" for="enabled">Aktif</label>
            </div>
            <button class="btn btn-primary w-100">Tambah</button>
          </div>
        </form>
      </div>

      <div class="card p-4">
        <h5 class="mb-3">Daftar Kategori</h5>
        <?php if (empty($cats)): ?>
          <div class="text-muted">Belum ada kategori. Tambahkan kategori lewat form di atas.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover table-sm">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Nama</th>
                  <th>Slug</th>
                  <th>Status</th>
                  <th class="text-end">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cats as $c): ?>
                  <tr>
                    <td><?= e($c['id']) ?></td>
                    <td><strong><?= e($c['name']) ?></strong></td>
                    <td><span class="badge bg-secondary"><?= e($c['slug']) ?></span></td>
                    <td>
                      <form method="post" style="display:inline">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="toggle_category">
                        <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                        <input type="hidden" name="enabled" value="<?= $c['enabled'] ? '0' : '1' ?>">
                        <button class="btn btn-sm <?= $c['enabled'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                          <?= $c['enabled'] ? 'Aktif' : 'Nonaktif' ?>
                        </button>
                      </form>
                    </td>
                    <td class="text-end">
                      <form method="post" style="display:inline" onsubmit="return confirm('Hapus kategori ini?');">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                        <button class="btn btn-sm btn-danger">Hapus</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- TAB 2: MANAJEMEN USER -->
    <div class="tab-pane fade <?= $activeTab === 'users' ? 'show active' : '' ?>" id="user-pane" role="tabpanel" tabindex="0">
      
      <div class="card p-4 mb-4">
        <h5 class="mb-3">Tambah User Baru</h5>
        <form method="post" class="row g-3">
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <input type="hidden" name="action" value="add_user">
          
          <div class="col-md-3">
            <label class="form-label">NIK</label>
            <input type="text" name="nik" class="form-control" required placeholder="Contoh: 12024501">
          </div>
          <div class="col-md-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required placeholder="Contoh: kornelius">
          </div>
          <div class="col-md-6">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="nama" class="form-control" required placeholder="Contoh: Kornelius Andrean">
          </div>
          
          <div class="col-md-3">
            <label class="form-label">Departemen</label>
            <input type="text" name="departemen" class="form-control" placeholder="Contoh: Sales">
          </div>
          <div class="col-md-3">
            <label class="form-label">Cabang</label>
            <input type="text" name="cabang" class="form-control" placeholder="Contoh: Surabaya">
          </div>
          <div class="col-md-4">
            <label class="form-label">Jabatan</label>
            <input type="text" name="jabatan" class="form-control" placeholder="Contoh: Sales Promotion">
          </div>
          <div class="col-md-2">
            <label class="form-label">Singkatan</label>
            <input type="text" name="singkatan_nama" class="form-control" placeholder="Contoh: KAB">
          </div>

          <div class="col-md-4">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required placeholder="***">
            <div class="form-text small-muted">Otomatis di-hash (SHA256) di sistem.</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select">
              <option value="user">User</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="col-md-5 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Simpan User</button>
          </div>
        </form>
      </div>

      <div class="card p-4">
        <h5 class="mb-3">Daftar Pengguna (<?= count($users) ?>)</h5>
        <div class="table-responsive">
          <table class="table table-hover table-sm" style="font-size: 0.9rem;">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>NIK / UID</th>
                <th>Username</th>
                <th>Nama Lengkap</th>
                <th>Dept / Cabang</th>
                <th>Jabatan / Singkatan</th>
                <th>Role</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= e($u['id']) ?></td>
                  <td>
                    <strong><?= e($u['nik']) ?></strong><br>
                    <span class="small-muted">uid-<?= e($u['nik']) ?></span>
                  </td>
                  <td><?= e($u['username']) ?></td>
                  <td><?= e($u['nama']) ?></td>
                  <td>
                    <?= e($u['departemen']) ?><br>
                    <span class="small-muted"><?= e($u['cabang']) ?></span>
                  </td>
                  <td>
                    <?= e($u['jabatan']) ?><br>
                    <span class="small-muted"><?= e($u['singkatan_nama']) ?></span>
                  </td>
                  <td>
                    <?php if($u['role'] === 'admin'): ?>
                        <span class="badge bg-danger">Admin</span>
                    <?php else: ?>
                        <span class="badge bg-primary">User</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <form method="post" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus user <?= e($u['nama']) ?>?');">
                      <input type="hidden" name="token" value="<?= e($token) ?>">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                      <button class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>