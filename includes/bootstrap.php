<?php
declare(strict_types=1);
date_default_timezone_set('UTC');

$sessionPath=session_save_path();
if($sessionPath===''||!is_dir($sessionPath)||!is_writable($sessionPath))session_save_path(sys_get_temp_dir());
session_start();
$config = require dirname(__DIR__) . '/config.php';

function db(): PDO
{
    static $pdo;
    global $config;
    if (!$pdo) {
        $db = $config['db'];
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function flash(string $type, string $message): void { $_SESSION['flash'] = compact('type', 'message'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) {
        http_response_code(419); exit('Invalid CSRF token');
    }
}
function admin(): ?array { return $_SESSION['admin'] ?? null; }
function require_admin(): void { if (!admin()) redirect('login.php'); }
function new_uuid(): string { $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4)); }

function upload_image(string $field, ?string $current = null): ?string
{
    if (empty($_FILES[$field]['name'])) return $current;
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK || $_FILES[$field]['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image upload failed or exceeds 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Only JPG, PNG and WebP images are allowed.');
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Could not store image.');
    return 'uploads/' . $name;
}
