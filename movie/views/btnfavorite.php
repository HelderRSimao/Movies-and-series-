<?php
require_once '../db/db.php';
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user']['id'] ?? null;

// seeif it's a movie or a collection
$collectionId = $_POST['collection_id'] ?? null;
$movieId = $_POST['movies_id'] ?? null;

if (!$userId || (!$collectionId && !$movieId)) {
    die('No valid ID provided.');
}

// Check if it’s already favorited
if ($collectionId) {
    $checkStmt = $con->prepare("SELECT id FROM favorites WHERE user_id = ? AND collections_id = ?");
    $checkStmt->bind_param("ii", $userId, $collectionId);
} else {
    $checkStmt = $con->prepare("SELECT id FROM favorites WHERE user_id = ? AND movies_id = ?");
    $checkStmt->bind_param("ii", $userId, $movieId);
}

$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {

    if ($collectionId) {
        $deleteStmt = $con->prepare("DELETE FROM favorites WHERE user_id = ? AND collections_id = ?");
        $deleteStmt->bind_param("ii", $userId, $collectionId);
    } else {
        $deleteStmt = $con->prepare("DELETE FROM favorites WHERE user_id = ? AND movies_id = ?");
        $deleteStmt->bind_param("ii", $userId, $movieId);
    }
    $deleteStmt->execute();
} else {
    
    if ($collectionId) {
        $insertStmt = $con->prepare("INSERT INTO favorites (user_id, collections_id) VALUES (?, ?)");
        $insertStmt->bind_param("ii", $userId, $collectionId);
    } else {
        $insertStmt = $con->prepare("INSERT INTO favorites (user_id, movies_id) VALUES (?, ?)");
        $insertStmt->bind_param("ii", $userId, $movieId);
    }
    $insertStmt->execute();
}


header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
