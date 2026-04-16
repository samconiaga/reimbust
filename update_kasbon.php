<?php
// update_kasbon.php
session_start();
require __DIR__.'/db.php';

if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin'){
    die("Akses ditolak");
}

$parcel_id = (int)($_POST['parcel_id'] ?? 0);
$raw_total = trim((string)($_POST['total'] ?? ''));

if ($parcel_id <= 0) {
    header("Location: parcel_list.php");
    exit;
}

// normalize input: allow 1.234.567 or 1,234,567
$clean = preg_replace('/[^\d\.,-]/', '', $raw_total);
$clean = str_replace(',', '.', $clean);
$total = (float)$clean;

// safety
if ($total < 0) $total = 0.0;

try {
    // ensure admin_total column exists
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parcels' AND COLUMN_NAME = 'admin_total'");
    $stmtCheck->execute();
    $c = (int)$stmtCheck->fetchColumn();
    if ($c === 0) {
        $pdo->exec("ALTER TABLE parcels ADD COLUMN admin_total DECIMAL(14,2) NULL DEFAULT NULL");
    }

    // set admin_total on parcel
    $u = $pdo->prepare("UPDATE parcels SET admin_total = :val, updated_at = NOW() WHERE id = :id LIMIT 1");
    $u->execute([':val' => $total, ':id' => $parcel_id]);

    // fetch outlets
    $stmt = $pdo->prepare("SELECT id FROM parcel_outlets WHERE parcel_id = ? ORDER BY id");
    $stmt->execute([$parcel_id]);
    $outlets = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $count = count($outlets);

    if ($count > 0) {
        // distribute in "cents" to avoid float rounding problems
        $total_cents = (int) round($total * 100);
        $per = intdiv($total_cents, $count);
        $rem = $total_cents % $count;

        $update = $pdo->prepare("UPDATE parcel_outlets SET amount = :amt WHERE id = :id");
        foreach ($outlets as $i => $oid) {
            $add = ($rem > 0) ? 1 : 0;
            $amt_cents = $per + ($add ? 1 : 0);
            if ($rem > 0) $rem--;
            $amt = $amt_cents / 100;
            $update->execute([':amt' => $amt, ':id' => $oid]);
        }
    }

    // selesai -> redirect ke daftar admin
    header("Location: parcel_list.php?admin=1");
    exit;
} catch (Exception $ex) {
    // log error jika perlu
    error_log("update_kasbon error: " . $ex->getMessage());
    header("Location: parcel_list.php?admin=1&error=1");
    exit;
}