<?php
declare(strict_types=1);

$env=static function(string $key,string $default):string{$value=getenv($key);return $value===false?$default:$value;};
$settings=[
    'app_name' => 'AskUs Tours',
    'auth_dev_code' => $env('AUTH_DEV_CODE', ''),
    'api_public_base_url' => rtrim($env('API_PUBLIC_BASE_URL', 'https://api.askustours.com/v1'), '/'),
    'otp' => [
        'webhook_url' => $env('AUTH_OTP_WEBHOOK_URL', ''),
        'webhook_token' => $env('AUTH_OTP_WEBHOOK_TOKEN', ''),
        'sender' => $env('AUTH_OTP_SENDER', 'ASKUSTOURS'),
        'timeout_seconds' => (int)$env('AUTH_OTP_TIMEOUT_SECONDS', '8'),
    ],
    'calendar' => [
        'horizon_days' => (int)$env('CALENDAR_HORIZON_DAYS', '120'),
    ],
    'cors_allowed_origins' => array_values(array_filter(array_map('trim', explode(',', $env('CORS_ALLOWED_ORIGINS', 'https://admin.askustours.com'))))),
    //'base_url' => getenv('APP_URL') ?: 'http://localhost:8000',
    'base_url' => $env('APP_URL', 'https://admin.askustours.com'),
    // Keep runtime uploads outside the Git deployment directory so pushes cannot remove them.
    'upload_storage_path' => $env('UPLOAD_STORAGE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'askustours-storage' . DIRECTORY_SEPARATOR . 'uploads'),
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'u452414377_mbapp',
        'user' => getenv('DB_USER') ?: 'u452414377_mbapp',
        'pass' => getenv('DB_PASS') ?: 'aw5AFFpx5kIJAwMNBJB1',
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
        
    ],
]; */
