<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

$error = '';

// Kategorileri çek
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM forum_categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Konu Oluşturma Formu Gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $user_id = (int)$_SESSION['user_id'];

    if (empty($title) || empty($content) || $category_id <= 0) {
        $error = 'Lütfen tüm alanları doldurun ve bir kategori seçin.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Konuyu Oluştur
            $stmt = $pdo->prepare("INSERT INTO forum_topics (user_id, category_id, title) VALUES (:u_id, :cat_id, :title)");
            $stmt->execute([
                'u_id' => $user_id,
                'cat_id' => $category_id,
                'title' => $title
            ]);
            $topic_id = $pdo->lastInsertId();

            // 2. İlk Gönderiyi (Mesajı) Ekle
            $stmt_post = $pdo->prepare("INSERT INTO forum_posts (topic_id, user_id, content) VALUES (:t_id, :u_id, :content)");
            $stmt_post->execute([
                't_id' => $topic_id,
                'u_id' => $user_id,
                'content' => $content
            ]);

            $pdo->commit();

            // Detay sayfasına veya foruma yönlendir
            header("Location: forum_detail.php?id=" . $topic_id);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Konu oluşturulurken bir hata meydana geldi: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h3 class="fw-bold mb-0 text-body">
                    <i class="bi bi-plus-circle text-primary me-2"></i>Yeni Konu Aç
                </h3>
                <a href="forum.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Foruma Dön
                </a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-3 mb-4 d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <div class="card bg-body-tertiary border shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="title" class="form-label fw-bold text-body">Konu Başlığı</label>
                            <input type="text" class="form-control theme-input" id="title" name="title" placeholder="Konunun ana başlığını yazın..." value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="category_id" class="form-label fw-bold text-body">Kategori</label>
                            <select class="form-select theme-input" id="category_id" name="category_id" required>
                                <option value="" disabled selected>Bir kategori seçin...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="content" class="form-label fw-bold text-body">İçerik / Mesajınız</label>
                            <textarea class="form-control theme-input" id="content" name="content" rows="6" placeholder="Tartışmak istediğiniz konuyu detaylıca açıklayın..." required><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="forum.php" class="btn btn-outline-secondary px-4 me-md-2">İptal</a>
                            <button type="submit" class="btn btn-primary fw-bold px-4">
                                <i class="bi bi-send-fill me-1"></i> Konuyu Yayınla
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- BOOTSTRAP JS PAKETİ (DROPDOWN VE TEMA BUTONLARININ ÇALIŞMASINI SAĞLAR) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>