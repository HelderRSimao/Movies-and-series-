<?php
require_once '../db/db.php';
require_once '../db/auth.php';
session_start();

$user = $_SESSION['user'] ?? null;
$userTier = $user['tier_access'] ?? 'free';
$userRole = $user['role_id'] ?? 0;

function isFavorited($userId, $itemId, $type, $con) {
    if ($type === 'collection') {
        $stmt = $con->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND collections_id = ?");
    } else {
        return false;
    }
    $stmt->bind_param("ii", $userId, $itemId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

$search = $_GET['s'] ?? '';
$search = trim($search);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Search condition
$searchSQL = '';
$params = [];
$types = '';

if ($search !== '') {
    $searchSQL = "WHERE title LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= 's';
}

// Total count
$totalQuery = "SELECT COUNT(*) as total FROM collections $searchSQL";
$totalStmt = $con->prepare($totalQuery);
if ($types) $totalStmt->bind_param($types, ...$params);
$totalStmt->execute();
$totalCollections = $totalStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalCollections / $perPage);
$totalStmt->close();

// Fetch paginated collections
$query = "SELECT id, title, description, cover_image FROM collections $searchSQL ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $con->prepare($query);
if ($types) {
    $types .= 'ii';
    $params[] = $perPage;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $perPage, $offset);
}
$stmt->execute();
$collections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Collections</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
     <script src="../js/scripts.js"></script>

    <link rel="stylesheet" href="../css/fixable.css">
    <style>
    :root {
        --bg: #fff;
        --text: #111;
        --card-bg: #f8f8f8;
        --border: #ccc;
        --accent: #007BFF;
        --card-hover-bg: #f0f0f0;
    }
    body.dark {
        --bg: #121212;
        --text: #eaeaea;
        --card-bg: #1e1e1e;
        --border: #333;
        --accent: #66b2ff;
        --card-hover-bg: #2a2a2a;
    }
    body {
        margin: 0;
        font-family: 'Poppins', sans-serif;
        background: var(--bg);
        color: var(--text);
        transition: background 0.3s, color 0.3s;
    }
    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(0, 0, 0, 0.7);
        padding: 10px 20px;
        position: sticky;
        top: 0;
        z-index: 1000;
        
    }
    .navbar .left, .navbar .right {
        display: flex;
        align-items: center;
         gap: 10px;          
    flex-wrap: nowrap;   
    white-space: nowrap; 
    }
    .navbar a {
        color: #ccc;
        text-decoration: none;
        margin: 0 10px;
    }
    .navbar a:hover { color: #fff; }
    .navbar input[type="text"] {
        padding: 5px 10px;
        border-radius: 3px;
        border: none;
        width: 180px;
        font-size: 13px;
    }
    .dark-toggle {
        background: transparent;
        color: #fff;
        border: none;
        font-size: 18px;
        cursor: pointer;
        margin-left: 10px;
    }
    .menu-btn {
        font-size: 16px;
        background-color: var(--accent);
        color: white;
        border: none;
        padding: 6px 10px;
        border-radius: 4px;
        cursor: pointer;
        margin-right: 10px;
    }
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        transform: translateX(-100%);
        width: 250px;
        height: 100%;
        background: var(--card-bg);
        backdrop-filter: blur(10px);
        color: var(--text);
        padding: 60px 20px 20px;
        transition: transform 0.3s ease-in-out;
        z-index: 1001;
    }
    .sidebar.open { transform: translateX(0); }
    .sidebar a {
        display: block;
        padding: 12px 0;
        color: var(--accent);
        text-decoration: none;
        border-bottom: 1px solid var(--border);
    }
    .grid {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        justify-content: flex-start;
    }
    .card-wrapper {
        position: relative;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card-wrapper:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: 0 10px 20px rgba(0,0,0,0.15);
    }
    .card {
        position: relative;
        width: 200px;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
        transition: background 0.3s;
    }
    .card:hover {
        background: var(--card-hover-bg);
    }
    .card img {
        width: 100%;
        height: 260px;
        object-fit: cover;
    }
    .card h3 {
        font-size: 16px;
        padding: 10px;
        margin: 0;
        color: var(--text);
    }
    .card a {
        display: block;
        padding: 0 10px 10px;
        color: var(--accent);
        text-decoration: none;
        font-weight: bold;
    }
    .pagination {
        margin-top: 40px;
        text-align: center;
    }
    .pagination button {
        padding: 8px 12px;
        margin: 0 5px;
        border: none;
        background-color: var(--accent);
        color: #fff;
        border-radius: 4px;
        cursor: pointer;
    }
    .pagination button.active {
        background-color: #0056b3;
    }
    .section {
        padding: 40px 20px;
    }
    </style>
</head>

<?php if ($userRole == 2): ?>
<div id="sidebar" class="sidebar">
  <a href="index.php">📺 My Page</a>
  <a href="../admin/dashboard.php">📈 Insights</a>
  <a href="../admin/library.php">📂 Library</a>
  <a href="../admin/libraryc.php">📂 Collections</a>
  <a href="../admin/librarym.php">🎬 Movies</a>
  <a href="../admin/add_collection.php">➕ Add Collection</a>
  <a href="../admin/add_video.php">➕ Add Episode</a>
  <a href="../admin/add_movie.php">🎞 Add Movie</a>
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
        <?php if ($user): ?>
            <span style="color: #fff; margin-right: 10px;">
                <?= $userRole == 2 ? 'Hello Admin' : 'Hello, ' . htmlspecialchars($user['name'] ?? $user['username']) ?>
            </span>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
            <a href="myfavorites.php">My  Favorites</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
        <input type="text" placeholder="Search title..." value="<?= htmlspecialchars($search) ?>" onkeyup="if(event.key === 'Enter') window.location.href='collections.php?s=' + encodeURIComponent(this.value)">
        <button class="dark-toggle" onclick="toggleDarkMode()">🌙</button>
    </div>
</div>

<div class="section">
    <h2 style="text-align:center; margin-bottom:30px;">Séries</h2>
    <div class="grid">
        <?php foreach ($collections as $row): ?>
            <div class="card-wrapper">
                <div class="card">
                    <?php if ($user && $userRole != 2): ?>
                        <form action="btnfavorite.php" method="POST" style="position:absolute; top:10px; left:10px;">
                            <input type="hidden" name="collection_id" value="<?= $row['id'] ?>">
                            <button type="submit" style="background:none; border:none; font-size:20px; color:var(--accent); cursor:pointer;">
                                <?= isFavorited($user['id'], $row['id'], 'collection', $con) ? '★' : '☆' ?>
                            </button>
                        </form>
                    <?php elseif (!$user): ?>
                        <a href="login.php" style="position:absolute; top:10px; left:10px; font-size:20px; color:var(--accent); text-decoration:none;">☆</a>
                    <?php endif; ?>
                    <img src="../<?= htmlspecialchars($row['cover_image']) ?>" 
                         alt="<?= htmlspecialchars($row['title']) ?>" 
                         onerror="this.onerror=null; this.src='../uploads/covers/default.jpg';">
                    <h3><?= htmlspecialchars($row['title']) ?></h3>
                    <a href="see_ep.php?id=<?= $row['id'] ?>&type=collection">▶ Ver Série</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <button onclick="location.href='collections.php?page=<?= $i ?>&s=<?= urlencode($search) ?>'" 
                class="<?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </button>
        <?php endfor; ?>
    </div>
</div>
</body>
</html>
