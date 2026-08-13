-- ============================================================
-- upgrade_v5_3.sql — 生产任务单三级状态（两级确认）
-- pending（待确认）→ requirement_confirmed（需求已确认）→ confirmed（正式）
-- ============================================================

ALTER TABLE `production_tasks`
  MODIFY COLUMN `status` ENUM('pending','requirement_confirmed','confirmed') NOT NULL DEFAULT 'pending' COMMENT '待确认/需求已确认/正式';
