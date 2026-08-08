<?php
/**
 * Quote 模型 — 报价单 + 明细
 */
class Quote
{
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM quotes WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** 生成报价单编号 Q20260808-001 */
    public static function generateNo(): string
    {
        $today = date('Ymd');
        $prefix = 'Q' . $today . '-';
        $stmt = Database::getInstance()->prepare(
            "SELECT quote_no FROM quotes WHERE quote_no LIKE ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetchColumn();
        if ($last) {
            $seq = (int)substr($last, -3) + 1;
        } else {
            $seq = 1;
        }
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    /** 列表查询 */
    public static function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        $keyword = trim($filters['keyword'] ?? '');
        if ($keyword !== '') {
            $where[] = '(project_name LIKE ? OR customer_name LIKE ? OR quote_no LIKE ?)';
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }

        $status = $filters['status'] ?? null;
        if ($status && $status !== 'all') {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $page = max(1, (int)($filters['page'] ?? 1));
        $pageSize = max(1, min(200, (int)($filters['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) AS c FROM quotes WHERE $whereSql");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT * FROM quotes WHERE $whereSql ORDER BY created_at DESC LIMIT $offset, $pageSize";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    /** 创建报价单（不含明细） */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $sql = "INSERT INTO quotes (quote_no, project_name, customer_name, contact_person, contact_phone,
                payment_terms, warranty, delivery_period, valid_until,
                subtotal, tax_rate, tax_amount, total_amount, status, remark, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['quote_no'],
            $data['project_name'],
            $data['customer_name'] ?: null,
            $data['contact_person'] ?: null,
            $data['contact_phone'] ?: null,
            $data['payment_terms'] ?: '预付30%，发货前付70%',
            $data['warranty'] ?: '1年',
            $data['delivery_period'] ?: null,
            $data['valid_until'] ?: null,
            $data['subtotal'] ?? 0,
            $data['tax_rate'] ?? 0.13,
            $data['tax_amount'] ?? 0,
            $data['total_amount'] ?? 0,
            $data['status'] ?? 'draft',
            $data['remark'] ?: null,
            $data['created_by'] ?? null,
        ]);
        return (int)$db->lastInsertId();
    }

    /** 更新报价单（不含明细） */
    public static function update(int $id, array $data): void
    {
        $db = Database::getInstance();
        $sql = "UPDATE quotes SET
                project_name = ?, customer_name = ?, contact_person = ?, contact_phone = ?,
                payment_terms = ?, warranty = ?, delivery_period = ?, valid_until = ?,
                subtotal = ?, tax_rate = ?, tax_amount = ?, total_amount = ?, status = ?, remark = ?
                WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['project_name'],
            $data['customer_name'] ?: null,
            $data['contact_person'] ?: null,
            $data['contact_phone'] ?: null,
            $data['payment_terms'] ?: '',
            $data['warranty'] ?: '',
            $data['delivery_period'] ?: null,
            $data['valid_until'] ?: null,
            $data['subtotal'] ?? 0,
            $data['tax_rate'] ?? 0.13,
            $data['tax_amount'] ?? 0,
            $data['total_amount'] ?? 0,
            $data['status'] ?? 'draft',
            $data['remark'] ?: null,
            $id,
        ]);
    }

    /** 删除报价单 + 所有明细 */
    public static function delete(int $id): void
    {
        $db = Database::getInstance();
        $db->prepare("DELETE FROM quote_items WHERE quote_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM quotes WHERE id = ?")->execute([$id]);
    }

    /** 获取报价单明细（含产品信息） */
    public static function getItems(int $quoteId): array
    {
        $sql = "SELECT qi.*,
                       p.name AS product_name, p.sku AS product_sku, p.cost_price AS product_cost,
                       sp.name AS self_product_name, sp.total_cost AS self_product_cost
                FROM quote_items qi
                LEFT JOIN products p ON p.id = qi.product_id
                LEFT JOIN self_products sp ON sp.id = qi.self_product_id
                WHERE qi.quote_id = ?
                ORDER BY qi.sort_order ASC, qi.id ASC";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([$quoteId]);
        return $stmt->fetchAll();
    }

    /** 保存明细（先删后插） */
    public static function saveItems(int $quoteId, array $items): void
    {
        $db = Database::getInstance();
        $db->prepare("DELETE FROM quote_items WHERE quote_id = ?")->execute([$quoteId]);

        $sql = "INSERT INTO quote_items
                (quote_id, source_type, product_id, self_product_id, item_name, spec, unit,
                 quantity, unit_price, discount, line_total, category_id, category_name, sort_order, remark)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        foreach ($items as $i => $item) {
            $stmt->execute([
                $quoteId,
                $item['source_type'] ?? 'product',
                !empty($item['product_id']) ? (int)$item['product_id'] : null,
                !empty($item['self_product_id']) ? (int)$item['self_product_id'] : null,
                ($item['source_type'] ?? '') === 'adhoc' ? ($item['item_name'] ?: null) : null,
                $item['spec'] ?: null,
                $item['unit'] ?: '套',
                $item['quantity'] ?? 1,
                $item['unit_price'] ?? 0,
                $item['discount'] ?? 1.00,
                $item['line_total'] ?? 0,
                $item['category_id'] ?: null,
                $item['category_name'] ?: null,
                $item['sort_order'] ?? $i,
                $item['remark'] ?: null,
            ]);
        }
    }
}
