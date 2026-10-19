<?php
require_once __DIR__ . '/../config/config.php';

// Aktif tab parametresi (beklenenler, getirilenler, reddedilenler)
$status_filter = $_GET['status'] ?? 'pending';
if (!in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $status_filter = 'pending';
}

// İstekleri Çek
$stmt = $pdo->prepare("
    SELECT r.*, u.username 
    FROM webtoon_requests r
    JOIN users u ON r.user_id = u.id
    WHERE r.status = :status
    ORDER BY r.created_at DESC
");
$stmt->execute(['status' => $status_filter]);
$requests = $stmt->fetchAll();

// Başlık ve Etiket Dilleri
$status_titles = [
    'pending'  => '⏳ Beklenen İstekler',
    'approved' => '✅ Getirilen Webtoonlar',
    'rejected' => '❌ Reddedilen İstekler'
];
?>

<!DOCTYPE html>
<html lang="<?= $current_lang ?>">
<head>
    <meta charset="UTF-8">
    <title>Webtoon İstek Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📚 Webtoon İstek Paneli</h2>
        <?php if (isset($_SESSION['user_id'])): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestModal">+ İstek Gönder</button>
        <?php else: ?>
            <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-warning">İstek göndermek için giriş yapın</a>
        <?php endif; ?>
    </div>

    <!-- Durum Sekmeleri (Tabs) -->
    <ul class="nav nav-pills mb-4 nav-justified bg-secondary p-2 rounded">
        <li class="nav-item">
            <a class="nav-link text-light <?= $status_filter === 'pending' ? 'active bg-warning text-dark fw-bold' : '' ?>" 
               href="index.php?status=pending">⏳ Beklenenler</a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-light <?= $status_filter === 'approved' ? 'active bg-success fw-bold' : '' ?>" 
               href="index.php?status=approved">✅ Getirilenler</a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-light <?= $status_filter === 'rejected' ? 'active bg-danger fw-bold' : '' ?>" 
               href="index.php?status=rejected">❌ Reddedilenler</a>
        </li>
    </ul>

    <!-- İstek Listesi -->
    <div class="row g-3">
        <?php if (empty($requests)): ?>
            <div class="col-12 text-center py-5 text-muted">
                <h5>Bu kategoride henüz bir istek bulunmuyor.</h5>
            </div>
        <?php else: ?>
            <?php foreach ($requests as $req): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card bg-secondary text-light h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-info"><?= htmlspecialchars($req['title']) ?></h5>
                            <p class="card-text text-light small"><?= nl2br(htmlspecialchars($req['description'] ?? 'Açıklama belirtilmedi.')) ?></p>
                            
                            <?php if (!empty($req['admin_note'])): ?>
                                <div class="alert alert-dark py-1 px-2 mt-2 small">
                                    <strong>Not:</strong> <?= htmlspecialchars($req['admin_note']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-dark text-muted d-flex justify-content-between align-items-center small">
                            <span>👤 <?= htmlspecialchars($req['username']) ?></span>
                            <span>📅 <?= date('d.m.Y', strtotime($req['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- İstek Gönderme Modalı -->
<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="process.php" method="POST" class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Yeni Webtoon İsteği</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Webtoon Adı *</label>
                    <input type="text" name="title" class="form-control bg-secondary text-light border-0" required placeholder="Örn: Solo Leveling">
                </div>
                <div class="mb-3">
                    <label class="form-label">Açıklama / Link (Opsiyonel)</label>
                    <textarea name="description" class="form-control bg-secondary text-light border-0" rows="3" placeholder="Seri hakkında kısa bilgi veya İngilizce kaynak linki..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Iptal</button>
                <button type="submit" name="submit_request" class="btn btn-primary">Gönder</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>