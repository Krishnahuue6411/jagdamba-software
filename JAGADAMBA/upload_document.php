<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['logged_in'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['doc'])) {
    $file = $_FILES['doc'];
    $type = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['type'] ?? 'document');

    $uploadDir = __DIR__ . '/uploads';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'pdf', 'docx', 'doc'])) {
        echo json_encode(['ok' => false, 'error' => 'Invalid file type. Only JPG, PNG, PDF, and DOCX allowed.']);
        exit;
    }

    $filename = 'worker_' . $type . '_' . uniqid() . '.' . $ext;
    $targetPath = $uploadDir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(['ok' => true, 'filepath' => 'uploads/' . $filename]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to save file.']);
    }
    exit;
}
echo json_encode(['ok' => false, 'error' => 'No file received.']);
