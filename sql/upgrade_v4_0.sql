-- ============================================================
-- upgrade_v4_0.sql — v4.0 报价系统
-- 1. products / self_products 加定价字段
-- 2. 新建 quotes / quote_items 表
-- ============================================================

-- ----------------------------
-- 1. products 表加字段（外采产品定价）
-- ----------------------------
ALTER TABLE `products`
  ADD COLUMN `guide_price_coefficient` DECIMAL(5,3) NOT NULL DEFAULT 1.100 COMMENT '指导价系数',
  ADD COLUMN `min_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '最低售价（绝对底线）';

-- ----------------------------
-- 2. self_products 表加字段（自产产品定价）
-- ----------------------------
ALTER TABLE `self_products`
  ADD COLUMN `guide_price_coefficient` DECIMAL(5,3) NOT NULL DEFAULT 1.600 COMMENT '指导价系数',
  ADD COLUMN `min_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '最低售价（绝对底线）';

-- ----------------------------
-- 3. 报价单主表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `quotes` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `quote_no` VARCHAR(20) NOT NULL COMMENT '报价单编号 Q20260808-001',
  `project_name` VARCHAR(200) NOT NULL COMMENT '项目名称',
  `customer_name` VARCHAR(100) DEFAULT NULL COMMENT '客户名称',
  `contact_person` VARCHAR(50) DEFAULT NULL COMMENT '联系人',
  `contact_phone` VARCHAR(30) DEFAULT NULL COMMENT '联系电话',
  `payment_terms` VARCHAR(200) DEFAULT '预付30%，发货前付70%' COMMENT '付款方式',
  `warranty` VARCHAR(100) DEFAULT '1年' COMMENT '质保',
  `delivery_period` VARCHAR(100) DEFAULT NULL COMMENT '交期',
  `valid_until` DATE DEFAULT NULL COMMENT '报价有效期',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '小计（自动）',
  `tax_rate` DECIMAL(4,2) NOT NULL DEFAULT 0.13 COMMENT '税率',
  `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '税额（自动）',
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '合计（自动）',
  `status` ENUM('draft','sent','accepted','rejected') NOT NULL DEFAULT 'draft' COMMENT '状态',
  `remark` TEXT COMMENT '内部备注',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_quote_no` (`quote_no`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='报价单';

-- ----------------------------
-- 4. 报价单明细
-- ----------------------------
CREATE TABLE IF NOT EXISTS `quote_items` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `quote_id` INT UNSIGNED NOT NULL COMMENT '报价单ID',
  `source_type` ENUM('product','self_product','adhoc') NOT NULL DEFAULT 'product' COMMENT '来源',
  `product_id` INT UNSIGNED DEFAULT NULL COMMENT '外采产品ID',
  `self_product_id` INT UNSIGNED DEFAULT NULL COMMENT '自产产品ID',
  `item_name` VARCHAR(200) DEFAULT NULL COMMENT '临时项名称',
  `spec` VARCHAR(200) DEFAULT NULL COMMENT '规格型号',
  `unit` VARCHAR(20) DEFAULT '套' COMMENT '单位',
  `quantity` DECIMAL(12,4) NOT NULL DEFAULT 1.0000 COMMENT '数量',
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
  `discount` DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT '折扣',
  `line_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '行小计',
  `category_id` INT UNSIGNED DEFAULT NULL COMMENT '所属一级分类ID（冗余）',
  `category_name` VARCHAR(50) DEFAULT NULL COMMENT '分类名（冗余）',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `remark` VARCHAR(200) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quote` (`quote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='报价单明细';
