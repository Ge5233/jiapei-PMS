<?php
/**
 * ProductionTask 模型 — 生产任务单（自产产品线）
 */
class ProductionTask
{
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM production_tasks WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function all(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['project_id'])) {
            $where[] = 'project_id = ?';
            $params[] = (int)$filters['project_id'];
        }
        $whereSql = implode(' AND ', $where);
        $stmt = Database::getInstance()->prepare(
            "SELECT t.*, p.name AS project_name
             FROM production_tasks t
             LEFT JOIN projects p ON p.id = t.project_id
             WHERE $whereSql
             ORDER BY t.updated_at DESC, t.id DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 生成下一个任务单号
     */
    public static function nextTaskNo(): string
    {
        $prefix = 'RW' . date('Ymd');
        $stmt = Database::getInstance()->prepare(
            "SELECT task_no FROM production_tasks WHERE task_no LIKE ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetchColumn();
        if ($last) {
            $num = (int)substr($last, strlen($prefix) + 1);
            return $prefix . '-' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
        }
        return $prefix . '-001';
    }

    /**
     * 为项目里的自产产品生成生产任务单（复制 BOM 模板）
     * @return array 生成的任务ID列表
     */
    public static function generateFromProject(int $projectId, array $requirementMap = []): array
    {
        $db = Database::getInstance();
        // 遍历项目所有清单，找自产产品（主材或配件中 source_type='self_product'）
        $stmt = $db->prepare(
            "SELECT i.*, sp.name, sp.model_no, sp.spec, sp.unit
             FROM project_list_items i
             JOIN project_lists l ON l.id = i.list_id
             JOIN self_products sp ON sp.id = i.self_product_id
             WHERE l.project_id = ? AND i.source_type = 'self_product' AND i.parent_id IS NULL
             ORDER BY l.sort_order, i.sort_order, i.id"
        );
        $stmt->execute([$projectId]);
        $selfProducts = $stmt->fetchAll();

        $ids = [];
        foreach ($selfProducts as $sp) {
            $taskNo = self::nextTaskNo();
            $db->prepare(
                "INSERT INTO production_tasks (task_no, project_id, self_product_id, product_name, model_no, spec, unit, quantity, requirement, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
            )->execute([
                $taskNo,
                $projectId,
                (int)$sp['self_product_id'],
                $sp['name'],
                $sp['model_no'] ?: null,
                $sp['spec'] ?: null,
                $sp['unit'] ?: '套',
                $sp['quantity'] ?? 1,
                $requirementMap[(int)$sp['id']] ?? null,
                $_SESSION['user_id'] ?? null,
            ]);
            $taskId = (int)$db->lastInsertId();

            // 复制 BOM 模板（主材 + 配件）
            self::copyBomTemplate($taskId, (int)$sp['self_product_id']);

            $ids[] = $taskId;
        }
        return $ids;
    }

    /**
     * 复制自产产品 BOM 模板到任务单
     */
    private static function copyBomTemplate(int $taskId, int $selfProductId): void
    {
        $db = Database::getInstance();
        $bom = SelfProduct::getBom($selfProductId);

        $insItem = $db->prepare(
            "INSERT INTO production_task_items (task_id, product_id, bom_self_product_id, item_name, spec, unit, quantity, unit_cost, module_name, parent_id, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($bom['modules'] as $mi => $mod) {
            foreach ($mod['items'] as $ii => $it) {
                $insItem->execute([
                    $taskId,
                    $it['product_id'] ?: null,
                    $it['self_product_id'] ?: null,
                    $it['item_name'] ?: null,
                    $it['spec'] ?: null,
                    $it['unit'] ?: null,
                    $it['quantity'] ?? 1,
                    $it['unit_price'] ?? 0,
                    $mod['name'] ?: null,
                    null,
                    $ii,
                ]);
                $mainId = (int)$db->lastInsertId();

                foreach ($it['subs'] ?? [] as $si => $sub) {
                    $insItem->execute([
                        $taskId,
                        $sub['product_id'] ?: null,
                        $sub['self_product_id'] ?: null,
                        $sub['item_name'] ?: null,
                        $sub['spec'] ?: null,
                        $sub['unit'] ?: null,
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

    /**
     * 获取任务单完整 BOM（模块层级），含外采/自产产品名称和最新单价
     */
    public static function fullBom(int $taskId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT i.*, p.name AS product_name, p.sku AS product_sku, p.cost_price AS product_cost_price, p.spec AS product_spec,
                    sp.name AS sp_name, sp.total_cost AS sp_cost
             FROM production_task_items i
             LEFT JOIN products p ON p.id = i.product_id
             LEFT JOIN self_products sp ON sp.id = i.bom_self_product_id
             WHERE i.task_id = ?
             ORDER BY i.sort_order, i.id"
        );
        $stmt->execute([$taskId]);
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
            'product_id' => $r['product_id'],
            'self_product_id' => $r['bom_self_product_id'],
            'item_name' => $r['item_name'],
            'spec' => $r['product_id'] ? ($r['product_spec'] ?? '') : ($r['spec'] ?? ''),
            'unit' => $r['unit'],
            'quantity' => $r['quantity'],
            'unit_price' => $r['product_id'] ? (float)($r['product_cost_price'] ?? 0)
                : ($r['bom_self_product_id'] ? (float)($r['sp_cost'] ?? 0)
                : (float)($r['unit_cost'] ?? 0)),
            'module_name' => $r['module_name'],
            'remark' => $r['remark'],
        ];
    }

    /**
     * 保存任务单 BOM（先删后插），支持模块层级
     */
    public static function saveBom(int $taskId, array $modules): void
    {
        $db = Database::getInstance();
        $db->prepare("DELETE FROM production_task_items WHERE task_id = ?")->execute([$taskId]);

        $insItem = $db->prepare(
            "INSERT INTO production_task_items (task_id, product_id, bom_self_product_id, item_name, spec, unit, quantity, unit_cost, module_name, parent_id, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($modules as $mi => $mod) {
            foreach ($mod['items'] ?? [] as $ii => $item) {
                $src = $item['source_type'] ?? 'adhoc';
                $insItem->execute([
                    $taskId,
                    $src === 'product' ? (int)($item['product_id'] ?? 0) : null,
                    $src === 'self_product' ? (int)($item['self_product_id'] ?? 0) : null,
                    $src === 'adhoc' ? ($item['item_name'] ?: null) : null,
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
                    $ssrc = $sub['source_type'] ?? 'adhoc';
                    $insItem->execute([
                        $taskId,
                        $ssrc === 'product' ? (int)($sub['product_id'] ?? 0) : null,
                        $ssrc === 'self_product' ? (int)($sub['self_product_id'] ?? 0) : null,
                        $ssrc === 'adhoc' ? ($sub['item_name'] ?: null) : null,
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

    public static function updateStatus(int $id, string $status): void
    {
        Database::getInstance()->prepare(
            "UPDATE production_tasks SET status = ? WHERE id = ?"
        )->execute([$status, $id]);
    }

    public static function delete(int $id): void
    {
        $db = Database::getInstance();
        $db->prepare("DELETE FROM production_task_items WHERE task_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM production_tasks WHERE id = ?")->execute([$id]);
    }
}
