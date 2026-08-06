<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['signature_img'])) {
    $file = $_FILES['signature_img'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
        // Save as signature.png in the workspace directory
        if (move_uploaded_file($file['tmp_name'], __DIR__ . '/signature.png')) {
            $_SESSION['signature_msg'] = ['type' => 'success', 'text' => 'Signature uploaded successfully.'];
        } else {
            $_SESSION['signature_msg'] = ['type' => 'error', 'text' => 'Failed to save uploaded file.'];
        }
    } else {
        $_SESSION['signature_msg'] = ['type' => 'error', 'text' => 'Only JPG, JPEG, and PNG images are allowed.'];
    }
}
header("Location: invoices_list.php");
exit;
