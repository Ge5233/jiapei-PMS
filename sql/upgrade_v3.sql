-- =====================================================
-- PMS v3.0 升级脚本
-- 新增：供应商管理模块 + products.supplier_id 字段
-- =====================================================

-- 1. 新建 suppliers 表
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL COMMENT '供应商名称',
    `contact` VARCHAR(50) DEFAULT NULL COMMENT '联系人',
    `phone` VARCHAR(50) DEFAULT NULL COMMENT '联系电话',
    `email` VARCHAR(100) DEFAULT NULL COMMENT '邮箱',
    `address` VARCHAR(255) DEFAULT NULL COMMENT '公司地址',
    `bank_name` VARCHAR(100) DEFAULT NULL COMMENT '开户行',
    `bank_account` VARCHAR(50) DEFAULT NULL COMMENT '银行账号',
    `license_no` VARCHAR(50) DEFAULT NULL COMMENT '营业执照号',
    `remark` TEXT DEFAULT NULL COMMENT '备注',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_name` (`name`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商';

-- 2. products 加 supplier_id 字段
-- 检查列是否存在后添加（兼容已部署的库）
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'products'
                   AND COLUMN_NAME = 'supplier_id');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `supplier_id` INT(11) UNSIGNED DEFAULT NULL COMMENT "供应商ID" AFTER `min_discount`, ADD KEY `idx_supplier_id` (`supplier_id`)',
    'SELECT "supplier_id 已存在，跳过" AS msg');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. 升级完成
SELECT 'PMS v3.0 升级成功' AS result;
