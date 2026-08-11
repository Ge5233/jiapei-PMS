<?php
require '/www/wwwroot/pms/includes/db.php';
// 外采产品
$r = $pdo->query("SELECT MAX(CHAR_LENGTH(CONCAT(sku,' ',name))) as ml FROM products WHERE status=1")->fetch();
echo "外采最长: " . $r['ml'] . " chars\n";
// 自产产品
$r = $pdo->query("SELECT MAX(CHAR_LENGTH(name)) as ml FROM self_products")->fetch();
echo "自产最长: " . $r['ml'] . " chars\n";
