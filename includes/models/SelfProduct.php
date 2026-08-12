<?php
/**
 * SelfProduct 模型 — 自产产品 + BOM 物料清单
 */
class SelfProduct
{
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM self_products WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * 列表查询
     */
    public static function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        $keyword = trim($filters['keyword'] ?? '');
        if ($keyword !== '') {
            $where[] = '(name LIKE ? OR model_no LIKE ?)';
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }

        $status = $filters['status'] ?? null;
        if ($status !== null && $status !== '') {
            $where[] = 'status = ?';
            $params[] = (int)$status;
        }

        $whereSql = implode(' AND ', $where);

        $page = max(1, (int)($filters['page'] ?? 1));
        $pageSize = max(1, min(200, (int)($filters['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) AS c FROM self_products WHERE $whereSql");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT * FROM self_products
                WHERE $whereSql
                ORDER BY updated_at DESC
                LIMIT $offset, $pageSize";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 创建自产产品（不含 BOM）
     * @return int 新 ID
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO self_products (name, image, model_no, spec, unit, description,
                labor_cost, overhead_cost, other_cost, material_cost, total_cost,
                guide_price, min_discount, guide_margin_rate, min_margin_rate, cost_remark, status, remark, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['image'] ?: null,
            $data['model_no'] ?: null,
            $data['spec'] ?: null,
            $data['unit'] ?: '套',
            $data['description'] ?: null,
            $data['labor_cost'] ?? 0,
            $data['overhead_cost'] ?? 0,
            $data['other_cost'] ?? 0,
            $data['material_cost'] ?? 0,
            $data['total_cost'] ?? 0,
            $data['guide_price'] ?? 0,
            $data['min_discount'] ?? 1.00,
            $data['guide_margin_rate'] ?? 30.00,
            $data['min_margin_rate'] ?? 15.00,
            $data['cost_remark'] ?: null,
            $data['status'] ?? 1,
            $data['remark'] ?: null,
            $data['created_by'] ?? null,
        ]);
        return (int)Database::getInstance()->lastInsertId();
    }

    /**
     * 更新自产产品（不含 BOM，BOM 在 api 层处理）
     */
    public static function update(int $id, array $data): void
    {
        $db = Database::getInstance();

        // 处理 image：传 null 表示不更新图片；传空字符串表示删除图片
        $imageUpdate = '';
        $params = [];
        if (array_key_exists('image', $data)) {
            if ($data['image'] === '') {
                // 删除图片
                $old = self::find($id);
                if ($old && $old['image']) {
                    $imgPath = __DIR__ . '/../../uploads/' . $old['image'];
                    if (file_exists($imgPath)) @unlink($imgPath);
                }
                $imageUpdate = ', image = NULL';
            } else {
                $imageUpdate = ', image = ?';
            }
        }

        if (array_key_exists('image', $data) && $data['image'] !== null && $data['image'] !== '') {
            $params[] = $data['image'];
        }

        $sql = "UPDATE self_products SET
                name = ?, model_no = ?, spec = ?, unit = ?, description = ?,
                labor_cost = ?, overhead_cost = ?, other_cost = ?, material_cost = ?, total_cost = ?,
                guide_price = ?, min_discount = ?, guide_margin_rate = ?, min_margin_rate = ?, cost_remark = ?,
                status = ?, remark = ?
                $imageUpdate
                WHERE id = ?";

        $params = array_merge([
            $data['name'],
            $data['model_no'] ?: null,
            $data['spec'] ?: null,
            $data['unit'] ?: '套',
            $data['description'] ?: null,
            $data['labor_cost'] ?? 0,
            $data['overhead_cost'] ?? 0,
            $data['other_cost'] ?? 0,
            $data['material_cost'] ?? 0,
            $data['total_cost'] ?? 0,
            $data['guide_price'] ?? 0,
            $data['min_discount'] ?? 1.00,
            $data['guide_margin_rate'] ?? 30.00,
            $data['min_margin_rate'] ?? 15.00,
            $data['cost_remark'] ?: null,
            $data['status'] ?? 1,
            $data['remark'] ?: null,
            $id
        ], $params);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * 删除自产产品 + 所有 BOM + 图片
     */
    public static function delete(int $id): void
    {
        $db = Database::getInstance();
        $row = self::find($id);
        if (!$row) return;

        // 删主图文件
        if ($row['image']) {
            $imgPath = __DIR__ . '/../../uploads/' . $row['image'];
            if (file_exists($imgPath)) @unlink($imgPath);
        }

        // 删 BOM
        $db->prepare("DELETE FROM self_product_items WHERE self_product_id = ?")->execute([$id]);
        // 删产品
        $db->prepare("DELETE FROM self_products WHERE id = ?")->execute([$id]);
    }

    /**
     * 获取 BOM 物料列表（含外采产品最新进价），返回模块层级结构
     * @return array ['items'=>[...], 'modules'=>[{name, items:[{...subs:[...]}]}]]
     */
    public static function getBom(int $selfProductId): array
    {
        $sql = "SELECT i.*, p.name AS product_name, p.sku AS product_sku,
                       p.cost_price AS product_cost_price, p.spec AS product_spec,
                       sp.name AS bom_sp_name, sp.total_cost AS bom_sp_cost, sp.model_no AS bom_sp_model
                FROM self_product_items i
                LEFT JOIN products p ON p.id = i.product_id
                LEFT JOIN self_products sp ON sp.id = i.bom_self_product_id
                WHERE i.self_product_id = ?
                ORDER BY i.sort_order ASC, i.id ASC";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([$selfProductId]);
        $rows = $stmt->fetchAll();

        // 主材（parent_id IS NULL）
        $mainItems = array_values(array_filter($rows, fn($r) => empty($r['parent_id'])));
        // 配件
        $subItems = array_filter($rows, fn($r) => !empty($r['parent_id']));

        // 按模块分组
        $moduleMap = [];
        foreach ($mainItems as &$item) {
            $modName = $item['module_name'] ?: '未分类';
            if (!isset($moduleMap[$modName])) {
                $moduleMap[$modName] = ['name' => $modName, 'items' => []];
            }
            // 找该主材的配件
            $item['subs'] = [];
            foreach ($subItems as $sub) {
                if ((int)$sub['parent_id'] === (int)$item['id']) {
                    $item['subs'][] = $sub;
                }
            }
            $moduleMap[$modName]['items'][] = $item;
        }
        unset($item);

        return [
            'items' => $rows,
            'modules' => array_values($moduleMap),
        ];
    }

    /**
     * 计算材料成本（外采产品 × 最新进价 + 临时物料 × 手动单价）
     */
    public static function calcMaterialCost(array $items): float
    {
        $total = 0;
        foreach ($items as $item) {
            $qty = (float)$item['quantity'];
            if (!empty($item['product_id']) && isset($item['product_cost_price'])) {
                $total += $qty * (float)$item['product_cost_price'];
            } else if (!empty($item['bom_self_product_id']) && isset($item['bom_sp_cost'])) {
                $total += $qty * (float)$item['bom_sp_cost'];
            } else {
                $total += $qty * (float)$item['unit_cost'];
            }
        }
        return round($total, 2);
    }

    /**
     * 保存 BOM 物料（先删后插），支持模块层级
     * @param array $items [{product_id, bom_self_product_id, item_name, quantity, unit, unit_cost, sort_order, module_name, subs:[...]}]
     */
    public static function saveBom(int $selfProductId, array $items): void
    {
        $db = Database::getInstance();
        $db->prepare("DELETE FROM self_product_items WHERE self_product_id = ?")->execute([$selfProductId]);

        $sql = "INSERT INTO self_product_items (self_product_id, product_id, bom_self_product_id, item_name, quantity, unit, unit_cost, sort_order, module_name, parent_id, remark)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);

        foreach ($items as $i => $item) {
            // 主材
            $stmt->execute([
                $selfProductId,
                !empty($item['product_id']) ? (int)$item['product_id'] : null,
                !empty($item['bom_self_product_id']) ? (int)$item['bom_self_product_id'] : null,
                (!empty($item['product_id']) || !empty($item['bom_self_product_id'])) ? null : ($item['item_name'] ?: null),
                $item['quantity'] ?? 1,
                $item['unit'] ?: null,
                $item['unit_cost'] ?? 0,
                $item['sort_order'] ?? $i,
                $item['module_name'] ?: null,
                null, // parent_id
                $item['remark'] ?: null,
            ]);

            $mainId = (int)$db->lastInsertId();

            // 配件
            if (!empty($item['subs'])) {
                foreach ($item['subs'] as $si => $sub) {
                    $stmt->execute([
                        $selfProductId,
                        !empty($sub['product_id']) ? (int)$sub['product_id'] : null,
                        !empty($sub['bom_self_product_id']) ? (int)$sub['bom_self_product_id'] : null,
                        (!empty($sub['product_id']) || !empty($sub['bom_self_product_id'])) ? null : ($sub['item_name'] ?: null),
                        $sub['quantity'] ?? 1,
                        $sub['unit'] ?: null,
                        $sub['unit_cost'] ?? 0,
                        $sub['sort_order'] ?? $si,
                        $item['module_name'] ?: null,
                        $mainId, // parent_id 指向主材
                        $sub['remark'] ?: null,
                    ]);
                }
            }
        }
    }

    /** 统计 */
    public static function stats(): array
    {
        $db = Database::getInstance();
        $total = (int)$db->query("SELECT COUNT(*) FROM self_products")->fetchColumn();
        $active = (int)$db->query("SELECT COUNT(*) FROM self_products WHERE status = 1")->fetchColumn();
        $inactive = $total - $active;
        return ['total' => $total, 'active' => $active, 'inactive' => $inactive];
    }

    /** 下拉选项（用于报价等其他页面） */
    public static function allForSelect(): array
    {
        return Database::getInstance()->query(
            "SELECT id, name, model_no, guide_price, total_cost, guide_margin_rate, min_margin_rate
             FROM self_products WHERE status = 1
             ORDER BY name ASC"
        )->fetchAll();
    }
}
