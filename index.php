<?php
/**
 * 入口：未登录跳登录页，已登录跳首页
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';

if (!PMS_INSTALLED) {
    header('Location: /install.php');
    exit;
}

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

header('Location: /login.php');
exit;
