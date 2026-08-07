<?php
/**
 * 退出
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
logout();
header('Location: /login.php');
exit;
