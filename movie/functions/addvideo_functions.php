<?php
function deleteEpisode($id) {
    global $con;
    $stmt = $con->prepare("SELECT video_url FROM episodes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $episode = $result->fetch_assoc();

    if ($episode) {
        $filePath = '../' . $episode['video_url'];
        if (file_exists($filePath)) unlink($filePath);

        $stmtDel = $con->prepare("DELETE FROM episodes WHERE id = ?");
        $stmtDel->bind_param("i", $id);
        $stmtDel->execute();

        return "Episódio deletado com sucesso!";
    }
    return "Episódio não encontrado para deletar.";
}

function getEpisode($id) {
    global $con;
    $stmt = $con->prepare("SELECT * FROM episodes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function uploadVideoFile($file, $existingUrl = null) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/videos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $filename = time() . '_' . basename($file['name']);
        $targetFile = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            // Delete old file
            if ($existingUrl && file_exists('../' . $existingUrl)) {
                unlink('../' . $existingUrl);
            }
            return str_replace('../', '', $targetFile);
        }
        return false;
    }
    return $existingUrl ?? false;
}

function updateEpisode($id, $collection_id, $title, $video_url, $tier_access, $mark_featured) {
    global $con;

    $stmt = $con->prepare("UPDATE episodes SET collection_id = ?, title = ?, video_url = ?, tier_access = ? WHERE id = ?");
    $stmt->bind_param("isssi", $collection_id, $title, $video_url, $tier_access, $id);
    if ($stmt->execute()) {
        handleFeaturedEpisode($id, $mark_featured); // pass episode ID
        return true;
    }
    return false;
}


function insertEpisode($collection_id, $title, $video_url, $tier_access, $mark_featured) {
    global $con;

    $stmt = $con->prepare("INSERT INTO episodes (collection_id, title, video_url, tier_access) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $collection_id, $title, $video_url, $tier_access);
    if ($stmt->execute()) {
        $episode_id = $con->insert_id;
        handleFeaturedEpisode($episode_id, $mark_featured); // pass newly inserted ID
        return true;
    }
    return false;
}



function handleFeaturedEpisode($episode_id, $mark_featured) {
    global $con;

    if ($mark_featured) {
        // Insert episode into featured_homepage
        $stmt = $con->prepare("INSERT INTO featured_homepage (episode_id, featured_at)
                               VALUES (?, NOW())
                               ON DUPLICATE KEY UPDATE featured_at = NOW()");
        $stmt->bind_param("i", $episode_id);
        $stmt->execute();
    } else {
        // Remove from featured_homepage
        $stmt = $con->prepare("DELETE FROM featured_homepage WHERE episode_id = ?");
        $stmt->bind_param("i", $episode_id);
        $stmt->execute();
    }
}


?>
