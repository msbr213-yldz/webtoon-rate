<?php
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!empty($email)) {
        // E-postanın veritabanında kayıtlı olup olmadığını kontrol edelim
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Lokal ortam testi için direkt ID ile reset_password.php sayfasına yönlendiriyoruz
            header("Location: reset_password.php?id=" . $user['id']);
            exit;
        } else {
            // E-posta veritabanında bulunamadığında session mesajı ile geri gönderiyoruz
            $_SESSION['auth_error'] = "Bu e-posta adresine kayıtlı bir kullanıcı bulunamadı!";
            header("Location: forget_password.php");
            exit;
        }
    } else {
        $_SESSION['auth_error'] = "Lütfen geçerli bir e-posta adresi girin.";
        header("Location: forget_password.php");
        exit;
    }
} else {
    header("Location: forget_password.php");
    exit;
}