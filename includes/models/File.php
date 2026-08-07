<?php
/**
 * File 模型
 */
class FileModel
{
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM product_files WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listByProduct(int $productId): array
    {
        $stmt = Database::getInstance()->prepare(
            "SELECT * FROM product_files WHERE product_id = ? ORDER BY uploaded_at DESC, id DESC"
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    public static function create(int $productId, string $originalName, string $storedName, string $fileType, int $fileSize): int
    {
        $stmt = Database::getInstance()->prepare(
            "INSERT INTO product_files (product_id, original_name, stored_name, file_type, file_size, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $productId,
            $originalName,
            $storedName,
            $fileType,
            $fileSize,
            $_SESSION['user_id'] ?? null,
        ]);
        return (int)Database::getInstance()->lastInsertId();
    }

    public static function delete(int $id): ?string
    {
        $file = self::find($id);
        if (!$file) return null;
        Database::getInstance()->prepare("DELETE FROM product_files WHERE id = ?")->execute([$id]);
        return $file['stored_name'];
    }
}
