-- ============================================================
-- upgrade_v5_2.sql — v5.0 项目清单（多清单 + 模块层级）
-- 替换之前的平铺 project_products 表
-- ============================================================

-- 1. 项目清单表（一个项目多张清单）
CREATE TABLE IF NOT EXISTS `project_lists` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL COMMENT '所属项目ID',
  `name` VARCHAR(200) NOT NULL COMMENT '清单名称（如：灌溉系统、环控系统）',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `remark` VARCHAR(200) DEFAULT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='项目清单';

-- 2. 清单物料表（模块 → 主材 → 配件 层级）
CREATE TABLE IF NOT EXISTS `project_list_items` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `list_id` INT UNSIGNED NOT NULL COMMENT '所属清单ID',
  `source_type` ENUM('product','self_product','adhoc') NOT NULL DEFAULT 'product' COMMENT '外采/自产/临时',
  `product_id` INT UNSIGNED DEFAULT NULL COMMENT '外采产品ID',
  `self_product_id` INT UNSIGNED DEFAULT NULL COMMENT '自产产品ID',
  `item_name` VARCHAR(200) DEFAULT NULL COMMENT '临时物料名称',
  `spec` VARCHAR(200) DEFAULT NULL COMMENT '规格',
  `unit` VARCHAR(20) DEFAULT NULL COMMENT '单位',
  `quantity` DECIMAL(12,4) NOT NULL DEFAULT 1.0000 COMMENT '数量',
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
  `module_name` VARCHAR(100) DEFAULT NULL COMMENT '模块名',
  `parent_id` INT UNSIGNED DEFAULT NULL COMMENT '父级ID，NULL=主材，指向主材ID=配件',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `remark` VARCHAR(200) DEFAULT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_list` (`list_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_self_product` (`self_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='清单物料（模块层级）';

-- 3. 删除旧的平铺表（如存在）
DROP TABLE IF EXISTS `project_products`;
