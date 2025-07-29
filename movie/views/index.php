<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../db/db.php';
require_once '../db/auth.php';
session_start();

$user = $_SESSION['user'] ?? null;
$userTier = $user['tier_access'] ?? 'free';
$userRole = $user['role_id'] ?? 0;
$search = $_GET['s'] ?? '';
$search = trim($search);

function isFavorited($userId, $itemId, $type, $con) {
    if ($type === 'collection') {
        $stmt = $con->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND collections_id = ?");
    } elseif ($type === 'movie') {
        $stmt = $con->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND movies_id = ?");
    } else {
        return false;
    }
    $stmt->bind_param("ii", $userId, $itemId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

$bannerResult = $con->query("(
    SELECT m.id, m.title, m.description, m.cover_image, 'movie' AS type, f.id AS featured_id
    FROM featured_homepage f
    JOIN movies m ON f.movie_id = m.id
    WHERE f.movie_id IS NOT NULL
)
UNION ALL
(
    SELECT c.id, e.title, c.description, c.cover_image, 'collection' AS type, f.id AS featured_id
    FROM featured_homepage f
    JOIN episodes e ON f.episode_id = e.id
    JOIN collections c ON e.collection_id = c.id
    WHERE f.episode_id IS NOT NULL
)
ORDER BY featured_id DESC
LIMIT 5");

$banners = $bannerResult->fetch_all(MYSQLI_ASSOC);

$popularCollections = $con->query("SELECT id, title, description, cover_image FROM collections ORDER BY RAND() LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$latestCollections = $con->query("SELECT id, title, description, cover_image FROM collections ORDER BY created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$popularMovies = $con->query("SELECT id, title, description, cover_image FROM movies ORDER BY RAND() LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$latestMovies = $con->query("SELECT id, title, description, cover_image FROM movies ORDER BY created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$searchResults = [];

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $con->prepare("
        SELECT id, title, description, cover_image, 'movie' AS type
        FROM movies
        WHERE title LIKE ?

        UNION
        SELECT id, title, description, cover_image, 'collection' AS type
        FROM collections
        WHERE title LIKE ?
    ");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $searchResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Início</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
        border-bottom: 1px solid #ddd;
    }

    .hero {
        position: relative;
        width: 100%;
        height: 400px;
        overflow: hidden;
        background: #000;
    }
    /* Dark mode hero background fix */
    body.dark .hero {
        background: linear-gradient(to bottom, #1c1c1c, #121212);
    }

    .banner-slide {
        position: absolute;
        width: 100%;
        height: 100%;
        opacity: 0;
        transition: opacity 1s ease-in-out;
    }

    .banner-slide.active {
        opacity: 1;
        z-index: 1;
    }
.hero img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center center;
  filter: brightness(0.75);
}

    .overlay {
        position: absolute;
        bottom: 30px;
        left: 40px;
        background: var(--overlay-bg);
        color: #fff;
        padding: 20px;
        border-radius: 10px;
        max-width: 600px;
    }
    .overlay h1 {
  font-size: 24px;
  margin-bottom: 10px;
}

.overlay p {
  display: -webkit-box;
  -webkit-line-clamp: 2;  /* Limit to 2 lines */
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  max-height: 3.2em; /* Approx height of 2 lines */
  font-size: 14px;
  line-height: 1.6em;
  margin-bottom: 10px;
}

    .banner-nav {
        position: absolute;
        bottom: 15px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 8px;
        z-index: 10;
    }

    .banner-btn {
        width: 12px;
        height: 12px;
        background: rgba(255, 255, 255, 0.5);
        border: none;
        border-radius: 50%;
        cursor: pointer;
        transition: background 0.3s;
        box-shadow: 0 0 0 1px white;
    }

    .banner-btn.active,
    .banner-btn:hover {
        background: var(--accent);
    }

    .section { padding: 40px 20px; }

    .buttons {
        margin-bottom: 30px;
        text-align: center;
    }

    .buttons button {
        padding: 10px 20px;
        margin: 5px;
        border: none;
        background: var(--accent);
        color: #fff;
        border-radius: 5px;
        font-weight: bold;
        cursor: pointer;
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
    display: -webkit-box;
    -webkit-line-clamp: 2;          /* Limit to 2 lines */
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    max-height: 3.2em;              /* Adjust to fit 2 lines of text */
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

    .hidden { display: none; }
    </style>

</head>
<body onload="loadTheme()">

<?php if ($userRole == 2): ?>
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
      <a href="register.php">Registrar</a>
    <?php endif; ?>
    <input type="text" placeholder="Search title..." value="<?= htmlspecialchars($_GET['s'] ?? '') ?>" onkeyup="if(event.key === 'Enter') window.location.href='?s=' + encodeURIComponent(this.value)">
    <button class="dark-toggle" onclick="toggleDarkMode()">🌙</button>
  </div>
</div>

<?php if (!empty($banners)): ?>
<div class="hero" id="heroBanner">
    <?php foreach ($banners as $index => $banner): ?>
    <div class="banner-slide <?= $index === 0 ? 'active' : '' ?>">
        <img src="../<?= htmlspecialchars($banner['cover_image']) ?>" alt="<?= htmlspecialchars($banner['title']) ?>">
        <div class="overlay">
            <h1><?= htmlspecialchars($banner['title']) ?></h1>
            <p><?= htmlspecialchars($banner['description']) ?></p>
            <?php if ($banner['type'] === 'collection'): ?>
                <a href="see_ep.php?id=<?= $banner['id'] ?>&type=<?= $banner['type'] ?>" class="btn btn-primary">
    ▶ Ver <?= $banner['type'] === 'movie' ? 'Filme' : 'Série' ?>
</a>

            <?php elseif ($banner['type'] === 'movie'): ?>
                <a href="see_ep.php?id=<?= $banner['id'] ?>&type=movie" class="btn btn-primary">▶ Ver Filme</a>
            <?php else: ?>
                <a href="see_ep.php?id=<?= $banner['id'] ?>&type=collection" class="btn btn-primary">▶ Ver Episódio</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="banner-nav">
        <?php foreach ($banners as $i => $_): ?>
            <button onclick="switchBanner(<?= $i ?>)" class="banner-btn <?= $i === 0 ? 'active' : '' ?>"></button>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>



<div class="section">
  <h2 style="text-align: center; margin-bottom: 10px;">Séries</h2>
  <div class="buttons">
      <button onclick="showSection('popular')">Mais Populares</button>
      <button onclick="showSection('latest')">Mais Recentes</button>
  </div>
  <div class="grid" id="popular">
      <?php foreach ($popularCollections as $row): ?>
      <div class="card-wrapper">
          <div class="card">
              <div class="card-top-icons" style="position: absolute; top: 10px; left: 10px; right: 10px; display: flex; justify-content: space-between; z-index: 2;">
                  <?php if ($user && $userRole != 2): ?>
                  <form action="btnfavorite.php" method="POST">
    <input type="hidden" name="collection_id" value="<?= $row['id'] ?>">

    <button type="submit" style="background:none; border:none; font-size:18px; color:var(--accent); cursor:pointer;">
        <?= isFavorited($user['id'], $row['id'], 'collection', $con) ? '★' : '☆' ?>
    </button>
</form>
                  <?php elseif (!$user): ?>
                  <a href="login.php" style="font-size:18px; color:var(--accent); text-decoration:none;">☆</a>
                  <?php endif; ?>
                  <div class="tooltip-wrapper">
                      <div class="info-icon">i</div>
                      <div class="tooltip"><?= htmlspecialchars($row['description']) ?></div>
                  </div>
              </div>
              <img src="../<?= htmlspecialchars($row['cover_image']) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
              <h3><?= htmlspecialchars($row['title']) ?></h3>
              <a href="see_ep.php?id=<?= $row['id'] ?>&type=collection">▶ Ver Série</a>
          </div>
      </div>
      <?php endforeach; ?>
  </div>

  <div class="grid hidden" id="latest">
      <?php foreach ($latestCollections as $row): ?>
      <div class="card-wrapper">
          <div class="card">
              <div class="card-top-icons" style="position: absolute; top: 10px; left: 10px; right: 10px; display: flex; justify-content: space-between; z-index: 2;">
                  <?php if ($user && $userRole != 2): ?>
                  <form action="btnfavorite.php" method="POST">
    <input type="hidden" name="collection_id" value="<?= $row['id'] ?>">
    <button type="submit" style="background:none; border:none; font-size:18px; color:var(--accent); cursor:pointer;">
        <?= isFavorited($user['id'], $row['id'], 'collection', $con) ? '★' : '☆' ?>
    </button>
</form>
                  <?php elseif (!$user): ?>
                  <a href="login.php" style="font-size:18px; color:var(--accent); text-decoration:none;">☆</a>
                  <?php endif; ?>
                  <div class="tooltip-wrapper">
                      <div class="info-icon">i</div>
                      <div class="tooltip"><?= htmlspecialchars($row['description']) ?></div>
                  </div>
              </div>
              <img src="../<?= htmlspecialchars($row['cover_image']) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
              <h3><?= htmlspecialchars($row['title']) ?></h3>
              <a href="see_ep.php?id=<?= $row['id'] ?>&type=collection" >▶ Ver Série</a>
          </div>
      </div>
      <?php endforeach; ?>
  </div>
</div>

<div class="section">
  <h2 style="text-align: center; margin-top: 40px; margin-bottom: 10px;">Filmes</h2>
  <div class="buttons">
      <button onclick="showSection('movies-popular')">Mais Populares</button>
      <button onclick="showSection('movies-latest')">Mais Recentes</button>
  </div>
  <div class="grid" id="movies-popular">
      <?php foreach ($popularMovies as $row): ?>
      <div class="card-wrapper">
          <div class="card">
              <div class="card-top-icons" style="position: absolute; top: 10px; left: 10px; right: 10px; display: flex; justify-content: space-between; z-index: 2;">
                  <?php if ($user && $userRole != 2): ?>
                  <form action="btnfavorite.php" method="POST">
    <input type="hidden" name="movies_id" value="<?= $row['id'] ?>">    
                      <button type="submit" style="background:none; border:none; font-size:18px; color:var(--accent); cursor:pointer;">
                          <?= isFavorited($user['id'], $row['id'], 'movie', $con) ? '★' : '☆' ?>
                      </button>
                  </form>
                  <?php elseif (!$user): ?>
                  <a href="login.php" style="font-size:18px; color:var(--accent); text-decoration:none;">☆</a>
                  <?php endif; ?>
                  <div class="tooltip-wrapper">
                      <div class="info-icon">i</div>
                      <div class="tooltip"><?= htmlspecialchars($row['description']) ?></div>
                  </div>
              </div>
              <img src="../<?= htmlspecialchars($row['cover_image']) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
              <h3><?= htmlspecialchars($row['title']) ?></h3>
              <a href="see_ep.php?id=<?= $row['id'] ?>&type=movie">▶ Ver Filme</a>
          </div>
      </div>
      <?php endforeach; ?>
  </div>

  <div class="grid hidden" id="movies-latest">
      <?php foreach ($latestMovies as $row): ?>
      <div class="card-wrapper">
          <div class="card">
              <div class="card-top-icons" style="position: absolute; top: 10px; left: 10px; right: 10px; display: flex; justify-content: space-between; z-index: 2;">
                  <?php if ($user && $userRole != 2): ?>
                <form action="btnfavorite.php" method="POST">
                    <input type="hidden" name="movies_id" value="<?= $row['id'] ?>">
                    <button type="submit" style="background:none; border:none; font-size:18px; color:var(--accent); cursor:pointer;">
                        <?= isFavorited($user['id'], $row['id'], 'movie', $con) ? '★' : '☆' ?>
                    </button>
                </form>

                  <?php elseif (!$user): ?>
                  <a href="login.php" style="font-size:18px; color:var(--accent); text-decoration:none;">☆</a>
                  <?php endif; ?>
                  <div class="tooltip-wrapper">
                      <div class="info-icon">i</div>
                      <div class="tooltip"><?= htmlspecialchars($row['description']) ?></div>
                  </div>
              </div>
              <img src="../<?= htmlspecialchars($row['cover_image']) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
              <h3><?= htmlspecialchars($row['title']) ?></h3>
              <a href="see_ep.php?id=<?= $row['id'] ?>&type=movie">▶ Ver Filme</a>
          </div>
      </div>
      <?php endforeach; ?>
  </div>
</div>

<script>
function showSection(section) {
    const seriesSections = ['popular', 'latest'];
    const movieSections = ['movies-popular', 'movies-latest'];

    if (seriesSections.includes(section)) {
        seriesSections.forEach(id => {
            document.getElementById(id).classList.toggle('hidden', id !== section);
        });
    }

    if (movieSections.includes(section)) {
        movieSections.forEach(id => {
            document.getElementById(id).classList.toggle('hidden', id !== section);
        });
    }
}
let currentBanner = 0;
const slides = document.querySelectorAll('.banner-slide');
const navDots = document.querySelectorAll('.banner-btn');

function switchBanner(index) {
    slides.forEach((slide, i) => {
        slide.classList.toggle('active', i === index);
        navDots[i].classList.toggle('active', i === index);
    });
    currentBanner = index;
}
 function toggleDarkMode() {
      document.body.classList.toggle("dark");
      localStorage.setItem("darkMode", document.body.classList.contains("dark") ? "on" : "off");
    }
    function loadTheme() {
      if (localStorage.getItem("darkMode") === "on") {
        document.body.classList.add("dark");
      }
      const alertBox = document.querySelector('.alert');
      if (alertBox) {
        setTimeout(() => {
          alertBox.style.opacity = '0';
          setTimeout(() => alertBox.remove(), 500);
        }, 3000);
      }
    }

setInterval(() => {
    const nextBanner = (currentBanner + 1) % slides.length;
    switchBanner(nextBanner);
}, 20000); 
</script>
</body>
</html> 