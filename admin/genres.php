<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sadece Admin Erişebilir
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "home.php");
    exit;
}

$message = '';
$message_type = 'danger';

// Tür Ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_genre'])) {
    $genre_name = trim($_POST['name'] ?? '');
    if (!empty($genre_name)) {
        $stmt_check = $pdo->prepare("SELECT id FROM genres WHERE name = ?");
        $stmt_check->execute([$genre_name]);
        if ($stmt_check->fetch()) {
            if (($current_lang ?? '') === 'en') {
                $message = 'This genre already exists.';
            } else {
                $message = 'Bu tür zaten mevcut.';
            }
        } else {
            $stmt_ins = $pdo->prepare("INSERT INTO genres (name) VALUES (?)");
            if ($stmt_ins->execute([$genre_name])) {
                if (($current_lang ?? '') === 'en') {
                    $_SESSION['flash_message'] = 'Genre added successfully.';
                } else {
                    $_SESSION['flash_message'] = 'Tür başarıyla eklendi.';
                }
                $_SESSION['flash_type'] = 'success';
                header("Location: " . BASE_URL . "admin/genres.php");
                exit;
            }
        }
    }
}

// Tür Silme
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    if ($delete_id > 0) {
        $pdo->prepare("DELETE FROM webtoon_genres WHERE genre_id = ?")->execute([$delete_id]);
        $pdo->prepare("DELETE FROM genres WHERE id = ?")->execute([$delete_id]);
        
        if (($current_lang ?? '') === 'en') {
            $_SESSION['flash_message'] = 'Genre deleted successfully.';
        } else {
            $_SESSION['flash_message'] = 'Tür başarıyla silindi.';
        }
        $_SESSION['flash_type'] = 'success';
        header("Location: " . BASE_URL . "admin/genres.php");
        exit;
    }
}

$message = $_SESSION['flash_message'] ?? $message;
$message_type = $_SESSION['flash_type'] ?? $message_type;
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Türleri Getir
$genres = $pdo->query("
    SELECT g.*, COUNT(wg.webtoon_id) as total_webtoons 
    FROM genres g 
    LEFT JOIN webtoon_genres wg ON g.id = wg.genre_id 
    GROUP BY g.id 
    ORDER BY g.name ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <h3 class="fw-bold text-body mb-0">
            <i class="bi bi-tags-fill text-warning me-2"></i><?= $lang['genre_title'] ?? 'Tür Yönetimi' ?>
        </h3>
        <a href="<?= BASE_URL ?>admin/manage_webtoons.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> <?= $lang['admin_manage_title'] ?? 'Webtoon Yönetimi' ?>
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show mb-4 rounded-3" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ÜST KISIM: Yeni Tür Ekle Formu -->
    <div class="card bg-body-tertiary border shadow-sm p-4 mb-4 rounded-4">
        <h5 class="fw-bold mb-3 text-body"><i class="bi bi-plus-lg text-primary me-2"></i> <?= $lang['genre_add_btn'] ?? 'Yeni Tür Ekle' ?></h5>
        <form action="genres.php" method="POST" class="row g-3 align-items-center">
            <input type="hidden" name="add_genre" value="1">
            <div class="col-md-9 col-lg-10">
                <input type="text" name="name" class="form-control theme-input" placeholder="<?= $lang['genre_name_ph'] ?? 'Yeni tür adı...' ?>" required>
            </div>
            <div class="col-md-3 col-lg-2">
                <button type="submit" class="btn btn-primary fw-bold w-100 rounded-pill shadow-sm">
                    <i class="bi bi-download me-1"></i> <?= $lang['genre_add_btn'] ?? 'Tür Ekle' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- ALT KISIM: Türler Listesi -->
    <div class="card bg-body-tertiary border shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-header bg-body-secondary fw-bold d-flex justify-content-between align-items-center border-bottom p-3">
            <span class="text-body"><i class="bi bi-list-task me-2 text-primary"></i> <?= $lang['all_genres'] ?? 'Tüm Türler' ?></span>
            <span class="badge bg-primary rounded-pill px-3"><?= count($genres) ?> Tür</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="table-active">
                            <th style="width: 80px;">#</th>
                            <th><?= $lang['table_genres'] ?? 'Türler' ?></th>
                            <th><?= $lang['all_webtoons'] ?? 'Tüm Webtoon Kataloğu' ?></th>
                            <th class="text-end" style="width: 150px;"><?= $lang['table_actions'] ?? 'İşlemler' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($genres)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-body-secondary">Henüz kayıtlı bir tür bulunmuyor.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($genres as $index => $g): ?>
                                <tr>
                                    <td><span class="text-body-secondary fw-bold"><?= $index + 1 ?></span></td>
                                    <td><strong class="text-body fs-6"><?= htmlspecialchars($g['name']) ?></strong></td>
                                    <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3"><?= (int)$g['total_webtoons'] ?> Webtoon</span></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>admin/genres.php?delete=<?= $g['id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('<?= $lang['confirm_delete'] ?? 'Bu türü silmek istediğinize emin misiniz?' ?>');">
                                            <i class="bi bi-trash me-1"></i> <?= $lang['btn_delete'] ?? 'Sil' ?>
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

<!-- BOOTSTRAP JS PAKETİ (Düzgün Çözüm: Alt Klasördeki Dropdown ve Tema Butonlarını Aktifleştirir) -->

</body>
</html>