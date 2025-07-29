<?php
require_once '../db/db.php';
require_once '../db/auth.php';
session_start();

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}

$user_id  = $_SESSION['user']['id'];
$tier     = $_GET['tier']     ?? '';
$duration = $_GET['duration'] ?? '';
$amount   = floatval($_GET['amount'] ?? 0);

// Validate inputs
$valid_tiers     = ['tier1','tier2'];
$valid_durations = ['monthly','annual'];
if (!in_array($tier, $valid_tiers) || !in_array($duration, $valid_durations)) {
  die("Invalid plan.");
}

// Build dates
$start_date = date('Y-m-d');
if ($duration === 'annual') {
  $end_date = date('Y-m-d', strtotime('+1 year'));
} else {
  $end_date = date('Y-m-d', strtotime('+1 month'));
}

$con->begin_transaction();
try {
  // 1) Cancel any active subscription immediately
  $cancel = $con->prepare("
    UPDATE subscriptions 
       SET status='cancelled', end_date=?
     WHERE user_id=? AND status='active'
  ");
  $cancel->bind_param("si", $start_date, $user_id);
  $cancel->execute();

  // 2) Insert the new subscription (full price)
  $insert = $con->prepare("
    INSERT INTO subscriptions
      (user_id, tier, price, start_date, end_date, status)
    VALUES
      (?, ?, ?, ?, ?, 'active')
  ");
  $insert->bind_param(
    "isdss",
    $user_id,
    $tier,
    $amount,
    $start_date,
    $end_date
  );
  $insert->execute();

  // 3) Update the user's current tier
  $upd = $con->prepare("UPDATE users SET tier=? WHERE id=?");
  $upd->bind_param("si", $tier, $user_id);
  $upd->execute();

  $_SESSION['user']['tier'] = $tier;
  $con->commit();

  header("Location: index.php");
  exit;
}
catch (Exception $e) {
  $con->rollback();
  echo "Error: " . htmlspecialchars($e->getMessage());
}
