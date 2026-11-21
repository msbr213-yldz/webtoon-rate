<?php

// Oturum henüz başlatılmadıysa başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. DİNAMİK VE HTTPS UYUMLU BASE_URL TESPİTİ (Herd, ngrok ve Localhost Uyumlu)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? 80) == 443
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

$protocol = $isHttps ? "https://" : "http://";

if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
} else {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
}

define('BASE_URL', $protocol . $host . '/');

// 2. VERİTABANI BAĞLANTI AYARLARI (Bu dosyayı config.php olarak kopyalayıp kendi bilgilerinizi girin)
$db_host = '127.0.0.1';
$db_name = 'webtoon_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// 3. DİL YÖNETİMİ (TR / EN)
if (isset($_GET['lang'])) {
    $lang_choice = strtolower($_GET['lang']);
    if (in_array($lang_choice, ['tr', 'en'])) {
        $_SESSION['lang'] = $lang_choice;
    }
}

$current_lang = $_SESSION['lang'] ?? 'tr';

$lang_file = __DIR__ . "/../lang/{$current_lang}.php";
if (file_exists($lang_file)) {
    $lang = require $lang_file;
} else {
    $lang = [
        'home'               => 'Ana Sayfa',
        'forum'              => 'Forum',
        'requests'           => 'İstekler',
        'admin_panel'        => 'Admin Paneli',
        'webtoon_management' => 'Webtoon Yönetimi',
        'add_webtoon'        => '+ Webtoon Ekle',
        'genre_management'   => 'Tür Yönetimi',
        'my_profile'         => 'Profilim',
        'my_webtoons'        => 'Okuduğum Seriler',
        'logout'             => 'Çıkış Yap',
        'login'              => 'Giriş Yap',
        'register'           => 'Kayıt Ol',
    ];
}

$functions_file = __DIR__ . '/../includes/functions.php';
if (file_exists($functions_file)) {
    require_once $functions_file;
}
