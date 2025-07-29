<?php

// Function to handle image upload for collections
function handleImageUpload($fileField) {
    if (isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES[$fileField]['tmp_name'];
        $ext = pathinfo($_FILES[$fileField]['name'], PATHINFO_EXTENSION);
        $filename = uniqid('cover_', true) . '.' . $ext;
        $upload_dir = '../uploads/covers/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        move_uploaded_file($tmp_name, $upload_dir . $filename);
        return 'uploads/covers/' . $filename;
    }
    return false;

}



// Function to add a new collection
function addCollection($title, $description, $cover_image, $con) {
    $stmt = $con->prepare("INSERT INTO collections (title, description, cover_image) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $description, $cover_image);
    return $stmt->execute();
}


// Function to delete a collection by ID
function deleteCollection($id, $con) {
    $stmt = $con->prepare("DELETE FROM collections WHERE id = ?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}


// Function to edit an existing collection
function editCollection($id, $title, $description, $cover_image, $con) {
    if ($cover_image) {
        $stmt = $con->prepare("UPDATE collections SET title = ?, description = ?, cover_image = ? WHERE id = ?");
        $stmt->bind_param("sssi", $title, $description, $cover_image, $id);
    } else {
        $stmt = $con->prepare("UPDATE collections SET title = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $title, $description, $id);
    }
    return $stmt->execute();
}


//// Function to get a collection by ID
function getAllCollections($con) {
    return $con->query("SELECT * FROM collections ORDER BY id DESC");
}
function getCollectionById($id, $con) {
    $stmt = $con->prepare("SELECT * FROM collections WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}




?>