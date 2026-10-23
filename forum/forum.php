<?php
require_once __DIR__ . '/../config/config.php';

// Tablolar yoksa otomatik oluştur
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `forum_categories` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `name` VARCHAR(100) NOT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `forum_topics` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT NOT NULL,
          `category_id` INT NOT NULL,
          `title` VARCHAR(255) NOT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `forum_posts` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `topic_id` INT NOT NULL,
          `user_id` INT NOT NULL,
          `content` TEXT NOT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Varsayılan kategori yoksa ekle
    $check_cat =$pdo->query("SELECT COUNT(*) FROM forum_categories")->fetchColumn();
    if ($check_cat == 0) {$pdo->exec("INSERT INTO `forum_categories` (`name`) VALUES ('Genel Tartışma'), ('Webtoon Önerileri'), ('Sohbet & İnceleme')");
    }
} catch (Exception $e) {
    // Tablo oluşturma hatası olursa sessizce devam et
}

$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Kategorileri Çek
$categories = [];
try {
    $stmt_cat =$pdo->query("SELECT * FROM forum_categories ORDER BY name ASC");
    if ($stmt_cat) {
        $categories =$stmt_cat->fetchAll();
    }
} catch (Exception $e) {$categories = [];
}

// Konuları Çek
$topics = [];
try {
    $sql = "
        SELECT t.*, u.username, c.name as category_name,
               (SELECT COUNT(p.id) FROM forum_posts p WHERE p.topic_id = t.id) - 1 as reply_count
        FROM forum_topics t
        JOIN users u ON t.user_id = u.id
        JOIN forum_categories c ON t.category_id = c.id
    ";

    if ($category_filter > 0) {$sql .= " WHERE t.category_id = :cat_id";
    }

    $sql .= " ORDER BY t.created_at DESC";

    $stmt = $pdo->prepare($sql);
    if ($category_filter > 0) {
        $stmt->execute(['cat_id' =>$category_filter]);
    } else {
        $stmt->execute();
    }
    $topics =$stmt->fetchAll();
} catch (Exception $e) {$topics = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">

    <!-- Üst Başlık & Yeni Konu Butonu -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-7">
            <h3 class="fw-bold mb-1 text-body"><i class="bi bi-chat-square-text text-primary"></i> <?= $lang['forum_title'] ?? 'Topluluk Forumu' ?></h3>
            <p class="text-body-secondary mb-0"><?= $lang['forum_subtitle'] ?? 'Webtoonlar hakkında tartışın, önerilerde bulunun ve sohbet edin.' ?></p>
        </div>
        <div class="col-md-5 text-md-end mt-3 mt-md-0">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?= BASE_URL ?>forum/create_topic.php" class="btn btn-primary fw-bold shadow-sm rounded-pill px-4">
                    <i class="bi bi-plus-circle me-1"></i> <?= $lang['create_new_topic'] ?? '+ Yeni Konu Aç' ?>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-primary rounded-pill px-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i> <?= $lang['login_to_reply'] ?? 'Giriş Yap' ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Kategori Filtreleri -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="forum.php" class="btn btn-sm <?= $category_filter == 0 ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>">
            <?= $lang['all_categories'] ?? 'Tüm Kategoriler' ?>
        </a>
        <?php foreach ($categories as$cat): ?>
            <a href="forum.php?category=<?= $cat['id'] ?>" class="btn btn-sm <?= $category_filter ==$cat['id'] ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Konu Listesi Tablosu -->
    <div class="card bg-body-tertiary shadow-sm border rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <?php if (empty($topics)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-chat-left-x mb-2 d-block opacity-50" style="font-size: 3rem;"></i>
                    <h5><?= $lang['no_topics_found'] ?? 'Bu kategoride henüz bir konu açılmamış.' ?></h5>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="table-active">
                                <th><?= $lang['topic_title'] ?? 'Konu Başlığı' ?></th>
                                <th><?= $lang['category'] ?? 'Kategori' ?></th>
                                <th><?= $lang['author'] ?? 'Yazar' ?></th>
                                <th class="text-center"><?= $lang['replies'] ?? 'Yanıtlar' ?></th>
                                <th class="text-end"><?= $lang['last_activity'] ?? 'Son Aktivite' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topics as$t): ?>
                                <tr>
                                    <td>
                                        <a href="forum_detail.php?id=<?= $t['id'] ?>" class="fw-bold text-decoration-none text-body fs-6 d-block">
                                            <?= htmlspecialchars($t['title']) ?>
                                        </a>
                                    </td>
                                    <td><span class="badge bg-secondary-subtle text-body border"><?= htmlspecialchars($t['category_name']) ?></span></td>
                                    <td><span class="text-primary fw-semibold"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($t['username']) ?></span></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary rounded-pill px-3"><?= max(0, (int)$t['reply_count']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <small class="text-body-secondary"><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Bootstrap 5 JS Bundle (Sağ Butonların/Dropdown'ların Çalışması İçin Şart) -->

</body>
</html>