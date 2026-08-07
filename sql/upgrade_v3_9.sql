-- ============================================================
-- upgrade_v3_9.sql — 自产产品管理模块
-- 适用：MySQL 5.7+ / 8.0
-- 新增：self_products（自产产品）+ self_product_items（BOM物料清单）
-- ============================================================

CREATE TABLE IF NOT EXISTS `self_products` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL COMMENT '产品名称',
  `image` VARCHAR(255) DEFAULT NULL COMMENT '产品主图文件名',
  `model_no` VARCHAR(50) DEFAULT NULL COMMENT '型号',
  `spec` VARCHAR(200) DEFAULT NULL COMMENT '规格',
  `unit` VARCHAR(20) DEFAULT '套' COMMENT '单位',
  `description` TEXT COMMENT '产品描述',
  `labor_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '人工成本',
  `overhead_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '制造费用',
  `material_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '材料成本（BOM汇总）',
  `total_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '总成本',
  `guide_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '参考售价',
  `min_discount` DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT '最低折扣',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=在生产 0=停产',
  `remark` TEXT COMMENT '备注',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='自产产品';

CREATE TABLE IF NOT EXISTS `self_product_items` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `self_product_id` INT UNSIGNED NOT NULL COMMENT '自产产品ID',
  `product_id` INT UNSIGNED DEFAULT NULL COMMENT '关联外采产品ID（NULL=临时物料）',
  `item_name` VARCHAR(200) DEFAULT NULL COMMENT '临时物料名称',
  `quantity` DECIMAL(12,4) NOT NULL DEFAULT 1.0000 COMMENT '用量',
  `unit` VARCHAR(20) DEFAULT NULL COMMENT '单位',
  `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `remark` VARCHAR(200) DEFAULT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_self_product` (`self_product_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='自产产品BOM物料清单';
