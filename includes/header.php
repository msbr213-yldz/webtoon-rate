<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

$unread_notifications = [];
$unread_count = 0;

if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    $current_u_id = (int)$_SESSION['user_id'];
    
    try {
        $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :u_id AND is_read = 0");
        $cnt_stmt->execute(['u_id' => $current_u_id]);
        $unread_count = (int)$cnt_stmt->fetchColumn();

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
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* ---------------- DARK MODE (Karanlık Tema) ---------------- */
        [data-bs-theme="dark"] body {
            background-color: #0f1115; /* Daha koyu arka plan */
            color: #e0e0e0;
        }

        [data-bs-theme="dark"] .navbar-custom {
            background-color: #1a1d24;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        [data-bs-theme="dark"] .slider-section-card {
            background-color: #16181f;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            border-radius: 1.25rem !important; /* Daha oval ana kutu */
        }

        [data-bs-theme="dark"] .theme-card,
        [data-bs-theme="dark"] .slider-card {
            background-color: #21252d !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
        }

        /* ---------------- LIGHT MODE (Aydınlık Tema) ---------------- */
        [data-bs-theme="light"] body {
            background-color: #f8f9fa; /* Saf açık gri/beyaz */
            color: #212529;
        }

        [data-bs-theme="light"] .navbar-custom {
            background-color: #ffffff;
            border-bottom: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        [data-bs-theme="light"] .slider-section-card {
            background-color: #ffffff;
            border: 1px solid #e9ecef;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
            border-radius: 1.25rem !important;
        }

        [data-bs-theme="light"] .theme-card,
        [data-bs-theme="light"] .slider-card {
            background-color: #ffffff !important;
            border: 1px solid #dee2e6 !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06) !important;
        }

        [data-bs-theme="light"] .navbar-brand,
        [data-bs-theme="light"] .nav-link:not(.text-warning) {
            color: #212529 !important;
        }
        
        [data-bs-theme="light"] .theme-input {
            background-color: #ffffff !important;
            color: #212529 !important;
            border-color: #ced4da !important;
        }

        [data-bs-theme="dark"] .theme-input {
            background-color: #1a1d24 !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        /* ---------------- ORTAK KART & SLIDER STİLLERİ ---------------- */
        .theme-card, .slider-card {
            border-radius: 1rem !important; /* Tüm kartlara oval köşe */
            overflow: hidden; /* Taşmaları engelle */
        }

        .slider-card {
            flex: 0 0 auto;
            width: 175px; 
        }

        .slider-card img {
            width: 100% !important;
            height: 240px !important;
            object-fit: cover !important;
            display: block;
        }

        .catalog-card-img {
            width: 100% !important;
            height: 280px !important;
            object-fit: cover !important;
            display: block;
        }

        .clickable-card {
            cursor: pointer;
            text-decoration: none !important;
            transition: transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 0.25s ease;
        }
        .clickable-card:hover {
            transform: translateY(-6px);
        }
        
        [data-bs-theme="dark"] .clickable-card:hover {
            box-shadow: 0 10px 20px rgba(13, 110, 253, 0.2) !important;
            border-color: rgba(13, 110, 253, 0.5) !important;
        }
        
        [data-bs-theme="light"] .clickable-card:hover {
            box-shadow: 0 10px 20px rgba(13, 110, 253, 0.15) !important;
            border-color: #0d6efd !important;
        }

        /* Menü Dropdown */
        .notification-dropdown { width: 340px; }
        .notification-scroll-area { max-height: 280px; overflow-y: auto; }
        .notification-item { transition: background-color 0.15s ease; text-decoration: none !important; }
        .notification-item:hover { background-color: rgba(13, 110, 253, 0.08) !important; }
        .notification-item.unread { background-color: rgba(13, 110, 253, 0.1); border-left: 3px solid #0d6efd; }

        .nav-btn-icon {
            width: 40px;
            height: 40px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* ---------------- MOBİL EKRAN İNCE AYARLARI ---------------- */
        @media (max-width: 576px) {
            .slider-card { width: 145px !important; }
            .slider-card img { height: 200px !important; }
            .catalog-card-img { height: 210px !important; }
            
            .slider-section-card {
                padding: 1rem 0.5rem !important;
                border-radius: 0.75rem !important;
                border-left: none;
                border-right: none;
            }
            .slider-card .card-body { padding: 0.5rem !important; }
        }
    </style>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
</head>
<body class="d-flex flex-column min-vh-100">

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-custom sticky-top mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= BASE_URL ?>home.php">
            <i class="bi bi-book-half text-primary fs-4"></i> Webtoon Tracker
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Navigasyon">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>home.php">Ana Sayfa</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>forum/forum.php">
                        <i class="bi bi-chat-square-text me-1"></i> Forum
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>requests/index.php">
                        <i class="bi bi-inbox me-1"></i> İstekler
                    </a>
                </li>

                <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-warning fw-bold" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-shield-lock-fill me-1"></i> Admin Paneli
                        </a>
                        <ul class="dropdown-menu shadow" aria-labelledby="adminDropdown">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/manage_webtoons.php"><i class="bi bi-journal-text me-2 text-primary"></i> Webtoon Yönetimi</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/add_webtoon.php"><i class="bi bi-plus-circle me-2 text-success"></i> Yeni Webtoon Ekle</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/genres.php"><i class="bi bi-tags me-2 text-info"></i> Tür Yönetimi</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/requests.php"><i class="bi bi-inbox-fill me-2 text-warning"></i> İstek Yönetimi</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
                <button class="btn btn-outline-secondary rounded-circle nav-btn-icon border-0" id="themeToggleBtn" type="button" title="Temayı Değiştir">
                    <i class="bi bi-moon-stars-fill fs-5" id="themeIcon"></i>
                </button>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary position-relative rounded-circle nav-btn-icon border-0" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell-fill fs-5"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                                    <?= $unread_count > 9 ? '9+' : $unread_count ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg p-0 notification-dropdown" aria-labelledby="notifDropdown">
                            <li class="p-2 border-bottom d-flex justify-content-between align-items-center">
                                <span class="fw-bold px-2"><i class="bi bi-bell me-1 text-primary"></i> Bildirimler</span>
                                <?php if ($unread_count > 0): ?>
                                    <a href="<?= BASE_URL ?>notifications/mark_notifications_read.php" class="text-primary text-decoration-none small px-2">Okundu İşaretle</a>
                                <?php endif; ?>
                            </li>
                            <li>
                                <div class="notification-scroll-area p-2">
                                    <?php if (empty($unread_notifications)): ?>
                                        <div class="text-center py-4 text-muted small"><i class="bi bi-inbox fs-3 d-block mb-1"></i>Bildiriminiz yok.</div>
                                    <?php else: ?>
                                        <?php foreach ($unread_notifications as $notif): ?>
                                            <?php $is_unread = ((int)($notif['is_read'] ?? 0) === 0); ?>
                                            <a href="<?= BASE_URL ?>notifications/notification.php?redirect_id=<?= $notif['id'] ?>" class="dropdown-item d-flex align-items-center gap-2 p-2 rounded mb-1 notification-item <?= $is_unread ? 'unread' : '' ?>">
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <small class="text-body d-block text-truncate" style="font-size: 0.8rem;"><?= htmlspecialchars($notif['message'] ?? '') ?></small>
                                                    <small class="text-muted" style="font-size: 0.65rem;"><?= date('d.m.Y H:i', strtotime($notif['created_at'])) ?></small>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <li class="border-top p-1">
                                <a class="dropdown-item text-center fw-bold text-primary py-2 rounded" href="<?= BASE_URL ?>notifications/notification.php"><i class="bi bi-list-ul me-1"></i> Tümünü Gör</a>
                            </li>
                        </ul>
                    </div>

                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle rounded-pill px-3" type="button" id="userProfileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Kullanıcı') ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userProfileDropdown">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>profile/profile.php"><i class="bi bi-person me-2"></i> Profilim</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>auth/change_password.php"><i class="bi bi-key me-2"></i> Şifre Değiştir</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Çıkış Yap</a></li>
                        </ul>
                    </div>

                <?php else: ?>
                    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-primary rounded-pill px-3">Giriş</a>
                    <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-primary rounded-pill px-3">Kayıt Ol</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

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