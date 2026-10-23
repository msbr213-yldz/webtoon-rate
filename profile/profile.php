<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bir üst dizindeki config/config.php dosyasını dahil ediyoruz
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$current_session_id = (int)$_SESSION['user_id'];

// URL'den id gelmişse o kullanıcıyı, gelmediyse oturum açan kullanıcıyı göster
$profile_user_id = isset($_GET['id']) && (int)$_GET['id'] > 0 ? (int)$_GET['id'] : $current_session_id;

// 1. Kullanıcı Bilgilerini Çek
$stmt = $pdo->prepare("SELECT id, username, email, created_at, role FROM users WHERE id = :id");
$stmt->execute(['id' => $profile_user_id]);
$profile_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile_user) {
    header('Location: ' . BASE_URL . 'home.php');
    exit;
}

$is_own_profile = ((int)$profile_user['id'] === $current_session_id);

// 2. TAKİP EDİLEN SERİLERİ ÇEK (user_follows)
$followed_webtoons = [];
try {
    $stmt = $pdo->prepare("
        SELECT w.*, 
               (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') 
                FROM webtoon_genres wg 
                JOIN genres g ON wg.genre_id = g.id 
                WHERE wg.webtoon_id = w.id) as genre_names
        FROM user_follows uf
        JOIN webtoons w ON uf.webtoon_id = w.id
        WHERE uf.user_id = :u_id
        ORDER BY uf.id DESC
    ");
    $stmt->execute(['u_id' => $profile_user_id]);
    $followed_webtoons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $followed_webtoons = [];
}
$followed_count = count($followed_webtoons);

// 3. OKUNAN SERİLERİ ÇEK
$my_webtoons = [];
try {
    $stmt = $pdo->prepare("
        SELECT w.*, 
               MAX(cr.chapter_number) as user_last_chapter,
               COALESCE(MAX(cr.rating), 0) as user_last_rating,
               MAX(cr.id) as max_sort_id,
               GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') as genre_names
        FROM chapter_ratings cr
        JOIN webtoons w ON cr.webtoon_id = w.id
        LEFT JOIN webtoon_genres wg ON w.id = wg.webtoon_id
        LEFT JOIN genres g ON wg.genre_id = g.id
        WHERE cr.user_id = :u_id
        GROUP BY w.id, w.title, w.description, w.cover_image, w.status, w.total_chapters, w.current_chapter, w.is_featured, w.created_at, w.updated_at
        ORDER BY max_sort_id DESC
    ");
    $stmt->execute(['u_id' => $profile_user_id]);
    $my_webtoons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $my_webtoons = [];
}

// 4. İstek Sayısı
$request_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM webtoon_requests WHERE user_id = :u_id");
    $stmt->execute(['u_id' => $profile_user_id]);
    $request_count = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $request_count = 0;
}

// Bir üst dizindeki includes/header.php dosyasını dahil ediyoruz
include __DIR__ . '/../includes/header.php'; 
?>

<style>
    /* Tıklanabilir satır ve kart efektleri */
    .clickable-row {
        cursor: pointer;
        transition: background-color 0.15s ease-in-out;
    }
    .clickable-card {
        cursor: pointer;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        text-decoration: none !important;
    }
    .clickable-card:hover {
        transform: translateY(-4px);
        border-color: #0d6efd !important;
        box-shadow: 0 0.5rem 1rem rgba(13, 110, 253, 0.25) !important;
    }
</style>

<div class="container my-4">

    <!-- BAŞKASININ PROFİLİNDEYSE ARAMA ÇUBUĞUNU GÖSTER -->
    <?php if (!$is_own_profile): ?>
        <div class="row justify-content-center mb-4">
            <div class="col-md-8">
                <form action="<?= BASE_URL ?>home.php" method="GET" class="d-flex gap-2">
                    <div class="input-group input-group-lg shadow-sm">
                        <span class="input-group-text theme-input text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control theme-input" 
                               placeholder="Webtoon veya kullanıcı adı ara...">
                        <button type="submit" class="btn btn-primary px-4 fw-bold">Ara</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Üst Banner -->
    <div class="card bg-body-tertiary border shadow-sm rounded-4 mb-4">
        <div class="card-body p-4 d-flex align-items-center gap-3 flex-wrap">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 80px; height: 80px; font-size: 2rem;">
                <?= strtoupper(substr($profile_user['username'], 0, 1)) ?>
            </div>
            <div class="flex-grow-1">
                <h3 class="mb-1 d-flex align-items-center gap-2 fw-bold text-body">
                    <?= htmlspecialchars($profile_user['username']) ?>
                    <?php if (!empty($profile_user['role']) && $profile_user['role'] === 'admin'): ?>
                        <span class="badge bg-warning text-dark fs-6">Admin</span>
                    <?php endif; ?>
                    <?php if ($is_own_profile): ?>
                        <span class="badge bg-info text-dark fs-6">Senin Profilin</span>
                    <?php endif; ?>
                </h3>
                <p class="text-body-secondary mb-0"><i class="bi bi-calendar3 me-1"></i> Kayıt Tarihi: <?= date('d.m.Y', strtotime($profile_user['created_at'])) ?></p>
            </div>
        </div>
    </div>

    <!-- İstatistik Rozetleri -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card bg-body-tertiary border border-primary-subtle p-3 d-flex flex-row align-items-center justify-content-between shadow-sm rounded-4" 
                 onclick="switchTab('followed-tab')" style="cursor: pointer;">
                <div>
                    <span class="text-body-secondary d-block small">Takip Edilen Seriler</span>
                    <h4 class="mb-0 text-primary fw-bold"><?= $followed_count ?></h4>
                </div>
                <div class="p-3 bg-primary bg-opacity-10 rounded-3">
                    <i class="bi bi-bookmark-heart fs-2 text-primary"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card bg-body-tertiary border border-warning-subtle p-3 d-flex flex-row align-items-center justify-content-between shadow-sm rounded-4">
                <div>
                    <span class="text-body-secondary d-block small">Yapılan İstekler</span>
                    <h4 class="mb-0 text-warning fw-bold"><?= $request_count ?></h4>
                </div>
                <div class="p-3 bg-warning bg-opacity-10 rounded-3">
                    <i class="bi bi-inbox fs-2 text-warning"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Sekmeler -->
    <ul class="nav nav-tabs border-bottom border-secondary-subtle mb-4" id="profileTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active text-body fw-bold" id="webtoons-tab" data-bs-toggle="tab" data-bs-target="#webtoons-tab-pane" type="button" role="tab">
                📖 Okunan Seriler (<?= count($my_webtoons) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-body fw-bold" id="followed-tab" data-bs-toggle="tab" data-bs-target="#followed-tab-pane" type="button" role="tab">
                ❤ Takip Edilen Seriler (<?= $followed_count ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-body fw-bold" id="info-tab" data-bs-toggle="tab" data-bs-target="#info-tab-pane" type="button" role="tab">
                👤 Profil Bilgileri
            </button>
        </li>
    </ul>

    <!-- Sekme İçerikleri -->
    <div class="tab-content" id="profileTabContent">
        
        <!-- 1. OKUNAN SERİLER -->
        <div class="tab-pane fade show active" id="webtoons-tab-pane" role="tabpanel" tabindex="0">
            <?php if (empty($my_webtoons)): ?>
                <div class="text-center py-5 bg-body-tertiary rounded-4 border text-muted">
                    <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                    <p class="mb-0">Henüz okuma listesine eklenmiş bir seri yok.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive bg-body-tertiary rounded-4 border shadow-sm p-2">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="table-active">
                                <th style="width: 70px;">Kapak</th>
                                <th>Webtoon Adı</th>
                                <th>Durum</th>
                                <th>Kaldığın Bölüm</th>
                                <th>Puanın</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $index = 0;
                            foreach ($my_webtoons as $w): 
                                $index++;
                                $user_ch = (int)$w['user_last_chapter'];
                                $total_ch = (int)($w['total_chapters'] ?? 0);
                                $is_completed = ($total_ch > 0 && $user_ch >= $total_ch);
                                $is_hidden = ($index > 5) ? 'd-none hidden-webtoon-row' : '';
                                $webtoon_url = BASE_URL . 'webtoon.php?id=' . $w['id'];
                            ?>
                                <tr class="clickable-row <?= $is_hidden ?>" onclick="window.location.href='<?= $webtoon_url ?>'">
                                    <td>
                                        <?php 
                                            $c_img = $w['cover_image'] ?? '';
                                            $cover = !empty($c_img) ? ((strpos($c_img, 'http') === 0) ? $c_img : BASE_URL . 'uploads/' . htmlspecialchars($c_img)) : 'https://via.placeholder.com/300x400?text=No+Cover'; 
                                        ?>
                                        <img src="<?= $cover ?>" class="rounded shadow-sm border" style="width: 45px; height: 60px; object-fit: cover;">
                                    </td>
                                    <td>
                                        <strong class="d-block text-body"><?= htmlspecialchars($w['title']) ?></strong>
                                        <small class="text-body-secondary"><?= htmlspecialchars($w['genre_names'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <?php if ($is_completed): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Tamamlandı</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark"><i class="bi bi-book-half"></i> Okunuyor</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-body">Bölüm <?= $user_ch ?></span> 
                                        <small class="text-body-secondary">/ <?= $total_ch ?></small>
                                    </td>
                                    <td>
                                        <?php if ((int)$w['user_last_rating'] > 0): ?>
                                            <span class="text-warning fw-bold">⭐ <?= (int)$w['user_last_rating'] ?> / 10</span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end" onclick="event.stopPropagation();">
                                        <a href="<?= $webtoon_url ?>" class="btn btn-sm btn-outline-primary fw-bold">
                                            <i class="bi bi-box-arrow-up-right me-1"></i> Detay
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (count($my_webtoons) > 5): ?>
                    <div class="text-center mt-3">
                        <button id="toggleWebtoonsBtn" onclick="toggleWebtoonsList()" class="btn btn-outline-primary btn-sm px-4 rounded-pill fw-bold">
                            <i class="bi bi-chevron-down me-1"></i> Devamını Göster (<?= count($my_webtoons) - 5 ?> seri daha)
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- 2. TAKİP EDİLEN SERİLER -->
        <div class="tab-pane fade" id="followed-tab-pane" role="tabpanel" tabindex="0">
            <?php if (empty($followed_webtoons)): ?>
                <div class="text-center py-5 bg-body-tertiary rounded-4 border text-muted">
                    <i class="bi bi-bookmark-dash fs-1 d-block mb-2 text-warning"></i>
                    <p class="mb-0">Takibe alınmış herhangi bir webtoon bulunmuyor.</p>
                </div>
            <?php else: ?>
                <div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3">
                    <?php foreach ($followed_webtoons as $fw): ?>
                        <?php $fw_url = BASE_URL . 'webtoon.php?id=' . $fw['id']; ?>
                        <div class="col">
                            <a href="<?= $fw_url ?>" class="card bg-body-tertiary border h-100 shadow-sm clickable-card">
                                <?php 
                                    $fc_img = $fw['cover_image'] ?? '';
                                    $fcover = !empty($fc_img) ? ((strpos($fc_img, 'http') === 0) ? $fc_img : BASE_URL . 'uploads/' . htmlspecialchars($fc_img)) : 'https://via.placeholder.com/300x400?text=No+Cover'; 
                                ?>
                                <img src="<?= $fcover ?>" class="card-img-top webtoon-card-img" style="height: 220px; object-fit: cover;" alt="<?= htmlspecialchars($fw['title']) ?>">
                                <div class="card-body p-2 text-center d-flex flex-column justify-content-between">
                                    <div>
                                        <h6 class="card-title text-truncate text-body fw-bold mb-1" title="<?= htmlspecialchars($fw['title']) ?>">
                                            <?= htmlspecialchars($fw['title']) ?>
                                        </h6>
                                        <small class="text-body-secondary d-block text-truncate"><?= htmlspecialchars($fw['genre_names'] ?? '-') ?></small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. PROFİL BİLGİLERİ -->
        <div class="tab-pane fade" id="info-tab-pane" role="tabpanel" tabindex="0">
            <div class="card bg-body-tertiary border shadow-sm p-4 rounded-4 col-md-8 mx-auto">
                <h5 class="fw-bold text-body mb-3"><i class="bi bi-person-lines-fill text-primary me-2"></i> Hesap Bilgileri</h5>
                <div class="row mb-3 border-bottom pb-2">
                    <div class="col-sm-4 text-body-secondary">Kullanıcı Adı:</div>
                    <div class="col-sm-8 fw-bold text-body"><?= htmlspecialchars($profile_user['username']) ?></div>
                </div>

                <?php if ($is_own_profile): ?>
                    <div class="row mb-3 border-bottom pb-2">
                        <div class="col-sm-4 text-body-secondary">E-posta Adresi:</div>
                        <div class="col-sm-8 text-body"><?= htmlspecialchars($profile_user['email']) ?></div>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-sm-4 text-body-secondary">Hesap Rolü:</div>
                    <div class="col-sm-8">
                        <span class="badge bg-<?= ($profile_user['role'] ?? '') === 'admin' ? 'warning text-dark' : 'secondary' ?> fs-6">
                            <?= ucfirst(htmlspecialchars($profile_user['role'] ?? 'Kullanıcı')) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>


<script>
function switchTab(tabId) {
    var triggerEl = document.getElementById(tabId);
    if (triggerEl) {
        var tab = new bootstrap.Tab(triggerEl);
        tab.show();
    }
}

function toggleWebtoonsList() {
    const hiddenRows = document.querySelectorAll('.hidden-webtoon-row');
    const btn = document.getElementById('toggleWebtoonsBtn');
    
    let isHidden = false;
    hiddenRows.forEach(row => {
        if (row.classList.contains('d-none')) {
            row.classList.remove('d-none');
            isHidden = true;
        } else {
            row.classList.add('d-none');
        }
    });

    if (isHidden) {
        btn.innerHTML = '<i class="bi bi-chevron-up me-1"></i> Daha Az Göster';
    } else {
        btn.innerHTML = '<i class="bi bi-chevron-down me-1"></i> Devamını Göster (<?= count($my_webtoons) - 5 ?> seri daha)';
    }
}
</script>
</body>
</html>