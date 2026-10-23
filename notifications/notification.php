<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

// OTURUM KONTROLÜ
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($user_id <= 0) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$message = '';
$message_type = '';

// BİLDİRİME TIKLANDIĞINDA OKUNDU YAPIP İLGİLİ SAYFAYA YÖNLENDİRME
if (isset($_GET['redirect_id'])) {
    $notif_id = (int)$_GET['redirect_id'];
    
    // Bildirimi çek
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = :id AND user_id = :u_id");
    $stmt->execute(['id' => $notif_id, 'u_id' => $user_id]);
    $n_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($n_data) {
        // Okundu işaretle
        $upd = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :u_id");
        $upd->execute(['id' => $notif_id, 'u_id' => $user_id]);

        $target_url = BASE_URL . 'home.php';

        // 1. Doğrudan webtoon_id sütunu tanımlıysa
        if (!empty($n_data['webtoon_id'])) {
            $target_url = BASE_URL . 'webtoon.php?id=' . (int)$n_data['webtoon_id'];
            if (!empty($n_data['comment_id'])) {
                $target_url .= '#comment-' . (int)$n_data['comment_id'];
            }
        } 
        // 2. Doğrudan link sütunu tanımlıysa
        elseif (!empty($n_data['link'])) {
            $target_url = $n_data['link'];
        } 
        // 3. Bildirim metni içindeki tek tırnak arası başlığı bulup LIKE ile esnek arama yap
        else {
            if (preg_match("/'([^']+)'/u", $n_data['message'], $matches)) {
                $webtoon_title = trim($matches[1]);
                
                // Tam eşleşme dene veya LIKE ile esnek ara
                $w_stmt = $pdo->prepare("SELECT id FROM webtoons WHERE LOWER(title) = LOWER(:title) OR title LIKE :like_title LIMIT 1");
                $w_stmt->execute([
                    'title' => $webtoon_title,
                    'like_title' => '%' . $webtoon_title . '%'
                ]);
                $found_w = $w_stmt->fetch(PDO::FETCH_ASSOC);

                if ($found_w) {
                    $target_url = BASE_URL . 'webtoon.php?id=' . $found_w['id'];
                    if (!empty($n_data['comment_id'])) {
                        $target_url .= '#comment-' . (int)$n_data['comment_id'];
                    }
                }
            }
        }

        header('Location: ' . $target_url);
        exit;
    }
}

// TÜMÜNÜ OKUNDU İŞARETLE
if (isset($_POST['mark_all_read'])) {
    try {
        $upd = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :u_id");
        $upd->execute(['u_id' => $user_id]);
        $message = "Tüm bildirimler okundu olarak işaretlendi.";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Hata: " . htmlspecialchars($e->getMessage());
        $message_type = "danger";
    }
}

// TEK BİLDİRİM SİLME
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    try {
        $del = $pdo->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :u_id");
        $del->execute(['id' => $del_id, 'u_id' => $user_id]);
        $message = "Bildirim silindi.";
        $message_type = "info";
    } catch (PDOException $e) {
        $message = "Silme hatası: " . htmlspecialchars($e->getMessage());
        $message_type = "danger";
    }
}

// TEK BİLDİRİMİ OKUNDU YAPMA
if (isset($_GET['read'])) {
    $read_id = (int)$_GET['read'];
    try {
        $upd_single = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :u_id");
        $upd_single->execute(['id' => $read_id, 'u_id' => $user_id]);
    } catch (PDOException $e) {
        // Sessiz geç
    }
}

// BİLDİRİMLERİ ÇEKME
$filter = $_GET['filter'] ?? 'all';
$query = "SELECT * FROM notifications WHERE user_id = :u_id";

if ($filter === 'unread') {
    $query .= " AND is_read = 0";
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute(['u_id' => $user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamış Sayısı
$unread_cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :u_id AND is_read = 0");
$unread_cnt_stmt->execute(['u_id' => $user_id]);
$unread_total = (int)$unread_cnt_stmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .notif-item {
        transition: background-color 0.2s ease;
    }
    .notif-item:hover {
        background-color: rgba(13, 110, 253, 0.05) !important;
    }
</style>

<div class="container my-4" style="max-width: 900px;">

    <!-- Üst Başlık ve Filtreler -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-body mb-1">
                <i class="bi bi-bell-fill text-primary me-2"></i>Bildirimlerim
            </h3>
            <p class="text-body-secondary small mb-0">Takip ettiğiniz serilerle ilgili güncellemeler ve sistem bildirimleri.</p>
        </div>

        <?php if ($unread_total > 0): ?>
            <form method="POST" action="<?= BASE_URL ?>notifications/notification.php" class="m-0">
                <button type="submit" name="mark_all_read" class="btn btn-outline-primary btn-sm rounded-pill shadow-sm">
                    <i class="bi bi-check2-all me-1"></i> Tümünü Okundu İşaretle (<?= $unread_total ?>)
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Bildirim / Hata Mesajı -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show rounded-3 mb-4" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtre Sekmeleri -->
    <div class="card bg-body-tertiary border shadow-sm rounded-4 mb-4">
        <div class="card-body p-2 d-flex gap-2">
            <a href="<?= BASE_URL ?>notifications/notification.php?filter=all" class="btn btn-sm rounded-pill px-4 fw-bold <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                Tümü
            </a>
            <a href="<?= BASE_URL ?>notifications/notification.php?filter=unread" class="btn btn-sm rounded-pill px-4 fw-bold <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                Okunmamış <?= $unread_total > 0 ? "($unread_total)" : '' ?>
            </a>
        </div>
    </div>

    <!-- Bildirim Listesi -->
    <div class="card bg-body-tertiary border shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-bell-slash text-body-tertiary display-4 d-block mb-3"></i>
                    <h5 class="fw-bold text-body">Bildirim Bulunmuyor</h5>
                    <p class="text-body-secondary small">Gösterilecek bildiriminiz yok.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $n): ?>
                        <?php $is_unread = ((int)$n['is_read'] === 0); ?>
                        <div class="list-group-item bg-transparent border-bottom p-3 d-flex align-items-center justify-content-between gap-3 notif-item <?= $is_unread ? 'border-start border-primary border-4' : '' ?>">
                            
                            <!-- TIKLANABİLİR BİLDİRİM İÇERİĞİ -->
                            <a href="<?= BASE_URL ?>notifications/notification.php?redirect_id=<?= $n['id'] ?>" class="text-decoration-none d-flex align-items-start gap-3 flex-grow-1">
                                <div class="p-2 rounded-circle <?= $is_unread ? 'bg-primary-subtle text-primary' : 'bg-body-secondary text-body-tertiary' ?>">
                                    <i class="bi bi-journal-arrow-up fs-4"></i>
                                </div>
                                <div>
                                    <p class="mb-1 text-body fs-6 <?= $is_unread ? 'fw-bold' : '' ?>">
                                        <?= htmlspecialchars($n['message']) ?>
                                    </p>
                                    <small class="text-body-secondary">
                                        <i class="bi bi-clock me-1"></i><?= date('d.m.Y H:i', strtotime($n['created_at'])) ?>
                                    </small>
                                </div>
                            </a>

                            <!-- İŞLEM BUTONLARI -->
                            <div class="d-flex align-items-center gap-2" onclick="event.stopPropagation();">
                                <?php if ($is_unread): ?>
                                    <a href="<?= BASE_URL ?>notifications/notification.php?read=<?= $n['id'] ?>&filter=<?= $filter ?>" class="btn btn-sm btn-outline-primary rounded-circle" title="Okundu İşaretle" style="width: 32px; height: 32px; padding: 0; line-height: 30px; text-align: center;">
                                        <i class="bi bi-check-lg"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>notifications/notification.php?delete=<?= $n['id'] ?>&filter=<?= $filter ?>" class="btn btn-sm btn-outline-danger rounded-circle" title="Sil" style="width: 32px; height: 32px; padding: 0; line-height: 30px; text-align: center;">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

</body>
</html>