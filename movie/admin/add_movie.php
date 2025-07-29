<?php
require_once '../db/db.php';
require_once '../db/auth.php';
require_once '../functions/addmovies_functions.php';

requireRole(2);

$editing = false;
$user = $_SESSION['user'] ?? [];
$error = "";
$success = '';

if (isset($_SESSION['success'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $success = $_SESSION['success'];
        unset($_SESSION['success']);
    } else {
        unset($_SESSION['success']);
    }
}

$edit_id = null;
$movie = null;
$is_featured = false;

if (isset($_GET['edit']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $edit_id = intval($_GET['edit']);
    $movie = getMovie($edit_id, $con);

    if (!$movie) {
        $error = "Movie not found for editing.";
    } else {
        $editing = true;
        $stmt = $con->prepare("SELECT 1 FROM featured_homepage WHERE movie_id = ?");
        $stmt->bind_param("i", $movie['id']);
        $stmt->execute();
        $is_featured = $stmt->get_result()->num_rows > 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_id'])) {
        $edit_id = intval($_POST['edit_id']);
        $movie = getMovie($edit_id, $con);
        if (!$movie) {
            $error = "Movie not found.";
        } else {
            $editing = true;
        }
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $tier_access = $_POST['tier_access'] ?? 'free';
    $mark_featured = isset($_POST['mark_featured']) ? 1 : 0;
    $cover_image = uploadMovieCover($_FILES['cover_image']);
    $video_url = uploadMovieVideo($_FILES['video_file'], $editing ? $movie['video_url'] : null);

    if (empty($title)) {
        $error = "Title is required.";
    } elseif (!$video_url && !$editing) {
        $error = "Video file is required.";
    } else {
        if ($editing) {
            if (updateMovie($edit_id, $title, $description, $cover_image, $video_url, $tier_access, $con)) {
                handleFeaturedMovie($edit_id, $mark_featured);
                $_SESSION['success'] = "Movie updated successfully!";
                header("Location: librarym.php");
                exit();
            } else {
                $error = "Error updating the movie.";
            }
        } else {
            if (insertMovie($title, $description, $cover_image, $video_url, $tier_access, $con)) {
                $new_id = $con->insert_id;
                handleFeaturedMovie($new_id, $mark_featured);
                $_SESSION['success'] = "Movie added successfully!";
                header("Location: librarym.php");
                exit();
            } else {
                $error = "Error inserting the movie.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title><?= $editing ? "Edit Movie" : "New Movie" ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/addstyle.css">
  <script src="../js/scripts.js"></script>
</head>
<body>
<div class="navbar">
  <div class="left">
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <a href="dashboard.php" class="logo">Admin Panel</a>
  </div>
  <div class="right">
     <span>Hello <?= htmlspecialchars($user['username'] ?? 'Admin') ?></span>
    <a href="../views/logout.php">Logout</a>
    <a href="profile.php">Profile</a>
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

<div class="container">
  <h2><?= $editing ? "✏️ Edit Movie" : "🎬 Add New Movie" ?></h2>

  <?php if ($success): ?>
      <p class="message success">✅ <?= htmlspecialchars($success) ?></p>
  <?php endif; ?>
  <?php if ($error): ?>
      <p class="message error">❌ <?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <?php if ($editing): ?>
        <input type="hidden" name="edit_id" value="<?= $movie['id'] ?>">
    <?php endif; ?>

    <label>Title:
      <input type="text" name="title" required value="<?= $editing ? htmlspecialchars($movie['title']) : '' ?>">
    </label>

    <label>Description:
      <textarea name="description" rows="4"><?= $editing ? htmlspecialchars($movie['description']) : '' ?></textarea>
    </label>

    <label>Tier Access:
      <select name="tier_access" required>
        <option value="free" <?= $editing && $movie['tier_access'] === 'free' ? 'selected' : '' ?>>Free</option>
        <option value="tier1" <?= $editing && $movie['tier_access'] === 'tier1' ? 'selected' : '' ?>>Tier 1</option>
        <option value="tier2" <?= $editing && $movie['tier_access'] === 'tier2' ? 'selected' : '' ?>>Tier 2</option>
      </select>
    </label>

    <label>Cover Image:</label>
    <?php if ($editing && $movie['cover_image']): ?>
        <img src="../<?= htmlspecialchars($movie['cover_image']) ?>" alt="Cover" style="max-width: 100%;"><br>
        <small>Leave empty to keep the existing cover</small>
    <?php endif; ?>
    <input type="file" name="cover_image" accept="image/*">

    <label>Video File:</label>
    <?php if ($editing && $movie['video_url']): ?>
        <video controls width="100%">
            <source src="../<?= htmlspecialchars($movie['video_url']) ?>" type="video/mp4">
            Your browser does not support the video tag.
        </video><br>
        <small>Leave empty to keep the existing video</small>
    <?php endif; ?>
    <input type="file" name="video_file" accept="video/*" <?= $editing ? '' : 'required' ?>>

    <label>
        <input type="checkbox" name="mark_featured" <?= !empty($is_featured) ? 'checked' : '' ?>>
        ⭐ Feature this movie on homepage
    </label>

    <button type="submit"><?= $editing ? "Update Movie" : "Upload Movie" ?></button>
  </form>
</div>


</body>
</html>
