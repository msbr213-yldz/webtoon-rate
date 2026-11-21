---
name: webtoon-conventions
description: >-
  Webtoon Tracker PHP projesinin arayüz, şablon, durum değerleri, dil dosyaları
  ve dosya yükleme standartlarını tanımlar. Yeni özellik veya sayfa eklerken kullan.
---

# Webtoon Tracker Proje Standartları

## 1. Şablon ve Bootstrap 5 Kuralları
- Her sayfa `require_once __DIR__ . '/config/config.php';` (alt klasörlerde `../config/config.php`) ile başlar. `<?php` öncesinde kesinlikle boşluk veya karakter bırakma.
- `includes/header.php` zaten `<!DOCTYPE html>`, `<html>`, `<head>`, Bootstrap 5 CSS, Bootstrap Icons, `bootstrap.bundle.min.js` ve Dark/Light tema değiştirme scriptini yükler.
- Sayfalara **tekrar** `<!DOCTYPE html>`, `<html>`, `<body>`, `bootstrap.bundle.min.js` veya ikinci bir tema değiştirme scripti ekleme.
- Dark/Light tema uyumu için sabit `bg-dark` / `bg-light` yerine Bootstrap 5.3 temaya duyarlı sınıfları (`bg-body-tertiary`, `bg-body-secondary`, `text-body`, `text-body-secondary`, `theme-input`) kullan.

## 2. Veritabanı Durum (`status`) Değerleri
- `webtoons.status` sütununda her zaman İngilizce standart değerler saklanır:
  - `ongoing` (Devam Ediyor)
  - `hiatus` (Ara Verildi)
  - `completed` (Tamamlandı)
  - `dropped` (Yarıda Bırakıldı)
- Türkçe etiketler yalnızca arayüzde gösterilir.

## 3. Çoklu Dil ve Dosya Yükleme
- Yeni bir arayüz metni eklendiğinde hem `lang/tr.php` hem `lang/en.php` dosyalarına karşılık gelen anahtar eklenmeli.
- Kapak görselleri `uploads/` klasörüne kaydedilir; yazmadan önce `if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);` kontrolü yapılmalı.
- `config/config.php` değiştirildiğinde şifresiz/örnek karşılığı `config/config.example.php` içinde de güncel tutulmalı.
