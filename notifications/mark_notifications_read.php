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

// Gelinen sayfaya (sadece aynı domain ise) geri döndür
$fallback = BASE_URL . 'notifications/notification.php';
$referer  = $_SERVER['HTTP_REFERER'] ?? '';
$ref_host = parse_url($referer, PHP_URL_HOST);
$own_host = parse_url(BASE_URL, PHP_URL_HOST);

$redirect = ($ref_host && $own_host && strcasecmp($ref_host, $own_host) === 0) ? $referer : $fallback;
header("Location: " . $redirect);
exit;