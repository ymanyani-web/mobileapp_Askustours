<?php
declare(strict_types=1);

$env=static function(string $key,string $default):string{$value=getenv($key);return $value===false?$default:$value;};
$settings=[
    'app_name' => 'AskUs Tours',
    'auth_dev_code' => $env('AUTH_DEV_CODE', ''),
    //'base_url' => getenv('APP_URL') ?: 'http://localhost:8000',
    'base_url' => $env('APP_URL', 'https://admin.askustours.com'),
    'db' => [
        'host' => $env('DB_HOST', '127.0.0.1'),
        'port' => $env('DB_PORT', '3306'),
        'name' => $env('DB_NAME', 'askustours'),
        'user' => $env('DB_USER', 'root'),
        'pass' => $env('DB_PASS', ''),
    ],
];
$localFile=__DIR__.'/config.local.php';
if(is_file($localFile))$settings=array_replace_recursive($settings,require $localFile);
return $settings;

/* 
return [
    'app_name' => 'AskUs Tours',
    //'base_url' => getenv('APP_URL') ?: 'http://localhost:8000',
    'base_url' => getenv('APP_URL') ?: 'https://admin.askustours.com',
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'u452414377_mbapp',
        'user' => getenv('DB_USER') ?: 'u452414377_mbapp',
        'pass' => getenv('DB_PASS') ?: 'aw5AFFpx5kIJAwMNBJB1',
    ],
]; */