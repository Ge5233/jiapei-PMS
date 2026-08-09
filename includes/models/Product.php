<?php
/**
 * Product 模型
 */
class Product
{
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findBySku(string $sku): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM products WHERE sku = ?");
        $stmt->execute([$sku]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * 列表查询
     * @param array{
     *   keyword?: string|null,
     *   category_id?: int|null,
     *   supplier_id?: int|null,
     *   status?: int|null,
     *   page?: int,
     *   page_size?: int
     * } $filters
     * @return array{rows: array, total: int, page: int, page_size: int}
     */
    public static function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        $keyword = trim($filters['keyword'] ?? '');
        if ($keyword !== '') {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }

        $categoryId = $filters['category_id'] ?? null;
        if ($categoryId) {
            // 如果选了一级分类，要包含其下所有二级分类的产品
            $cat = Category::find((int)$categoryId);
            if ($cat && (int)$cat['parent_id'] === 0) {
                $children = Category::childrenOf((int)$categoryId);
                $ids = array_merge([(int)$categoryId], array_column($children, 'id'));
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $where[] = "p.category_id IN ($placeholders)";
                $params = array_merge($params, $ids);
            } else {
                $where[] = 'p.category_id = ?';
                $params[] = (int)$categoryId;
            }
        }

        $supplierId = $filters['supplier_id'] ?? null;
        if ($supplierId !== null && $supplierId !== '' && $supplierId !== false) {
            $where[] = 'p.supplier_id = ?';
            $params[] = (int)$supplierId;
        }

        $status = $filters['status'] ?? null;
        if ($status !== null && $status !== '' && $status !== false) {
            $where[] = 'p.status = ?';
            $params[] = (int)$status;
        }

        $whereSql = implode(' AND ', $where);

        $page = max(1, (int)($filters['page'] ?? 1));
        $pageSize = max(1, min(200, (int)($filters['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        $sortCol = $filters['sort_col'] ?? 'p.updated_at';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        // 总数
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) AS c FROM products p WHERE $whereSql");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // 列表
        $sql = "SELECT p.*, c.name AS category_name, s.name AS supplier_name,
                       COALESCE(pc.parent_sort_id, 0) AS parent_sort_id,
                       COALESCE(c.sub_id, 0) AS sub_id
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN categories pc ON pc.id = c.parent_id
                LEFT JOIN suppliers s ON s.id = p.supplier_id
                WHERE $whereSql
                ORDER BY $sortCol $sortDir
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
     * 创建产品
     * @return int 新 ID
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO products (sku, name, category_id, spec, unit, cost_price, other_cost, guide_price, min_discount, guide_margin_rate, min_margin_rate, cost_remark, supplier_id, status, remark, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([
            $data['sku'],
            $data['name'],
            $data['category_id'] ?: null,
            $data['spec'] ?: null,
            $data['unit'] ?: null,
            $data['cost_price'] ?? 0,
            $data['other_cost'] ?? 0,
            $data['guide_price'] ?? 0,
            $data['min_discount'] ?? 1.00,
            $data['guide_margin_rate'] ?? 30.00,
            $data['min_margin_rate'] ?? 15.00,
            $data['cost_remark'] ?: null,
            $data['supplier_id'] ?: null,
            $data['status'] ?? 1,
            $data['remark'] ?: null,
            $data['created_by'] ?? null,
        ]);
        return (int)Database::getInstance()->lastInsertId();
    }

    /**
     * 更新产品，自动写价格历史
     */
    public static function update(int $id, array $data): void
    {
        $db = Database::getInstance();
        $old = self::find($id);
        if (!$old) return;

        $sql = "UPDATE products SET
                sku = ?, name = ?, category_id = ?, spec = ?, unit = ?,
                cost_price = ?, other_cost = ?, guide_price = ?, min_discount = ?, guide_margin_rate = ?, min_margin_rate = ?, cost_remark = ?,
                supplier_id = ?, status = ?, remark = ?
                WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['sku'],
            $data['name'],
            $data['category_id'] ?: null,
            $data['spec'] ?: null,
            $data['unit'] ?: null,
            $data['cost_price'] ?? 0,
            $data['other_cost'] ?? 0,
            $data['guide_price'] ?? 0,
            $data['min_discount'] ?? 1.00,
            $data['guide_margin_rate'] ?? 30.00,
            $data['min_margin_rate'] ?? 15.00,
            $data['cost_remark'] ?: null,
            $data['supplier_id'] ?: null,
            $data['status'] ?? 1,
            $data['remark'] ?: null,
            $id,
        ]);

        // 价格变更检测
        $priceFields = [
            'cost_price' => '进价',
            'guide_price' => '售价',
            'min_discount' => '折扣',
        ];
        $changedBy = $_SESSION['user_id'] ?? null;
        foreach ($priceFields as $field => $label) {
            $oldVal = (float)$old[$field];
            $newVal = (float)($data[$field] ?? 0);
            if (abs($oldVal - $newVal) > 0.001) {
                $sql = "INSERT INTO price_history (product_id, field, old_value, new_value, changed_by, remark)
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$id, $field, $oldVal, $newVal, $changedBy, $data['price_remark'] ?? null]);
            }
        }
    }

    public static function delete(int $id): void
    {
        $db = Database::getInstance();
        // 删文件记录（实际文件保留，可手动清理）
        $db->prepare("DELETE FROM product_files WHERE product_id = ?")->execute([$id]);
        // 删价格历史
        $db->prepare("DELETE FROM price_history WHERE product_id = ?")->execute([$id]);
        // 删产品
        $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    }

    public static function stats(): array
    {
        $db = Database::getInstance();
        $total = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $onSale = (int)$db->query("SELECT COUNT(*) FROM products WHERE status = 1")->fetchColumn();
        $offSale = $total - $onSale;
        $avgMargin = (float)$db->query("SELECT AVG((guide_price - cost_price) / NULLIF(guide_price, 0) * 100) FROM products WHERE guide_price > 0")->fetchColumn();
        $avgMargin = $avgMargin ? round($avgMargin, 2) : 0;
        return [
            'total' => $total,
            'on_sale' => $onSale,
            'off_sale' => $offSale,
            'avg_margin' => $avgMargin,
        ];
    }

    public static function recentUpdated(int $limit = 5): array
    {
        $stmt = Database::getInstance()->prepare(
            "SELECT p.id, p.sku, p.name, p.cost_price, p.guide_price, p.min_discount, p.updated_at,
                    c.name AS category_name, s.name AS supplier_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             ORDER BY p.updated_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * 用于报价计算器的下拉选项
     */
    public static function allForSelect(): array
    {
        $fields = canViewCost()
            ? "id, sku, name, spec, unit, cost_price, other_cost, guide_price, min_discount, guide_margin_rate, min_margin_rate"
            : "id, sku, name, spec, unit, 0 AS cost_price, 0 AS other_cost, guide_price, min_discount, guide_margin_rate, min_margin_rate";
        return Database::getInstance()->query(
            "SELECT $fields FROM products WHERE status = 1 ORDER BY sku, name ASC"
        )->fetchAll();
    }
}
