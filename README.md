# Webtoon Tracker

PHP 8.4, MySQL (PDO) ve Bootstrap 5 ile geliştirilmiş webtoon takip, bölüm ilerlemesi kaydetme, puanlama, topluluk forumu, bildirim ve admin yönetim platformu.

## Hakkında

Asıl ilgi alanım oyun tasarımı ve oyun geliştirme (Unity). Bu projeyi, kişisel olarak ilgimi çeken webtoonları takip edebilmek ve web geliştirme tarafında pratik yapmak amacıyla yapay zeka araçlarının desteğiyle geliştirdim.

## Özellikler

- **Webtoon Kataloğu & Filtreleme:** Çoklu tür (genre) seçimi, yayın durumu filtresi (`Devam Ediyor`, `Ara Verildi`, `Tamamlandı`, `Yarıda Bırakıldı`), Editörün Seçimi ve En Yüksek Puanlılar (Top 10) slider'ları.
- **Takip & Okuma İlerlemesi:** Serileri takibe alma, kalınan bölümü kaydetme ve 1–10 arası puanlama.
- **Yorumlar & Rozet Sistemi:** Webtoonlara yorum yapma, beğenme/beğenmeme (AJAX) ve yorumlara ödül/rozet hediye etme.
- **Topluluk Forumu:** Kategorilere ayrılmış konu açma ve yanıt yazma sistemi.
- **Webtoon İstek Paneli:** Kullanıcıların yeni webtoon isteği göndermesi ve admin onayı/reddi sonrası otomatik bildirim alması.
- **Bildirim Merkezi:** Yeni bölüm, durum değişikliği, yorum beğenisi, rozet ve istek sonucu bildirimleri.
- **Admin Paneli:** Webtoon ekleme/düzenleme (URL veya bilgisayardan kapak yükleme), hızlı bölüm güncelleme, tür yönetimi ve istek yönetimi.
- **Arayüz & Güvenlik:** Karanlık/Aydınlık (Dark/Light) tema desteği, PDO prepared statements, `password_hash`, CSRF (Same-Origin) koruması, rol bazlı admin erişimi.

## Kullanılan Teknolojiler

- **Backend:** PHP 8.4 (PDO)
- **Veritabanı:** MySQL (DBngin / TablePlus)
- **Frontend:** Bootstrap 5.3, Bootstrap Icons, Vanilla JavaScript
- **Yerel Sunucu:** Laravel Herd

## Kurulum

1. **Projeyi klonlayın:**
   ```bash
   git clone https://github.com/msbr213-yldz/webtoon-rate.git
   cd webtoon-rate
   ```

2. **Veritabanını oluşturun:**
   - MySQL üzerinde `webtoon_db` adında yeni bir veritabanı oluşturun.
   - `database.sql` dosyasını veritabanına içe aktarın (TablePlus veya MySQL CLI ile).

3. **Yapılandırma dosyasını oluşturun:**
   - `config/config.example.php` dosyasını `config/config.php` olarak kopyalayın.
   - Veritabanı bilgilerinizi (`$db_host`, `$db_name`, `$db_user`, `$db_pass`) kendi ortamınıza göre düzenleyin.

4. **Projeyi çalıştırın:**
   - Laravel Herd kullanıyorsanız proje klasörünü Herd dizinine koyup `http://webtoon.test` adresinden açabilirsiniz.
   - Alternatif olarak PHP dahili sunucusuyla çalıştırabilirsiniz:
     ```bash
     php -S 127.0.0.1:8000
     ```

## Proje Yapısı

```text
├── admin/          # Webtoon, tür ve istek yönetim sayfaları
├── auth/           # Giriş, kayıt, çıkış ve şifre değiştirme
├── comments/       # Yorum beğeni ve rozet AJAX uç noktası
├── config/         # Veritabanı ve dil yapılandırması (config.example.php)
├── forum/          # Topluluk forumu sayfaları
├── includes/       # Ortak header, footer ve yardımcı fonksiyonlar
├── lang/           # Dil dosyaları (TR / EN)
├── notifications/  # Bildirim listesi ve okundu işaretleme
├── profile/        # Kullanıcı profili, okunan ve takip edilen seriler
├── requests/       # Kullanıcı webtoon istek paneli
├── home.php        # Ana sayfa ve katalog
└── webtoon.php     # Webtoon detay, ilerleme, puanlama ve yorumlar
```

## Lisans

Bu proje [MIT Lisansı](LICENSE) ile lisanslanmıştır.
