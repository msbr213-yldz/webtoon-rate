<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Kullanıcının etkileşimde bulunduğu veya kaldığı tüm webtoonları kapsayan SQL sorgusu
$stmt = $pdo->prepare("
    SELECT w.*, 
           COALESCE(MAX(cr.chapter_number), w.current_chapter) as user_last_chapter,
           COALESCE(MAX(cr.rating), 0) as user_last_rating,
           COALESCE(MAX(cr.id), w.id) as max_sort_id,
           GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') as genre_names
    FROM webtoons w
    LEFT JOIN chapter_ratings cr ON w.id = cr.webtoon_id AND cr.user_id = ?
    LEFT JOIN webtoon_genres wg ON w.id = wg.webtoon_id
    LEFT JOIN genres g ON wg.genre_id = g.id
    WHERE cr.user_id = ? OR w.current_chapter > 0
    GROUP BY w.id, w.title, w.description, w.cover_image, w.status, w.total_chapters, w.current_chapter, w.is_featured, w.created_at, w.updated_at
    ORDER BY max_sort_id DESC
");
$stmt->execute([$user_id, $user_id]);
$my_webtoons = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Dual Tab Navigasyon Başlığı -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link text-body fw-bold" href="<?= BASE_URL ?>profile/profile.php">
            <i class="bi bi-person-badge"></i> <?= $lang['profile_tab_info'] ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active fw-bold" href="<?= BASE_URL ?>profile/my_webtoons.php">
            <i class="bi bi-journal-bookmark"></i> <?= $lang['profile_tab_webtoons'] ?>
        </a>
    </li>
</ul>

<div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white fw-bold d-flex align-items-center justify-content-between">
        <span><i class="bi bi-journal-check me-2"></i> <?= $lang['my_reading_list'] ?></span>
        <span class="badge bg-primary"><?= count($my_webtoons) ?> <?= $lang['all_webtoons'] ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($my_webtoons)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-journal-x mb-3" style="font-size: 3rem;"></i>
                <h5><?= $lang['no_webtoons_in_list'] ?></h5>
                <a href="<?= BASE_URL ?>home.php" class="btn btn-primary btn-sm mt-2"><?= $lang['home'] ?></a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">Kapak</th>
                            <th><?= $lang['webtoon_title'] ?></th>
                            <th><?= $lang['status_label'] ?></th>
                            <th><?= $lang['your_last_chapter'] ?></th>
                            <th><?= $lang['your_rating'] ?></th>
                            <th class="text-end"><?= $lang['action'] ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_webtoons as $w): ?>
                            <?php 
                                $user_ch = (int)$w['user_last_chapter'];
                                $total_ch = (int)($w['total_chapters'] ?? 0);
                                $is_completed = ($total_ch > 0 && $user_ch >= $total_ch);
                            ?>
                            <tr>
                                <td>
                                    <?php $cover = !empty($w['cover_image']) ? htmlspecialchars($w['cover_image']) : 'https://via.placeholder.com/300x400?text=No+Cover'; ?>
                                    <img src="<?= $cover ?>" class="rounded shadow-sm" style="width: 45px; height: 60px; object-fit: cover;">
                                </td>
                                <td>
                                    <strong class="d-block text-body"><?= htmlspecialchars($w['title']) ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars($w['genre_names'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <?php if ($is_completed): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> <?= $lang['tooltip_completed'] ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark"><i class="bi bi-book-half"></i> <?= $lang['tooltip_reading'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold"><?= $lang['chapter'] ?> <?= $user_ch ?></span> 
                                    <small class="text-muted">/ <?= $total_ch ?></small>
                                </td>
                                <td>
                                    <?php if ((int)$w['user_last_rating'] > 0): ?>
                                        <span class="text-warning fw-bold">⭐ <?= (int)$w['user_last_rating'] ?> / 10</span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= BASE_URL ?>webtoon.php?id=<?= $w['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-box-arrow-up-right"></i> <?= $lang['go_to_webtoon'] ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>