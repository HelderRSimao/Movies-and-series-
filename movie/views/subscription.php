<?php
require_once '../db/db.php';
require_once '../db/auth.php';
require_once '../config/paypal.php';
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$user_id = $user['id'];
$userRole = $user['role_id'] ?? 0;
$isAdmin = isAdmin();

// Fetch current active subscription
$stmt = $con->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' ORDER BY end_date DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$current_subscription = $res->fetch_assoc();

$current_tier = $current_subscription['tier'] ?? 'free';
$current_duration = $current_subscription['duration'] ?? 'none';
$end_date = $current_subscription['end_date'] ?? null;

$today = new DateTime();
$can_downgrade = true;
$can_resubscribe = true;
$days_left = 0;

if ($current_subscription && $end_date) {
    $end = new DateTime($end_date);
    $interval = $today->diff($end);
    $days_left = (int) $interval->format('%r%a');

    if ($days_left > 10) $can_resubscribe = false;
    if ($current_tier === 'tier2' && $current_duration === 'annual' && $days_left > 30) {
        $can_downgrade = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Subscribe</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/fixable.css">
  <script src="../js/scripts.js"></script>
  <style>
    .dark-toggle {
      background: transparent;
      border: none;
      font-size: 20px;
      cursor: pointer;
      color: var(--accent);
      padding: 6px;
      margin-left: 8px;
      border-radius: 6px;
      transition: background 0.2s;
    }
    .dark-toggle:hover {
      background: rgba(255, 255, 255, 0.1);
    }
  </style>
</head>

<body>
<?php if ($userRole == 2): ?>
<div id="sidebar" class="sidebar">
  <a href="../views/index.php">📺 My Page</a>
  <a href="../admin/dashboard.php">📈 Insights</a>
  <a href="../admin/library.php">📂 Library</a>
  <a href="../admin/libraryc.php">📂 Library collection</a>
  <a href="../admin/librarym.php">📂 Library movie</a>
  <a href="../admin/add_collection.php">➕ Add Collection</a>
  <a href="../admin/add_video.php">➕ Add Episode</a>
  <a href="../admin/add_movie.php">🎬 Add Movie</a>
</div>
<?php endif; ?>

<div class="navbar">
  <div class="left">
    <?php if ($userRole == 2): ?>
      <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <?php endif; ?>
    <a href="index.php">Home</a>
    <a href="collections.php">Series</a>
    <a href="movies.php">Movies</a>
  </div>
  <div class="right">
    <span style="color: #fff; margin-right: 10px;">
      <?= $userRole == 2 ? 'Hello Admin' : 'Hello, ' . htmlspecialchars($user['username']) ?>
    </span>
    <a href="profile.php">Profile</a>
    <a href="logout.php">Logout</a>
    <a href="myfavorites.php">Meus Favoritos</a>
    <input type="text" placeholder="Search title..." value="<?= htmlspecialchars($_GET['s'] ?? '') ?>" onkeyup="if(event.key === 'Enter') window.location.href='?s=' + encodeURIComponent(this.value)">
    <button class="dark-toggle" onclick="toggleDarkMode()">🌙</button>
  </div>
</div>

<div class="container mt-5 pt-5">
  <h2>📦 Choose a Subscription Plan</h2>
  <p>Your current plan: <strong><?= ucfirst($current_tier) ?> (<?= $current_duration ?>)</strong></p>

  <?php if (!$can_resubscribe && $current_subscription): ?>
    <div class="alert alert-info">
      ✅ You already have an active subscription ending on <strong><?= $end_date ?></strong>.<br>
      You can only subscribe again when there are 10 or fewer days remaining (currently <strong><?= $days_left ?></strong> days left).
    </div>
  <?php endif; ?>

  <?php if (!$can_downgrade && $current_tier === 'tier2'): ?>
    <div class="alert alert-warning">
      ⛔ You can only downgrade from Tier 2 when on a monthly plan or with less than 30 days left in an annual plan.
    </div>
  <?php endif; ?>

  <form id="subscription-form" <?= !$can_resubscribe ? 'style="pointer-events: none; opacity: 0.5;"' : '' ?>>
    <input type="hidden" id="canResubscribe" value="<?= $can_resubscribe ? '1' : '0' ?>">
    <div class="mb-3">
      <label for="tier" class="form-label">Plan Type:</label>
      <select name="tier" id="tier" class="form-select" <?= !$can_resubscribe ? 'disabled' : '' ?> required>
        <option value="">-- Select Tier --</option>
        <option value="tier1" <?= (!$can_downgrade && $current_tier === 'tier2') ? 'disabled' : '' ?>>Tier 1 - Basic Content</option>
        <option value="tier2">Tier 2 - Full Content</option>
      </select>
    </div>

    <div class="mb-3">
      <label for="duration" class="form-label">Duration:</label>
      <select name="duration" id="duration" class="form-select" <?= !$can_resubscribe ? 'disabled' : '' ?> required>
        <option value="">-- Select Duration --</option>
        <option value="monthly">Monthly</option>
        <option value="annual">Annual</option>
      </select>
    </div>
  </form>

  <div class="mt-4">
    <h5>💡 Plan Details:</h5>
    <ul>
      <li><strong>Tier 1:</strong> €1/month or €10.80/year (10% off)</li>
      <li><strong>Tier 2:</strong> €3/month or €28.80/year (20% off)</li>
    </ul>
  </div>

  <?php if ($can_resubscribe): ?>
  <div id="paypal-section" class="mt-4" style="display: none;">
    <h5>🔐 Checkout with PayPal</h5>
    <p>Total: <strong id="paypal-total">0.00 €</strong></p>
    <div id="paypal-button-container"></div>
  </div>
  <?php endif; ?>

  <a href="index.php" class="btn btn-secondary mt-4">⬅️ Back to Home</a>
</div>

<script>
const prices = {
    tier1: { monthly: 1.00, annual: 10.80 },
    tier2: { monthly: 3.00, annual: 28.80 }
};

const tierSelect = document.getElementById("tier");
const durationSelect = document.getElementById("duration");
const paypalSection = document.getElementById("paypal-section");
const paypalTotal = document.getElementById("paypal-total");
const canResubscribe = document.getElementById("canResubscribe").value === "1";

function updatePayPal() {
    if (!canResubscribe) {
        paypalSection.style.display = "none";
        document.getElementById("paypal-button-container").innerHTML = "";
        return;
    }

    const tier = tierSelect.value;
    const duration = durationSelect.value;

    if (prices[tier] && prices[tier][duration]) {
        const total = prices[tier][duration].toFixed(2);
        paypalTotal.textContent = total + " €";
        paypalSection.style.display = "block";
        document.getElementById("paypal-button-container").innerHTML = "";

        paypal.Buttons({
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: { value: total }
                    }]
                });
            },
            onApprove: function(data, actions) {
                return actions.order.capture().then(function(details) {
                    window.location.href = `finishsubscription.php?tier=${tier}&duration=${duration}&amount=${total}`;
                });
            },
            onError: function(err) {
                console.error(err);
                alert("Error processing PayPal payment.");
            }
        }).render("#paypal-button-container");
    } else {
        paypalSection.style.display = "none";
        document.getElementById("paypal-button-container").innerHTML = "";
    }
}

tierSelect.addEventListener("change", updatePayPal);
durationSelect.addEventListener("change", updatePayPal);
</script>

<script src="https://www.paypal.com/sdk/js?client-id=<?= $PAYPAL_CLIENT_ID ?>&currency=EUR"></script>
</body>
</html>
