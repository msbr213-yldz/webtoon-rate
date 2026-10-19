<?php
require_once __DIR__ . '/../config/config.php';

$message = '';
$message_type = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Şifre sıfırlama bağlantısı oluşturuluyor
            $reset_link = BASE_URL . "auth/reset_password.php?id=" . $user['id'];
            $message = "Şifre sıfırlama bağlantınız oluşturuldu: <a href='{$reset_link}' class='alert-link'>Şifreyi Sıfırla</a>";
            $message_type = "success";
        } else {
            $message = "Bu e-posta adresiyle kayıtlı bir kullanıcı bulunamadı.";
        }
    } else {
        $message = "Lütfen e-posta adresinizi girin.";
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center my-5">
    <div class="col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white text-center py-3">
                <h4 class="mb-0">Şifremi Unuttum</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
                <?php endif; ?>

                <form action="forget_password.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label">E-Posta Adresiniz</label>
                        <input type="email" name="email" class="form-control" required placeholder="ör. ahmet@gmail.com">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2">Sıfırlama Bağlantısı Gönder</button>
                </form>
            </div>
            <div class="card-footer text-center py-3">
                <a href="login.php" class="text-decoration-none">Giriş Sayfasına Dön</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>