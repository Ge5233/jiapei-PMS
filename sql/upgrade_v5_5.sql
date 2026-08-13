-- ============================================================
-- upgrade_v5_5.sql — 自产产品增加 SKU（jp 前缀，自动生成）
-- ============================================================

-- 1. 加 sku 字段
ALTER TABLE `self_products`
  ADD COLUMN `sku` VARCHAR(50) DEFAULT NULL COMMENT '产品编码（jp开头，自动生成）' AFTER `id`,
  ADD UNIQUE KEY `uk_sku` (`sku`);

-- 2. 给已有自产产品补生成 SKU（按 id 顺序：jp001、jp002...）
UPDATE `self_products` sp
LEFT JOIN (
  SELECT id, CONCAT('jp', LPAD(@rownum := @rownum + 1, 3, '0')) AS new_sku
  FROM self_products, (SELECT @rownum := 0) r
  ORDER BY id ASC
) t ON t.id = sp.id
SET sp.sku = t.new_sku
WHERE sp.sku IS NULL;
