<?php
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$search_query = trim($_GET['q'] ?? '');
$selected_genres = isset($_GET['genres']) && is_array($_GET['genres']) ? array_map('intval', $_GET['genres']) : [];
$selected_genres = array_filter($selected_genres, function($v) { return $v > 0; });
$selected_status = trim($_GET['status'] ?? '');

$str_contains_custom = function($haystack, $needle) {
    if (function_exists('mb_stripos')) {
        return mb_stripos($haystack, $needle) !== false;
    }
    return stripos($haystack, $needle) !== false;
};

// 1. TÜRLER
$genres = $pdo->query("SELECT * FROM genres ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. EDİTÖRÜN SEÇİMİ
$featured_webtoons = $pdo->query("
    SELECT w.*, GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') as genre_names
    FROM webtoons w
    LEFT JOIN webtoon_genres wg ON w.id = wg.webtoon_id
    LEFT JOIN genres g ON wg.genre_id = g.id
    WHERE w.is_featured = 1
    GROUP BY w.id
    ORDER BY w.updated_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 3. TOP 10
$top_rated_webtoons = $pdo->query("
    SELECT w.*, AVG(cr.rating) as avg_rating, GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') as genre_names
    FROM webtoons w
    LEFT JOIN chapter_ratings cr ON w.id = cr.webtoon_id AND cr.rating > 0
    LEFT JOIN webtoon_genres wg ON w.id = wg.webtoon_id
    LEFT JOIN genres g ON wg.genre_id = g.id
    GROUP BY w.id
    ORDER BY avg_rating DESC, w.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 4. KULLANICI ARAMA
$found_users = [];
if (!empty($search_query)) {
    try {
        $u_stmt = $pdo->prepare("SELECT id, username, role, created_at FROM users WHERE username LIKE :u_search ORDER BY username ASC LIMIT 6");
        $u_stmt->execute(['u_search' => "%{$search_query}%"]);
        $found_users = $u_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// 5. TÜM KATALOG
$sql = "
    SELECT w.*, 
           (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') FROM webtoon_genres wg JOIN genres g ON wg.genre_id = g.id WHERE wg.webtoon_id = w.id) as genre_names,
           COALESCE((SELECT MAX(cr.chapter_number) FROM chapter_ratings cr WHERE cr.webtoon_id = w.id AND cr.user_id = :u_id), 0) as user_last_chapter
    FROM webtoons w WHERE 1=1
";

$params = ['u_id' => $user_id];

if (!empty($search_query)) {
    $sql .= " AND w.title LIKE :search";
    $params['search'] = "%{$search_query}%";
}

if (!empty($selected_genres)) {
    $placeholders = [];
    foreach ($selected_genres as $idx => $g_id) {
        $p_name = ":g_" . $idx;
        $placeholders[] = $p_name;
        $params[$p_name] = $g_id;
    }
    $in_clause = implode(',', $placeholders);
    $sql .= " AND w.id IN (SELECT webtoon_id FROM webtoon_genres WHERE genre_id IN ($in_clause))";
}

if (!empty($selected_status)) {
    if ($selected_status === 'Devam Ediyor') $sql .= " AND (w.status LIKE '%Devam%' OR w.status LIKE '%Ongoing%')";
    elseif ($selected_status === 'Ara Verildi') $sql .= " AND (w.status LIKE '%Ara%' OR w.status LIKE '%Hiatus%' OR w.status LIKE '%Pause%')";
    elseif ($selected_status === 'Tamamlandı') $sql .= " AND (w.status LIKE '%Tamam%' OR w.status LIKE '%Completed%')";
    elseif ($selected_status === 'Yarıda Bırakıldı') $sql .= " AND (w.status LIKE '%Yarıda%' OR w.status LIKE '%Dropped%')";
    else { $sql .= " AND w.status = :status"; $params['status'] = $selected_status; }
}

$sql .= " ORDER BY w.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$catalog_webtoons = $stmt->fetchAll(PDO::FETCH_ASSOC);

function buildMultiFilterUrl($genres = [], $status = null, $search = null) {
    $params = [];
    if (!empty($genres)) $params['genres'] = array_values($genres);
    if ($status !== null && $status !== '') $params['status'] = $status;
    if ($search !== null && $search !== '') $params['q'] = $search;
    return 'home.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

include __DIR__ . '/includes/header.php';
?>

<style>
    .slider-wrapper { position: relative; }
    .slider-arrow {
        position: absolute; top: 50%; transform: translateY(-50%); z-index: 10;
        width: 42px; height: 42px; background-color: rgba(0, 0, 0, 0.65);
        border: 1px solid rgba(255, 255, 255, 0.2); color: #fff; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all 0.2s; opacity: 0.7; backdrop-filter: blur(4px);
    }
    .slider-arrow:hover { opacity: 1; background-color: #0d6efd; border-color: #0d6efd; transform: translateY(-50%) scale(1.1); }
    .slider-arrow.arrow-left { left: -15px; }
    .slider-arrow.arrow-right { right: -15px; }
    
    .horizontal-scroll-container {
        display: flex; gap: 1.25rem; overflow-x: auto; padding: 0.5rem 0.5rem 1rem 0.5rem;
        cursor: grab; user-select: none; scroll-behavior: smooth;
    }
    .horizontal-scroll-container::-webkit-scrollbar { height: 6px; }
    .horizontal-scroll-container::-webkit-scrollbar-thumb { background: rgba(150, 150, 150, 0.3); border-radius: 4px; }
    .horizontal-scroll-container.active { cursor: grabbing !important; scroll-behavior: auto; }
    .horizontal-scroll-container img, .horizontal-scroll-container a { -webkit-user-drag: none !important; user-select: none !important; }
</style>

<div class="container my-4">

    <!-- 1. ARAMA -->
    <div class="row justify-content-center mb-5">
        <div class="col-md-8">
            <form action="home.php" method="GET" class="d-flex gap-2">
                <?php foreach ($selected_genres as $sg): ?>
                    <input type="hidden" name="genres[]" value="<?= $sg ?>">
                <?php endforeach; ?>
                <?php if (!empty($selected_status)): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($selected_status) ?>">
                <?php endif; ?>
                <div class="input-group input-group-lg shadow-sm" style="border-radius: 2rem; overflow: hidden;">
                    <span class="input-group-text theme-input text-muted border-0 ps-4"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control theme-input border-0" placeholder="Webtoon veya kullanıcı ara..." value="<?= htmlspecialchars($search_query) ?>">
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Ara</button>
                </div>
            </form>
        </div>
    </div>

    <!-- BULUNAN KULLANICILAR -->
    <?php if (!empty($search_query) && !empty($found_users)): ?>
        <div class="slider-section-card p-4 mb-5">
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2"><i class="bi bi-people-fill text-primary"></i> Bulunan Kullanıcılar (<?= count($found_users) ?>)</h5>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
                <?php foreach ($found_users as $fu): ?>
                    <div class="col">
                        <a href="<?= BASE_URL ?>profile/profile.php?id=<?= $fu['id'] ?>" class="text-decoration-none">
                            <div class="card theme-card p-3 h-100 d-flex flex-row align-items-center gap-3 clickable-card">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 48px; height: 48px; font-size: 1.2rem;">
                                    <?= strtoupper(substr($fu['username'], 0, 1)) ?>
                                </div>
                                <div class="overflow-hidden">
                                    <h6 class="fw-bold mb-0 text-truncate"><?= htmlspecialchars($fu['username']) ?></h6>
                                    <small class="text-secondary d-block">
                                        <?php if (($fu['role'] ?? '') === 'admin'): ?><span class="badge bg-warning text-dark me-1" style="font-size: 0.65rem;">Admin</span><?php endif; ?> Üye
                                    </small>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 2. EDİTÖRÜN SEÇİMİ -->
    <?php if (!empty($featured_webtoons) && empty($search_query) && empty($selected_genres) && empty($selected_status)): ?>
        <div class="slider-section-card mb-5">
            <h4 class="fw-bold mb-3 d-flex align-items-center gap-2 ps-2"><i class="bi bi-fire text-danger"></i> Editörün Seçimi</h4>
            <div class="slider-wrapper">
                <button class="slider-arrow arrow-left" onclick="scrollSlider('featured-slider', -300)"><i class="bi bi-chevron-left fs-5"></i></button>
                <div class="horizontal-scroll-container draggables-scroll" id="featured-slider">
                    <?php foreach ($featured_webtoons as $fw): ?>
                        <?php 
                            $cover_img = $fw['cover_image'] ?? '';
                            $cover = !empty($cover_img) ? ((strpos($cover_img, 'http') === 0) ? $cover_img : BASE_URL . 'uploads/' . htmlspecialchars($cover_img)) : 'https://via.placeholder.com/180x240?text=No+Cover';
                        ?>
                        <a href="<?= BASE_URL ?>webtoon.php?id=<?= $fw['id'] ?>" class="card theme-card slider-card clickable-card border-0">
                            <img src="<?= $cover ?>" class="card-img-top" alt="<?= htmlspecialchars($fw['title']) ?>" draggable="false">
                            <div class="card-body p-3 text-center d-flex flex-column justify-content-between">
                                <div><h6 class="card-title text-truncate fw-bold mb-0" title="<?= htmlspecialchars($fw['title']) ?>"><?= htmlspecialchars($fw['title']) ?></h6></div>
                                <span class="btn btn-sm btn-outline-primary rounded-pill w-100 mt-3 fw-bold">İncele</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <button class="slider-arrow arrow-right" onclick="scrollSlider('featured-slider', 300)"><i class="bi bi-chevron-right fs-5"></i></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3. TOP 10 -->
    <?php if (!empty($top_rated_webtoons) && empty($search_query) && empty($selected_genres) && empty($selected_status)): ?>
        <div class="slider-section-card mb-5">
            <h4 class="fw-bold mb-3 d-flex align-items-center gap-2 ps-2"><i class="bi bi-star-fill text-warning"></i> En Yüksek Puan Alanlar</h4>
            <div class="slider-wrapper">
                <button class="slider-arrow arrow-left" onclick="scrollSlider('top-rated-slider', -300)"><i class="bi bi-chevron-left fs-5"></i></button>
                <div class="horizontal-scroll-container draggables-scroll" id="top-rated-slider">
                    <?php foreach ($top_rated_webtoons as $tr): ?>
                        <?php 
                            $cover_img = $tr['cover_image'] ?? '';
                            $cover = !empty($cover_img) ? ((strpos($cover_img, 'http') === 0) ? $cover_img : BASE_URL . 'uploads/' . htmlspecialchars($cover_img)) : 'https://via.placeholder.com/180x240?text=No+Cover';
                        ?>
                        <a href="<?= BASE_URL ?>webtoon.php?id=<?= $tr['id'] ?>" class="card theme-card slider-card clickable-card border-0">
                            <img src="<?= $cover ?>" class="card-img-top" alt="<?= htmlspecialchars($tr['title']) ?>" draggable="false">
                            <div class="card-body p-3 text-center d-flex flex-column justify-content-between">
                                <div><h6 class="card-title text-truncate fw-bold mb-0" title="<?= htmlspecialchars($tr['title']) ?>"><?= htmlspecialchars($tr['title']) ?></h6></div>
                                <span class="btn btn-sm btn-warning rounded-pill text-dark fw-bold w-100 mt-3">⭐ <?= number_format((float)($tr['avg_rating'] ?? 0), 1) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <button class="slider-arrow arrow-right" onclick="scrollSlider('top-rated-slider', 300)"><i class="bi bi-chevron-right fs-5"></i></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4. TÜM KATALOG -->
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h4 class="fw-bold mb-0 d-flex align-items-center gap-2"><i class="bi bi-grid-3x3-gap-fill text-primary"></i> Tüm Webtoon Kataloğu</h4>
            <?php if (!empty($selected_genres) || !empty($selected_status) || !empty($search_query)): ?>
                <a href="home.php" class="btn btn-sm btn-danger rounded-pill fw-bold"><i class="bi bi-x-circle me-1"></i> Filtreleri Temizle</a>
            <?php endif; ?>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="<?= buildMultiFilterUrl([], $selected_status, $search_query) ?>" class="btn btn-sm rounded-pill <?= empty($selected_genres) ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>">Tüm Türler</a>
            <?php foreach ($genres as $g): ?>
                <?php 
                    $g_id = (int)$g['id'];
                    $is_selected = in_array($g_id, $selected_genres);
                    $temp_genres = $is_selected ? array_diff($selected_genres, [$g_id]) : array_merge($selected_genres, [$g_id]);
                ?>
                <a href="<?= buildMultiFilterUrl($temp_genres, $selected_status, $search_query) ?>" class="btn btn-sm rounded-pill <?= $is_selected ? 'btn-primary fw-bold shadow-sm' : 'btn-outline-secondary' ?>">
                    <?php if ($is_selected): ?><i class="bi bi-check2 me-1"></i><?php endif; ?><?= htmlspecialchars($g['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="<?= buildMultiFilterUrl($selected_genres, '', $search_query) ?>" class="btn btn-sm rounded-pill <?= empty($selected_status) ? 'btn-secondary fw-bold' : 'btn-outline-secondary' ?>">Tümü</a>
            <?php 
                $statuses = ['Devam Ediyor' => 'success', 'Ara Verildi' => 'warning', 'Tamamlandı' => 'info', 'Yarıda Bırakıldı' => 'danger'];
                foreach ($statuses as $st_name => $st_color):
                    $is_st_active = ($selected_status === $st_name);
            ?>
                <a href="<?= buildMultiFilterUrl($selected_genres, $is_st_active ? '' : $st_name, $search_query) ?>" class="btn btn-sm rounded-pill <?= $is_st_active ? "btn-{$st_color} fw-bold text-white" : "btn-outline-{$st_color}" ?>"><?= $st_name ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($catalog_webtoons) && empty($found_users)): ?>
            <div class="text-center py-5 slider-section-card text-muted">
                <i class="bi bi-search fs-1 d-block mb-3"></i>
                <h5 class="fw-bold">Sonuç Bulunamadı</h5>
                <p>Aramanıza veya seçtiğiniz filtrelere uygun webtoon bulunamadı.</p>
                <a href="home.php" class="btn btn-primary rounded-pill mt-2 px-4">Tümünü Göster</a>
            </div>
        <?php else: ?>
            <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-3 g-md-4">
                <?php foreach ($catalog_webtoons as $cw): ?>
                    <?php 
                        $cover_img = $cw['cover_image'] ?? '';
                        $cover = !empty($cover_img) ? ((strpos($cover_img, 'http') === 0) ? $cover_img : BASE_URL . 'uploads/' . htmlspecialchars($cover_img)) : 'https://via.placeholder.com/300x400?text=No+Cover';
                        $user_ch = (int)($cw['user_last_chapter'] ?? 0);
                        $total_ch = (int)($cw['total_chapters'] ?? 0);
                        $status_str = $cw['status'] ?? 'Devam Ediyor';
                    ?>
                    <div class="col">
                        <a href="<?= BASE_URL ?>webtoon.php?id=<?= $cw['id'] ?>" class="card theme-card h-100 clickable-card border-0">
                            <img src="<?= $cover ?>" class="card-img-top catalog-card-img" alt="<?= htmlspecialchars($cw['title']) ?>" draggable="false">
                            
                            <!-- Kart Üstü Durum Rozeti -->
                            <div class="position-absolute top-0 end-0 m-2">
                                <?php if ($str_contains_custom($status_str, 'Tamam') || $str_contains_custom($status_str, 'Completed')): ?>
                                    <span class="badge bg-info text-dark shadow-sm">Tamamlandı</span>
                                <?php elseif ($str_contains_custom($status_str, 'Ara') || $str_contains_custom($status_str, 'Hiatus')): ?>
                                    <span class="badge bg-warning text-dark shadow-sm">Ara Verildi</span>
                                <?php elseif ($str_contains_custom($status_str, 'Yarıda') || $str_contains_custom($status_str, 'Dropped')): ?>
                                    <span class="badge bg-danger shadow-sm">Yarıda</span>
                                <?php else: ?>
                                    <span class="badge bg-success shadow-sm">Devam</span>
                                <?php endif; ?>
                            </div>

                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <h6 class="card-title fw-bold text-truncate mb-2" title="<?= htmlspecialchars($cw['title']) ?>">
                                        <?= htmlspecialchars($cw['title']) ?>
                                    </h6>
                                    
                                    <div class="genre-badge-list mb-3">
                                        <?php 
                                            $g_array = array_map('trim', explode(',', $cw['genre_names'] ?? ''));
                                            foreach (array_slice($g_array, 0, 3) as $gn):
                                                if (!empty($gn)):
                                        ?>
                                            <span class="badge bg-secondary opacity-75 me-1 mb-1 fw-normal" style="font-size:0.7rem;"><?= htmlspecialchars($gn) ?></span>
                                        <?php endif; endforeach; ?>
                                    </div>
                                </div>

                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <small class="text-secondary fw-bold" style="font-size:0.75rem;">
                                            <i class="bi bi-bookmark-check text-primary"></i> <?= $user_ch ?>/<?= $total_ch ?>
                                        </small>
                                    </div>
                                    <div class="btn btn-outline-primary rounded-pill w-100 fw-bold btn-sm">İncele</div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });
});

function scrollSlider(sliderId, distance) {
    const slider = document.getElementById(sliderId);
    if (slider) slider.scrollBy({ left: distance, behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', function() {
    const containers = document.querySelectorAll('.draggables-scroll');
    containers.forEach(slider => {
        let isDown = false, startX, scrollLeft, isDragged = false;
        slider.querySelectorAll('img').forEach(img => img.ondragstart = () => false);

        slider.addEventListener('mousedown', (e) => {
            isDown = true; isDragged = false; slider.classList.add('active');
            startX = e.pageX - slider.offsetLeft; scrollLeft = slider.scrollLeft;
        });
        slider.addEventListener('mouseleave', () => { isDown = false; slider.classList.remove('active'); });
        slider.addEventListener('mouseup', () => { isDown = false; slider.classList.remove('active'); });
        slider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 2;
            if (Math.abs(walk) > 6) isDragged = true;
            slider.scrollLeft = scrollLeft - walk;
        });
        slider.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', (e) => { if (isDragged) { e.preventDefault(); e.stopPropagation(); } });
        });
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>