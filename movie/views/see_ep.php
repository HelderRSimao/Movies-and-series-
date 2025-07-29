<?php
session_start();
require_once '../db/db.php';
require_once '../db/auth.php';

$user = $_SESSION['user'] ?? null;
$userId = $user['id'] ?? null;
$userTier = $user['tier'] ?? 'free';
$userRole = $user['role'] ?? '';
$isAdmin = ($userRole == 2);

$id = $_GET['id'] ?? null;
$type = $_GET['type'] ?? null;
if (!$id || !$type) die('Missing ID or type.');

$isMovie = ($type === 'movie');
$rating = 0;

if ($isMovie) {
    $stmt = $con->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $movie = $stmt->get_result()->fetch_assoc();
    if (!$movie) die('Movie not found.');

    $cover = $movie['cover_image'];
    $title = $movie['title'];
    $description = $movie['description'];
    $videoUrl = $movie['video_url'];
    $tierAccess = $movie['tier_access'];

    // 🔐 Enforce tier access check for movies
    $canWatchMovie = $tierAccess === 'free' ||
        $tierAccess === $userTier ||
        ($userTier === 'tier2' && $tierAccess === 'tier1');

    if (!$canWatchMovie && !$isAdmin) {
        header("Location: subscription.php");
        exit;
    }

    $ratingStmt = $con->prepare("SELECT AVG(rating) as avg_rating FROM ratings WHERE movie_id = ?");
    $ratingStmt->bind_param("i", $id);
    $ratingStmt->execute();
    $rating = round($ratingStmt->get_result()->fetch_assoc()['avg_rating'] ?? 0, 1);

} else {
    $stmt = $con->prepare("SELECT * FROM collections WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $collection = $stmt->get_result()->fetch_assoc();
    if (!$collection) die('Collection not found.');

    $cover = $collection['cover_image'];
    $title = $collection['title'];
    $description = $collection['description'];

    $stmt = $con->prepare("SELECT * FROM episodes WHERE collection_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $episodes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $watched = [];
    if ($userId) {
        $check = $con->query("SHOW TABLES LIKE 'user_watched_episodes'");
        if ($check && $check->num_rows) {
            $stmt = $con->prepare("SELECT episode_id FROM user_watched_episodes WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $watched[] = $r['episode_id'];
        }
    }

    $firstPlayable = null;
    foreach ($episodes as $ep) {
        if (
            $ep['tier_access'] === 'free' ||
            $ep['tier_access'] === $userTier ||
            ($userTier === 'tier2' && $ep['tier_access'] === 'tier1')
        ) {
            $firstPlayable = $ep;
            break;
        }
    }

    $ratingStmt = $con->prepare("SELECT AVG(rating) as avg_rating FROM ratings WHERE collection_id = ?");
    $ratingStmt->bind_param("i", $id);
    $ratingStmt->execute();
    $rating = round($ratingStmt->get_result()->fetch_assoc()['avg_rating'] ?? 0, 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($title) ?></title>
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

    .background {
        position: fixed;
        inset: 0;
        background: url('../<?= $cover ?>') center/cover no-repeat;
        filter: blur(10px);
        z-index: -2;
    }

    .overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        z-index: -1;
    }

    .container {
        max-width: 850px;
        margin: 120px auto 60px;
        padding: 30px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 20px;
        backdrop-filter: blur(10px);
    }

    .info {
        display: flex;
        gap: 20px;
        margin-top: 20px;
    }

    .info img {
        width: 140px;
        border-radius: 12px;
        object-fit: cover;
    }

    .video {
        margin-top: 20px;
        border-radius: 12px;
        overflow: hidden;
    }

    video {
        width: 100%;
        border-radius: 12px;
    }

    .rating {
        margin: 15px 0;
        font-size: 24px;
    }

    .stars input[type="radio"] {
        display: none;
    }

    .stars label {
        color: gray;
        font-size: 26px;
        cursor: pointer;
        transition: color 0.3s;
    }

    .stars input:checked ~ label,
    .stars label:hover,
    .stars label:hover ~ label {
        color: gold;
    }

    .episode {
        padding: 10px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        margin-bottom: 10px;
        cursor: pointer;
    }

    .text-muted {
        opacity: 0.5;
    }
  </style>
</head>


<?php if ($userRole == 'admin' || $userRole == 2): ?>
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
    <?php if ($userRole == 'admin' || $userRole == 2): ?>
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <?php endif; ?>
    <a href="index.php">Home</a>
    <a href="collections.php">Series</a>
    <a href="movies.php">Movies</a>
  </div>
  <div class="right">
    <?php if ($user): ?>
      <span style="color: #fff; margin-right: 10px;">
        <?= $userRole == 'admin' || $userRole == 2 ? 'Hello Admin' : 'Hello, ' . htmlspecialchars($user['name'] ?? $user['username']) ?>
      </span>
      <a href="profile.php">Profile</a>
      <a href="logout.php">Logout</a>
      <a href="myfavorites.php">My Favorites</a>
    <?php else: ?>
      <a href="login.php">Login</a>
      <a href="register.php">Register</a>
    <?php endif; ?>
    <input type="text" placeholder="Search title...">
    <button class="dark-toggle" onclick="toggleDarkMode()">🌙</button>
  </div>
</div>

<div class="background"></div>
<div class="overlay"></div>

<div class="container">
  <div class="video">
    <?php if ($isMovie): ?>
      <video controls>
        <source src="/movie/<?= ltrim($videoUrl, '/') ?>" type="video/mp4">
      </video>
    <?php elseif ($firstPlayable): ?>
      <video id="videoPlayer" controls>
        <source src="/movie/<?= ltrim($firstPlayable['video_url'], '/') ?>" type="video/mp4">
      </video>
    <?php endif; ?>
  </div>

  <div class="info mt-4">
    <img src="../<?= $cover ?>" alt="">
    <div>
      <h2><?= htmlspecialchars($title) ?></h2>
      <p><?= htmlspecialchars($description) ?></p>
      <div class="rating">⭐ <?= $rating ?> / 5</div>
      <?php if ($userId): ?>
        <div class="stars" data-id="<?= $id ?>" data-type="<?= $isMovie ? 'movie' : 'collection' ?>">
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <input type="radio" id="star-<?= $type ?>-<?= $i ?>" name="rating-<?= $type ?>-<?= $id ?>" value="<?= $i ?>">
            <label for="star-<?= $type ?>-<?= $i ?>">★</label>
          <?php endfor; ?>
        </div>
      <?php else: ?>
        <div class="mt-2">
          <a href="login.php" class="btn btn-warning btn-sm">🔐 Login to rate this <?= $isMovie ? 'movie' : 'series' ?></a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$isMovie): ?>
    <div class="mt-4">
      <button id="toggleBtn" class="btn btn-outline-light btn-sm mb-2" onclick="toggleEpisodes()">🙈 Hide Episodes</button>
      <div id="episodeList">
        <?php foreach ($episodes as $i => $ep):
     $canPlay = $isAdmin || 
            $ep['tier_access'] === 'free' || 
            $ep['tier_access'] === $userTier || 
            ($userTier === 'tier2' && $ep['tier_access'] === 'tier1');


          $watchedEp = $userId && in_array($ep['id'], $watched);
        ?>
          <div class="episode <?= !$canPlay ? 'text-muted' : '' ?>" 
            <?= $canPlay ? "onclick=\"playEpisode('" . ltrim($ep['video_url'], '/') . "')\"" : "onclick=\"window.location.href='subscription.php'\"" ?>>
            <?= $i+1 ?>. <?= htmlspecialchars($ep['title']) ?>
            <span class="float-end"><?= $canPlay ? ($watchedEp ? '👁️ Watched' : '▶ Play') : '🔒 Locked' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>

function playEpisode(url) {
  const player = document.getElementById('videoPlayer');
  if (player) {
    player.src = '/movie/' + url;
    player.play();
  }
}

function toggleEpisodes() {
  const list = document.getElementById('episodeList');
  const btn = document.getElementById('toggleBtn');

  if (list.style.display === 'none') {
    list.style.display = 'block';
    btn.innerHTML = '🙈 Hide Episodes';
  } else {
    list.style.display = 'none';
    btn.innerHTML = '👁️ See Episodes';
  }
}

document.querySelectorAll('.stars input').forEach(star => {
  star.addEventListener('change', function () {
    const rating = this.value;
    const wrapper = this.closest('.stars');
    const contentId = wrapper.dataset.id;
    const type = wrapper.dataset.type;

    fetch('rate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `rating=${rating}&${type}_id=${contentId}`
    }).then(res => res.json()).then(data => {
      if (data.success) {
        alert("Thanks for rating!");
        location.reload();
      } else {
        alert(data.message || "Rating failed.");
      }
    });
  });
});
</script>
</body>
</html>
