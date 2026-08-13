-- ============================================================
-- upgrade_v5_4.sql — 生产任务单五级状态
-- pending → requirement_confirmed → confirmed → in_production → done
-- ============================================================

ALTER TABLE `production_tasks`
  MODIFY COLUMN `status` ENUM('pending','requirement_confirmed','confirmed','in_production','done') NOT NULL DEFAULT 'pending' COMMENT '待确认/需求已确认/已确认/生产中/生产完成';
