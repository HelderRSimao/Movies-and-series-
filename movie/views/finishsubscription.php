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
$valid_tiers     = ['tier1', 'tier2'];
$valid_durations = ['monthly', 'annual'];
if (!in_array($tier, $valid_tiers) || !in_array($duration, $valid_durations)) {
  die("Invalid subscription plan.");
}

// Determine start and end dates for the new subscription
$start_date = date('Y-m-d');
$end_date = ($duration === 'annual')
  ? date('Y-m-d', strtotime('+1 year'))
  : date('Y-m-d', strtotime('+1 month'));

$con->begin_transaction();
try {
  // Step 1: Check if there's an active subscription
  $stmt = $con->prepare("SELECT id, end_date FROM subscriptions WHERE user_id = ? AND status = 'active' ORDER BY end_date DESC LIMIT 1");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $active_sub = $result->fetch_assoc();

  if ($active_sub) {
    $existing_end = new DateTime($active_sub['end_date']);
    $today = new DateTime();
    $days_left = (int) $today->diff($existing_end)->format('%r%a');

    // Only cancel the current subscription if 10 or fewer days remain
    if ($days_left <= 10) {
      $cancel = $con->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE id = ?");
      $cancel->bind_param("i", $active_sub['id']);
      $cancel->execute();
    } else {
      // Do not proceed if user shouldn't subscribe again
      throw new Exception("You still have more than 10 days remaining on your current subscription.");
    }
  }

  // Step 2: Insert new subscription
  $insert = $con->prepare("
    INSERT INTO subscriptions
      (user_id, tier, price, duration, start_date, end_date, status)
    VALUES
      (?, ?, ?, ?, ?, ?, 'active')
  ");
  $insert->bind_param(
    "isdsss",
    $user_id,
    $tier,
    $amount,
    $duration,
    $start_date,
    $end_date
  );
  $insert->execute();

  // Step 3: Update user tier
  $upd = $con->prepare("UPDATE users SET tier = ? WHERE id = ?");
  $upd->bind_param("si", $tier, $user_id);
  $upd->execute();

  $_SESSION['user']['tier'] = $tier;

  $con->commit();
  header("Location: index.php");
  exit;
} catch (Exception $e) {
  $con->rollback();
  echo "Error: " . htmlspecialchars($e->getMessage());
}
?>
