<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($user_id > 0) {
    try {
        if (isset($_GET['id'])) {
            // Tek bir bildirimi okundu yap
            $notif_id = (int)$_GET['id'];
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :u_id");
            $stmt->execute(['id' => $notif_id, 'u_id' => $user_id]);
        } else {
            // Tüm bildirimleri okundu yap
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :u_id");
            $stmt->execute(['u_id' => $user_id]);
        }
    } catch (PDOException $e) {
        error_log("Okundu İşaretleme Hatası: " . $e->getMessage());
    }
}

// Gelinen sayfaya (örneğin notifications.php veya bulunulan sayfaya) geri döndür
$redirect = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . 'notifications/notifications.php');
header("Location: " . $redirect);
exit;