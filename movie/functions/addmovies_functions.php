<?php
// Upload cover image for movie
function uploadMovieCover($file) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/covers/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $filename = uniqid('movie_cover_') . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $path = $uploadDir . $filename;
        move_uploaded_file($file['tmp_name'], $path);
        return str_replace('../', '', $path);
    }
    return false;
}

// Upload video file for movie
function uploadMovieVideo($file, $existing = null) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/videos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $filename = time() . '_' . basename($file['name']);
        $target = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            if ($existing && file_exists('../' . $existing)) unlink('../' . $existing);
            return str_replace('../', '', $target);
        }
    }
    return $existing;
}

// Insert a new movie
function insertMovie($title, $description, $cover_image, $video_url, $tier_access, $con) {
    $stmt = $con->prepare("INSERT INTO movies (title, description, cover_image, video_url, tier_access, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("sssss", $title, $description, $cover_image, $video_url, $tier_access);
    return $stmt->execute();
}

// Update an existing movie
function updateMovie($id, $title, $description, $cover_image, $video_url, $tier_access, $con) {
    $sql = "UPDATE movies SET title = ?, description = ?, tier_access = ?, updated_at = NOW()";
    $params = [$title, $description, $tier_access];
    $types = "sss";

    if ($cover_image) {
        $sql .= ", cover_image = ?";
        $types .= "s";
        $params[] = $cover_image;
    }

    if ($video_url) {
        $sql .= ", video_url = ?";
        $types .= "s";
        $params[] = $video_url;
    }

    $sql .= " WHERE id = ?";
    $types .= "i";
    $params[] = $id;

    $stmt = $con->prepare($sql);
    $stmt->bind_param($types, ...$params);
    return $stmt->execute();
}

// Get movie by ID
function getMovie($id, $con) {
    $stmt = $con->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get all movies
function getAllMovies($con) {
    return $con->query("SELECT * FROM movies ORDER BY id DESC");
}

// Delete movie
function deleteMovie($id, $con) {
    $movie = getMovie($id, $con);
    if (!$movie) return false;

    if (!empty($movie['video_url']) && file_exists('../' . $movie['video_url'])) {
        unlink('../' . $movie['video_url']);
    }
    if (!empty($movie['cover_image']) && file_exists('../' . $movie['cover_image'])) {
        unlink('../' . $movie['cover_image']);
    }

    $stmt = $con->prepare("DELETE FROM movies WHERE id = ?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

function handleFeaturedMovie($movie_id, $mark_featured) {
    global $con;

    if ($mark_featured) {
        // Add or update the movie in featured_homepage
        $stmt = $con->prepare("INSERT INTO featured_homepage (movie_id, featured_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE featured_at = NOW()");
        $stmt->bind_param("i", $movie_id);
        $stmt->execute();
    } else {
        // Remove from featured_homepage
        $stmt = $con->prepare("DELETE FROM featured_homepage WHERE movie_id = ?");
        $stmt->bind_param("i", $movie_id);
        $stmt->execute();
    }
}

?>
