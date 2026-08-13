<?php
/**
 * Project 模型 — 项目制管理（项目 + 多清单 + 模块层级）
 */
class Project
{
    public static function all(): array
    {
        return Database::getInstance()->query(
            "SELECT * FROM projects ORDER BY updated_at DESC, id DESC"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $db->prepare("INSERT INTO projects (name, customer_name, status, remark, created_by) VALUES (?, ?, ?, ?, ?)")
           ->execute([
               $data['name'],
               $data['customer_name'] ?? null,
               $data['status'] ?? 'active',
               $data['remark'] ?? null,
               $data['created_by'] ?? null,
           ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::getInstance()->prepare(
            "UPDATE projects SET name = ?, customer_name = ?, status = ?, remark = ? WHERE id = ?"
        )->execute([
            $data['name'],
            $data['customer_name'] ?? null,
            $data['status'] ?? 'active',
            $data['remark'] ?? null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $db = Database::getInstance();
        // 删清单物料
        $listIds = $db->query("SELECT id FROM project_lists WHERE project_id = $id")->fetchAll(\PDO::FETCH_COLUMN);
        if ($listIds) {
            $db->exec("DELETE FROM project_list_items WHERE list_id IN (" . implode(',', array_map('intval', $listIds)) . ")");
        }
        $db->prepare("DELETE FROM project_lists WHERE project_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
    }

    /**
     * 获取项目的清单列表（含每张清单的完整 BOM）
     */
    public static function lists(int $projectId): array
    {
        $stmt = Database::getInstance()->prepare(
            "SELECT * FROM project_lists WHERE project_id = ? ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute([$projectId]);
        $lists = $stmt->fetchAll();

        foreach ($lists as &$list) {
            $list['modules'] = self::listBom((int)$list['id']);
        }
        unset($list);
        return $lists;
    }

    /**
     * 获取某清单的完整 BOM（模块 → 主材 → 配件）
     */
    public static function listBom(int $listId): array
    {
        $stmt = Database::getInstance()->prepare(
            "SELECT i.*, p.name AS product_name, p.sku AS product_sku, p.cost_price AS product_cost_price, p.spec AS product_spec,
                    sp.name AS sp_name, sp.total_cost AS sp_cost
             FROM project_list_items i
             LEFT JOIN products p ON p.id = i.product_id
             LEFT JOIN self_products sp ON sp.id = i.self_product_id
             WHERE i.list_id = ?
             ORDER BY i.sort_order, i.id"
        );
        $stmt->execute([$listId]);
        $rows = $stmt->fetchAll();

        $mainItems = array_values(array_filter($rows, fn($r) => empty($r['parent_id'])));
        $subItems = array_filter($rows, fn($r) => !empty($r['parent_id']));

        $moduleMap = [];
        foreach ($mainItems as $it) {
            $modName = $it['module_name'] ?: '未分类';
            if (!isset($moduleMap[$modName])) {
                $moduleMap[$modName] = ['name' => $modName, 'items' => []];
            }
            $out = self::formatItem($it);
            $out['subs'] = [];
            foreach ($subItems as $sub) {
                if ((int)$sub['parent_id'] === (int)$it['id']) {
                    $out['subs'][] = self::formatItem($sub);
                }
            }
            $moduleMap[$modName]['items'][] = $out;
        }
        return array_values($moduleMap);
    }

    private static function formatItem(array $r): array
    {
        return [
            'id' => $r['id'],
            'source_type' => $r['source_type'],
            'product_id' => $r['product_id'],
            'self_product_id' => $r['self_product_id'],
            'item_name' => $r['item_name'],
            'spec' => $r['product_id'] ? ($r['product_spec'] ?? '') : ($r['spec'] ?? ''),
            'unit' => $r['unit'],
            'quantity' => $r['quantity'],
            'unit_price' => $r['product_id'] ? (float)($r['product_cost_price'] ?? 0)
                : ($r['self_product_id'] ? (float)($r['sp_cost'] ?? 0)
                : (float)($r['unit_price'] ?? 0)),
            'module_name' => $r['module_name'],
            'remark' => $r['remark'],
        ];
    }

    /**
     * 保存项目全部清单（先删后插）
     * @param array $lists [{name, modules:[{name, items:[{...sub_items:[...]}]}]}]
     */
    public static function saveLists(int $projectId, array $lists): void
    {
        $db = Database::getInstance();

        // 删旧
        $oldListIds = $db->query("SELECT id FROM project_lists WHERE project_id = $projectId")->fetchAll(\PDO::FETCH_COLUMN);
        if ($oldListIds) {
            $db->exec("DELETE FROM project_list_items WHERE list_id IN (" . implode(',', array_map('intval', $oldListIds)) . ")");
        }
        $db->prepare("DELETE FROM project_lists WHERE project_id = ?")->execute([$projectId]);

        $insItem = $db->prepare(
            "INSERT INTO project_list_items (list_id, source_type, product_id, self_product_id, item_name, spec, unit, quantity, unit_price, module_name, parent_id, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($lists as $li => $list) {
            $db->prepare("INSERT INTO project_lists (project_id, name, sort_order) VALUES (?, ?, ?)")
               ->execute([$projectId, $list['name'] ?? ('清单' . ($li + 1)), $li]);
            $listId = (int)$db->lastInsertId();

            foreach ($list['modules'] ?? [] as $mi => $mod) {
                foreach ($mod['items'] ?? [] as $ii => $item) {
                    $insItem->execute([
                        $listId,
                        $item['source_type'] ?? 'product',
                        !empty($item['product_id']) ? (int)$item['product_id'] : null,
                        !empty($item['self_product_id']) ? (int)$item['self_product_id'] : null,
                        ($item['source_type'] ?? '') === 'adhoc' ? ($item['item_name'] ?: null) : null,
                        $item['spec'] ?? null,
                        $item['unit'] ?? null,
                        $item['quantity'] ?? 1,
                        $item['unit_price'] ?? 0,
                        $mod['name'] ?: null,
                        null,
                        $ii,
                    ]);
                    $mainId = (int)$db->lastInsertId();

                    foreach ($item['sub_items'] ?? [] as $si => $sub) {
                        $insItem->execute([
                            $listId,
                            $sub['source_type'] ?? 'adhoc',
                            !empty($sub['product_id']) ? (int)$sub['product_id'] : null,
                            !empty($sub['self_product_id']) ? (int)$sub['self_product_id'] : null,
                            ($sub['source_type'] ?? '') === 'adhoc' ? ($sub['item_name'] ?: null) : null,
                            $sub['spec'] ?? null,
                            $sub['unit'] ?? null,
                            $sub['quantity'] ?? 1,
                            $sub['unit_price'] ?? 0,
                            $mod['name'] ?: null,
                            $mainId,
                            $si,
                        ]);
                    }
                }
            }
        }
    }
}
