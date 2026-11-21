---
name: dbngin-mysql-audit
description: >-
  DBngin MySQL (webtoon_db) veritabanında salt-okunur şema/sağlık kontrolü,
  değişiklik öncesi tam yedekleme ve transaction içinde geri alınabilir doğrulama testi yapar.
  Veritabanı veya TablePlus SQL kontrolleri istendiğinde kullan.
---

# DBngin MySQL Sağlık Kontrolü ve Yedekleme

Bağlantı bilgileri (`config/config.php` ile uyumlu):
- Host: `127.0.0.1`, Port: `3306`, Veritabanı: `webtoon_db`, Kullanıcı: `root`
- PHP CLI: `C:\Users\User\.config\herd\bin\php84\php.exe`

## 1. Güvenlik Kuralları
- Kullanıcı açıkça onay vermeden **hiçbir yazma (`INSERT`, `UPDATE`, `DELETE`, `ALTER`, `DROP`) sorgusu çalıştırma**.
- Şema değişikliği yapmadan önce proje klasörünün dışına (`C:\Users\User\Herd\webtoon_db_yedek_YYYY-MM-DD.sql`) tam SQL yedeği (yapı + veri) al.
- Test sorgularını mutlaka `beginTransaction()` ... `rollBack()` içinde çalıştır; canlı veritabanında test verisi bırakma.

## 2. Sağlık Kontrolü Listesi
- `SHOW CREATE TABLE` ile kodun beklediği sütunların varlığını doğrula (özellikle `forum_posts.topic_id`, `notifications.message`, `webtoons.status`).
- Yetim kayıt kontrolü: `webtoon_genres`, `user_follows`, `chapter_ratings`, `webtoon_comments`, `comment_reactions`, `comment_badges`, `notifications`, `forum_posts`, `webtoon_requests`.
- Çift kayıt kontrolü: `chapter_ratings (user_id, webtoon_id)`, `user_follows (user_id, webtoon_id)`, `comment_reactions (comment_id, user_id)`, `comment_badges (comment_id, giver_id, badge_id)`.
- `webtoons.status` değerlerinin sadece `ongoing`, `hiatus`, `completed`, `dropped` olduğunu doğrula.
- `ONLY_FULL_GROUP_BY` uyumluluğunu kontrol et.
