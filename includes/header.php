<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

// OKUNMAMIŞ BİLDİRİMLERİ ÇEKME
$unread_notifications = [];
$unread_count = 0;

if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    $current_u_id = (int)$_SESSION['user_id'];
    
    try {
        // 1. Okunmamış bildirim sayısını çek
        $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :u_id AND is_read = 0");
        $cnt_stmt->execute(['u_id' => $current_u_id]);
        $unread_count = (int)$cnt_stmt->fetchColumn();

        // 2. Bildirimleri çek
        $notif_stmt = $pdo->prepare("
            SELECT * 
            FROM notifications 
            WHERE user_id = :u_id 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $notif_stmt->execute(['u_id' => $current_u_id]);
        $unread_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $unread_count = 0;
        $unread_notifications = [];
    }
}
?>
<!DOCTYPE html>
<html lang="tr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webtoon Tracker</title>
    <!-- Bootstrap 5 CSS & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* DARK MODE DÜZENLEMELERİ */
        [data-bs-theme="dark"] body {
            background-color: #121212;
            color: #e0e0e0;
        }

        [data-bs-theme="dark"] .navbar-custom {
            background-color: #1a1c23;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        [data-bs-theme="dark"] .theme-card {
            background-color: #1a1c23 !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        [data-bs-theme="dark"] .theme-input {
            background-color: #1a1c23 !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.15) !important;
        }

        /* LIGHT MODE DÜZENLEMELERİ */
        [data-bs-theme="light"] body {
            background-color: #f4f6f9;
            color: #212529;
        }

        [data-bs-theme="light"] .navbar-custom {
            background-color: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        [data-bs-theme="light"] .navbar-brand,
        [data-bs-theme="light"] .nav-link:not(.text-warning) {
            color: #212529 !important;
        }

        [data-bs-theme="light"] .theme-card {
            background-color: #ffffff !important;
            color: #212529 !important;
            border-color: #e0e0e0 !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;
        }

        [data-bs-theme="light"] .theme-input {
            background-color: #ffffff !important;
            color: #212529 !important;
            border-color: #ced4da !important;
        }

        [data-bs-theme="light"] .text-light {
            color: #212529 !important;
        }

        /* BİLDİRİM AÇILIR MENÜSÜ DÜZENLEMESİ */
        .notification-dropdown {
            width: 340px;
        }

        .notification-scroll-area {
            max-height: 280px;
            overflow-y: auto;
        }

        .notification-item {
            transition: background-color 0.15s ease;
            text-decoration: none !important;
        }

        .notification-item:hover {
            background-color: rgba(255, 255, 255, 0.08) !important;
        }

        .notification-item.unread {
            background-color: rgba(13, 110, 253, 0.15);
            border-left: 3px solid #0d6efd;
        }

        .slider-card {
            flex: 0 0 auto;
            width: 180px;
        }

        .slider-card img {
            height: 240px !important;
            object-fit: cover !important;
        }

        .catalog-card-img {
            height: 280px !important;
            object-fit: cover !important;
        }

        .clickable-card {
            cursor: pointer;
            text-decoration: none !important;
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .clickable-card:hover {
            transform: translateY(-4px);
            border-color: #0d6efd !important;
            box-shadow: 0 0.5rem 1rem rgba(13, 110, 253, 0.15) !important;
        }

        .genre-badge-list .badge {
            font-size: 0.8rem;
            padding: 0.25em 0.5em;
        }
    </style>

    <!-- SAYFA YÜKLENİRKEN TEMAYI DOĞRUDAN UYGULA -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-custom sticky-top mb-4">
    <div class="container">
        <!-- Logo -->
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= BASE_URL ?>home.php">
            <i class="bi bi-book-half text-primary fs-4"></i> Webtoon Tracker
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>home.php">Ana Sayfa</a>
                </li>
                
                <!-- FORUM SEKMESİ -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>forum/forum.php">
                        <i class="bi bi-chat-square-text me-1"></i> Forum
                    </a>
                </li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>profile/profile.php">Profilim</a>
                    </li>
                <?php endif; ?>

                <!-- ADMIN PANELI AÇILIR MENÜSÜ -->
                <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-warning fw-bold" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-shield-lock-fill me-1"></i> Admin Paneli
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark border-secondary shadow" aria-labelledby="adminDropdown">
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>admin/manage_webtoons.php">
                                    <i class="bi bi-journal-text me-2 text-primary"></i> Webtoon Yönetimi
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>admin/add_webtoon.php">
                                    <i class="bi bi-plus-circle me-2 text-success"></i> Yeni Webtoon Ekle
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>admin/genres.php">
                                    <i class="bi bi-tags me-2 text-info"></i> Tür / Kategori Yönetimi
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-3">
                
                <!-- TEMA DEĞİŞTİRME BUTONU -->
                <button class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" 
                        id="themeToggleBtn" type="button" style="width: 40px; height: 40px; padding: 0;" title="Temayı Değiştir">
                    <i class="bi bi-moon-stars-fill fs-5" id="themeIcon"></i>
                </button>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- BİLDİRİMLER DROPDOWN -->
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary position-relative rounded-circle d-flex align-items-center justify-content-center" 
                                type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" 
                                style="width: 40px; height: 40px; padding: 0;">
                            <i class="bi bi-bell-fill fs-5"></i>
                            
                            <?php if ($unread_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                                    <?= $unread_count > 9 ? '9+' : $unread_count ?>
                                </span>
                            <?php endif; ?>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark border-secondary shadow-lg p-0 notification-dropdown" aria-labelledby="notifDropdown">
                            <!-- BAŞLIK BÖLÜMÜ (SABİT) -->
                            <li class="p-2 border-bottom border-secondary d-flex justify-content-between align-items-center">
                                <span class="fw-bold px-2"><i class="bi bi-bell me-1 text-primary"></i> Bildirimler</span>
                                <?php if ($unread_count > 0): ?>
                                    <a href="<?= BASE_URL ?>notifications/notification.php?mark_all=1" class="text-primary text-decoration-none small px-2">Tümünü Okundu İşaretle</a>
                                <?php endif; ?>
                            </li>

                            <!-- KAYDIRILABİLİR İÇERİK ALANI -->
                            <li>
                                <div class="notification-scroll-area p-2">
                                    <?php if (empty($unread_notifications)): ?>
                                        <div class="text-center py-4 text-muted small">
                                            <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                            Henüz bildiriminiz bulunmuyor.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($unread_notifications as $notif): ?>
                                            <?php $is_unread = ((int)($notif['is_read'] ?? 0) === 0); ?>
                                            <!-- BİLDİRİME TIKLANDIĞINDA YÖNLENDİRME (redirect_id) -->
                                            <a href="<?= BASE_URL ?>notifications/notification.php?redirect_id=<?= $notif['id'] ?>" class="dropdown-item d-flex align-items-center gap-2 p-2 rounded mb-1 notification-item <?= $is_unread ? 'unread' : '' ?>">
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <small class="text-body d-block text-truncate" style="font-size: 0.8rem;">
                                                        <?= htmlspecialchars($notif['message'] ?? '') ?>
                                                    </small>
                                                    <small class="text-muted" style="font-size: 0.65rem;">
                                                        <?= date('d.m.Y H:i', strtotime($notif['created_at'])) ?>
                                                    </small>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </li>

                            <!-- TÜM BİLDİRİMLERİ GÖR LINKI (ALTTA SABİT) -->
                            <li class="border-top border-secondary p-1">
                                <a class="dropdown-item text-center fw-bold text-primary py-2 rounded" href="<?= BASE_URL ?>notifications/notification.php">
                                    <i class="bi bi-list-ul me-1"></i> Tüm Bildirimleri Gör
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- KULLANICI PROFİL DROPDOWN -->
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Kullanıcı') ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark border-secondary">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>profile/profile.php"><i class="bi bi-person me-2"></i> Profilim</a></li>
                            <li><hr class="dropdown-divider border-secondary"></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Çıkış Yap</a></li>
                        </ul>
                    </div>

                <?php else: ?>
                    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-primary rounded-pill px-3">Giriş Yap</a>
                    <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-primary rounded-pill px-3">Kayıt Ol</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- GARANTİ TEMA DEĞİŞTİRME SCRIPT'İ -->
<script>
(function() {
    function initTheme() {
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeIcon = document.getElementById('themeIcon');
        const htmlTag = document.documentElement;

        function applyTheme(theme) {
            htmlTag.setAttribute('data-bs-theme', theme);
            localStorage.setItem('theme', theme);

            if (themeIcon) {
                if (theme === 'light') {
                    themeIcon.className = 'bi bi-sun-fill text-warning fs-5';
                    if (themeToggleBtn) themeToggleBtn.title = 'Karanlık Moda Geç';
                } else {
                    themeIcon.className = 'bi bi-moon-stars-fill text-light fs-5';
                    if (themeToggleBtn) themeToggleBtn.title = 'Aydınlık Moda Geç';
                }
            }
        }

        const savedTheme = localStorage.getItem('theme') || 'dark';
        applyTheme(savedTheme);

        if (themeToggleBtn) {
            themeToggleBtn.onclick = function (e) {
                e.preventDefault();
                const currentTheme = htmlTag.getAttribute('data-bs-theme') || 'dark';
                const nextTheme = (currentTheme === 'dark') ? 'light' : 'dark';
                applyTheme(nextTheme);
            };
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
})();
</script>