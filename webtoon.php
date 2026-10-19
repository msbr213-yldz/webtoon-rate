<?php
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$webtoon_id = (int)($_GET['id'] ?? 0);

if ($webtoon_id <= 0) {
    header('Location: ' . BASE_URL . 'home.php');
    exit;
}

$message = '';
$message_type = '';

// 1. TAKİP ETME / TAKİPTEN ÇIKMA İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_follow') {
    if ($user_id > 0) {
        $chk = $pdo->prepare("SELECT id FROM user_follows WHERE user_id = :u_id AND webtoon_id = :w_id");
        $chk->execute(['u_id' => $user_id, 'w_id' => $webtoon_id]);
        if ($chk->fetch()) {
            $del = $pdo->prepare("DELETE FROM user_follows WHERE user_id = :u_id AND webtoon_id = :w_id");
            $del->execute(['u_id' => $user_id, 'w_id' => $webtoon_id]);
            $message = "Seri takipten çıkarıldı.";
        } else {
            $ins = $pdo->prepare("INSERT INTO user_follows (user_id, webtoon_id) VALUES (:u_id, :w_id)");
            $ins->execute(['u_id' => $user_id, 'w_id' => $webtoon_id]);
            $message = "Seri takibe alındı!";
        }
        $message_type = 'info';
    }
}

// 2. KALDIĞIN BÖLÜMÜ GÜNCELLEME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_progress') {
    if ($user_id > 0) {
        $last_ch = (int)($_POST['chapter_number'] ?? 0);

        $chk = $pdo->prepare("SELECT id FROM chapter_ratings WHERE user_id = :u_id AND webtoon_id = :w_id");
        $chk->execute(['u_id' => $user_id, 'w_id' => $webtoon_id]);
        $row = $chk->fetch();

        if ($row) {
            $upd = $pdo->prepare("UPDATE chapter_ratings SET chapter_number = :ch WHERE id = :id");
            $upd->execute(['ch' => $last_ch, 'id' => $row['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO chapter_ratings (user_id, webtoon_id, chapter_number) VALUES (:u_id, :w_id, :ch)");
            $ins->execute(['u_id' => $user_id, 'w_id' => $webtoon_id, 'ch' => $last_ch]);
        }

        $message = "Kaldığın bölüm başarıyla kaydedildi!";
        $message_type = 'success';
    }
}

// 3. GENEL WEBTOON PUANI VERME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rate_webtoon') {
    if ($user_id > 0) {
        $rating = (int)($_POST['webtoon_rating'] ?? 0);
        if ($rating >= 1 && $rating <= 10) {
            
            $chk_cr = $pdo->prepare("SELECT id FROM chapter_ratings WHERE user_id = :u_id AND webtoon_id = :w_id");
            $chk_cr->execute(['u_id' => $user_id, 'w_id' => $webtoon_id]);
            if ($chk_cr->fetch()) {
                $upd_cr = $pdo->prepare("UPDATE chapter_ratings SET rating = :rt WHERE user_id = :u_id AND webtoon_id = :w_id");
                $upd_cr->execute(['rt' => $rating, 'u_id' => $user_id, 'w_id' => $webtoon_id]);
            } else {
                $ins_cr = $pdo->prepare("INSERT INTO chapter_ratings (user_id, webtoon_id, chapter_number, rating) VALUES (:u_id, :w_id, 0, :rt)");
                $ins_cr->execute(['u_id' => $user_id, 'w_id' => $webtoon_id, 'rt' => $rating]);
            }

            try {
                $rate_stmt = $pdo->prepare("
                    INSERT INTO webtoon_ratings (user_id, webtoon_id, rating) 
                    VALUES (:u_id, :w_id, :rt1)
                    ON DUPLICATE KEY UPDATE rating = :rt2
                ");
                $rate_stmt->execute(['u_id' => $user_id, 'w_id' => $webtoon_id, 'rt1' => $rating, 'rt2' => $rating]);
            } catch (PDOException $e) {
                // Tablo yoksa yoksay
            }

            $message = "Puanınız kaydedildi!";
            $message_type = 'success';
        }
    }
}

// 4. YORUM EKLEME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    if ($user_id > 0) {
        $comment_text = trim($_POST['comment'] ?? '');
        if (!empty($comment_text)) {
            try {
                $ins_c = $pdo->prepare("INSERT INTO webtoon_comments (webtoon_id, user_id, comment) VALUES (:w_id, :u_id, :cmt)");
                $ins_c->execute(['w_id' => $webtoon_id, 'u_id' => $user_id, 'cmt' => $comment_text]);
                $message = "Yorumun başarıyla yayınlandı!";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = "Yorum kaydedilirken bir hata oluştu.";
                $message_type = 'danger';
            }
        } else {
            $message = "Lütfen boş yorum göndermeyin.";
            $message_type = 'warning';
        }
    }
}

// DETAYLI WEBTOON VE ORTALAMA PUAN BİLGİSİNİ ÇEK
$stmt = $pdo->prepare("
    SELECT w.*, 
           GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') as genre_names,
           COALESCE(AVG(cr.rating), 0) as avg_rating,
           COUNT(DISTINCT cr.id) as total_raters
    FROM webtoons w
    LEFT JOIN webtoon_genres wg ON w.id = wg.webtoon_id
    LEFT JOIN genres g ON wg.genre_id = g.id
    LEFT JOIN chapter_ratings cr ON w.id = cr.webtoon_id AND cr.rating > 0
    WHERE w.id = :id
    GROUP BY w.id
");
$stmt->execute(['id' => $webtoon_id]);
$webtoon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$webtoon) {
    header('Location: ' . BASE_URL . 'home.php');
    exit;
}

// TAKİP BİLGİSİ
$is_following = false;
if ($user_id > 0) {
    $f_stmt = $pdo->prepare("SELECT id FROM user_follows WHERE user_id = :u_id AND webtoon_id = :w_id");
    $f_stmt->execute(['u_id' => $user_id, 'w_id' => $webtoon_id]);
    $is_following = (bool)$f_stmt->fetch();
}

// KALDIĞI BÖLÜM BİLGİSİ
$user_chapter = 0;
if ($user_id > 0) {
    $p_stmt = $pdo->prepare("SELECT chapter_number FROM chapter_ratings WHERE user_id = :u_id AND webtoon_id = :w_id");
    $p_stmt->execute(['u_id' => $user_id, 'w_id' => $webtoon_id]);
    $user_chapter = (int)$p_stmt->fetchColumn();
}

// KULLANICININ VERDİĞİ PUAN
$user_rating = 0;
if ($user_id > 0) {
    $r_stmt = $pdo->prepare("SELECT rating FROM chapter_ratings WHERE user_id = :u_id AND webtoon_id = :w_id");
    $r_stmt->execute(['u_id' => $user_id, 'w_id' => $webtoon_id]);
    $user_rating = (int)$r_stmt->fetchColumn();
}

// YORUMLARI LIKE/DISLIKE SAYILARI VE KULLANICI ID'Sİ İLE ÇEK
$comments = [];
try {
    $c_stmt = $pdo->prepare("
        SELECT wc.*, u.id as comment_user_id, u.username, u.role,
               SUM(CASE WHEN cr.type = 'like' THEN 1 ELSE 0 END) as like_count,
               SUM(CASE WHEN cr.type = 'dislike' THEN 1 ELSE 0 END) as dislike_count,
               (SELECT type FROM comment_reactions WHERE comment_id = wc.id AND user_id = :u_id) as user_reaction
        FROM webtoon_comments wc
        JOIN users u ON wc.user_id = u.id
        LEFT JOIN comment_reactions cr ON wc.id = cr.comment_id
        WHERE wc.webtoon_id = :w_id
        GROUP BY wc.id
        ORDER BY wc.created_at DESC
    ");
    $c_stmt->execute(['w_id' => $webtoon_id, 'u_id' => $user_id]);
    $comments = $c_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $comments = [];
}

// TÜM ROZETLERİ ÇEK (Ödül Modal Yapısı İçin)
$all_badges = [];
try {
    $b_all = $pdo->query("SELECT * FROM badges ORDER BY id ASC");
    $all_badges =$b_all->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {$all_badges = [];
}

$total_chapters = (int)($webtoon['total_chapters'] ?? 0);

// KAPAK GÖRSELİ YOLU HESAPLAMA
$c_img = trim($webtoon['cover_image'] ?? '');
if (!empty($c_img)) {
    if (strpos($c_img, 'http://') === 0 || strpos($c_img, 'https://') === 0) {
        $cover =$c_img;
    } else {
        $clean_path = ltrim($c_img, '/');
        if (strpos($clean_path, 'uploads/') !== 0) {
            $clean_path = 'uploads/' .$clean_path;
        }
        $cover = rtrim(BASE_URL, '/') . '/' .$clean_path;
    }
} else {
    $cover = 'https://via.placeholder.com/300x400?text=Kapak+Yok';
}

include __DIR__ . '/includes/header.php';
?>

<div class="container my-4">

    <!-- 1. ARAMA ÇUBUĞU -->
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

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ÜST BİLGİ KARTI -->
    <div class="card bg-body-tertiary border shadow-sm p-4 mb-4 rounded-4">
        <div class="row g-4">
            <!-- Kapak Görseli -->
            <div class="col-md-3 text-center text-md-start">
                <img src="<?= htmlspecialchars($cover) ?>" 
                     class="img-fluid rounded border shadow-sm" 
                     alt="<?= htmlspecialchars($webtoon['title']) ?>"
                     style="max-height: 380px; width: 100%; object-fit: cover; aspect-ratio: 3/4;">
            </div>

            <!-- Detaylar -->
            <div class="col-md-9 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                        <h2 class="fw-bold text-body mb-0"><?= htmlspecialchars($webtoon['title']) ?></h2>
                        
                        <?php if ($user_id > 0): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="toggle_follow">
                                <button type="submit" class="btn <?= $is_following ? 'btn-danger' : 'btn-outline-primary' ?> rounded-pill px-3">
                                    <i class="bi <?= $is_following ? 'bi-bookmark-x-fill' : 'bi-bookmark-plus' ?> me-1"></i>
                                    <?= $is_following ? 'Takipten Çık' : 'Takip Et' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <p class="text-body-secondary mb-2">
                        <i class="bi bi-tags me-1"></i> <?= htmlspecialchars($webtoon['genre_names'] ?? '-') ?>
                    </p>

                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-success fs-6"><?= htmlspecialchars($webtoon['status'] ?? 'Devam Ediyor') ?></span>
                        <span class="badge bg-secondary fs-6">Toplam Bölüm: <?= $total_chapters ?></span>
                        
                        <!-- Genel Ortalama Puan -->
                        <span class="badge bg-warning text-dark fs-6 fw-bold">
                            ⭐ <?= number_format((float)($webtoon['avg_rating'] ?? 0), 1) ?> / 10
                        </span>
                    </div>

                    <p class="text-body mb-4" style="line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($webtoon['description'] ?? 'Açıklama bulunmuyor.')) ?>
                    </p>
                </div>

                <!-- OKUMA DURUMU & PUAN VERME KUTULARI -->
                <?php if ($user_id > 0): ?>
                    <div class="row g-3">
                        <!-- 1. Okuma İlerlemesi -->
                        <div class="col-md-6">
                            <div class="card bg-body-secondary border p-3 h-100 rounded-3">
                                <h6 class="fw-bold text-body mb-2">
                                    <i class="bi bi-book-half text-info me-1"></i> Okuma İlerlemesi
                                </h6>
                                <form method="POST" class="d-flex gap-2 align-items-end">
                                    <input type="hidden" name="action" value="update_progress">
                                    <div class="flex-grow-1">
                                        <label class="text-body-secondary small d-block mb-1">Kaldığın Bölümü Seç</label>
                                        <select name="chapter_number" class="form-select border">
                                            <option value="0" <?= $user_chapter === 0 ? 'selected' : '' ?>>0 - Henüz Başlanmadı</option>
                                            <?php 
                                                $max_select = max($total_chapters,$user_chapter, 50); 
                                                for ($i = 1; $i <= $max_select; $i++): 
                                            ?>
                                                <option value="<?= $i ?>" <?= ($user_chapter ===$i) ? 'selected' : '' ?>>
                                                    <?= $i ?>. Bölüm <?= ($i === $total_chapters &&$total_chapters > 0) ? '(Son Bölüm)' : '' ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary fw-bold px-3">Kaydet</button>
                                </form>
                            </div>
                        </div>

                        <!-- 2. Webtoona Puan Ver -->
                        <div class="col-md-6">
                            <div class="card bg-body-secondary border p-3 h-100 rounded-3">
                                <h6 class="fw-bold text-body mb-2"><i class="bi bi-star-fill text-warning me-1"></i> Webtoona Puan Ver</h6>
                                <form method="POST" class="d-flex gap-2 align-items-end">
                                    <input type="hidden" name="action" value="rate_webtoon">
                                    <div class="flex-grow-1">
                                        <label class="text-body-secondary small d-block mb-1">Genel Puanın (1-10)</label>
                                        <select name="webtoon_rating" class="form-select border">
                                            <option value="0">Puan Seç...</option>
                                            <?php for ($i = 10; $i >= 1; $i--): ?>
                                                <option value="<?= $i ?>" <?= ($user_rating ===$i) ? 'selected' : '' ?>>
                                                    ⭐ <?= $i ?> / 10
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-warning text-dark fw-bold px-3">Puanla</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- YORUMLAR -->
    <div class="card bg-body-tertiary border shadow-sm p-4 rounded-4">
        <h4 class="fw-bold text-body mb-4">
            <i class="bi bi-chat-left-text-fill text-primary me-2"></i> Yorumlar (<?= count($comments) ?>)
        </h4>

        <?php if ($user_id > 0): ?>
            <form method="POST" class="mb-4">
                <input type="hidden" name="action" value="add_comment">
                <div class="mb-2">
                    <textarea name="comment" class="form-control border" rows="3" 
                              placeholder="Bu webtoon hakkında ne düşünüyorsun? Düşüncelerini paylaş..." required></textarea>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary px-4 rounded-pill fw-bold">
                        <i class="bi bi-send-fill me-1"></i> Yorum Yap
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-secondary text-body mb-4 border">
                Yorum yapabilmek ve puan verebilmek için <a href="<?= BASE_URL ?>auth/login.php" class="text-primary fw-bold">giriş yapmalısınız</a>.
            </div>
        <?php endif; ?>

        <hr class="border-secondary opacity-25 mb-4">

        <?php if (empty($comments)): ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-chat-dots fs-1 d-block mb-2"></i>
                <p class="mb-0">Henüz hiç yorum yapılmamış. İlk yorumu sen yap!</p>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($comments as$c): ?>
                    <?php
                    // Yoruma verilmiş rozetleri çek
                    $comment_badges = [];
                    try {
                        $cb_stmt =$pdo->prepare("
                            SELECT b.name, b.icon, b.color, COUNT(cb.id) as total 
                            FROM comment_badges cb 
                            JOIN badges b ON cb.badge_id = b.id 
                            WHERE cb.comment_id = :c_id 
                            GROUP BY b.id
                        ");
                        $cb_stmt->execute(['c_id' =>$c['id']]);
                        $comment_badges =$cb_stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {$comment_badges = [];
                    }
                    ?>
                    <!-- BİLDİRİM FOKUSLAMASI İÇİN ID EKLENDİ -->
                    <div class="p-3 bg-body-secondary border rounded-3 comment-item" id="comment-<?= $c['id'] ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <a href="<?= BASE_URL ?>profile/profile.php?id=<?= $c['comment_user_id'] ?>" class="text-decoration-none fw-bold text-primary hover-underline">
                                    <?= htmlspecialchars($c['username']) ?>
                                </a>
                                <?php if (($c['role'] ?? '') === 'admin'): ?>
                                    <span class="badge bg-warning text-dark" style="font-size: 0.7rem;">Admin</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></small>
                        </div>

                        <!-- KAZANILAN ROZETLER -->
                        <?php if (!empty($comment_badges)): ?>
                            <div class="d-flex gap-1 mb-2 flex-wrap">
                                <?php foreach ($comment_badges as$cb): ?>
                                    <span class="badge bg-<?= $cb['color'] ?>-subtle text-<?= $cb['color'] ?> border border-<?=$cb['color'] ?> rounded-pill px-2 py-1 small" title="<?= htmlspecialchars($cb['name']) ?>">
                                        <i class="bi <?= $cb['icon'] ?> me-1"></i> <?= $cb['total'] ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <p class="text-body mb-3" style="white-space: pre-line;"><?= htmlspecialchars($c['comment']) ?></p>

                        <!-- YORUM ETKİLEŞİM VE ÖDÜL BUTONLARI -->
                        <div class="d-flex align-items-center gap-2 border-top pt-2 border-secondary-subtle">
                            <!-- Like -->
                            <button class="btn btn-sm btn-outline-success border-0 rounded-pill px-2 <?= ($c['user_reaction'] ?? '') === 'like' ? 'active' : '' ?>" 
                                    onclick="reactComment(<?= $c['id'] ?>, 'like', this)">
                                <i class="bi bi-hand-thumbs-up-fill me-1"></i> <span class="like-cnt"><?= (int)($c['like_count'] ?? 0) ?></span>
                            </button>

                            <!-- Dislike -->
                            <button class="btn btn-sm btn-outline-danger border-0 rounded-pill px-2 <?= ($c['user_reaction'] ?? '') === 'dislike' ? 'active' : '' ?>" 
                                    onclick="reactComment(<?= $c['id'] ?>, 'dislike', this)">
                                <i class="bi bi-hand-thumbs-down-fill me-1"></i> <span class="dislike-cnt"><?= (int)($c['dislike_count'] ?? 0) ?></span>
                            </button>

                            <!-- Ödül Ver -->
                            <button class="btn btn-sm btn-outline-warning border-0 rounded-pill px-2 ms-auto fw-bold" 
                                    onclick="openBadgeModal(<?= $c['id'] ?>)">
                                <i class="bi bi-award-fill me-1"></i> Ödül Ver
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- ROZET / ÖDÜL SEÇME MODALI -->
<div class="modal fade" id="badgeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-body-tertiary border shadow-lg rounded-4">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold text-body"><i class="bi bi-award text-warning me-2"></i> Yoruma Rozet Hediye Et</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="selected_comment_id">
                <div class="row g-3">
                    <?php if (!empty($all_badges)): ?>
                        <?php foreach ($all_badges as$bg): ?>
                            <div class="col-6">
                                <div class="card h-100 bg-body border shadow-sm p-3 text-center clickable-card" onclick="giveBadge(<?= $bg['id'] ?>)" style="cursor: pointer;">
                                    <i class="bi <?= $bg['icon'] ?> text-<?=$bg['color'] ?> display-5 mb-2"></i>
                                    <h6 class="fw-bold mb-1 text-body"><?= htmlspecialchars($bg['name']) ?></h6>
                                    <small class="text-body-secondary d-block" style="font-size: 0.75rem;"><?= htmlspecialchars($bg['description']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-3 text-muted">
                            Tanımlı rozet bulunamadı.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ESTETİK TOAST BİLDİRİM KUTUSU -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
    <div id="customToast" class="toast align-items-center text-white bg-success border-0 rounded-4 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex p-2">
            <div class="toast-body d-flex align-items-center gap-2 fs-6 fw-bold" id="toastMessage">
                <i class="bi bi-check-circle-fill fs-5"></i> İşlem başarılı.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- BİLDİRİMDEN GELEN YORUMA ODAKLANMA VE VURGULAMA KODU -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash) {
        const targetComment = document.querySelector(window.location.hash);
        if (targetComment) {
            setTimeout(() => {
                targetComment.scrollIntoView({ behavior: 'smooth', block: 'center' });
                targetComment.classList.add('border-primary', 'shadow-lg');
                targetComment.style.transition = 'all 0.5s ease-in-out';
                targetComment.style.backgroundColor = 'rgba(13, 110, 253, 0.2)';
                
                setTimeout(() => {
                    targetComment.style.backgroundColor = '';
                }, 3000);
            }, 300);
        }
    }
});

function showToast(message, type = 'success') {
    const toastEl = document.getElementById('customToast');
    const toastMsg = document.getElementById('toastMessage');
    
    toastEl.className = `toast align-items-center text-white bg-${type} border-0 rounded-4 shadow-lg`;
    
    let icon = 'bi-check-circle-fill';
    if(type === 'danger') icon = 'bi-exclamation-triangle-fill';
    if(type === 'warning') icon = 'bi-exclamation-circle-fill';
    if(type === 'info') icon = 'bi-info-circle-fill';

    toastMsg.innerHTML = `<i class="bi ${icon} fs-5"></i> ${message}`;
    
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
    toast.show();
}

function reactComment(commentId, action, btnObj) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('comment_id', commentId);

    fetch('<?= BASE_URL ?>comments/react_comment.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const parent = btnObj.closest('.d-flex');
            parent.querySelector('.like-cnt').textContent = data.like_count;
            parent.querySelector('.dislike-cnt').textContent = data.dislike_count;
            
            if(action === 'like') {
                btnObj.classList.toggle('active');
                parent.querySelector('.btn-outline-danger').classList.remove('active');
            } else {
                btnObj.classList.toggle('active');
                parent.querySelector('.btn-outline-success').classList.remove('active');
            }
        } else {
            showToast(data.message, 'warning');
        }
    })
    .catch(err => console.error(err));
}

function openBadgeModal(commentId) {
    document.getElementById('selected_comment_id').value = commentId;
    const badgeModal = new bootstrap.Modal(document.getElementById('badgeModal'));
    badgeModal.show();
}

function giveBadge(badgeId) {
    const commentId = document.getElementById('selected_comment_id').value;
    const formData = new FormData();
    formData.append('action', 'give_badge');
    formData.append('comment_id', commentId);
    formData.append('badge_id', badgeId);

    const badgeModalEl = document.getElementById('badgeModal');
    const badgeModal = bootstrap.Modal.getInstance(badgeModalEl);
    if(badgeModal) badgeModal.hide();

    fetch('<?= BASE_URL ?>comments/react_comment.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                location.reload();
            }, 1200);
        } else {
            showToast(data.message, 'warning');
        }
    })
    .catch(err => {
        showToast('Bir hata oluştu.', 'danger');
    });
}
</script>
</body>
</html>