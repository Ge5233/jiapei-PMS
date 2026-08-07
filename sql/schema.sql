-- ============================================================
-- 产品管理系统（PMS）数据库结构
-- 适用：MySQL 5.7+ / 8.0
-- 字符集：utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 用户表
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL COMMENT '登录账号',
  `password_hash` VARCHAR(255) NOT NULL COMMENT '密码哈希',
  `name` VARCHAR(50) NOT NULL COMMENT '姓名',
  `role` ENUM('admin','staff') NOT NULL DEFAULT 'staff' COMMENT '角色：管理员/员工',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=启用 0=禁用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- ----------------------------
-- 分类表（两级分类）
-- parent_id = 0 → 一级分类
-- parent_id > 0 → 二级分类，指向一级分类的 id
-- ----------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL COMMENT '分类名称',
  `parent_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级 ID，0=一级分类',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序权重，小在前',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='产品分类';

-- ----------------------------
-- 产品表
-- ----------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `sku` VARCHAR(50) NOT NULL COMMENT '产品编码',
  `name` VARCHAR(200) NOT NULL COMMENT '产品名称',
  `category_id` INT UNSIGNED NULL COMMENT '分类 ID（指向二级分类）',
  `spec` VARCHAR(200) DEFAULT NULL COMMENT '规格',
  `unit` VARCHAR(20) DEFAULT NULL COMMENT '单位',
  `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '综合进价（含运费/税）',
  `guide_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '指导售价',
  `min_discount` DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT '最高允许折扣 0.85=8.5折',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=上架 0=下架',
  `remark` TEXT COMMENT '备注',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sku` (`sku`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='产品表';

-- ----------------------------
-- 产品文件表（图片/PDF/Word/Excel）
-- ----------------------------
DROP TABLE IF EXISTS `product_files`;
CREATE TABLE `product_files` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `original_name` VARCHAR(255) NOT NULL COMMENT '原始文件名',
  `stored_name` VARCHAR(255) NOT NULL COMMENT '存储文件名',
  `file_type` VARCHAR(20) NOT NULL COMMENT 'image/pdf/word/excel/other',
  `file_size` INT UNSIGNED NOT NULL COMMENT '字节数',
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='产品资料文件';

-- ----------------------------
-- 价格历史表（进价/售价/折扣变更）
-- ----------------------------
DROP TABLE IF EXISTS `price_history`;
CREATE TABLE `price_history` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `field` ENUM('cost_price','guide_price','min_discount') NOT NULL COMMENT '变更字段',
  `old_value` DECIMAL(12,2) NOT NULL,
  `new_value` DECIMAL(12,2) NOT NULL,
  `changed_by` INT UNSIGNED DEFAULT NULL,
  `remark` VARCHAR(255) DEFAULT NULL COMMENT '变更原因/备注',
  `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_time` (`product_id`, `changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='价格变更历史';

-- ----------------------------
-- 操作日志表
-- ----------------------------
DROP TABLE IF EXISTS `operation_logs`;
CREATE TABLE `operation_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `username` VARCHAR(50) DEFAULT NULL COMMENT '冗余存储，避免用户删除后失联',
  `action` VARCHAR(50) NOT NULL COMMENT 'create/update/delete/login/logout/...',
  `target_type` VARCHAR(50) DEFAULT NULL COMMENT 'product/category/user/...',
  `target_id` INT UNSIGNED DEFAULT NULL,
  `details` TEXT COMMENT '详情描述',
  `ip` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`, `created_at`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志';

SET FOREIGN_KEY_CHECKS = 1;
