<?php
session_start();
require_once '../db/db.php';
require_once '../db/auth.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$user_id = $user['id'];
$userRole = $user['role_id'] ?? 0;
$isAdmin = isAdmin();

$subscription = null;
if (!$isAdmin) {
    $stmt = $con->prepare("SELECT * FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $subscription = $res->fetch_assoc();
}

// Handle query string feedback
$success = '';
$error = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'info') {
        $success = "Information updated successfully.";
    } elseif ($_GET['success'] === 'password') {
        $success = "Password changed successfully.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin && ($_POST['action'] ?? '') === 'cancel_subscription' && $subscription && $subscription['status'] === 'active') {
        $cancel_stmt = $con->prepare("UPDATE subscriptions SET status = 'cancel' WHERE id = ?");
        $cancel_stmt->bind_param("i", $subscription['id']);
        $cancel_stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if (($_POST['action'] ?? '') === 'update_info') {
        $new_email = trim($_POST['new_email'] ?? '');
        $new_username = trim($_POST['new_username'] ?? '');

        if ($new_email || $new_username) {
            if ($new_email && !$new_username) {
                $stmt = $con->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->bind_param("si", $new_email, $user_id);
                $stmt->execute();
                $_SESSION['user']['email'] = $new_email;
            } elseif ($new_username && !$new_email) {
                $stmt = $con->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt->bind_param("si", $new_username, $user_id);
                $stmt->execute();
                $_SESSION['user']['username'] = $new_username;
            } elseif ($new_email && $new_username) {
                $stmt = $con->prepare("UPDATE users SET email = ?, username = ? WHERE id = ?");
                $stmt->bind_param("ssi", $new_email, $new_username, $user_id);
                $stmt->execute();
                $_SESSION['user']['email'] = $new_email;
                $_SESSION['user']['username'] = $new_username;
            }

            header("Location: " . $_SERVER['PHP_SELF'] . "?success=info");
            exit();
        } else {
            $error = "Please enter at least one value to update.";
        }
    }

    if (($_POST['action'] ?? '') === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';

        $stmt = $con->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (password_verify($current_password, $result['password'])) {
            if (strlen($new_password) >= 6) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $con->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hash, $user_id);
                $stmt->execute();
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=password");
                exit();
            } else {
                $error = "Password must be at least 6 characters.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<script src="../js/scripts.js"></script>
<link rel="stylesheet" href="../css/style.css">
<style>
.dark-toggle {
  background: transparent;
  color: #fff;
  border: none;
  font-size: 18px;
  margin-left: 10px;
  cursor: pointer;
}

/* Sidebar */
.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  width: 240px;
  height: 100%;
  background: var(--card-bg);
  border-right: 1px solid var(--border);
  padding-top: 70px;
  transform: translateX(-100%);
  transition: transform 0.3s ease-in-out;
  z-index: 999;
}

.sidebar.open {
  transform: translateX(0);
}

.sidebar a {
  display: block;
  padding: 12px 20px;
  border-bottom: 1px solid var(--border);
  color: var(--accent);
  text-decoration: none;
  font-weight: 500;
}

.container {
  padding-top: 100px;
  max-width: 700px;
  margin: auto;
}

h2, h4 {
  margin-bottom: 20px;
  font-weight: 600;
}

form {
  margin-bottom: 30px;
}

form input[type="email"],
form input[type="text"],
form input[type="password"] {
  background: var(--card-bg);
  border: 1px solid var(--border);
  color: var(--text);
}

form .form-label {
  font-weight: 500;
}

.btn {
  padding: 8px 14px;
  font-size: 14px;
  font-weight: 500;
  border-radius: 5px;
}

.btn-primary {
  background: var(--accent);
  border: none;
}

.btn-warning {
  background: #ff9800;
  border: none;
}

.btn:hover {
  opacity: 0.9;
}

.alert {
  padding: 10px 15px;
  border-radius: 6px;
  margin-bottom: 20px;
  font-size: 14px;
}

.alert-success {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.alert-danger {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.text-warning {
  color: #ffc107;
}

.text-danger {
  color: #dc3545;
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
  <h2>Hello, <?= htmlspecialchars($user['username']) ?></h2>

  <?php if (!$isAdmin): ?>
    <h4 class="mt-4">🧾 Subscription</h4>
    <?php
    $today = date('Y-m-d');
    if ($subscription) {
        $status = $subscription['status'];
        $tier = $subscription['tier'];
        $start = $subscription['start_date'];
        $end = $subscription['end_date'];
        echo "<p>Tier: <strong>" . ucfirst($tier) . "</strong></p>";
        echo "<p>Status: <strong>" . ucfirst($status) . "</strong></p>";
        echo "<p>Valid From: $start to $end</p>";

        if ($status === 'active') {
            echo '<form method="POST">
                    <input type="hidden" name="action" value="cancel_subscription" />
                    <button type="submit" class="btn btn-warning">⏸ Cancel Subscription</button>
                  </form>';
        } elseif ($status === 'cancel' && $end >= $today) {
            echo "<p class='text-warning'>⏳ Subscription canceled but still valid until <strong>$end</strong></p>";
        } else {
            echo "<p class='text-danger'>❌ Subscription expired or invalid.</p>";
            echo '<a href="subscription.php" class="btn btn-primary">📋 Subscribe Again</a>';
        }
    } else {
        echo "<p class='text-danger'>❌ No active subscription.</p>";
        echo '<a href="subscription.php" class="btn btn-primary">📋 Choose a Plan</a>';
    }
    ?>
  <?php endif; ?>

  <hr>
  <h4>🛠 Update Info</h4>
  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST">
    <input type="hidden" name="action" value="update_info">
    <div class="mb-3">
      <label class="form-label">Current Email:</label>
      <p><strong><?= htmlspecialchars($user['email']) ?></strong></p>
      <input type="email" name="new_email" class="form-control">
    </div>
    <div class="mb-3">
      <label class="form-label">Current Username:</label>
      <p><strong><?= htmlspecialchars($user['username']) ?></strong></p>
      <input type="text" name="new_username" class="form-control">
    </div>
    <button type="submit" class="btn btn-primary">💾 Update Info</button>
  </form>

  <hr>
  <h4>🔐 Change Password</h4>
  <form method="POST">
    <input type="hidden" name="action" value="change_password">
    <div class="mb-3">
      <label class="form-label">Current Password:</label>
      <input type="password" name="current_password" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">New Password:</label>
      <input type="password" name="new_password" class="form-control" minlength="6" required>
    </div>
    <button type="submit" class="btn btn-primary">🔒 Change Password</button>
  </form>
</div>
</body>
</html>
