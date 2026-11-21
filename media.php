<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/home_content.php';

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
$homeSection = str_starts_with($type, 'home_') ? substr($type,5) : '';
if ((!isset($resources[$type]) && !isset(home_sections()[$homeSection])) || $id === false) {
    http_response_code(404);
    exit;
}

$path = null;
if (isset(home_sections()[$homeSection])) {
    foreach (home_configuration()[$homeSection] ?? [] as $card) {
        if ((int)($card['_mediaId'] ?? 0) === (int)$id) {$path=home_card_image($card); break;}
    }
} else {
    $statement = db()->prepare('SELECT image_path FROM `' . $resources[$type] . '` WHERE id=?');
    $statement->execute([(int)$id]);
    $path = $statement->fetchColumn();
}
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
