<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center my-5">
    <div class="col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white text-center py-3">
                <h4 class="mb-0">Şifremi Unuttum</h4>
            </div>
            <div class="card-body p-4">
                <p class="mb-3">Bu sitede e-posta ile şifre sıfırlama bulunmuyor. Şifreni unuttuysan site yöneticisiyle iletişime geç.</p>
                <p class="mb-0 text-muted small">Giriş yaptıysan şifreni <a href="change_password.php">buradan değiştirebilirsin</a>.</p>
            </div>
            <div class="card-footer text-center py-3">
                <a href="login.php" class="text-decoration-none">Giriş Sayfasına Dön</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
