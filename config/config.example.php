<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_URL', 'http://localhost/webtoon/');

$host     = '127.0.0.1';
$db       = 'webtoon_db';
$user     = 'root';
$pass     = '';
$charset  = 'utf8mb4';