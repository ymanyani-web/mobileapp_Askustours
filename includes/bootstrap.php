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

function upload_storage_root(): string
{
    global $config;
    $configured = trim((string)($config['upload_storage_path'] ?? ''));
    return rtrim($configured !== '' ? $configured : dirname(__DIR__) . '/uploads', '/\\');
}

function legacy_upload_storage_root(): string
{
    return dirname(__DIR__) . '/uploads';
}

function stored_asset_file(?string $path): ?string
{
    $path = trim((string)$path);
    if ($path === '') return null;
    if (preg_match('#^https://#i', $path)) {
        global $config;
        $assetUrl = parse_url($path);
        $baseUrl = parse_url((string)($config['base_url'] ?? ''));
        if (!is_array($assetUrl) || empty($assetUrl['host']) || empty($baseUrl['host'])) return null;
        if (strcasecmp((string)$assetUrl['host'], (string)$baseUrl['host']) !== 0) return null;
        $path = rawurldecode((string)($assetUrl['path'] ?? ''));
    }
    $relative = ltrim(str_replace('\\', '/', $path), '/');
    if ($relative === '' || str_contains($relative, '../')) return null;
    if (!preg_match('#^(uploads|assets)/(.+\.(?:jpe?g|png|webp|svg))$#i', $relative, $match)) return null;
    if (strtolower($match[1]) === 'assets') {
        $candidate = dirname(__DIR__) . '/' . $relative;
        return is_file($candidate) ? $candidate : null;
    }
    $suffix = str_replace('/', DIRECTORY_SEPARATOR, $match[2]);
    foreach (array_unique([upload_storage_root(), legacy_upload_storage_root()]) as $root) {
        $candidate = $root . DIRECTORY_SEPARATOR . $suffix;
        if (is_file($candidate)) return $candidate;
    }
    return null;
}

function public_content_media_url(string $type, int $id, ?string $path): string
{
    $path = trim((string)$path);
    if (preg_match('#^https://#i', $path)) return $path;
    global $config;
    // Upload filenames are immutable. Changing a photo changes its URL/cache
    // key, while unchanged images stay cached across automatic app refreshes.
    return rtrim((string)$config['base_url'], '/') . '/media.php?type=' . rawurlencode($type) . '&id=' . $id . '&v=' . substr(hash('sha256',$path),0,16);
}

function public_asset_available(?string $path): bool
{
    $path = trim((string)$path);
    if ($path === '') return false;
    if (preg_match('#^https://#i', $path)) {
        global $config;
        $assetUrl = parse_url($path);
        $baseUrl = parse_url((string)($config['base_url'] ?? ''));
        if (!is_array($assetUrl) || empty($assetUrl['host'])) return false;
        if (empty($baseUrl['host']) || strcasecmp((string)$assetUrl['host'], (string)$baseUrl['host']) !== 0) return true;
        $path = rawurldecode((string)($assetUrl['path'] ?? ''));
    }
    return stored_asset_file($path) !== null;
}

function upload_image(string $field, ?string $current = null): ?string
{
    if (empty($_FILES[$field]['name'])) return $current;
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK || $_FILES[$field]['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image upload failed or exceeds 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Only JPG, PNG and WebP images are allowed.');
    $dir = upload_storage_root();
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Could not create persistent upload storage.');
    $name = bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Could not store image.');
    return 'uploads/' . $name;
}
