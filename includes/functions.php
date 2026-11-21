<?php
/**
 * Bir webtoona yeni bölüm eklendiğinde tüm takipçilerine bildirim gönderir.
 */
if (!function_exists('sendNewChapterNotification')) {
    function sendNewChapterNotification(PDO $pdo, int $webtoon_id, int $new_chapter_number): void {
        try {
            // 1. Webtoon adını çek
            $stmt = $pdo->prepare("SELECT title FROM webtoons WHERE id = :id");
            $stmt->execute(['id' => $webtoon_id]);
            $webtoon_title = $stmt->fetchColumn();

            if (!$webtoon_title) {
                return;
            }

            // 2. Takipçileri çek
            $stmt = $pdo->prepare("SELECT user_id FROM user_follows WHERE webtoon_id = :webtoon_id");
            $stmt->execute(['webtoon_id' => $webtoon_id]);
            $followers = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($followers)) {
                return;
            }

            // 3. Bildirim mesajını hazırla
            $message = "'{$webtoon_title}' serisinin {$new_chapter_number}. bölümü yayınlandı!";

            $ins_stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, message, is_read, created_at) 
                VALUES (:user_id, :message, 0, NOW())
            ");

            foreach ($followers as $follower_id) {
                $ins_stmt->execute([
                    'user_id' => (int)$follower_id,
                    'message' => $message
                ]);
            }

        } catch (PDOException $e) {
            error_log("Bildirim Hatası: " . $e->getMessage());
        }
    }
}

/**
 * Bir webtoonun durumu değiştiğinde (Yayında, Ara Verdi, Tamamlandı, Yarıda Bırakıldı) tüm takipçilerine bildirim gönderir.
 */
if (!function_exists('sendStatusChangeNotification')) {
    function sendStatusChangeNotification(PDO $pdo, int $webtoon_id, string $old_status, string $new_status): void {
        if ($old_status === $new_status) {
            return; // Durum değişmediyse bildirim atma
        }

        try {
            // 1. Webtoon adını çek
            $stmt = $pdo->prepare("SELECT title FROM webtoons WHERE id = :id");
            $stmt->execute(['id' => $webtoon_id]);
            $webtoon_title = $stmt->fetchColumn();

            if (!$webtoon_title) {
                return;
            }

            // 2. Takipçileri çek
            $stmt = $pdo->prepare("SELECT user_id FROM user_follows WHERE webtoon_id = :webtoon_id");
            $stmt->execute(['webtoon_id' => $webtoon_id]);
            $followers = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($followers)) {
                return;
            }

            // 3. Yeni duruma göre özel bildirim mesajı oluştur
            switch ($new_status) {
                case 'hiatus':
                    $message = "'{$webtoon_title}' serisi ara verdi.";
                    break;
                case 'ongoing':
                    $message = "'{$webtoon_title}' serisi tekrar yayına başladı!";
                    break;
                case 'completed':
                    $message = "'{$webtoon_title}' serisi tamamlandı!";
                    break;
                case 'dropped':
                    $message = "'{$webtoon_title}' serisi yarıda bırakıldı.";
                    break;
                default:
                    $message = "'{$webtoon_title}' serisinin durumu güncellendi.";
                    break;
            }

            // 4. Bildirimleri ekle
            $ins_stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, message, is_read, created_at) 
                VALUES (:user_id, :message, 0, NOW())
            ");

            foreach ($followers as $follower_id) {
                $ins_stmt->execute([
                    'user_id' => (int)$follower_id,
                    'message' => $message
                ]);
            }

        } catch (PDOException $e) {
            error_log("Durum Bildirimi Hatası: " . $e->getMessage());
        }
    }
}

// --- CSRF KORUMASI: POST istekleri sadece sitenin kendisinden gelebilir ---
if (!function_exists('is_same_origin_request')) {
    function is_same_origin_request(): bool {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return true;
        }
        $source = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        if ($source === '') {
            return true; // Tarayıcı kaynak bilgisi göndermemişse engelleme
        }
        $source_host = parse_url($source, PHP_URL_HOST);
        $own = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
        $own = trim(explode(',', $own)[0]);
        $own_host = parse_url('//' . $own, PHP_URL_HOST);
        if (!$source_host || !$own_host) {
            return false;
        }
        return strcasecmp($source_host, $own_host) === 0;
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): void {
        if (!is_same_origin_request()) {
            http_response_code(403);
            exit('Geçersiz istek kaynağı.');
        }
    }
}

verify_csrf();
