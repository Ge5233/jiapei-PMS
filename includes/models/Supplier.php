<?php
/**
 * Supplier 模型
 */
class Supplier
{
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByName(string $name): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM suppliers WHERE name = ?");
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        $keyword = trim($filters['keyword'] ?? '');
        if ($keyword !== '') {
            $where[] = '(name LIKE ? OR contact LIKE ? OR phone LIKE ?)';
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }

        $status = $filters['status'] ?? null;
        if ($status !== null && $status !== '' && $status !== false) {
            $where[] = 'status = ?';
            $params[] = (int)$status;
        }

        $whereSql = implode(' AND ', $where);

        $page = max(1, (int)($filters['page'] ?? 1));
        $pageSize = max(1, min(200, (int)($filters['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) AS c FROM suppliers WHERE $whereSql");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT s.*, 
                       (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.id) AS product_count
                FROM suppliers s
                WHERE $whereSql
                ORDER BY s.status DESC, s.id DESC
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

    public static function create(array $data): int
    {
        $stmt = Database::getInstance()->prepare(
            "INSERT INTO suppliers (name, contact, phone, email, address, bank_name, bank_account, license_no, remark, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['name'],
            $data['contact'] ?: null,
            $data['phone'] ?: null,
            $data['email'] ?: null,
            $data['address'] ?: null,
            $data['bank_name'] ?: null,
            $data['bank_account'] ?: null,
            $data['license_no'] ?: null,
            $data['remark'] ?: null,
            $data['status'] ?? 1,
        ]);
        return (int)Database::getInstance()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::getInstance()->prepare(
            "UPDATE suppliers SET
                name = ?, contact = ?, phone = ?, email = ?, address = ?,
                bank_name = ?, bank_account = ?, license_no = ?, remark = ?, status = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $data['name'],
            $data['contact'] ?: null,
            $data['phone'] ?: null,
            $data['email'] ?: null,
            $data['address'] ?: null,
            $data['bank_name'] ?: null,
            $data['bank_account'] ?: null,
            $data['license_no'] ?: null,
            $data['remark'] ?: null,
            $data['status'] ?? 1,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $db = Database::getInstance();
        // 检查是否有产品关联
        $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('该供应商下还有产品，无法删除');
        }
        $db->prepare("DELETE FROM suppliers WHERE id = ?")->execute([$id]);
    }

    /**
     * 用于下拉选项
     */
    public static function allActive(): array
    {
        return Database::getInstance()->query(
            "SELECT id, name, contact, phone FROM suppliers WHERE status = 1 ORDER BY name ASC"
        )->fetchAll();
    }

    public static function count(): int
    {
        return (int)Database::getInstance()->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    }
}
