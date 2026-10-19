<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$message = '';
$message_type = '';

// Durum Güncelleme Veya Silme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    
    if (isset($_POST['update_status'])) {
        $status = $_POST['status'] ?? 'pending';
        $admin_note = trim($_POST['admin_note'] ?? '');

        if (in_array($status, ['pending', 'approved', 'rejected']) && $request_id > 0) {
            
            // 1. Önce mevcut isteği ve istek yapan kullanıcı bilgisini al
            $stmt = $pdo->prepare("SELECT user_id, title FROM webtoon_requests WHERE id = :id");
            $stmt->execute(['id' => $request_id]);
            $req_data = $stmt->fetch();

            if ($req_data) {
                // 2. İstek durumunu güncelle
                $stmt = $pdo->prepare("UPDATE webtoon_requests SET status = :status, admin_note = :admin_note WHERE id = :id");
                $stmt->execute([
                    'status'     => $status,
                    'admin_note' => $admin_note,
                    'id'         => $request_id
                ]);

                // 3. Kullanıcıya Gönderilecek Bildirim Mesajını Oluştur
                $notification_msg = '';
                $webtoon_title = htmlspecialchars($req_data['title']);

                if ($status === 'approved') {
                    $notification_msg = "🎉 Tebrikler! '{$webtoon_title}' adlı webtoon isteğiniz kabul edildi ve siteye eklendi.";
                } elseif ($status === 'rejected') {
                    $notification_msg = "❌ '{$webtoon_title}' adlı webtoon isteğiniz reddedildi.";
                }

                // Eğer admin notu varsa bildirime ekle
                if (!empty($admin_note) && !empty($notification_msg)) {
                    $notification_msg .= " (Admin Notu: " . htmlspecialchars($admin_note) . ")";
                }

                // 4. Bildirimi Veritabanına Kaydet (Sadece onaylandı veya reddedildiyse)
                if (!empty($notification_msg)) {
                    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
                    $notif_stmt->execute([
                        'user_id' => $req_data['user_id'],
                        'message' => $notification_msg
                    ]);
                }

                $message = 'İstek durumu güncellendi ve kullanıcıya bildirim gönderildi!';
                $message_type = 'success';
            }
        }
    }
    
    if (isset($_POST['delete_request'])) {
        if ($request_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM webtoon_requests WHERE id = :id");
            $stmt->execute(['id' => $request_id]);
            $message = 'İstek silindi.';
            $message_type = 'warning';
        }
    }
}

// Filtreleme
$filter = $_GET['filter'] ?? 'all';
$query = "
    SELECT r.*, u.username 
    FROM webtoon_requests r
    JOIN users u ON r.user_id = u.id
";

if (in_array($filter, ['pending', 'approved', 'rejected'])) {
    $query .= " WHERE r.status = " . $pdo->quote($filter);
}

$query .= " ORDER BY r.created_at DESC";
$requests = $pdo->query($query)->fetchAll();
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🛠️ Admin - Webtoon İstek Yönetimi</h2>
        <a href="<?= BASE_URL ?>requests/index.php" class="btn btn-outline-info" target="_blank">Canlı Sayfayı Gör ↗</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtre Butonları -->
    <div class="mb-4 btn-group">
        <a href="requests.php?filter=all" class="btn btn-<?= $filter === 'all' ? 'light' : 'outline-light' ?>">Tümü</a>
        <a href="requests.php?filter=pending" class="btn btn-<?= $filter === 'pending' ? 'warning' : 'outline-warning' ?>">⏳ Bekleyenler</a>
        <a href="requests.php?filter=approved" class="btn btn-<?= $filter === 'approved' ? 'success' : 'outline-success' ?>">✅ Getirilenler</a>
        <a href="requests.php?filter=rejected" class="btn btn-<?= $filter === 'rejected' ? 'danger' : 'outline-danger' ?>">❌ Reddedilenler</a>
    </div>

    <!-- İstekler Tablosu -->
    <div class="table-responsive bg-secondary rounded p-3">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Kullanıcı</th>
                    <th>Webtoon Adı</th>
                    <th>Açıklama</th>
                    <th>Durum</th>
                    <th>Admin Notu</th>
                    <th>Tarih</th>
                    <th class="text-end">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">Kayıtlı istek bulunamadı.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                        <tr>
                            <td><?= $req['id'] ?></td>
                            <td>
                                <!-- Kullanıcı Profil Linki -->
                                <a href="<?= BASE_URL ?>profile/profile.php?id=<?= $req['user_id'] ?>" class="text-decoration-none text-info fw-bold">
                                    👤 <?= htmlspecialchars($req['username']) ?>
                                </a>
                            </td>
                            <td class="text-warning font-monospace"><?= htmlspecialchars($req['title']) ?></td>
                            <td style="max-width: 250px;"><?= nl2br(htmlspecialchars($req['description'] ?? '-')) ?></td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Bekliyor</span>
                                <?php elseif ($req['status'] === 'approved'): ?>
                                    <span class="badge bg-success">Getirildi</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Reddedildi</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-warning"><?= htmlspecialchars($req['admin_note'] ?? '-') ?></small></td>
                            <td><small><?= date('d.m.Y H:i', strtotime($req['created_at'])) ?></small></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-primary me-1" 
                                        onclick="openEditModal(<?= $req['id'] ?>, '<?= addslashes(htmlspecialchars($req['title'])) ?>', '<?= $req['status'] ?>', '<?= addslashes(htmlspecialchars($req['admin_note'] ?? '')) ?>')">
                                    Düzenle
                                </button>
                                
                                <form action="requests.php" method="POST" class="d-inline" onsubmit="return confirm('Bu isteği silmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                    <button type="submit" name="delete_request" class="btn btn-sm btn-danger">Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- GLOBAL DÜZENLEME MODALI -->
<div class="modal fade" id="globalEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="requests.php" method="POST" class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="modalTitle">İstek Güncelle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="request_id" id="modalRequestId">
                
                <div class="mb-3">
                    <label class="form-label">Durum Seçin</label>
                    <select name="status" id="modalStatus" class="form-select bg-secondary text-light border-0">
                        <option value="pending">⏳ Bekleyenler</option>
                        <option value="approved">✅ Getirilenler (Onayla)</option>
                        <option value="rejected">❌ Reddedilenler</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Admin Notu (Opsiyonel)</label>
                    <textarea name="admin_note" id="modalAdminNote" class="form-control bg-secondary text-light border-0" rows="3" placeholder="Örn: Haftaya eklenecek veya Telif nedeniyle reddedildi..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                <button type="submit" name="update_status" class="btn btn-success">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEditModal(id, title, status, note) {
    document.getElementById('modalRequestId').value = id;
    document.getElementById('modalTitle').innerText = 'İstek Güncelle: ' + title;
    document.getElementById('modalStatus').value = status;
    document.getElementById('modalAdminNote').value = note;

    var editModal = new bootstrap.Modal(document.getElementById('globalEditModal'));
    editModal.show();
}
</script>
</body>
</html>