<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $user_id = $_SESSION['user_id'];

    if (!empty($title)) {
        $stmt = $pdo->prepare("INSERT INTO webtoon_requests (user_id, title, description) VALUES (:user_id, :title, :description)");
        $stmt->execute([
            'user_id'     => $user_id,
            'title'       => $title,
            'description' => $description
        ]);
    }
}

header('Location: ' . BASE_URL . 'requests/index.php?status=pending');
exit;