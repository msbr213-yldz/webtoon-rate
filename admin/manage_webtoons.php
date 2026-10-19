<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin Yetki Kontrolü
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . 'home.php');
    exit;
}

$message = '';
$message_type = '';

// HIZLI BÖLÜM GÜNCELLEME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_chapter'])) {
    $webtoon_id = (int)($_POST['webtoon_id'] ?? 0);
    $new_chapter = (int)($_POST['total_chapters'] ?? 0);

    if ($webtoon_id > 0 && $new_chapter >= 0) {
        $stmt_old = $pdo->prepare("SELECT total_chapters FROM webtoons WHERE id = :id");
        $stmt_old->execute(['id' => $webtoon_id]);
        $old_chapter = (int)$stmt_old->fetchColumn();

        $stmt = $pdo->prepare("UPDATE webtoons SET total_chapters = :ch, updated_at = NOW() WHERE id = :id");
        $stmt->execute(['ch' => $new_chapter, 'id' => $webtoon_id]);

        if ($new_chapter > $old_chapter && function_exists('sendNewChapterNotification')) {
            sendNewChapterNotification($pdo, $webtoon_id, $new_chapter);
            $message = "Bölüm sayısı güncellendi ve takipçilere bildirim gönderildi!";
        } else {
            $message = "Bölüm sayısı başarıyla güncellendi.";
        }
        $message_type = 'success';
    }
}

// WEBTOON SILME İŞLEMİ
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if ($delete_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM webtoons WHERE id = :id");
        $stmt->execute(['id' => $delete_id]);
        $message = "Webtoon başarıyla silindi.";
        $message_type = 'warning';
    }
}

// SIRALAMA PARAMETRESİ (Varsayılan: Duruma Göre Sıralama)
$sort = $_GET['sort'] ?? 'status';
$order_sql = "
    CASE 
        WHEN LOWER(w.status) LIKE '%devam%' OR LOWER(w.status) LIKE '%ongoing%' THEN 1
        WHEN LOWER(w.status) LIKE '%ara%' OR LOWER(w.status) LIKE '%hiatus%' OR LOWER(w.status) LIKE '%pause%' THEN 2
        WHEN LOWER(w.status) LIKE '%tamam%' OR LOWER(w.status) LIKE '%completed%' THEN 3
        WHEN LOWER(w.status) LIKE '%yarıda%' OR LOWER(w.status) LIKE '%dropped%' THEN 4
        ELSE 5
    END ASC, w.updated_at DESC
";

switch ($sort) {
    case 'status':
        $order_sql = "
            CASE 
                WHEN LOWER(w.status) LIKE '%devam%' OR LOWER(w.status) LIKE '%ongoing%' THEN 1
                WHEN LOWER(w.status) LIKE '%ara%' OR LOWER(w.status) LIKE '%hiatus%' OR LOWER(w.status) LIKE '%pause%' THEN 2
                WHEN LOWER(w.status) LIKE '%tamam%' OR LOWER(w.status) LIKE '%completed%' THEN 3
                WHEN LOWER(w.status) LIKE '%yarıda%' OR LOWER(w.status) LIKE '%dropped%' THEN 4
                ELSE 5
            END ASC, w.updated_at DESC
        ";
        break;
    case 'updated':
        $order_sql = "w.updated_at DESC";
        break;
    case 'title_asc':
        $order_sql = "w.title ASC";
        break;
    case 'chapters_desc':
        $order_sql = "w.total_chapters DESC";
        break;
    case 'followers_desc':
        $order_sql = "follower_count DESC";
        break;
}

// TÜM WEBTOONLARI ÇEK
$stmt = $pdo->query("
    SELECT w.*, 
           GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') as genre_names,
           (SELECT COUNT(*) FROM user_follows uf WHERE uf.webtoon_id = w.id) as follower_count
    FROM webtoons w
    LEFT JOIN webtoon_genres wg ON w.id = wg.webtoon_id
    LEFT JOIN genres g ON wg.genre_id = g.id
    GROUP BY w.id
    ORDER BY {$order_sql}
");
$webtoons = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h3 class="fw-bold text-body mb-0">
            <i class="bi bi-kanban text-primary me-2"></i> Webtoon Yönetimi
        </h3>
        <a href="<?= BASE_URL ?>admin/add_webtoon.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Yeni Webtoon Ekle
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- SIRALAMA FİLTRESİ BAR -->
    <div class="card bg-body-tertiary border shadow-sm p-3 mb-3 rounded-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-body-secondary small fw-bold">
                <i class="bi bi-funnel me-1"></i> Sıralama Ölçütü:
            </span>
            <div class="btn-group btn-group-sm" role="group">
                <a href="manage_webtoons.php?sort=status" class="btn <?= ($sort === 'status') ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-arrow-down-up me-1"></i> Duruma Göre
                </a>
                <a href="manage_webtoons.php?sort=updated" class="btn <?= ($sort === 'updated') ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-clock-history me-1"></i> Son Güncellenenler
                </a>
                <a href="manage_webtoons.php?sort=title_asc" class="btn <?= ($sort === 'title_asc') ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-sort-alpha-down me-1"></i> A-Z
                </a>
                <a href="manage_webtoons.php?sort=chapters_desc" class="btn <?= ($sort === 'chapters_desc') ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-book me-1"></i> En Çok Bölüm
                </a>
                <a href="manage_webtoons.php?sort=followers_desc" class="btn <?= ($sort === 'followers_desc') ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-heart me-1"></i> En Çok Takip Edilen
                </a>
            </div>
        </div>
    </div>

    <div class="card bg-body-tertiary border shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="table-active">
                            <th style="width: 70px;">Kapak</th>
                            <th>Başlık</th>
                            <th>Türler</th>
                            <th>Durum</th>
                            <th>Toplam Bölüm</th>
                            <th>Takipçi</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($webtoons)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-body-secondary">Henüz hiç webtoon eklenmemiş.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($webtoons as $w): ?>
                                <?php 
                                    $cover_img = $w['cover_image'] ?? '';
                                    $cover = !empty($cover_img) ? ((strpos($cover_img, 'http') === 0) ? $cover_img : BASE_URL . 'uploads/' . htmlspecialchars($cover_img)) : 'https://via.placeholder.com/100x140?text=No+Cover';
                                    $st_val = $w['status'] ?? 'Devam Ediyor';
                                ?>
                                <tr>
                                    <td>
                                        <img src="<?= $cover ?>" class="rounded border shadow-sm" style="width: 45px; height: 60px; object-fit: cover;">
                                    </td>
                                    <td>
                                        <strong class="d-block text-body"><?= htmlspecialchars($w['title']) ?></strong>
                                        <?php if (!empty($w['is_featured'])): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> Öne Çıkan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-body-secondary"><?= htmlspecialchars($w['genre_names'] ?? '-') ?></small></td>
                                    <td>
                                        <?php if (mb_stripos($st_val, 'Tamam') !== false || mb_stripos($st_val, 'completed') !== false): ?>
                                            <span class="badge bg-info text-dark">Tamamlandı</span>
                                        <?php elseif (mb_stripos($st_val, 'Ara') !== false || mb_stripos($st_val, 'hiatus') !== false): ?>
                                            <span class="badge bg-warning text-dark">Ara Verildi</span>
                                        <?php elseif (mb_stripos($st_val, 'Yarıda') !== false || mb_stripos($st_val, 'dropped') !== false): ?>
                                            <span class="badge bg-danger">Yarıda Bırakıldı</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Devam Ediyor</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-flex align-items-center gap-2" style="max-width: 160px;">
                                            <input type="hidden" name="update_chapter" value="1">
                                            <input type="hidden" name="webtoon_id" value="<?= $w['id'] ?>">
                                            <input type="number" name="total_chapters" class="form-control form-control-sm theme-input" 
                                                   value="<?= (int)$w['total_chapters'] ?>" min="0">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Güncelle & Bildirim Gönder">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary rounded-pill">❤️ <?= (int)$w['follower_count'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>admin/edit_webtoon.php?id=<?= $w['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                                            <i class="bi bi-pencil-square"></i> Düzenle
                                        </a>
                                        <a href="<?= BASE_URL ?>admin/manage_webtoons.php?delete_id=<?= $w['id'] ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('Bu webtoonu silmek istediğinize emin misiniz?');">
                                            <i class="bi bi-trash"></i> Sil
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>