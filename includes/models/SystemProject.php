<?php
/**
 * SystemProject 模型 — 大型系统 BOM
 */
class SystemProject
{
    public static function all(): array
    {
        return Database::getInstance()->query(
            "SELECT * FROM system_projects ORDER BY id DESC"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM system_projects WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $db->prepare("INSERT INTO system_projects (name, description, status) VALUES (?, ?, ?)")
           ->execute([$data['name'], $data['description'] ?? '', $data['status'] ?? 1]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::getInstance()->prepare(
            "UPDATE system_projects SET name = ?, description = ?, status = ? WHERE id = ?"
        )->execute([$data['name'], $data['description'] ?? '', $data['status'] ?? 1, $id]);
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->prepare("DELETE FROM system_projects WHERE id = ?")->execute([$id]);
    }

    /** 获取项目完整 BOM（模块 + 主材 + 紧固件） */
    public static function fullBom(int $projectId): array
    {
        $db = Database::getInstance();
        $modules = $db->prepare(
            "SELECT * FROM system_modules WHERE project_id = ? ORDER BY sort_order, id"
        );
        $modules->execute([$projectId]);
        $modules = $modules->fetchAll();

        foreach ($modules as &$mod) {
            $items = $db->prepare(
                "SELECT i.*, p.name AS product_name, p.sku AS product_sku, p.cost_price AS product_cost_price,
                        sp.name AS sp_name, sp.total_cost AS sp_cost
                 FROM system_items i
                 LEFT JOIN products p ON p.id = i.product_id
                 LEFT JOIN self_products sp ON sp.id = i.self_product_id
                 WHERE i.module_id = ? ORDER BY i.sort_order, i.id"
            );
            $items->execute([$mod['id']]);
            $items = $items->fetchAll();

            foreach ($items as &$it) {
                $subs = $db->prepare(
                    "SELECT s.*, p.name AS product_name, p.sku AS product_sku, p.cost_price AS product_cost_price,
                            sp.name AS sp_name, sp.total_cost AS sp_cost
                     FROM system_sub_items s
                     LEFT JOIN products p ON p.id = s.product_id
                     LEFT JOIN self_products sp ON sp.id = s.self_product_id
                     WHERE s.item_id = ? ORDER BY s.sort_order, s.id"
                );
                $subs->execute([$it['id']]);
                $subs = $subs->fetchAll();
                $it['sub_items'] = $subs;
            }
            $mod['items'] = $items;
        }
        return $modules;
    }

    /** 保存项目全部 BOM */
    public static function saveBom(int $projectId, array $modules): void
    {
        $db = Database::getInstance();

        // 删旧数据
        $oldMods = $db->query("SELECT id FROM system_modules WHERE project_id = $projectId")->fetchAll(\PDO::FETCH_COLUMN);
        if ($oldMods) {
            $oldItemIds = $db->query(
                "SELECT id FROM system_items WHERE module_id IN (" . implode(',', array_map('intval', $oldMods)) . ")"
            )->fetchAll(\PDO::FETCH_COLUMN);
            if ($oldItemIds) {
                $db->exec("DELETE FROM system_sub_items WHERE item_id IN (" . implode(',', array_map('intval', $oldItemIds)) . ")");
            }
            $db->exec("DELETE FROM system_items WHERE module_id IN (" . implode(',', array_map('intval', $oldMods)) . ")");
            $db->exec("DELETE FROM system_modules WHERE project_id = $projectId");
        }

        // 重新写入
        foreach ($modules as $mi => $mod) {
            $db->prepare(
                "INSERT INTO system_modules (project_id, name, module_no, sort_order) VALUES (?, ?, ?, ?)"
            )->execute([$projectId, $mod['name'], $mod['module_no'] ?? '', $mi]);

            $moduleId = (int)$db->lastInsertId();

            foreach ($mod['items'] ?? [] as $ii => $item) {
                $db->prepare(
                    "INSERT INTO system_items (module_id, source_type, product_id, self_product_id, item_name, spec, unit, quantity, unit_price, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $moduleId,
                    $item['source_type'] ?? 'product',
                    !empty($item['product_id']) ? (int)$item['product_id'] : null,
                    !empty($item['self_product_id']) ? (int)$item['self_product_id'] : null,
                    $item['item_name'] ?? null,
                    $item['spec'] ?? '',
                    $item['unit'] ?? '',
                    $item['quantity'] ?? 0,
                    $item['unit_price'] ?? 0,
                    $ii,
                ]);

                $itemId = (int)$db->lastInsertId();

                foreach ($item['sub_items'] ?? [] as $si => $sub) {
                    $db->prepare(
                        "INSERT INTO system_sub_items (item_id, source_type, product_id, self_product_id, item_name, spec, unit, quantity, unit_price, sort_order)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        $itemId,
                        $sub['source_type'] ?? 'adhoc',
                        !empty($sub['product_id']) ? (int)$sub['product_id'] : null,
                        !empty($sub['self_product_id']) ? (int)$sub['self_product_id'] : null,
                        $sub['item_name'] ?? null,
                        $sub['spec'] ?? '',
                        $sub['unit'] ?? '',
                        $sub['quantity'] ?? 0,
                        $sub['unit_price'] ?? 0,
                        $si,
                    ]);
                }
            }
        }
    }
}
