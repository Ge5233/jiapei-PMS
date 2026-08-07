<?php
/**
 * Bootstrap - 启动文件
 * 加载配置、连接数据库、启动 Session
 */

// 防止直接访问
if (!defined('PMS_ENTRY')) {
    define('PMS_ENTRY', true);
}

// 错误显示（生产关闭）
$appDebug = (($_ENV['APP_DEBUG'] ?? '') === 'true');
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
error_reporting(E_ALL);

// 项目根目录
define('PMS_ROOT', dirname(__DIR__));

// 加载 .env
$envFile = PMS_ROOT . '/.env';
$envLoaded = false;
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // 去掉引号
        $value = trim($value, "\"'");
        if (empty($_ENV[$key])) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
    $envLoaded = true;
}
// 是否已安装（用 installed.lock 判断，install.php 自己会生成）
define('PMS_INSTALLED', file_exists(PMS_ROOT . '/installed.lock'));

// Session 配置
$sessionName = 'PMS_SID';
session_name($sessionName);

$lifetime = (int)($_ENV['SESSION_LIFETIME'] ?? '7200');
ini_set('session.cookie_lifetime', $lifetime);
ini_set('session.gc_maxlifetime', $lifetime);
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

// HTTPS 下开启 secure
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
if ($isHttps) {
    ini_set('session.cookie_secure', '1');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 时区
date_default_timezone_set('Asia/Shanghai');

// 包含核心库
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// 包含 models
foreach (glob(__DIR__ . '/models/*.php') as $modelFile) {
    require_once $modelFile;
}
