<?php
session_start();

/*
  Robust login.php
  - Compatible with db.php that exposes either $pdo (PDO) or $conn (mysqli)
  - If neither present, will attempt to create both using config.php
  - Password comparison follows existing pattern: SQL uses SHA2(...,256)
  - Redirects admin -> admin_monitoring.php, normal user -> index.php
*/

// try include db.php / config.php
if (file_exists(__DIR__ . '/db.php')) {
    include_once __DIR__ . '/db.php';
}
if (!isset($conn) && file_exists(__DIR__ . '/config.php')) {
    // load config to later create fallback connections if needed
    $config = include __DIR__ . '/config.php';
} else {
    $config = $config ?? null;
}

// If db.php did not provide PDO ($pdo) but config available -> create PDO
if (!isset($pdo)) {
    if (isset($config['db']) && is_array($config['db'])) {
        $db = $config['db'];
        // create PDO
        try {
            $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset=" . ($db['charset'] ?? 'utf8mb4');
            $pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Exception $e) {
            // ignore here; we'll try mysqli fallback below
            $pdo = null;
        }
    }
}

// If db.php did not provide mysqli ($conn) but config available -> create mysqli
if (!isset($conn)) {
    if (isset($config['db']) && is_array($config['db'])) {
        $db = $config['db'];
        $conn = @new mysqli($db['host'], $db['user'], $db['pass'], $db['dbname']);
        if ($conn && $conn->connect_errno) {
            // failed, unset to signal not available
            $conn = null;
        } else {
            // set charset if possible
            if ($conn) $conn->set_charset($db['charset'] ?? 'utf8mb4');
        }
    }
}

// Final fallback: if none found, try very basic mysqli with default credentials
if (!isset($pdo) && !isset($conn)) {
    // last resort: try default local mysql with empty password
    $try = @new mysqli('127.0.0.1', 'root', '', 'reimb_db');
    if ($try && !$try->connect_errno) {
        $conn = $try;
    } else {
        // cannot connect — fatal
        die("Database connection not found. Please ensure db.php or config.php exists and provides DB credentials.");
    }
}

// Helper: escape output
function e($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// Unified fetch_user: use PDO if available, else mysqli
function fetch_user($username, $password) {
    global $pdo, $conn;

    if (isset($pdo) && $pdo instanceof PDO) {
        $sql = "SELECT * FROM users WHERE username = :u AND password = SHA2(:p,256) LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u' => $username, ':p' => $password]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    if (isset($conn) && $conn instanceof mysqli) {
        $sql = "SELECT * FROM users WHERE username = ? AND password = SHA2(?,256) LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_assoc() : false;
    }

    return false;
}

// ========== Login process ==========
$error = '';
$next = 'index.php';
if (isset($_GET['next'])) $next = $_GET['next'];
if (isset($_POST['next'])) $next = $_POST['next'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "Username dan Password wajib diisi.";
    } else {
        $user = fetch_user($username, $password);

        if (!$user) {
            $error = "Username atau Password salah.";
        } else {
            // regenerate session id
            session_regenerate_id(true);

            // set common session fields (keep names used in your app)
            $_SESSION['user_id']  = $user['id'] ?? null;
            $_SESSION['nik']      = $user['nik'] ?? null;
            $_SESSION['username'] = $user['username'] ?? null;
            $_SESSION['name']     = $user['nama'] ?? ($user['name'] ?? null);
            $_SESSION['jabatan']  = $user['jabatan'] ?? null;
            $_SESSION['cabang']   = $user['cabang'] ?? null;
            $_SESSION['jabatan']  = $user['jabatan'] ?? null;
            $_SESSION['departemen'] = $user['departemen'] ?? null;

            // role (handle absent column)
            $_SESSION['role'] = strtolower($user['role'] ?? 'user');

            // additional
            $_SESSION['selected_user_id'] = $user['id'] ?? null;
            $_SESSION['selected_nik']     = $user['nik'] ?? null;
            $_SESSION['selected_name']    = $user['nama'] ?? ($user['name'] ?? null);

            // redirect based on role
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                header("Location: admin_categories.php");
                exit;
            }

            header("Location: " . $next);
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - Expense Reimbursement</title>
<style>
body{
    font-family:Arial,Helvetica,sans-serif;
    background:#f4f6f8;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}
.box{
    width:420px;
    background:#fff;
    padding:30px;
    border-radius:12px;
    box-shadow:0 10px 30px rgba(0,0,0,0.08);
}
h2{
    text-align:center;
    margin-bottom:20px;
}
input{
    width:100%;
    padding:12px;
    border:1px solid #ddd;
    border-radius:8px;
    margin-top:6px;
    margin-bottom:15px;
}
button{
    width:100%;
    padding:12px;
    border:none;
    border-radius:8px;
    background:#7a0004;
    color:#fff;
    font-weight:bold;
    cursor:pointer;
}
button:hover{
    background:#5e0003;
}
.err{
    color:#c62828;
    margin-bottom:10px;
    text-align:center;
}
.small{
    font-size:13px;
    text-align:center;
    margin-top:10px;
    color:#666;
}
</style>
</head>
<body>

<div class="box">
    <h2>Login Sistem</h2>

    <?php if ($error): ?>
        <div class="err"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <label>Username</label>
        <input type="text" name="username" placeholder="Masukkan Username" required>

        <label>Password</label>
        <input type="password" name="password" placeholder="Masukkan Password" required>

        <input type="hidden" name="next" value="<?= e($next) ?>">

        <button type="submit">LOGIN</button>
    </form>

    <div class="small">
        Gunakan username dan password yang terdaftar.
    </div>
</div>

</body>
</html>