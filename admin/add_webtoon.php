<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

// Admin Yetki Kontrolü
if (!isset($_SESSION['user_id']) || empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$error = '';
$success = '';

// Türleri Veritabanından Çek
$genres = [];
try {
    $stmt_genres = $pdo->query("SELECT * FROM genres ORDER BY name ASC");
    $genres = $stmt_genres->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $genres = [];
}

// Form Gönderildiğinde (Webtoon Ekleme)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cover_image = trim($_POST['cover_image'] ?? '');
    $status = trim($_POST['status'] ?? 'Devam Ediyor');
    $current_chapter = (int)($_POST['current_chapter'] ?? 1);
    $total_chapters = (int)($_POST['total_chapters'] ?? 0);
    $selected_genres = $_POST['genres'] ?? [];

    if (empty($title)) {
        $error = 'Lütfen webtoon başlığını girin.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Webtoon Tablosuna Ekle
            $stmt = $pdo->prepare("
                INSERT INTO webtoons (title, description, cover_image, status, current_chapter, total_chapters, created_at, updated_at) 
                VALUES (:title, :description, :cover_image, :status, :current_chapter, :total_chapters, NOW(), NOW())
            ");
            $stmt->execute([
                'title' => $title,
                'description' => $description,
                'cover_image' => $cover_image,
                'status' => $status,
                'current_chapter' => $current_chapter,
                'total_chapters' => $total_chapters
            ]);

            $webtoon_id = $pdo->lastInsertId();

            // 2. Tür Eşleştirmelerini Ekle (webtoon_genres)
            if (!empty($selected_genres) && is_array($selected_genres)) {
                $stmt_genre = $pdo->prepare("INSERT INTO webtoon_genres (webtoon_id, genre_id) VALUES (:w_id, :g_id)");
                foreach ($selected_genres as $g_id) {
                    $stmt_genre->execute([
                        'w_id' => $webtoon_id,
                        'g_id' => (int)$g_id
                    ]);
                }
            }

            $pdo->commit();
            $success = 'Webtoon başarıyla eklendi!';

            // Form alanlarını sıfırla
            $_POST = [];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <!-- Üst Başlık ve Yönlendirme -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <h3 class="fw-bold text-body mb-0">
            <i class="bi bi-plus-circle-fill text-primary me-2"></i>Yeni Webtoon Ekle
        </h3>
        <a href="<?= BASE_URL ?>admin/manage_webtoons.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Webtoon Yönetimi
        </a>
    </div>

    <!-- Bildirim Mesajları -->
    <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 mb-4 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success rounded-3 mb-4 d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= htmlspecialchars($success) ?></div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Sol Taraf: Ekleme Formu -->
        <div class="col-lg-8">
            <div class="card bg-body-tertiary border shadow-sm rounded-4 p-4">
                <form action="" method="POST">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold text-body">Webtoon Başlığı</label>
                        <input type="text" class="form-control theme-input" id="title" name="title" 
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="Örn: Solo Leveling" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold text-body">Açıklama / Özet</label>
                        <textarea class="form-control theme-input" id="description" name="description" rows="5" 
                                  placeholder="Webtoon konusunu kısaca yazın..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="cover_image" class="form-label fw-bold text-body">Kapak Görseli URL</label>
                        <input type="text" class="form-control theme-input" id="cover_image" name="cover_image" 
                               value="<?= htmlspecialchars($_POST['cover_image'] ?? '') ?>" placeholder="https://image-url.com/cover.jpg veya dosya adı">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="status" class="form-label fw-bold text-body">Yayın Durumu</label>
                            <select class="form-select theme-input" id="status" name="status">
                                <option value="Devam Ediyor" <?= (($_POST['status'] ?? '') === 'Devam Ediyor') ? 'selected' : '' ?>>Devam Ediyor</option>
                                <option value="Ara Verdi" <?= (($_POST['status'] ?? '') === 'Ara Verdi') ? 'selected' : '' ?>>Ara Verdi</option>
                                <option value="Bitti" <?= (($_POST['status'] ?? '') === 'Bitti') ? 'selected' : '' ?>>Bitti</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="current_chapter" class="form-label fw-bold text-body">Şu Anki Bölüm</label>
                            <input type="number" class="form-control theme-input" id="current_chapter" name="current_chapter" 
                                   value="<?= (int)($_POST['current_chapter'] ?? 1) ?>" min="0">
                        </div>
                        <div class="col-md-4">
                            <label for="total_chapters" class="form-label fw-bold text-body">Toplam Bölüm</label>
                            <input type="number" class="form-control theme-input" id="total_chapters" name="total_chapters" 
                                   value="<?= (int)($_POST['total_chapters'] ?? 0) ?>" min="0">
                        </div>
                    </div>

                    <!-- Türler -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-body mb-2">Türler</label>
                        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-2 p-3 bg-body-secondary rounded-3 border">
                            <?php foreach ($genres as $g): ?>
                                <?php $checked = isset($_POST['genres']) && in_array($g['id'], $_POST['genres']) ? 'checked' : ''; ?>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="genres[]" value="<?= $g['id'] ?>" id="genre_<?= $g['id'] ?>" <?= $checked ?>>
                                        <label class="form-check-label text-body small" for="genre_<?= $g['id'] ?>">
                                            <?= htmlspecialchars($g['name']) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="manage_webtoons.php" class="btn btn-outline-secondary px-4 rounded-pill">İptal</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm">
                            <i class="bi bi-check-lg me-1"></i> Kaydet ve Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sağ Taraf: Canlı Araştırma Paneli -->
        <div class="col-lg-4">
            <div class="card bg-body-tertiary border border-warning-subtle shadow-sm rounded-4 p-4 sticky-top" style="top: 90px;">
                <h5 class="fw-bold text-warning mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-search"></i> Canlı Araştırma Paneli
                </h5>
                <p class="text-body-secondary small mb-3">
                    Bölüm sayısı, kapak görseli veya detay araştırmak için dış kaynaklarda hızlıca aratın:
                </p>

                <div class="d-grid gap-2">
                    <button type="button" onclick="searchExternal('google')" class="btn btn-outline-danger btn-sm text-start d-flex align-items-center gap-2 p-2">
                        <i class="bi bi-google fs-5"></i> Google'da Ara
                    </button>
                    <button type="button" onclick="searchExternal('mangaupdates')" class="btn btn-outline-info btn-sm text-start d-flex align-items-center gap-2 p-2">
                        <i class="bi bi-book fs-5"></i> MangaUpdates'te Ara
                    </button>
                    <button type="button" onclick="searchExternal('animeplanet')" class="btn btn-outline-success btn-sm text-start d-flex align-items-center gap-2 p-2">
                        <i class="bi bi-globe fs-5"></i> Anime-Planet'te Ara
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function searchExternal(platform) {
    const title = document.getElementById('title').value.trim();
    if (!title) {
        alert('Lütfen önce Webtoon Başlığı alanını doldurun.');
        return;
    }

    let url = '';
    const query = encodeURIComponent(title + ' webtoon');

    if (platform === 'google') {
        url = 'https://www.google.com/search?q=' + query;
    } else if (platform === 'mangaupdates') {
        url = 'https://www.mangaupdates.com/series.html?search=' + encodeURIComponent(title);
    } else if (platform === 'animeplanet') {
        url = 'https://www.anime-planet.com/manga/all?name=' + encodeURIComponent(title);
    }

    if (url) {
        window.open(url, '_blank');
    }
}
</script>

<!-- Bootstrap JS Paket Dosyası -->

</body>
</html>