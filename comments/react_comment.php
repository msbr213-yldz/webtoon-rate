<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$user_id = (int)($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? 'Bir kullanıcı';

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Lütfen önce giriş yapın.']);
    exit;
}

$action = $_POST['action'] ?? '';
$comment_id = (int)($_POST['comment_id'] ?? 0);

if ($comment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz yorum.']);
    exit;
}

// Yorum sahibini öğrenmek için yardımcı sorgu
function getCommentOwner(PDO $pdo, int $comment_id) {
    $stmt = $pdo->prepare("SELECT user_id, comment FROM webtoon_comments WHERE id = :id");
    $stmt->execute(['id' => $comment_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 1. LIKE / DISLIKE İŞLEMİ
if ($action === 'like' || $action === 'dislike') {
    try {
        $stmt = $pdo->prepare("SELECT type FROM comment_reactions WHERE comment_id = :c_id AND user_id = :u_id");
        $stmt->execute(['c_id' => $comment_id, 'u_id' => $user_id]);
        $existing = $stmt->fetchColumn();

        if ($existing === $action) {
            // Tıklanan tepki zaten varsa kaldır (Toggle)
            $del = $pdo->prepare("DELETE FROM comment_reactions WHERE comment_id = :c_id AND user_id = :u_id");
            $del->execute(['c_id' => $comment_id, 'u_id' => $user_id]);
        } else {
            // Parametre çakışmasını önlemek için type1 ve type2 olarak ayrıştırıldı
            $ins = $pdo->prepare("
                INSERT INTO comment_reactions (comment_id, user_id, type) 
                VALUES (:c_id, :u_id, :type1) 
                ON DUPLICATE KEY UPDATE type = :type2
            ");
            $ins->execute([
                'c_id'  => $comment_id, 
                'u_id'  => $user_id, 
                'type1' => $action,
                'type2' => $action
            ]);

            // BEĞENİ BİLDİRİMİ GÖNDERME
            if ($action === 'like') {
                $comment_data = getCommentOwner($pdo, $comment_id);
                $owner_id = (int)($comment_data['user_id'] ?? 0);

                // Kendi yorumunu beğenmediyse ve yorum sahibi varsa bildirim at
                if ($owner_id > 0 && $owner_id !== $user_id) {
                    $notif_msg = "{$username} yorumunuzu beğendi.";
                    
                    $ins_notif = $pdo->prepare("
                        INSERT INTO notifications (user_id, message, is_read, created_at) 
                        VALUES (:u_id, :msg, 0, NOW())
                    ");
                    $ins_notif->execute([
                        'u_id' => $owner_id,
                        'msg'  => $notif_msg
                    ]);
                }
            }
        }

        // Güncel Sayıları Çek
        $likes = $pdo->prepare("SELECT COUNT(*) FROM comment_reactions WHERE comment_id = :c_id AND type = 'like'");
        $likes->execute(['c_id' => $comment_id]);
        $like_count = (int)$likes->fetchColumn();

        $dislikes = $pdo->prepare("SELECT COUNT(*) FROM comment_reactions WHERE comment_id = :c_id AND type = 'dislike'");
        $dislikes->execute(['c_id' => $comment_id]);
        $dislike_count = (int)$dislikes->fetchColumn();

        echo json_encode([
            'success' => true,
            'like_count' => $like_count,
            'dislike_count' => $dislike_count
        ]);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'PDO Hatası: ' . $e->getMessage()]);
        exit;
    }
}

// 2. ROZET / ÖDÜL VERME İŞLEMİ
if ($action === 'give_badge') {
    $badge_id = (int)($_POST['badge_id'] ?? 0);

    if ($badge_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz rozet.']);
        exit;
    }

    try {
        $comment_data = getCommentOwner($pdo, $comment_id);
        $owner_id = (int)($comment_data['user_id'] ?? 0);

        if ($owner_id === $user_id) {
            echo json_encode(['success' => false, 'message' => 'Kendi yorumunuza rozet veremezsiniz.']);
            exit;
        }

        // Rozet Bilgisini Çek
        $b_stmt = $pdo->prepare("SELECT name FROM badges WHERE id = :id");
        $b_stmt->execute(['id' => $badge_id]);
        $badge_name = $b_stmt->fetchColumn() ?: 'Ödül';

        // Rozeti Ekle
        $ins = $pdo->prepare("INSERT INTO comment_badges (comment_id, giver_id, badge_id) VALUES (:c_id, :g_id, :b_id)");
        $ins->execute([
            'c_id' => $comment_id,
            'g_id' => $user_id,
            'b_id' => $badge_id
        ]);

        // ROZET / ÖDÜL BİLDİRİMİ GÖNDERME
        if ($owner_id > 0) {
            $notif_msg = "{$username} yorumunuza '{$badge_name}' ödülünü hediye etti!";
            
            $ins_notif = $pdo->prepare("
                INSERT INTO notifications (user_id, message, is_read, created_at) 
                VALUES (:u_id, :msg, 0, NOW())
            ");
            $ins_notif->execute([
                'u_id' => $owner_id,
                'msg'  => $notif_msg
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Rozet başarıyla hediye edildi!']);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'PDO Hatası: ' . $e->getMessage()]);
        exit;
    }
}