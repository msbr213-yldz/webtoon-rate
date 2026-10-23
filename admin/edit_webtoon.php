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

// Yardımcı fonksiyonları dahil et
require_once __DIR__ . '/../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . 'admin/manage_webtoons.php');
    exit;
}

$message = '';
$message_type = '';

// DÜZENLENECEK WEBTOONU ÇEK
$stmt = $pdo->prepare("SELECT * FROM webtoons WHERE id = :id");
$stmt->execute(['id' => $id]);
$webtoon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$webtoon) {
    header('Location: ' . BASE_URL . 'admin/manage_webtoons.php');
    exit;
}

// SEÇİLİ TÜRLERİ ÇEK
$stmt_g = $pdo->prepare("SELECT genre_id FROM webtoon_genres WHERE webtoon_id = :id");
$stmt_g->execute(['id' => $id]);
$current_genres = $stmt_g->fetchAll(PDO::FETCH_COLUMN);

// TÜM TÜRLERİ ÇEK
$all_genres = $pdo->query("SELECT * FROM genres ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// FORM GÖNDERİLDİĞİNDE (UPDATE İŞLEMİ)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status_input = trim($_POST['status'] ?? 'Devam Ediyor');
    $total_chapters = (int)($_POST['total_chapters'] ?? 0);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $selected_genres = $_POST['genres'] ?? [];

    // ESKİ DEĞERLERİ YEDEKLE (Bildirim kontrolleri için)
    $old_chapters = (int)$webtoon['total_chapters'];
    $old_status   = $webtoon['status'] ?? ''; // Veritabanındaki eski durum (ongoing, hiatus, completed, dropped)

    if (!empty($title)) {
        // Kapak görseli yükleme işlemi
        $cover_image = $webtoon['cover_image'];
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
            $new_name = 'cover_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $upload_path = __DIR__ . '/../uploads/' . $new_name;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_path)) {
                $cover_image = $new_name;
            }
        }

        // Status Verisini Uyumlu Hale Getirme
        $status = $status_input;
        if ($status_input === 'Devam Ediyor') {
            $status = 'ongoing';
        } elseif ($status_input === 'Ara Verildi') {
            $status = 'hiatus';
        } elseif ($status_input === 'Tamamlandı') {
            $status = 'completed';
        } elseif ($status_input === 'Yarıda Bırakıldı') {
            $status = 'dropped';
        }

        try {
            // Webtoon Tablosunu Güncelle
            $upd = $pdo->prepare("
                UPDATE webtoons 
                SET title = :title,
                    description = :description,
                    cover_image = :cover_image,
                    status = :status,
                    total_chapters = :total_chapters,
                    is_featured = :is_featured,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $upd->execute([
                'title' => $title,
                'description' => $description,
                'cover_image' => $cover_image,
                'status' => $status,
                'total_chapters' => $total_chapters,
                'is_featured' => $is_featured,
                'id' => $id
            ]);

            // Tür Bağlantılarını Güncelle
            $pdo->prepare("DELETE FROM webtoon_genres WHERE webtoon_id = :id")->execute(['id' => $id]);
            if (!empty($selected_genres)) {
                $ins_g = $pdo->prepare("INSERT INTO webtoon_genres (webtoon_id, genre_id) VALUES (:w_id, :g_id)");
                foreach ($selected_genres as $g_id) {
                    $ins_g->execute(['w_id' => $id, 'g_id' => (int)$g_id]);
                }
            }

            $notifications_sent = [];

            // 1. YENİ BÖLÜM BİLDİRİMİ TETİKLEME
            if ($total_chapters > $old_chapters) {
                if (function_exists('sendNewChapterNotification')) {
                    sendNewChapterNotification($pdo, $id, $total_chapters);
                    $notifications_sent[] = "Yeni bölüm bildirimi gönderildi!";
                }
            }

            // 2. DURUM DEĞİŞİKLİĞİ BİLDİRİMİ TETİKLEME
            if ($old_status !== $status) {
                if (function_exists('sendStatusChangeNotification')) {
                    sendStatusChangeNotification($pdo, $id, $old_status, $status);
                    $notifications_sent[] = "Durum değişikliği bildirimi takipçilere gönderildi!";
                }
            }

            // Başarı Mesajı
            if (!empty($notifications_sent)) {
                $message = "Webtoon güncellendi. " . implode(" ", $notifications_sent);
            } else {
                $message = "Webtoon bilgileri başarıyla güncellendi.";
            }
            $message_type = 'success';

            // Güncel veriyi tekrar çek
            $stmt->execute(['id' => $id]);
            $webtoon = $stmt->fetch(PDO::FETCH_ASSOC);

            // Güncel türleri tekrar çek
            $stmt_g->execute(['id' => $id]);
            $current_genres = $stmt_g->fetchAll(PDO::FETCH_COLUMN);

        } catch (PDOException $e) {
            $message = "Güncelleme hatası: " . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
        }
    } else {
        $message = "Lütfen başlık alanını boş bırakmayın.";
        $message_type = 'danger';
    }
}

// Dış Kaynak Arama Bağlantıları
$encoded_title = urlencode($webtoon['title']);
$anime_planet_url = "https://www.anime-planet.com/manga/all?name=" . $encoded_title;
$google_url = "https://www.google.com/search?q=" . $encoded_title . "+webtoon+raw+chapters";
$mangaupdates_url = "https://www.mangaupdates.com/series.html?search=" . $encoded_title;

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <!-- Üst Başlık ve Geri Dön Butonu -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h3 class="fw-bold text-body mb-0">
            <i class="bi bi-pencil-square text-primary me-2"></i> Webtoon Düzenle: <span class="text-primary"><?= htmlspecialchars($webtoon['title']) ?></span>
        </h3>
        <a href="<?= BASE_URL ?>admin/manage_webtoons.php" class="btn btn-outline-secondary rounded-pill btn-sm px-3">
            <i class="bi bi-arrow-left me-1"></i> Yönetim Paneline Dön
        </a>
    </div>

    <!-- HIZLI KONTROL LİNKLERİ -->
    <div class="card bg-body-tertiary border shadow-sm mb-4 rounded-4 p-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-body small fw-bold">
                <i class="bi bi-search text-info me-1"></i> Güncel Bölüm / Durum Kontrol Bağlantıları:
            </span>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= $anime_planet_url ?>" target="_blank" class="btn btn-sm btn-outline-warning rounded-pill">
                    <i class="bi bi-globe me-1"></i> Anime-Planet'te Ara
                </a>
                <a href="<?= $google_url ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill">
                    <i class="bi bi-google me-1"></i> Google'da Ara
                </a>
                <a href="<?= $mangaupdates_url ?>" target="_blank" class="btn btn-sm btn-outline-info rounded-pill">
                    <i class="bi bi-journal-text me-1"></i> MangaUpdates'te Ara
                </a>
            </div>
        </div>
    </div>

    <!-- Bildirim / Mesaj Alanı -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show mb-4 rounded-3" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Form Kartı -->
    <div class="card bg-body-tertiary border shadow-sm rounded-4 p-4 mb-5">
        <form method="POST" enctype="multipart/form-data">
            <div class="row g-4">
                
                <!-- Sol Taraf: Metin Bilgileri -->
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label text-body fw-bold">Webtoon Adı</label>
                        <input type="text" name="title" class="form-control theme-input" 
                               value="<?= htmlspecialchars($webtoon['title']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-body fw-bold">Açıklama</label>
                        <textarea name="description" class="form-control theme-input" rows="6"><?= htmlspecialchars($webtoon['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-body fw-bold">Durum</label>
                            <?php $curr_status = strtolower($webtoon['status'] ?? ''); ?>
                            <select name="status" class="form-select theme-input">
                                <option value="Devam Ediyor" <?= ($curr_status === 'ongoing' || strpos($curr_status, 'devam') !== false) ? 'selected' : '' ?>>Devam Ediyor</option>
                                <option value="Ara Verildi" <?= ($curr_status === 'hiatus' || strpos($curr_status, 'ara') !== false) ? 'selected' : '' ?>>Ara Verildi</option>
                                <option value="Tamamlandı" <?= ($curr_status === 'completed' || strpos($curr_status, 'tamam') !== false) ? 'selected' : '' ?>>Tamamlandı</option>
                                <option value="Yarıda Bırakıldı" <?= ($curr_status === 'dropped' || strpos($curr_status, 'yarıda') !== false) ? 'selected' : '' ?>>Yarıda Bırakıldı</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-body fw-bold">Toplam Bölüm Sayısı</label>
                            <input type="number" name="total_chapters" class="form-control theme-input" 
                                   value="<?= (int)$webtoon['total_chapters'] ?>" min="0">
                        </div>
                    </div>

                    <div class="mb-3 form-check form-switch pt-2">
                        <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" 
                               <?= !empty($webtoon['is_featured']) ? 'checked' : '' ?>>
                        <label class="form-check-label text-body fw-bold ms-2" for="is_featured">Öne Çıkarılanlar (Editörün Seçimi) Listesinde Göster</label>
                    </div>
                </div>

                <!-- Sağ Taraf: Kapak Resmi ve Tür Seçimi -->
                <div class="col-md-4 border-start border-secondary-subtle ps-md-4">
                    <div class="mb-4 text-center">
                        <label class="form-label text-body fw-bold d-block">Mevcut Kapak Görseli</label>
                        <?php 
                            $cover_img = $webtoon['cover_image'] ?? '';
                            if (!empty($cover_img)) {
                                $cover = (strpos($cover_img, 'http') === 0) ? $cover_img : BASE_URL . 'uploads/' . htmlspecialchars($cover_img);
                            } else {
                                $cover = 'https://via.placeholder.com/200x280?text=No+Cover';
                            }
                        ?>
                        <img src="<?= $cover ?>" class="img-fluid rounded border shadow-sm mb-3 mx-auto" style="max-height: 230px; object-fit: cover;" alt="Kapak Görseli">
                        <input type="file" name="cover_image" class="form-control form-control-sm theme-input" accept="image/*">
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-body fw-bold">Türler</label>
                        <div class="bg-body-secondary border p-3 rounded-3" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($all_genres as $g): ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="genres[]" value="<?= $g['id'] ?>" id="g_<?= $g['id'] ?>"
                                           <?= in_array($g['id'], $current_genres) ? 'checked' : '' ?>>
                                    <label class="form-check-label text-body small" for="g_<?= $g['id'] ?>">
                                        <?= htmlspecialchars($g['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 mt-3 fw-bold shadow-sm">
                        <i class="bi bi-save me-1"></i> Değişiklikleri Kaydet
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>

</body>
</html>