-- ============================================================
-- upgrade_v5_1.sql — v5.0 生产任务单（自产产品线）
-- ============================================================

CREATE TABLE IF NOT EXISTS `production_tasks` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `task_no` VARCHAR(30) NOT NULL COMMENT '任务单号 RW20260813-001',
  `project_id` INT UNSIGNED NOT NULL COMMENT '所属项目ID',
  `self_product_id` INT UNSIGNED NOT NULL COMMENT '要生产的自产产品ID',
  `product_name` VARCHAR(200) DEFAULT NULL COMMENT '产品名（冗余）',
  `model_no` VARCHAR(50) DEFAULT NULL COMMENT '型号（冗余）',
  `spec` VARCHAR(200) DEFAULT NULL COMMENT '规格（冗余）',
  `unit` VARCHAR(20) DEFAULT '套' COMMENT '单位（冗余）',
  `quantity` DECIMAL(12,4) NOT NULL DEFAULT 1.0000 COMMENT '生产数量',
  `requirement` TEXT COMMENT '需求说明（项目经理写的特殊要求）',
  `status` ENUM('pending','confirmed') NOT NULL DEFAULT 'pending' COMMENT '待确认/已确认',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_no` (`task_no`),
  KEY `idx_project` (`project_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='生产任务单';

CREATE TABLE IF NOT EXISTS `production_task_items` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `task_id` INT UNSIGNED NOT NULL COMMENT '所属任务单ID',
  `product_id` INT UNSIGNED DEFAULT NULL COMMENT '外采产品ID',
  `bom_self_product_id` INT UNSIGNED DEFAULT NULL COMMENT '自产产品ID（嵌套）',
  `item_name` VARCHAR(200) DEFAULT NULL COMMENT '临时物料名称',
  `spec` VARCHAR(200) DEFAULT NULL COMMENT '规格',
  `unit` VARCHAR(20) DEFAULT NULL COMMENT '单位',
  `quantity` DECIMAL(12,4) NOT NULL DEFAULT 1.0000 COMMENT '用量',
  `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
  `module_name` VARCHAR(100) DEFAULT NULL COMMENT '模块名',
  `parent_id` INT UNSIGNED DEFAULT NULL COMMENT '父级ID，NULL=主材，指向主材ID=配件',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `remark` VARCHAR(200) DEFAULT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task` (`task_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='生产任务BOM明细（BOM实例，可微改）';
