<?php
require_once __DIR__ . '/../config/config.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($email) && !empty($password)) {
        // Kullanıcı adı veya Email kontrolü
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt_check->execute([$username, $email]);

        if ($stmt_check->fetch()) {
            $message = $lang['username_email_taken'];
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_ins = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, 'user', NOW())");
            if ($stmt_ins->execute([$username, $email, $hashed_password])) {
                $_SESSION['flash_message'] = $lang['register_success'];
                $_SESSION['flash_type']    = 'success';
                header("Location: " . BASE_URL . "auth/login.php");
                exit;
            }
        }
    } else {
        $message = $lang['fill_all_fields'];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center my-5">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow border-0 p-4">
            <h3 class="fw-bold text-center mb-4"><i class="bi bi-person-plus text-success"></i> <?= $lang['register_title'] ?></h3>

            <?php if ($message): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold"><?= $lang['username_label'] ?></label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold"><?= $lang['email_label'] ?></label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold"><?= $lang['password'] ?></label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success fw-bold w-100 py-2 mb-3"><?= $lang['register_btn'] ?></button>
                <div class="text-center">
                    <small class="text-muted"><?= $lang['already_have_account'] ?> <a href="login.php" class="fw-bold"><?= $lang['login'] ?></a></small>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>