-- v4.6: 自产产品 BOM 升级为模块分组格式
-- 执行前备份：mysqldump -u pms_user -p pms_db self_product_items > backup_v4_6.sql

-- 1. 加字段
ALTER TABLE self_product_items 
  ADD COLUMN IF NOT EXISTS module_name VARCHAR(100) DEFAULT NULL COMMENT '所属模块名',
  ADD COLUMN IF NOT EXISTS parent_id INT DEFAULT NULL COMMENT '父级ID，NULL=主材，指向主材ID=配件';

-- 2. 如果大型系统有数据，迁移到自产产品
-- 创建一个"来自大型系统"的自产产品
INSERT INTO self_products (name, model_no, spec, unit, description, labor_cost, overhead_cost, other_cost, material_cost, total_cost, guide_price, min_discount, guide_margin_rate, min_margin_rate, status, created_at, updated_at)
SELECT 
  CONCAT('大型系统-', p.name),
  '',
  '',
  '套',
  p.description,
  0, 0, 0, 0, 0, 0, 1.00, 30.00, 15.00,
  p.status,
  NOW(), NOW()
FROM system_projects p
WHERE NOT EXISTS (SELECT 1 FROM self_products WHERE name LIKE CONCAT('大型系统-%', p.name, '%'));

-- 3. 迁移 BOM 物料（把 system_items 转为 self_product_items）
INSERT INTO self_product_items (self_product_id, product_id, item_name, quantity, unit, unit_price, sort_order, module_name, parent_id, created_at, updated_at)
SELECT 
  sp.id AS self_product_id,
  si.product_id,
  si.item_name,
  si.quantity,
  si.unit,
  si.unit_price,
  si.sort_order,
  m.name AS module_name,
  NULL AS parent_id,
  NOW(), NOW()
FROM system_items si
JOIN system_modules m ON si.module_id = m.id
JOIN system_projects p ON m.project_id = p.id
JOIN self_products sp ON sp.name = CONCAT('大型系统-', p.name)
LEFT JOIN self_product_items existing ON existing.self_product_id = sp.id 
  AND existing.product_id = si.product_id 
  AND existing.item_name = si.item_name
  AND existing.module_name = m.name
WHERE existing.id IS NULL;

-- 4. 迁移配件（sub_items）
INSERT INTO self_product_items (self_product_id, product_id, item_name, quantity, unit, unit_price, sort_order, module_name, parent_id, created_at, updated_at)
SELECT 
  sp.id AS self_product_id,
  sub.product_id,
  sub.item_name,
  sub.quantity,
  sub.unit,
  sub.unit_price,
  sub.sort_order,
  m.name AS module_name,
  spi.id AS parent_id,
  NOW(), NOW()
FROM system_sub_items sub
JOIN system_items si ON sub.item_id = si.id
JOIN system_modules m ON si.module_id = m.id
JOIN system_projects p ON m.project_id = p.id
JOIN self_products sp ON sp.name = CONCAT('大型系统-', p.name)
JOIN self_product_items spi ON spi.self_product_id = sp.id 
  AND spi.product_id = si.product_id 
  AND spi.item_name = si.item_name
  AND spi.module_name = m.name
  AND spi.parent_id IS NULL
LEFT JOIN self_product_items existing ON existing.self_product_id = sp.id 
  AND existing.parent_id = spi.id 
  AND existing.product_id = sub.product_id
  AND existing.item_name = sub.item_name
WHERE existing.id IS NULL;
