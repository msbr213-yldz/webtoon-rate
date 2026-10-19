<?php
require_once __DIR__ . '/../config/config.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if (!$user_id) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['password'] ?? '';

    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashed_password, $user_id])) {
            $success = "Şifreniz başarıyla güncellendi! Giriş yapabilirsiniz.";
        } else {
            $error = "Şifre güncellenirken bir hata oluştu.";
        }
    } else {
        $error = "Lütfen yeni bir şifre girin.";
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center my-5">
    <div class="col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h3 class="card-title text-center fw-bold mb-4">Yeni Şifre Belirleme</h3>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success) ?><br>
                        <a href="login.php" class="btn btn-sm btn-success mt-2">Giriş Yap</a>
                    </div>
                <?php else: ?>
                    <form action="reset_password.php?id=<?= $user_id ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Yeni Şifre</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            Şifreyi Güncelle
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>