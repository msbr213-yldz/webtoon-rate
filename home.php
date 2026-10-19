<?php
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Arama ve Filtre Parametreleri (Çoklu Tür Desteği)
$search_query = trim($_GET['q'] ?? '');
$selected_genres = isset($_GET['genres']) && is_array($_GET['genres']) ? array_map('intval', $_GET['genres']) : [];
$selected_genres = array_filter($selected_genres, function($v) { return $v > 0; });

$selected_status = trim($_GET['status'] ?? '');

// 1. TÜRLERİ ÇEK
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

// 3. EN YÜKSEK PUAN ALANLAR (TOP 10)
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

// 4. KULLANICI ARAMA SORGUSU
$found_users = [];
if (!empty($search_query)) {
    try {
        $u_stmt = $pdo->prepare("
            SELECT id, username, role, created_at 
            FROM users 
            WHERE username LIKE :u_search 
            ORDER BY username ASC 
            LIMIT 6
        ");
        $u_stmt->execute(['u_search' => "%{$search_query}%"]);
        $found_users = $u_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $found_users = [];
    }
}

// 5. TÜM WEBTOON KATALOĞU SORGUSU
$sql = "
    SELECT w.*, 
           (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') 
            FROM webtoon_genres wg 
            JOIN genres g ON wg.genre_id = g.id 
            WHERE wg.webtoon_id = w.id) as genre_names,
           COALESCE((SELECT MAX(cr.chapter_number) 
             FROM chapter_ratings cr 
             WHERE cr.webtoon_id = w.id AND cr.user_id = :u_id), 0) as user_last_chapter
    FROM webtoons w
    WHERE 1=1
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
    if ($selected_status === 'Devam Ediyor') {
        $sql .= " AND (w.status LIKE '%Devam%' OR w.status LIKE '%Ongoing%')";
    } elseif ($selected_status === 'Ara Verildi') {
        $sql .= " AND (w.status LIKE '%Ara%' OR w.status LIKE '%Hiatus%' OR w.status LIKE '%Pause%')";
    } elseif ($selected_status === 'Tamamlandı') {
        $sql .= " AND (w.status LIKE '%Tamam%' OR w.status LIKE '%Completed%')";
    } elseif ($selected_status === 'Yarıda Bırakıldı') {
        $sql .= " AND (w.status LIKE '%Yarıda%' OR w.status LIKE '%Dropped%')";
    } else {
        $sql .= " AND w.status = :status";
        $params['status'] = $selected_status;
    }
}

$sql .= " ORDER BY w.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$catalog_webtoons = $stmt->fetchAll(PDO::FETCH_ASSOC);

function buildMultiFilterUrl($genres = [], $status = null, $search = null) {
    $params = [];
    if (!empty($genres)) {
        $params['genres'] = array_values($genres);
    }
    if ($status !== null && $status !== '') {
        $params['status'] = $status;
    }
    if ($search !== null && $search !== '') {
        $params['q'] = $search;
    }
    return 'home.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

include __DIR__ . '/includes/header.php';
?>

<style>
    /* DİKDÖRTGEN KAPSAYICI STİLLERİ */
    .slider-section-card {
        padding: 1.5rem;
        border-radius: 1rem;
        transition: background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    }

    [data-bs-theme="dark"] .slider-section-card {
        background-color: #1a1c23;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
    }

    [data-bs-theme="light"] .slider-section-card {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    }

    .slider-wrapper { position: relative; }

    .slider-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        width: 42px;
        height: 42px;
        background-color: rgba(0, 0, 0, 0.65);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        opacity: 0.7;
        backdrop-filter: blur(4px);
    }

    .slider-arrow:hover {
        opacity: 1;
        background-color: #0d6efd;
        border-color: #0d6efd;
        transform: translateY(-50%) scale(1.1);
    }

    .slider-arrow.arrow-left { left: -15px; }
    .slider-arrow.arrow-right { right: -15px; }

    .horizontal-scroll-container {
        display: flex;
        gap: 1rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
        cursor: grab;
        user-select: none;
        -webkit-user-select: none;
        scroll-behavior: smooth;
    }

    .horizontal-scroll-container::-webkit-scrollbar { height: 6px; }
    .horizontal-scroll-container::-webkit-scrollbar-thumb {
        background: rgba(150, 150, 150, 0.3);
        border-radius: 4px;
    }
    
    .horizontal-scroll-container.active {
        cursor: grabbing !important;
        scroll-behavior: auto;
    }

    .horizontal-scroll-container img,
    .horizontal-scroll-container a {
        -webkit-user-drag: none !important;
        user-select: none !important;
    }
</style>

<div class="container my-4">

    <!-- 1. ARAMA ÇUBUĞU -->
    <div class="row justify-content-center mb-5">
        <div class="col-md-8">
            <form action="home.php" method="GET" class="d-flex gap-2">
                <?php foreach ($selected_genres as $sg): ?>
                    <input type="hidden" name="genres[]" value="<?= $sg ?>">
                <?php endforeach; ?>
                <?php if (!empty($selected_status)): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($selected_status) ?>">
                <?php endif; ?>
                <div class="input-group input-group-lg shadow-sm">
                    <span class="input-group-text theme-input text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control theme-input" 
                           placeholder="Webtoon veya kullanıcı adı ara..." value="<?= htmlspecialchars($search_query) ?>">
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Ara</button>
                </div>
            </form>
        </div>
    </div>

    <!-- BULUNAN KULLANICILAR BÖLÜMÜ (Arama yapıldığında görünür) -->
    <?php if (!empty($search_query) && !empty($found_users)): ?>
        <div class="card bg-body-tertiary border shadow-sm rounded-4 p-4 mb-5">
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2 text-body">
                <i class="bi bi-people-fill text-primary"></i> Bulunan Kullanıcılar (<?= count($found_users) ?>)
            </h5>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
                <?php foreach ($found_users as $fu): ?>
                    <div class="col">
                        <a href="<?= BASE_URL ?>/profile/profile.php?id=<?= $fu['id'] ?>" class="text-decoration-none">
                            <div class="card bg-body border shadow-sm p-3 rounded-3 h-100 d-flex flex-row align-items-center gap-3 clickable-card hover-lift">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 48px; height: 48px; font-size: 1.2rem;">
                                    <?= strtoupper(substr($fu['username'], 0, 1)) ?>
                                </div>
                                <div class="overflow-hidden">
                                    <h6 class="fw-bold mb-0 text-body text-truncate"><?= htmlspecialchars($fu['username']) ?></h6>
                                    <small class="text-body-secondary d-block">
                                        <?php if (($fu['role'] ?? '') === 'admin'): ?>
                                            <span class="badge bg-warning text-dark me-1" style="font-size: 0.65rem;">Admin</span>
                                        <?php endif; ?>
                                        Üye
                                    </small>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 2. EDİTÖRÜN SEÇİMİ (DİKDÖRTGEN KUTU İÇİNDE SLIDER) -->
    <?php if (!empty($featured_webtoons) && empty($search_query) && empty($selected_genres) && empty($selected_status)): ?>
        <div class="slider-section-card mb-5">
            <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-fire text-danger"></i> Editörün Seçimi
            </h4>
            <div class="slider-wrapper">
                <button class="slider-arrow arrow-left" onclick="scrollSlider('featured-slider', -300)">
                    <i class="bi bi-chevron-left fs-5"></i>
                </button>

                <div class="horizontal-scroll-container draggables-scroll" id="featured-slider">
                    <?php foreach ($featured_webtoons as $fw): ?>
                        <?php 
                            $webtoon_url = BASE_URL . 'webtoon.php?id=' . $fw['id'];
                            $cover_img = $fw['cover_image'] ?? '';
                            $cover = !empty($cover_img) ? ((strpos($cover_img, 'http') === 0) ? $cover_img : BASE_URL . 'uploads/' . htmlspecialchars($cover_img)) : 'https://via.placeholder.com/180x240?text=No+Cover';
                        ?>
                        <a href="<?= $webtoon_url ?>" class="card theme-card slider-card shadow-sm clickable-card">
                            <img src="<?= $cover ?>" class="card-img-top" alt="<?= htmlspecialchars($fw['title']) ?>" draggable="false">
                            <div class="card-body p-2 text-center d-flex flex-column justify-content-between">
                                <div>
                                    <h6 class="card-title text-truncate mb-1" title="<?= htmlspecialchars($fw['title']) ?>">
                                        <?= htmlspecialchars($fw['title']) ?>
                                    </h6>
                                </div>
                                <span class="btn btn-sm btn-outline-primary w-100 mt-2">İncele</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <button class="slider-arrow arrow-right" onclick="scrollSlider('featured-slider', 300)">
                    <i class="bi bi-chevron-right fs-5"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3. EN YÜKSEK PUAN ALANLAR (DİKDÖRTGEN KUTU İÇİNDE SLIDER) -->
    <?php if (!empty($top_rated_webtoons) && empty($search_query) && empty($selected_genres) && empty($selected_status)): ?>
        <div class="slider-section-card mb-5">
            <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-star-fill text-warning"></i> En Yüksek Puan Alanlar (Top 10)
            </h4>
            <div class="slider-wrapper">
                <button class="slider-arrow arrow-left" onclick="scrollSlider('top-rated-slider', -300)">
                    <i class="bi bi-chevron-left fs-5"></i>
                </button>

                <div class="horizontal-scroll-container draggables-scroll" id="top-rated-slider">
                    <?php foreach ($top_rated_webtoons as $tr): ?>
                        <?php 
                            $webtoon_url = BASE_URL . 'webtoon.php?id=' . $tr['id'];
                            $cover_img = $tr['cover_image'] ?? '';
                            $cover = !empty($cover_img) ? ((strpos($cover_img, 'http') === 0) ? $cover_img : BASE_URL . 'uploads/' . htmlspecialchars($cover_img)) : 'https://via.placeholder.com/180x240?text=No+Cover';
                        ?>
                        <a href="<?= $webtoon_url ?>" class="card theme-card slider-card shadow-sm clickable-card">
                            <img src="<?= $cover ?>" class="card-img-top" alt="<?= htmlspecialchars($tr['title']) ?>" draggable="false">
                            <div class="card-body p-2 text-center d-flex flex-column justify-content-between">
                                <div>
                                    <h6 class="card-title text-truncate mb-1" title="<?= htmlspecialchars($tr['title']) ?>">
                                        <?= htmlspecialchars($tr['title']) ?>
                                    </h6>
                                </div>
                                <span class="btn btn-sm btn-outline-warning w-100 mt-2">
                                    ⭐ <?= number_format((float)($tr['avg_rating'] ?? 0), 1) ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <button class="slider-arrow arrow-right" onclick="scrollSlider('top-rated-slider', 300)">
                    <i class="bi bi-chevron-right fs-5"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4. TÜM WEBTOON KATALOĞU & ÇOKLU FİLTRELER -->
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-grid-3x3-gap-fill text-primary"></i> Tüm Webtoon Kataloğu
                <?php if (!empty($selected_genres)): ?>
                    <span class="badge bg-primary fs-6 align-middle ms-2"><?= count($selected_genres) ?> Tür Seçili</span>
                <?php endif; ?>
            </h4>
            
            <?php if (!empty($selected_genres) || !empty($selected_status) || !empty($search_query)): ?>
                <a href="home.php" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-x-circle me-1"></i> Filtreleri Temizle
                </a>
            <?php endif; ?>
        </div>

        <!-- ÇOKLU TÜR FİLTRELEME BUTONLARI -->
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="<?= buildMultiFilterUrl([], $selected_status, $search_query) ?>" 
               class="btn btn-sm <?= empty($selected_genres) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                Tüm Türler
            </a>
            <?php foreach ($genres as $g): ?>
                <?php 
                    $g_id = (int)$g['id'];
                    $is_selected = in_array($g_id, $selected_genres);
                    
                    $temp_genres = $selected_genres;
                    if ($is_selected) {
                        $temp_genres = array_diff($temp_genres, [$g_id]);
                    } else {
                        $temp_genres[] = $g_id;
                    }
                    
                    $url = buildMultiFilterUrl($temp_genres, $selected_status, $search_query);
                ?>
                <a href="<?= $url ?>" 
                   class="btn btn-sm <?= $is_selected ? 'btn-primary fw-bold shadow-sm' : 'btn-outline-secondary' ?>">
                    <?php if ($is_selected): ?><i class="bi bi-check2 me-1"></i><?php endif; ?>
                    <?= htmlspecialchars($g['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- DURUM FİLTRELEME BUTONLARI -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="<?= buildMultiFilterUrl($selected_genres, '', $search_query) ?>" 
               class="btn btn-sm <?= empty($selected_status) ? 'btn-secondary' : 'btn-outline-secondary' ?>">
                Tümü
            </a>
            <?php 
                $statuses = [
                    'Devam Ediyor' => 'success',
                    'Ara Verildi' => 'warning',
                    'Tamamlandı' => 'info',
                    'Yarıda Bırakıldı' => 'danger'
                ];
                foreach ($statuses as $st_name => $st_color):
                    $is_st_active = ($selected_status === $st_name);
                    $target_status = $is_st_active ? '' : $st_name;
                    $url = buildMultiFilterUrl($selected_genres, $target_status, $search_query);
            ?>
                <a href="<?= $url ?>" 
                   class="btn btn-sm <?= $is_st_active ? "btn-{$st_color} fw-bold" : "btn-outline-{$st_color}" ?>">
                    <?= $st_name ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- WEBTOON LİSTESİ IZGARA (GRID) -->
        <?php if (empty($catalog_webtoons) && empty($found_users)): ?>
            <div class="text-center py-5 theme-card rounded border text-muted">
                <i class="bi bi-search fs-1 d-block mb-2"></i>
                <h5>Sonuç Bulunamadı</h5>
                <p class="mb-3">Aramanıza veya seçtiğiniz çoklu filtrelere uygun webtoon veya kullanıcı bulunamadı.</p>
                <a href="home.php" class="btn btn-outline-primary btn-sm">Tüm Kataloğu Göster</a>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                <?php foreach ($catalog_webtoons as $cw): ?>
                    <?php 
                        $webtoon_url = BASE_URL . 'webtoon.php?id=' . $cw['id'];
                        $cover_img = $cw['cover_image'] ?? '';
                        $cover = !empty($cover_img) ? ((strpos($cover_img, 'http') === 0) ? $cover_img : BASE_URL . 'uploads/' . htmlspecialchars($cover_img)) : 'https://via.placeholder.com/300x400?text=No+Cover';
                        
                        $user_ch = (int)($cw['user_last_chapter'] ?? 0);
                        $total_ch = (int)($cw['total_chapters'] ?? 0);
                        $status_str = $cw['status'] ?? 'Devam Ediyor';

                        if ($user_ch >= $total_ch && $total_ch > 0) {
                            $read_icon = '<i class="bi bi-check-circle-fill text-success fs-6 me-2" title="Okundu (Tamamlandı)" data-bs-toggle="tooltip"></i>';
                        } elseif ($user_ch > 0) {
                            $read_icon = '<i class="bi bi-book-half text-primary fs-6 me-2" title="Okunuyor (Kaldığın: ' . $user_ch . '. Bölüm)" data-bs-toggle="tooltip"></i>';
                        } else {
                            $read_icon = '<i class="bi bi-bookmark text-muted fs-6 me-2" title="Henüz başlanmadı" data-bs-toggle="tooltip"></i>';
                        }
                    ?>
                    <div class="col">
                        <a href="<?= $webtoon_url ?>" class="card theme-card h-100 shadow-sm clickable-card">
                            <img src="<?= $cover ?>" class="card-img-top catalog-card-img" alt="<?= htmlspecialchars($cw['title']) ?>" draggable="false">
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <h5 class="card-title text-truncate mb-2 d-flex align-items-center" title="<?= htmlspecialchars($cw['title']) ?>">
                                        <?= $read_icon ?>
                                        <span class="text-truncate"><?= htmlspecialchars($cw['title']) ?></span>
                                    </h5>
                                    
                                    <div class="genre-badge-list mb-3">
                                        <?php 
                                            $g_array = array_map('trim', explode(',', $cw['genre_names'] ?? ''));
                                            foreach (array_slice($g_array, 0, 4) as $gn):
                                                if (!empty($gn)):
                                        ?>
                                                    <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($gn) ?></span>
                                        <?php 
                                                endif;
                                            endforeach; 
                                        ?>
                                    </div>
                                </div>

                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <?php if (mb_stripos($status_str, 'Tamam') !== false || mb_stripos($status_str, 'Completed') !== false): ?>
                                            <span class="badge bg-info text-dark">Tamamlandı</span>
                                        <?php elseif (mb_stripos($status_str, 'Ara') !== false || mb_stripos($status_str, 'Hiatus') !== false): ?>
                                            <span class="badge bg-warning text-dark">Ara Verildi</span>
                                        <?php elseif (mb_stripos($status_str, 'Yarıda') !== false || mb_stripos($status_str, 'Dropped') !== false): ?>
                                            <span class="badge bg-danger text-white">Yarıda Bırakıldı</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Devam Ediyor</span>
                                        <?php endif; ?>

                                        <span class="badge bg-body-secondary text-body border">
                                            Kaldığın: <?= $user_ch ?> / <?= $total_ch ?>
                                        </span>
                                    </div>

                                    <div class="btn btn-primary w-100 fw-bold">
                                        Detay / Puanlar
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function scrollSlider(sliderId, distance) {
    const slider = document.getElementById(sliderId);
    if (slider) {
        slider.scrollBy({
            left: distance,
            behavior: 'smooth'
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const containers = document.querySelectorAll('.draggables-scroll');

    containers.forEach(slider => {
        let isDown = false;
        let startX;
        let scrollLeft;
        let isDragged = false;

        const images = slider.querySelectorAll('img');
        images.forEach(img => {
            img.ondragstart = () => false;
        });

        slider.addEventListener('mousedown', (e) => {
            isDown = true;
            isDragged = false;
            slider.classList.add('active');
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        });

        slider.addEventListener('mouseleave', () => {
            isDown = false;
            slider.classList.remove('active');
        });

        slider.addEventListener('mouseup', () => {
            isDown = false;
            slider.classList.remove('active');
        });

        slider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 2;
            
            if (Math.abs(walk) > 6) {
                isDragged = true;
            }
            
            slider.scrollLeft = scrollLeft - walk;
        });

        const links = slider.querySelectorAll('a');
        links.forEach(link => {
            link.addEventListener('click', (e) => {
                if (isDragged) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });
    });
});
</script>
</body>
</html>