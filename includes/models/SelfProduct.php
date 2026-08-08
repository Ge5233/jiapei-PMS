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
                guide_price, min_discount, guide_price_coefficient, min_price_coefficient, cost_remark, status, remark, created_by)
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
            $data['guide_price_coefficient'] ?? 1.600,
            $data['min_price_coefficient'] ?? 0.900,
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
                guide_price = ?, min_discount = ?, guide_price_coefficient = ?, min_price_coefficient = ?, cost_remark = ?,
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
            $data['guide_price_coefficient'] ?? 1.600,
            $data['min_price_coefficient'] ?? 0.900,
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
     * 获取 BOM 物料列表（含外采产品最新进价）
     */
    public static function getBom(int $selfProductId): array
    {
        $sql = "SELECT i.*, p.name AS product_name, p.sku AS product_sku,
                       p.cost_price AS product_cost_price
                FROM self_product_items i
                LEFT JOIN products p ON p.id = i.product_id
                WHERE i.self_product_id = ?
                ORDER BY i.sort_order ASC, i.id ASC";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([$selfProductId]);
        return $stmt->fetchAll();
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
                // 关联外采产品 → 取最新进价
                $total += $qty * (float)$item['product_cost_price'];
            } else {
                // 临时物料 → 取存的手动单价
                $total += $qty * (float)$item['unit_cost'];
            }
        }
        return round($total, 2);
    }

    /**
     * 保存 BOM 物料（先删后插）
     * @param array $items [{product_id, item_name, quantity, unit, unit_cost, sort_order, remark}, ...]
     */
    public static function saveBom(int $selfProductId, array $items): void
    {
        $db = Database::getInstance();
        $db->prepare("DELETE FROM self_product_items WHERE self_product_id = ?")->execute([$selfProductId]);

        $sql = "INSERT INTO self_product_items (self_product_id, product_id, item_name, quantity, unit, unit_cost, sort_order, remark)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        foreach ($items as $i => $item) {
            $stmt->execute([
                $selfProductId,
                !empty($item['product_id']) ? (int)$item['product_id'] : null,
                empty($item['product_id']) ? ($item['item_name'] ?: null) : null,
                $item['quantity'] ?? 1,
                $item['unit'] ?: null,
                $item['unit_cost'] ?? 0,
                $item['sort_order'] ?? $i,
                $item['remark'] ?: null,
            ]);
        }
    }

    /** 统计 */
    public static function stats(): array
    {
        $db = Database::getInstance();
        $total = (int)$db->query("SELECT COUNT(*) FROM self_products")->fetchColumn();
        $active = (int)$db->query("SELECT COUNT(*) FROM self_products WHERE status = 1")->fetchColumn();
        return ['total' => $total, 'active' => $active];
    }

    /** 下拉选项（用于报价等其他页面） */
    public static function allForSelect(): array
    {
        return Database::getInstance()->query(
            "SELECT id, name, model_no, guide_price, total_cost, guide_price_coefficient, min_price_coefficient
             FROM self_products WHERE status = 1
             ORDER BY name ASC"
        )->fetchAll();
    }
}
