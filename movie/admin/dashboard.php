<?php
require_once '../db/db.php';
require_once '../db/auth.php';
requireRole(2);

$user = $_SESSION['user'] ?? [];


$totalUsers = $con->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$totalCollections = $con->query("SELECT COUNT(*) FROM collections")->fetch_row()[0];
$totalMovies = $con->query("SELECT COUNT(*) FROM movies")->fetch_row()[0];
$totalEpisodes = $con->query("SELECT COUNT(*) FROM episodes")->fetch_row()[0];

$tierCounts = ['tier1' => 0, 'tier2' => 0];
$res = $con->query("SELECT tier, COUNT(*) as count FROM subscriptions WHERE status='active' GROUP BY tier");
while ($row = $res->fetch_assoc()) {
  $tierCounts[$row['tier']] = (int)$row['count'];
}


$monthlyRevenue = [];
$res = $con->query("SELECT DATE_FORMAT(start_date, '%Y-%m') as month, SUM(price) as total FROM subscriptions WHERE status='active' GROUP BY month ORDER BY month");
while ($row = $res->fetch_assoc()) {
  $monthlyRevenue[] = $row;
}


$monthlyCount = $con->query("SELECT COUNT(*) FROM subscriptions WHERE price = 3.00 AND status='active'")->fetch_row()[0];
$yearlyCount = $con->query("SELECT COUNT(*) FROM subscriptions WHERE price = 36.00 AND status='active'")->fetch_row()[0];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
 
    .chart-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 30px;
    }
    canvas {
      max-width: 100%;
    }
     :root {
    --bg: #f4f4f4;
    --text: #333;
    --card-bg: #fff;
    --border: #ccc;
    --accent: #007BFF;
  }

  body.dark {
    --bg: #121212;
    --text: #eee;
    --card-bg: #1e1e1e;
    --border: #444;
    --accent: #66b2ff;
  }

  body {
    margin: 0;
    overflow-x: hidden;
    font-family: 'Poppins', sans-serif;
    background-color: var(--bg);
    color: var(--text);
    transition: background 0.3s, color 0.3s;
  }

  .navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    padding: 12px 20px;
    position: fixed;
    top: 0;
    width: 100%;
    z-index: 1000;
    box-sizing: border-box;
  }

  .navbar a {
    color: #ddd;
    margin-left: 15px;
    text-decoration: none;
  }

  .navbar .right {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: nowrap;
    overflow-x: auto;
  }

  .navbar .right span,
  .navbar .right a,
  .navbar .right button {
    display: inline-flex;
    align-items: center;
    font-weight: 500;
    color: #fff;
    text-decoration: none;
    padding: 4px 6px;
    white-space: nowrap;
  }

  .menu-btn {
    font-size: 18px;
    background: var(--accent);
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 5px;
    cursor: pointer;
  }

  .toggle-dark {
    font-size: 18px;
    background: transparent;
    color: #fff;
    border: none;
    cursor: pointer;
    padding: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
  }

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
    box-shadow: 2px 0 6px rgba(0, 0, 0, 0.1);
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

  .section {
    padding: 100px 30px 40px;
    max-width: 1200px;
    margin: auto;
  }

  .buttons {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
  }

  .right-btns {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .btn-collection {
    background: var(--card-bg);
    border: 1px solid var(--accent);
    color: var(--accent);
    padding: 8px 14px;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 40px;
    line-height: 1;
  }

  .btn-collection:hover {
    background: var(--accent);
    color: white;
  }

  .collections-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    overflow: hidden;
  }

  .collections-table th,
  .collections-table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    text-align: center;
  }

  .collections-table th {
    background: rgba(0, 0, 0, 0.03);
    font-weight: 600;
  }

  .collections-table tr:hover {
    background: rgba(0, 0, 0, 0.03);
  }

  .collections-table td a {
    color: var(--accent);
    text-decoration: none;
    margin: 0 5px;
  }

  .collections-table td a:hover {
    color: #0056b3;
  }

  .pagination {
    text-align: center;
    margin-top: 20px;
  }

  .pagination a {
    background: var(--card-bg);
    color: var(--accent);
    border: 1px solid var(--border);
    padding: 6px 12px;
    margin: 0 4px;
    border-radius: 4px;
    text-decoration: none;
  }

  .pagination a.active {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
  }

  .alert {
    background: #dff0d8;
    color: #3c763d;
    padding: 10px 15px;
    border: 1px solid #c3e6cb;
    border-radius: 6px;
    margin-bottom: 20px;
    transition: opacity 0.5s ease;
  }

  @media (max-width: 768px) {
    .buttons {
      flex-direction: column;
      align-items: flex-start;
    }

    .btn-collection {
      width: 100%;
    }

    .navbar .right {
      flex-direction: column;
      align-items: flex-end;
    }
  }
  </style>
</head>
<body onload="loadTheme()">
<div class="navbar">
  <div class="left">
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <a href="dashboard.php" class="logo">Admin Panel</a>
  </div>
  <div class="right">
     <span>Hello <?= htmlspecialchars($user['username'] ?? 'Admin') ?></span>
    <a href="../views/logout.php">Logout</a>
    <button class="toggle-dark" onclick="toggleDarkMode()">🌙</button>
  </div>
</div>

<div id="sidebar" class="sidebar">
  <a href="../views/index.php">📺 My Page</a>
  <a href="../admin/dashboard.php" target="_blank">📈 Insights</a>
  <a href="../admin/library.php">📂 Library</a>
  <a href="../admin/libraryc.php">📂 Library collection</a>
  <a href="../admin/librarym.php">➕ Add Series</a>
  <a href="../admin/add_collection.php">➕ Add Collection</a>
  <a href="../admin/add_video.php">➕ Add Episode</a>
  <a href="../admin/add_movie.php">🎬 Add Movie</a>
</div>

<div class="section">
  <h2>📊 Dashboard Insights</h2>
  <div class="row text-center mb-4">
    <div class="col-md-3"><div class="chart-card">👤 Users<br><strong><?= $totalUsers ?></strong></div></div>
    <div class="col-md-3"><div class="chart-card">📚 Collections<br><strong><?= $totalCollections ?></strong></div></div>
    <div class="col-md-3"><div class="chart-card">🎬 Movies<br><strong><?= $totalMovies ?></strong></div></div>
    <div class="col-md-3"><div class="chart-card">🎞️ Episodes<br><strong><?= $totalEpisodes ?></strong></div></div>
  </div>

  <div class="chart-card">
    <h5>Active Subscriptions by Tier</h5>
    <canvas id="subsTierChart"></canvas>
  </div>

  <div class="chart-card">
    <h5>Revenue Over Time</h5>
    <canvas id="revenueChart"></canvas>
  </div>

  <div class="chart-card">
    <h5>Monthly vs Yearly Plans</h5>
    <canvas id="planTypeChart"></canvas>
  </div>
</div>

<script>
const subsTierChart = new Chart(document.getElementById('subsTierChart'), {
  type: 'bar',
  data: {
    labels: ['Tier 1', 'Tier 2'],
    datasets: [{
      label: 'Active Subscriptions',
      data: [<?= $tierCounts['tier1'] ?>, <?= $tierCounts['tier2'] ?>],
      backgroundColor: ['#007BFF', '#66b2ff']
    }]
  }
});

const revenueChart = new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($monthlyRevenue, 'month')) ?>,
    datasets: [{
      label: 'Total Revenue (€)',
      data: <?= json_encode(array_map(fn($r) => (float)$r['total'], $monthlyRevenue)) ?>,
      fill: true,
      borderColor: '#28a745',
      backgroundColor: 'rgba(40,167,69,0.2)',
      tension: 0.3
    }]
  }
});

const planTypeChart = new Chart(document.getElementById('planTypeChart'), {
  type: 'doughnut',
  data: {
    labels: ['Monthly (€3)', 'Yearly (€36)'],
    datasets: [{
      data: [<?= $monthlyCount ?>, <?= $yearlyCount ?>],
      backgroundColor: ['#36A2EB', '#FFCE56']
    }]
  }
});
</script>
<script>
function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  sidebar.classList.toggle("open");
  if (sidebar.classList.contains("open")) {
    document.addEventListener("click", handleOutsideClick);
  } else {
    document.removeEventListener("click", handleOutsideClick);
  }
}
function handleOutsideClick(e) {
  const sidebar = document.getElementById("sidebar");
  const button = document.querySelector(".menu-btn");
  if (!sidebar.contains(e.target) && !button.contains(e.target)) {
    sidebar.classList.remove("open");
    document.removeEventListener("click", handleOutsideClick);
  }
}
function toggleDarkMode() {
  document.body.classList.toggle("dark");
  localStorage.setItem("darkMode", document.body.classList.contains("dark") ? "on" : "off");
}
function loadTheme() {
  if (localStorage.getItem("darkMode") === "on") {
    document.body.classList.add("dark");
  }
}
</script>
</body>
</html>
