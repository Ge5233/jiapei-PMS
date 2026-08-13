-- ============================================================
-- upgrade_v5_6.sql — 删除大型系统（功能已由「项目+自产产品」取代）
-- 数据已在 v4.6 迁移到自产产品
-- ============================================================

DROP TABLE IF EXISTS `system_sub_items`;
DROP TABLE IF EXISTS `system_items`;
DROP TABLE IF EXISTS `system_modules`;
DROP TABLE IF EXISTS `system_projects`;
