<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$resources = [
    'banners' => 'banners',
    'offers' => 'home_offers',
    'services' => 'services',
    'destinations' => 'destinations',
    'hotels' => 'hotels',
    'vehicles' => 'vehicles',
];
$type = (string)($_GET['type'] ?? '');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!isset($resources[$type]) || $id === false) {
    http_response_code(404);
    exit;
}

$statement = db()->prepare('SELECT image_path FROM `' . $resources[$type] . '` WHERE id=?');
$statement->execute([(int)$id]);
$path = $statement->fetchColumn();
$file = is_string($path) ? stored_asset_file($path) : null;
if ($file === null) {
    http_response_code(404);
    exit;
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'], true)) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($file);
