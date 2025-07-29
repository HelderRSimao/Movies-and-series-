<?php
require_once '../db/db.php';
require_once '../db/auth.php';
require_once '../functions/addvideo_functions.php';

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
$episode = null;
$collections = $con->query("SELECT id, title FROM collections");
$is_featured = false;

if (isset($_GET['edit']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $edit_id = intval($_GET['edit']);
    $episode = getEpisode($edit_id);

    if (!$episode) {
        $error = "Episode not found for editing.";
    } else {
        $editing = true;
    $stmt = $con->prepare("SELECT 1 FROM featured_homepage WHERE episode_id = ?");
$stmt->bind_param("i", $edit_id);  // use episode id directly

        $stmt->execute();
        $is_featured = $stmt->get_result()->num_rows > 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_GET['edit'])) {
        $edit_id = intval($_GET['edit']);
        $episode = getEpisode($edit_id);
        if (!$episode) {
            $error = "Episode not found.";
        } else {
            $editing = true;
        }
    }

    $collection_id = $_POST['collection_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $tier_access = $_POST['tier_access'] ?? 'free';
    $mark_featured = isset($_POST['mark_featured']) ? 1 : 0;

    if (empty($title) || empty($collection_id)) {
        $error = "Title and collection are required.";
    } else {
        if ($editing) {
            $video_url = uploadVideoFile($_FILES['video_file'], $episode['video_url']);
            if ($video_url === false) {
                $error = "Error updating the video.";
            } else {
                if (updateEpisode($edit_id, $collection_id, $title, $video_url, $tier_access, $con)) {
                    handleFeaturedEpisode($edit_id, $mark_featured);
                    $_SESSION['success'] = "Episode updated successfully!";
                    header("Location: library.php");
                    exit();
                } else {
                    $error = "Error updating the database.";
                }
            }
        } else {
            $video_url = uploadVideoFile($_FILES['video_file']);
            if (!$video_url) {
                $error = "Error uploading the video.";
            } else {
                if (insertEpisode($collection_id, $title, $video_url, $tier_access, $con)) {
                    $new_id = $con->insert_id;
                    handleFeaturedEpisode($new_id, $mark_featured);
                    $_SESSION['success'] = "Episode created successfully!";
                    header("Location: library.php");
                    exit();
                } else {
                    $error = "Error saving to the database.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $editing ? "Edit Episode" : "New Episode" ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

<h2><?= $editing ? "✏️ Edit Episode" : "➕ New Episode" ?></h2>

<?php if ($success): ?>
    <p class="message success">✅ <?= htmlspecialchars($success) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="message error">❌ <?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <label>Collection:
        <select name="collection_id" required>
            <option value="">-- Select a collection --</option>
            <?php $collections->data_seek(0); while ($col = $collections->fetch_assoc()): ?>
                <option value="<?= $col['id'] ?>" <?= ($editing && $episode['collection_id'] == $col['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($col['title']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </label>

    <label>Episode Title:
        <input type="text" name="title" required value="<?= $editing ? htmlspecialchars($episode['title']) : '' ?>">
    </label>

    <label>Video (MP4) <?= $editing ? "(leave empty to keep current)" : "" ?>:
        <input type="file" name="video_file" accept="video/mp4" <?= $editing ? '' : 'required' ?>>
    </label>

    <?php if ($editing && !empty($episode['video_url'])): ?>
        <video controls>
            <source src="../<?= htmlspecialchars($episode['video_url']) ?>" type="video/mp4">
        </video>
    <?php endif; ?>

    <label>Access:
        <select name="tier_access">
            <?php foreach (['free' => 'Free', 'tier1' => 'Tier 1', 'tier2' => 'Tier 2'] as $key => $label): ?>
                <option value="<?= $key ?>" <?= ($editing && $episode['tier_access'] == $key) ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        <input type="checkbox" name="mark_featured" <?= $is_featured ? 'checked' : '' ?>>
        ⭐ Feature this episode
    </label>

    <button type="submit"><?= $editing ? "Update Episode" : "Upload Episode" ?></button>
</form>


</body>
</html>