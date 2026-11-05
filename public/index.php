<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$root = dirname(__DIR__);
$files = [
    $root . '/app/Core/Database.php',
    $root . '/app/Core/Security.php',
    $root . '/app/Core/Auth.php',
    $root . '/app/Core/SchemaInspector.php',
    $root . '/app/Core/CrudRepository.php',
    $root . '/app/Support/Modules.php',
    $root . '/app/Controllers/UserController.php',
    $root . '/app/Controllers/AppController.php',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        http_response_code(500);
        exit('Required file not found: ' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8'));
    }
    require_once $file;
}

try {
    (new AppController())->dispatch();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>خطأ</title>';
    echo '<body style="font-family:Tahoma;padding:30px;background:#f6f7fb">';
    echo '<h2>تعذر تشغيل النظام</h2><pre style="white-space:pre-wrap;background:#fff;padding:20px;border-radius:8px">';
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '</pre><a href="?page=system_check">فحص النظام</a></body></html>';
}
