-- ============================================================
-- upgrade_v5_0.sql — v5.0 项目制管理（阶段1：项目 + 项目产品）
-- ============================================================

CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL COMMENT '项目名称',
  `customer_name` VARCHAR(100) DEFAULT NULL COMMENT '客户名称',
  `status` ENUM('active','done','cancelled') NOT NULL DEFAULT 'active' COMMENT '进行中/已完成/已取消',
  `remark` TEXT COMMENT '备注',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='项目表';

CREATE TABLE IF NOT EXISTS `project_products` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL COMMENT '所属项目ID',
  `item_type` ENUM('purchase','self_product') NOT NULL DEFAULT 'purchase' COMMENT '直接外采 / 自产产品',
  `product_id` INT UNSIGNED DEFAULT NULL COMMENT '直接外采时 → 外采产品ID',
  `self_product_id` INT UNSIGNED DEFAULT NULL COMMENT '自产产品时 → 自产产品ID',
  `item_name` VARCHAR(200) DEFAULT NULL COMMENT '临时料名称',
  `spec` VARCHAR(200) DEFAULT NULL COMMENT '规格',
  `unit` VARCHAR(20) DEFAULT NULL COMMENT '单位',
  `quantity` DECIMAL(12,4) NOT NULL DEFAULT 1.0000 COMMENT '数量',
  `requirement` TEXT COMMENT '需求说明（自产产品时写给产品经理）',
  `remark` VARCHAR(200) DEFAULT NULL COMMENT '备注',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_self_product` (`self_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='项目产品表（项目里要什么）';
