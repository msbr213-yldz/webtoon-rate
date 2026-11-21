---
name: herd-php-test
description: >-
  Laravel Herd üzerindeki PHP 8.4 binary'sini kullanarak projedeki tüm PHP dosyalarının
  sözdizimini (php -l) ve yerel HTTP sunucusuyla canlı sayfaları test eder.
  Kod değişikliği sonrası doğrulama ve smoke test istendiğinde kullan.
---

# Herd PHP 8.4 Lint ve Sayfa Testi

Bu makinede `php` komutu sistem PATH'inde değildir; Laravel Herd içindeki PHP 8.4 binary'si kullanılır:

```powershell
$php = 'C:\Users\User\.config\herd\bin\php84\php.exe'
```

## 1. Tüm PHP Dosyalarında Sözdizimi Kontrolü
```powershell
$php = 'C:\Users\User\.config\herd\bin\php84\php.exe'
$files = Get-ChildItem -Recurse -Filter *.php
$err = 0
foreach ($f in $files) {
    $out = & $php -l $f.FullName 2>&1
    if ($LASTEXITCODE -ne 0) { Write-Output "ERROR: $out"; $err++ }
}
Write-Output "Checked $($files.Count) PHP files. Syntax errors: $err"
```

## 2. Canlı Sayfa (Smoke) Testi
- `Invoke-WebRequest` zaman aşımına takılabildiği için `curl.exe --max-time 10` kullan.
- Önce `http://webtoon.test` erişilebilir mi bak; Herd servisi kapalıysa geçici olarak `php -S 127.0.0.1:8765 -t c:\Users\User\Herd\webtoon` başlat, testleri yap ve `finally` bloğunda süreci mutlaka durdur.
- Her sayfada şunları doğrula:
  - PHP hata/uyarısı yok (`Fatal error`, `Parse error`, `Warning:`, `Notice:`, `Deprecated:`, `Uncaught`).
  - `bootstrap.bundle.min.js` tam **1 kez** yükleniyor (çift yükleme dropdown'ları bozar).
  - `<html` etiketi tam **1 tane**.
  - Girişsiz erişimde `admin/*` sayfaları `302` ile yönlendiriliyor.
  - `notifications/mark_notifications_read.php` `notification.php` adresine (404 vermeden) yönlendiriyor.
