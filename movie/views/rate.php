<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db/db.php';
header('Content-Type: application/json');

$user = $_SESSION['user'] ?? null;
$rating = $_POST['rating'] ?? null;

// Validate user andrate
if (!$user || !$rating) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in and provide a rating.'
    ]);
    exit;
}

$rating = (int)$rating;
if ($rating < 1 || $rating > 5) {
    echo json_encode([
        'success' => false,
        'message' => 'Rating must be between 1 and 5.'
    ]);
    exit;
}

$userId = $user['id'];
$movieId = $_POST['movie_id'] ?? null;
$collectionId = $_POST['collection_id'] ?? null;

if (!$movieId && !$collectionId) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing movie or collection ID.'
    ]);
    exit;
}

try {
    // Prepare insert or update statement
    if ($movieId) {
        $stmt = $con->prepare("
            INSERT INTO ratings (user_id, movie_id, rating, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = NOW()
        ");
        $stmt->bind_param("iii", $userId, $movieId, $rating);
    } else {
        $stmt = $con->prepare("
            INSERT INTO ratings (user_id, collection_id, rating, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = NOW()
        ");
        $stmt->bind_param("iii", $userId, $collectionId, $rating);
    }

    if (!$stmt->execute()) {
        throw new Exception("Could not save rating.");
    }

    // Auto-feature: If admin gives 5 stars, promote it ,cool feuature 
    if ($user['role'] === 'admin' && $rating === 5) {
        if ($movieId) {
            $con->query("INSERT IGNORE INTO featured_homepage (movie_id) VALUES ($movieId)");
        } else {
            $result = $con->query("
                SELECT id FROM episodes
                WHERE collection_id = $collectionId
                ORDER BY id ASC
                LIMIT 1
            ");
            $episode = $result->fetch_assoc();
            if (!empty($episode['id'])) {
                $episodeId = $episode['id'];
                $con->query("INSERT IGNORE INTO featured_homepage (episode_id) VALUES ($episodeId)");
            }
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
