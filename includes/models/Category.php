<?php
/**
 * Category 模型（两级分类）
 */
class Category
{
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * 全部一级分类（含其二级）
     */
    public static function allGrouped(): array
    {
        $all = Database::getInstance()->query(
            "SELECT * FROM categories ORDER BY parent_id ASC, sort_order ASC, id ASC"
        )->fetchAll();
        $grouped = [];
        $byId = [];
        foreach ($all as $c) {
            if ((int)$c['parent_id'] === 0) {
                $c['children'] = [];
                $grouped[$c['id']] = $c;
                $byId[$c['id']] = &$grouped[$c['id']];
            }
        }
        foreach ($all as $c) {
            if ((int)$c['parent_id'] > 0 && isset($byId[$c['parent_id']])) {
                $byId[$c['parent_id']]['children'][] = $c;
            }
        }
        return array_values($grouped);
    }

    public static function allLevel1(): array
    {
        return Database::getInstance()->query(
            "SELECT * FROM categories WHERE parent_id = 0 ORDER BY sort_order ASC, id ASC"
        )->fetchAll();
    }

    public static function childrenOf(int $parentId): array
    {
        $stmt = Database::getInstance()->prepare(
            "SELECT * FROM categories WHERE parent_id = ? ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute([$parentId]);
        return $stmt->fetchAll();
    }

    public static function create(string $name, int $parentId, int $sortOrder = 0): int
    {
        $subId = 0;
        $parentSortId = 0;
        
        if ($parentId === 0) {
            // 父分类：自动计算 parent_sort_id
            $stmt = Database::getInstance()->prepare(
                "SELECT COALESCE(MAX(parent_sort_id), 0) + 1 AS next_parent_sort_id FROM categories WHERE parent_id = 0"
            );
            $stmt->execute();
            $parentSortId = (int)$stmt->fetchColumn();
        } else {
            // 子分类：自动计算 sub_id
            $stmt = Database::getInstance()->prepare(
                "SELECT COALESCE(MAX(sub_id), 0) + 1 AS next_sub_id FROM categories WHERE parent_id = ?"
            );
            $stmt->execute([$parentId]);
            $subId = (int)$stmt->fetchColumn();
        }
        
        $stmt = Database::getInstance()->prepare(
            "INSERT INTO categories (name, parent_id, parent_sort_id, sub_id, sort_order) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$name, $parentId, $parentSortId, $subId, $sortOrder]);
        return (int)Database::getInstance()->lastInsertId();
    }

    public static function update(int $id, string $name, int $parentId, int $sortOrder): void
    {
        $stmt = Database::getInstance()->prepare(
            "UPDATE categories SET name = ?, parent_id = ?, sort_order = ? WHERE id = ?"
        );
        $stmt->execute([$name, $parentId, $sortOrder, $id]);
    }

    /**
     * 修改父级（"移动"操作）
     */
    public static function moveTo(int $id, int $newParentId): void
    {
        $stmt = Database::getInstance()->prepare(
            "UPDATE categories SET parent_id = ? WHERE id = ?"
        );
        $stmt->execute([$newParentId, $id]);
    }

    /**
     * 获取"未分类"分类 ID
     */
    public static function getUncategorizedId(): int
    {
        return 1; // 固定 ID
    }

    public static function delete(int $id): void
    {
        $db = Database::getInstance();
        
        // 禁止删除"未分类"
        if ($id === self::getUncategorizedId()) {
            throw new RuntimeException('不能删除"未分类"');
        }
        
        // 如果是父级，检查是否有子分类
        $stmt = $db->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('该分类下还有子分类，请先删除子分类');
        }
        
        // 把该分类下的产品移到"未分类"
        $uncategorizedId = self::getUncategorizedId();
        $stmt = $db->prepare("UPDATE products SET category_id = ? WHERE category_id = ?");
        $stmt->execute([$uncategorizedId, $id]);
        
        // 删除分类
        $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    }

    /**
     * 取某分类下所有二级 ID（递归，因为支持两级所以就是一层）
     */
    public static function descendantIds(int $parentId): array
    {
        $stmt = Database::getInstance()->prepare(
            "SELECT id FROM categories WHERE parent_id = ?"
        );
        $stmt->execute([$parentId]);
        return array_map(fn($r) => (int)$r['id'], $stmt->fetchAll());
    }

    /**
     * 批量重排序（一级或某一级下的二级）
     * @param array<int> $ids 排序后的 ID 列表
     */
    public static function reorder(array $ids): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
        $order = 0;
        foreach ($ids as $id) {
            $stmt->execute([$order, (int)$id]);
            $order++;
        }
    }
}
