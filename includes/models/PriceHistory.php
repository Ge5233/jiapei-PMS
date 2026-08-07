<?php
/**
 * PriceHistory 模型
 */
class PriceHistory
{
    public static function listByProduct(int $productId, int $limit = 50): array
    {
        $stmt = Database::getInstance()->prepare(
            "SELECT ph.*, u.name AS user_name
             FROM price_history ph
             LEFT JOIN users u ON u.id = ph.changed_by
             WHERE ph.product_id = ?
             ORDER BY ph.changed_at DESC, ph.id DESC
             LIMIT ?"
        );
        $stmt->execute([$productId, $limit]);
        return $stmt->fetchAll();
    }
}
