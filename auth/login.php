<?php
require_once __DIR__ . '/../config/config.php';

$message = '';
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['login_input']);
    $password    = trim($_POST['password']);

    if (!empty($login_input) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$login_input, $login_input]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            header("Location: " . BASE_URL . "home.php");
            exit;
        } else {
            $message = $lang['invalid_credentials'];
            $message_type = 'danger';
        }
    } else {
        $message = $lang['fill_all_fields'];
        $message_type = 'danger';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center my-5">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow border-0 p-4">
            <h3 class="fw-bold text-center mb-4"><i class="bi bi-box-arrow-in-right text-primary"></i> <?= $lang['login_title'] ?></h3>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?? 'danger' ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold"><?= $lang['email_or_username'] ?></label>
                    <input type="text" name="login_input" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold"><?= $lang['password'] ?></label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary fw-bold w-100 py-2 mb-3"><?= $lang['login_btn'] ?></button>
                <div class="text-center">
                    <small class="text-muted"><?= $lang['dont_have_account'] ?> <a href="register.php" class="fw-bold"><?= $lang['register'] ?></a></small>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>