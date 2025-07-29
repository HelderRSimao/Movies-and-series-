<?php
require_once '../db/db.php';
require_once '../db/auth.php';
session_start();

$user = $_SESSION['user'] ?? null;
$userId = $user['id'] ?? null;

if (!$userId) {
    header("Location: login.php");
    exit;
}

// Fetch favorites collections and movies
$stmt = $con->prepare("
    (
        SELECT c.id, c.title, c.description, c.cover_image, 'collection' AS type
        FROM favorites f
        JOIN collections c ON f.collections_id = c.id
        WHERE f.user_id = ? AND f.collections_id IS NOT NULL
    )
    UNION ALL
    (
        SELECT m.id, m.title, m.description, m.cover_image, 'movie' AS type
        FROM favorites f
        JOIN movies m ON f.movies_id = m.id
        WHERE f.user_id = ? AND f.movies_id IS NOT NULL
    )
");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$favorites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Updated function
function isFavorited($userId, $contentId, $type, $con) {
    if ($type === 'collection') {
        $stmt = $con->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND collections_id = ?");
    } else {
        $stmt = $con->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND movies_id = ?");
    }
    $stmt->bind_param("ii", $userId, $contentId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Meus Favoritos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <script src="../js/scripts.js"></script>
    <style>
        :root {
            --bg: #fff;
            --text: #111;
            --card-bg: #f8f8f8;
            --border: #ccc;
            --accent: #007BFF;
            --tooltip-bg: rgba(0, 0, 0, 0.85);
            --tooltip-text: #fff;
            --overlay-bg: rgba(0, 0, 0, 0.6);
            --navbar-bg: rgba(0, 0, 0, 0.6);
            --card-hover-bg: #f0f0f0;
        }

        body.dark {
            --bg: #121212;
            --text: #eaeaea;
            --card-bg: #1e1e1e;
            --border: #333;
            --accent: #66b2ff;
            --tooltip-bg: rgba(255, 255, 255, 0.9);
            --tooltip-text: #111;
            --overlay-bg: rgba(20, 20, 20, 0.8);
            --navbar-bg: rgba(0, 0, 0, 0.85);
            --card-hover-bg: #2a2a2a;
        }

        * { box-sizing: border-box; }
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
            background: var(--navbar-bg);
            padding: 10px 20px;
            position: absolute;
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        .navbar .left, .navbar .right {
            display: flex;
            align-items: center;
        }

        .navbar a {
            color: #ccc;
            text-decoration: none;
            margin: 0 10px;
        }

        .navbar a:hover { color: #fff; }

        .dark-toggle {
            background: transparent;
            color: #fff;
            border: none;
            font-size: 18px;
            cursor: pointer;
            margin-left: 10px;
        }

        .section {
            padding: 80px 20px 40px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            justify-items: center;
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
            width: 200px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            transition: background 0.3s;
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

        .tooltip-wrapper {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 2;
        }

        .info-icon {
            background: var(--accent);
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            text-align: center;
            line-height: 22px;
            font-size: 12px;
            cursor: pointer;
            user-select: none;
        }

        .tooltip {
            background: var(--tooltip-bg);
            color: var(--tooltip-text);
            padding: 10px;
            max-width: 180px;
            border-radius: 6px;
            font-size: 13px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease-in-out;
            z-index: 3;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            user-select: none;
        }

        .tooltip-wrapper:hover .tooltip {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>
<body>
<div class="navbar">
    <div class="left">
        <a href="index.php">Home</a>
        <a href="collections.php">Séries</a>
        <a href="myfavorites.php">Favoritos</a>
    </div>
    <div class="right">
        <?php if ($user): ?>
            <span style="color: #fff; margin-right: 10px;">
                Olá, <?= htmlspecialchars($user['name'] ?? $user['username']) ?>
            </span>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
        <?php endif; ?>
        <button class="dark-toggle" onclick="toggleDarkMode()">🌙</button>
    </div>
</div>

<div class="section">
    <h2 style="text-align:center">Meus Favoritos</h2>
    <div class="grid">
        <?php foreach ($favorites as $row): ?>
            <div class="card-wrapper">
                <div class="card">
                    <div class="card-top-icons" style="position: absolute; top: 10px; left: 10px; right: 10px; display: flex; justify-content: space-between; z-index: 2;">
                        <form action="toggle_favorite.php" method="POST">
                            <input type="hidden" name="<?= $row['type'] === 'movie' ? 'movies_id' : 'collections_id' ?>" value="<?= $row['id'] ?>">
                            <button type="submit" style="background:none; border:none; font-size:18px; color:var(--accent); cursor:pointer;">
                                <?= isFavorited($userId, $row['id'], $row['type'], $con) ? '★' : '☆' ?>
                            </button>
                        </form>
                        <div class="tooltip-wrapper">
                            <div class="info-icon">i</div>
                            <div class="tooltip"><?= htmlspecialchars($row['description']) ?></div>
                        </div>
                    </div>
                    <img src="../<?= htmlspecialchars($row['cover_image']) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
                    <h3><?= htmlspecialchars($row['title']) ?></h3>
                    <a href="see_ep.php?id=<?= $row['id'] ?>&type=<?= $row['type'] ?>">
                        ▶ <?= $row['type'] === 'movie' ? 'Ver Filme' : 'Ver Série' ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>


</body>
</html>