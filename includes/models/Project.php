<?php
/**
 * Project 模型 — 项目制管理（项目 + 项目产品）
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
        $db->prepare("DELETE FROM project_products WHERE project_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
    }

    /**
     * 获取项目产品列表（含外采/自产产品名称）
     */
    public static function products(int $projectId): array
    {
        $stmt = Database::getInstance()->prepare(
            "SELECT pp.*, p.name AS product_name, p.sku AS product_sku,
                    sp.name AS sp_name
             FROM project_products pp
             LEFT JOIN products p ON p.id = pp.product_id
             LEFT JOIN self_products sp ON sp.id = pp.self_product_id
             WHERE pp.project_id = ?
             ORDER BY pp.sort_order ASC, pp.id ASC"
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    /**
     * 保存项目产品（先删后插）
     * @param array $items [{item_type, product_id, self_product_id, item_name, spec, unit, quantity, requirement, remark}]
     */
    public static function saveProducts(int $projectId, array $items): void
    {
        $db = Database::getInstance();
        $db->prepare("DELETE FROM project_products WHERE project_id = ?")->execute([$projectId]);

        $sql = "INSERT INTO project_products
                (project_id, item_type, product_id, self_product_id, item_name, spec, unit, quantity, requirement, remark, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);

        foreach ($items as $i => $item) {
            $itemType = ($item['item_type'] ?? 'purchase') === 'self_product' ? 'self_product' : 'purchase';
            $stmt->execute([
                $projectId,
                $itemType,
                !empty($item['product_id']) ? (int)$item['product_id'] : null,
                !empty($item['self_product_id']) ? (int)$item['self_product_id'] : null,
                (!empty($item['product_id']) || !empty($item['self_product_id'])) ? null : ($item['item_name'] ?: null),
                $item['spec'] ?: null,
                $item['unit'] ?: null,
                $item['quantity'] ?? 1,
                $item['requirement'] ?: null,
                $item['remark'] ?: null,
                $i,
            ]);
        }
    }
}
