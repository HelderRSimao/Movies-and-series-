<?php
require_once '../db/db.php';
require_once '../db/auth.php';
require_once '../functions/collection_functions.php';

requireRole(2);
$user = $_SESSION['user'];
$error = '';
$success = ''; 
$edit_mode = false;
$edit_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $cover_image = handleImageUpload('cover_image');

    if (isset($_POST['edit_id'])) {
        $edit_id = intval($_POST['edit_id']);
        if (editCollection($edit_id, $title, $description, $cover_image, $con)) {
            $_SESSION['success'] = "Collection updated successfully!";
            header("Location: libraryc.php");
            exit;
        } else {
            $error = "Error updating collection.";
        }
    } else {
        if (addCollection($title, $description, $cover_image, $con)) {
            $_SESSION['success'] = "Collection added successfully!";
            header("Location: libraryc.php");
            exit;
        } else {
            $error = "Error adding collection.";
        }
    }
}

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_data = getCollectionById($edit_id, $con);
    if ($edit_data) {
        $edit_mode = true;
    } else {
        $error = "Collection not found.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title><?= $edit_mode ? "Edit Collection" : "Add New Collection" ?></title>
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

<h2><?= $edit_mode ? "✏️ Edit Collection" : "➕ Add New Collection" ?></h2>

<?php if ($success): ?>
    <p class="message success">✅ <?= htmlspecialchars($success) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="message error">❌ <?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?php if ($edit_mode): ?>
        <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?>">
    <?php endif; ?>

    <label>Title:
        <input type="text" name="title" required value="<?= $edit_mode ? htmlspecialchars($edit_data['title']) : '' ?>">
    </label>

    <label>Description:
        <textarea name="description" rows="4"><?= $edit_mode ? htmlspecialchars($edit_data['description']) : '' ?></textarea>
    </label>

    <label>Cover:
        <?php if ($edit_mode && $edit_data['cover_image']): ?>
            <img src="../<?= htmlspecialchars($edit_data['cover_image']) ?>" alt="Current cover" style="max-width: 200px; border: 1px solid var(--border);"><br>
            <small>Leave empty to keep the existing cover</small>
        <?php endif; ?>
        <input type="file" name="cover_image" accept="image/*">
    </label>

    <button type="submit"><?= $edit_mode ? "Update Collection" : "Add Collection" ?></button>
</form>
</body>
</html>
