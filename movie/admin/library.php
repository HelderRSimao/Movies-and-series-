<?php
require_once '../db/db.php';
require_once '../db/auth.php';
require_once '../functions/addvideo_functions.php';
requireRole(2);

// Delete Episode
if (isset($_GET['delete'])) {
    $_SESSION['success'] = deleteEpisode(intval($_GET['delete']));
    header("Location: library.php");
    exit();
}

// Pagination
$episodePage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($episodePage - 1) * $limit;

$totalQuery = $con->query("SELECT COUNT(*) AS total FROM episodes");
$totalEpisodes = $totalQuery->fetch_assoc()['total'];
$totalPages = ceil($totalEpisodes / $limit);

// Fetch episodes
$episodes = $con->query("SELECT * FROM episodes ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

// Fetch collections
$collections = $con->query("SELECT * FROM collections");
$collectionMap = [];
while ($col = $collections->fetch_assoc()) {
    $collectionMap[$col['id']] = $col['title'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Episodes Library</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <script src="../js/scripts.js"></script>

  <style>

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
     padding-right: 30px; 
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
  overflow: hidden;
  white-space: nowrap;
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

  .navbar .right a:hover {
    text-decoration: underline;
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
  padding: 4px;
  margin: 0;
  display: flex;
  align-items: center;
  flex-shrink: 0;
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
    margin-bottom: 20px;
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
  }

  .btn-collection:hover {
    background: var(--accent);
    color: white;
  }

  .episodes-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    overflow: hidden;
  }

  .episodes-table tbody tr:last-child td {
  border-bottom: none;
}
  .episodes-table th,
  .episodes-table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    text-align: center;
  }

  .episodes-table th {
    background: rgba(0, 0, 0, 0.03);
    font-weight: 600;
  }

  .episodes-table tr:hover {
    background: rgba(0, 0, 0, 0.03);
  }

.pagination {
  display: flex;
  justify-content: center;
  margin-top: 20px;
  gap: 5px;
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
  }

  </style>
</head>
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
  <a href="../admin/dashboard.php" >📈 Insights</a>
  <a href="../admin/library.php">📂 Library</a>
  <a href="../admin/libraryc.php">📂 Library collection</a>
  <a href="../admin/librarym.php">📂 Library movie</a>
  <a href="../admin/add_collection.php">➕ Add Collection</a>
  <a href="../admin/add_video.php">➕ Add Episode</a>
  <a href="../admin/add_movie.php">🎬 Add Movie</a>
</div>

  <div class="section">
    <div class="buttons">
      <h2>🎬 Episodes Library</h2>
      <div class="right-btns">
        <a href="libraryc.php"><button class="btn-collection">📚 Go to Collections</button></a>
        <a href="add_video.php"><button class="btn-collection">➕ Create Episode</button></a>
      </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert"><?= htmlspecialchars($_SESSION['success']) ?></div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <table class="episodes-table">
      <thead>
        <tr>
          <th>Title</th><th>Collection</th><th>Publish Date</th>
          <th>Access</th><th>Post Type</th>    <th>Featured</th>   <th>Actions</th>
        </tr>
      </thead>
      <tbody>
  <?php $i = 0; while ($row = $episodes->fetch_assoc()): ?>
    <?php
      $rowNumber = $offset + $i + 1;
      $collection_id = $row['collection_id'];
      $isFeatured = false;
      $stmt = $con->prepare("SELECT 1 FROM featured_homepage WHERE episode_id = ?");
      $stmt->bind_param("i", $row['id']);
      $stmt->execute();
      $res = $stmt->get_result();
      $isFeatured = $res && $res->num_rows > 0;
    ?>
   <tr class="<?= $isFeatured ? 'featured' : '' ?>">
  <td><?= htmlspecialchars($row['title']) ?></td>
  <td><?= htmlspecialchars($collectionMap[$collection_id] ?? '-') ?></td>
  <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
  <td><?= $row['tier_access'] === 'free' ? 'All members' : 'Tier ' . ($row['tier_access'] === 'tier1' ? '1' : '2') ?></td>
  <td>🎞️ Video</td>
  <td><?= $isFeatured ? '⭐' : '' ?></td>
  <td>
    <a href="add_video.php?edit=<?= $row['id'] ?>" style="text-decoration: none; color: var(--accent);">✏️ Edit</a> |
    <a href="library.php?delete=<?= $row['id'] ?>" onclick="return confirm('Are you sure?')" style="text-decoration: none; color: var(--accent);">🗑️ Delete</a>
  </td>
</tr>

  <?php $i++; endwhile; ?>
</tbody>

    </table>

    <div class="pagination">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>" class="<?= $i == $episodePage ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
</body>
</html>
