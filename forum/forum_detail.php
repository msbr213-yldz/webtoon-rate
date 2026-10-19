<?php
require_once __DIR__ . '/../config/config.php';

$topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id  = $_SESSION['user_id'] ?? 0;

if ($topic_id <= 0) {
    header("Location: " . BASE_URL . "forum/forum.php");
    exit;
}

// 1. Konu Bilgilerini Getir
$stmt = $pdo->prepare("
    SELECT t.*, u.username, c.name as category_name 
    FROM forum_topics t
    JOIN users u ON t.user_id = u.id
    JOIN forum_categories c ON t.category_id = c.id
    WHERE t.id = ?
");
$stmt->execute([$topic_id]);
$topic = $stmt->fetch();

if (!$topic) {
    header("Location: " . BASE_URL . "forum/forum.php");
    exit;
}

// 2. Cevap Gönderme İşlemi
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id > 0) {
    $reply_content = trim($_POST['content']);

    if (!empty($reply_content)) {
        $stmt_reply = $pdo->prepare("INSERT INTO forum_posts (topic_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmt_reply->execute([$topic_id, $user_id, $reply_content])) {
            header("Location: " . BASE_URL . "forum/forum_detail.php?id=" . $topic_id);
            exit;
        }
    } else {
        $message = $lang['reply_empty_error'] ?? ($current_lang === 'en' ? 'Reply content cannot be empty.' : 'Yanıt alanı boş bırakılamaz.');
    }
}

// 3. Konuya Ait Tüm Gönderileri Getir
$stmt_posts = $pdo->prepare("
    SELECT p.*, u.username, u.role 
    FROM forum_posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.topic_id = ?
    ORDER BY p.created_at ASC
");
$stmt_posts->execute([$topic_id]);
$posts = $stmt_posts->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>forum/forum.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> <?= $lang['back_to_forum'] ?? 'Foruma Dön' ?>
    </a>
</div>

<!-- Konu Başlığı Ve Kategori Rozeti -->
<div class="card shadow-sm border-0 mb-4 p-3 bg-body-tertiary">
    <div class="d-flex justify-content-between align-items-center">
        <h3 class="fw-bold mb-0"><?= htmlspecialchars($topic['title']) ?></h3>
        <span class="badge bg-primary"><?= htmlspecialchars($topic['category_name']) ?></span>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Gönderiler / Cevaplar Listesi -->
<div class="d-flex flex-column gap-3 mb-4">
    <?php foreach ($posts as $index => $post): ?>
        <div class="card shadow-sm border-0 <?= $index === 0 ? 'border-start border-4 border-primary' : '' ?>">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-body">👤 <?= htmlspecialchars($post['username']) ?></span>
                    <?php if ($index === 0): ?>
                        <span class="badge bg-info text-dark" style="font-size: 0.7rem;"><?= $lang['original_poster'] ?? 'Konu Sahibi' ?></span>
                    <?php endif; ?>
                    <?php if ($post['role'] === 'admin'): ?>
                        <span class="badge bg-danger" style="font-size: 0.7rem;"><?= $lang['admin_badge'] ?? 'ADMIN' ?></span>
                    <?php endif; ?>
                </div>
                <small class="text-muted"><?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></small>
            </div>
            <div class="card-body">
                <p class="card-text mb-0"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Cevap Yazma Paneli -->
<?php if ($user_id > 0): ?>
    <div class="card shadow-sm border-0 p-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-reply-fill text-primary"></i> <?= $lang['write_reply'] ?? 'Cevap Yaz' ?></h5>
        <form action="forum_detail.php?id=<?= $topic_id ?>" method="POST">
            <div class="mb-3">
                <textarea name="content" class="form-control" rows="4" placeholder="<?= $lang['reply_placeholder'] ?? 'Düşüncelerinizi veya yanıtınızı yazın...' ?>" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary fw-bold px-4">
                <i class="bi bi-send-fill"></i> <?= $lang['post_reply_btn'] ?? 'Yanıtı Gönder' ?>
            </button>
        </form>
    </div>
<?php else: ?>
    <div class="alert alert-info shadow-sm border-0">
        <i class="bi bi-info-circle-fill me-2"></i> <?= $lang['login_to_reply'] ?? 'Konulara yanıt yazabilmek için lütfen giriş yapın.' ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>