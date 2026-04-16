<?php
// uc_store.php
session_start();
require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: uc_create.php'); exit;
}

// token CSRF simple
if (!isset($_POST['token']) || $_POST['token'] !== ($_SESSION['form_token'] ?? '')) {
    // tidak valid, tapi jangan bertele-tele
    // continue anyway or reject
}

// ambil data
$user_id = $_POST['user_id'] ?? null;
$requester_name = $_POST['requester_name'] ?? '';
$branch = $_POST['branch'] ?? '';
$month_label = $_POST['month_label'] ?? '';
$start_date = $_POST['start_date'] ?: null;
$end_date = $_POST['end_date'] ?: null;

$hotel_per_day = (float)($_POST['hotel_per_day'] ?? 0);
$hotel_nights = (int)($_POST['hotel_nights'] ?? 0);
$meal_per_day = (float)($_POST['meal_per_day'] ?? 0);
$meal_days = (int)($_POST['meal_days'] ?? 0);
$fuel_amount = (float)($_POST['fuel_amount'] ?? 0);
$other_amount = (float)($_POST['other_amount'] ?? 0);

$hotel_total = $hotel_per_day * $hotel_nights;
$meal_total = $meal_per_day * $meal_days;
$total_amount = $hotel_total + $meal_total + $fuel_amount + $other_amount;

// signature data
$sig_data = $_POST['signature_data'] ?? '';

// simpan signature (jika ada)
$sig_path = null;
if ($sig_data && preg_match('/^data:image\/png;base64,/', $sig_data)) {
    $data = substr($sig_data, strpos($sig_data, ',') + 1);
    $decoded = base64_decode($data);
    if ($decoded !== false) {
        if (!is_dir($config['upload_dir'])) {
            mkdir($config['upload_dir'], 0775, true);
        }
        $filename = 'sig_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
        $filePath = rtrim($config['upload_dir'], '/\\') . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($filePath, $decoded);
        $sig_path = 'uploads/' . $filename; // relative path for web + pdf
    }
}

// insert request
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("INSERT INTO uc_requests
      (user_id, requester_name, branch, start_date, end_date, month_label, hotel_per_day, hotel_nights, meal_per_day, meal_days, fuel_amount, other_amount, total_amount, signature_path)
      VALUES (:user_id, :requester_name, :branch, :start_date, :end_date, :month_label, :hotel_per_day, :hotel_nights, :meal_per_day, :meal_days, :fuel_amount, :other_amount, :total_amount, :signature_path)");
    $stmt->execute([
      ':user_id'=>$user_id,
      ':requester_name'=>$requester_name,
      ':branch'=>$branch,
      ':start_date'=>$start_date,
      ':end_date'=>$end_date,
      ':month_label'=>$month_label,
      ':hotel_per_day'=>$hotel_per_day,
      ':hotel_nights'=>$hotel_nights,
      ':meal_per_day'=>$meal_per_day,
      ':meal_days'=>$meal_days,
      ':fuel_amount'=>$fuel_amount,
      ':other_amount'=>$other_amount,
      ':total_amount'=>$total_amount,
      ':signature_path'=>$sig_path
    ]);
    $request_id = $pdo->lastInsertId();

    // insert outlets (arrays)
    $trip_dates = $_POST['trip_date'] ?? [];
    $destinations = $_POST['destination'] ?? [];
    $outlet_names = $_POST['outlet_name'] ?? [];
    $est_sales = $_POST['est_sales'] ?? [];

    $ins = $pdo->prepare("INSERT INTO uc_outlets (request_id, trip_date, destination, outlet_name, est_sales) VALUES (:rid, :td, :dest, :out_name, :sales)");
    for ($i=0;$i<count($outlet_names);$i++){
        $td = $trip_dates[$i] ?? null;
        $dest = $destinations[$i] ?? '';
        $on = $outlet_names[$i] ?? '';
        $es = floatval(str_replace([',',' '],'',$est_sales[$i] ?? 0));
        if (trim($on) === '' && trim($dest)==='') continue;
        $ins->execute([':rid'=>$request_id, ':td'=>$td ?: null, ':dest'=>$dest, ':out_name'=>$on, ':sales'=>$es]);
    }

    $pdo->commit();
    header('Location: uc_view.php?id=' . $request_id);
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}