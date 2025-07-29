<?php
require_once '../db/db.php';
require_once '../db/auth.php';
require_once '../functions/collection_functions.php';
requireRole(2);

if (isset($_GET['delete'])) {
   $deleted = deleteCollection(intval($_GET['delete']), $con);

    $_SESSION['success'] = $deleted ? 'Collection deleted successfully!' : 'Failed to delete collection.';
    header("Location: libraryc.php");
    exit;
}
$collectionPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($collectionPage - 1) * $limit;

$totalQuery = $con->query("SELECT COUNT(*) AS total FROM collections");
$totalCollections = $totalQuery->fetch_assoc()['total'];
$totalPages = ceil($totalCollections / $limit);

$sql = "
  SELECT c.id, c.title, c.created_at, COUNT(e.id) AS episode_count
  FROM collections c
  LEFT JOIN episodes e ON c.id = e.collection_id
  GROUP BY c.id
  ORDER BY c.created_at DESC
  LIMIT $limit OFFSET $offset
";
$collections = $con->query($sql);
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - Collections</title>
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
  <a href="../admin/librarym.php"> 📂 Library movie</a>
  <a href="../admin/add_collection.php">➕ Add Collection</a>
  <a href="../admin/add_video.php">➕ Add Episode</a>
  <a href="../admin/add_movie.php">🎬 Add Movie</a>
</div>

<div class="section">
  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <?php if (isset($error)): ?>
    <p style="color: red; text-align:center; font-weight:bold;"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <div class="buttons">
    <h2>📚 Collections Library</h2>
    <div class="right-btns">
      <a href="library.php"><button class="btn-collection">🎬 Go to Episodes</button></a>
      <a href="add_collection.php"><button class="btn-collection">➕ Create Collection</button></a>
    </div>
  </div>

  <table class="collections-table">
    <thead>
      <tr>
        <th>Title</th>
        <th>Created On</th>
        <th># of Episodes</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($row = $collections->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= date("d M Y", strtotime($row['created_at'])) ?></td>
        <td><?= $row['episode_count'] ?></td>
        <td>
          <a href="add_collection.php?edit=<?= $row['id'] ?>">✏️ Edit</a> |
          <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this collection? Episodes will also be removed.')">🗑️ Delete</a>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="?page=<?= $i ?>" class="<?= ($i == $collectionPage) ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
</div>
</body>
</html>
