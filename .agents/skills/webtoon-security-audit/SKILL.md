---
name: webtoon-security-audit
description: >-
  Webtoon PHP projesinde güvenlik ve kod denetimi yapar (admin yetki kontrolü,
  CSRF, XSS, Open Redirect, PDO prepared statements, oturum güvenliği ve hassas dosya kontrolü).
  Yeni sayfa eklendiğinde veya güvenlik kontrolü istendiğinde kullan.
---

# Webtoon Güvenlik ve Kod Denetimi

Bu proje saf PHP 8.4 + PDO (MySQL) ile yazılmıştır. Denetim yaparken şu kontrolleri sırayla uygula:

## 1. Yetki ve Oturum Kontrolü
- `admin/` klasöründeki her dosyada hem `isset($_SESSION['user_id'])` hem de `($_SESSION['role'] ?? '') === 'admin'` kontrolü bulunmalı.
- Giriş (`auth/login.php`) ve şifre değiştirme (`auth/change_password.php`) sonrasında `session_regenerate_id(true)` çağrılmalı.
- Kayıt ve şifre formlarında sunucu tarafında minimum 8 karakter şifre ve `filter_var($email, FILTER_VALIDATE_EMAIL)` kontrolü olmalı.

## 2. CSRF ve HTTP Metodları
- `includes/functions.php` içindeki `verify_csrf()` sadece `POST` isteklerini denetler.
- Silme, güncelleme, durum değiştirme gibi state değiştiren hiçbir işlem `GET` parametresiyle (`?delete=...`, `?delete_id=...`) yapılmamalı; `<form method="POST">` kullanılmalı.

## 3. XSS, SQL Injection ve Yönlendirmeler
- Tüm veritabanı sorgularında PDO prepared statements (`prepare` / `execute`) kullanılmalı.
- Kullanıcıdan gelen veriler veritabanına ham olarak kaydedilmeli, sadece HTML çıktısında `htmlspecialchars()` ile kaçırılmalı (çift kaçıştan kaçın).
- `$_GET['filter']`, `$_GET['sort']`, `$_GET['status']` gibi parametreler beyaz listeyle (`in_array`) doğrulanmalı.
- `$_SERVER['HTTP_REFERER']` ile yönlendirme yapılırken host değerinin `BASE_URL` hostuyla aynı olduğu doğrulanmalı (Open Redirect koruması).

## 4. Dosya Yükleme ve Git Güvenliği
- Görsel yüklemelerinde uzantı beyaz listesi + `getimagesize()` MIME doğrulaması + boyut sınırı (<= 5 MB) + rastgele dosya adı kullanılmalı.
- `config/config.php` ve `uploads/` dosyalarının `.gitignore` içinde olduğu ve git geçmişine girmediği kontrol edilmeli.

## Raporlama Kuralı
- Kullanıcı özellikle "sen düzelt" demedikçe dosyaları doğrudan değiştirme; bulguları öncelik sırasıyla (Kritik / Bug / Öneri) listele ve kopyalanabilir düzeltme kodlarını paylaş.
