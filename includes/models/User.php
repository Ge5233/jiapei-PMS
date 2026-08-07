<?php
/**
 * User 模型
 */
class User
{
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(): array
    {
        $stmt = Database::getInstance()->query("SELECT id, username, name, role, status, created_at FROM users ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public static function create(string $username, string $password, string $name, string $role, int $status = 1): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = Database::getInstance()->prepare(
            "INSERT INTO users (username, password_hash, name, role, status) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$username, $hash, $name, $role, $status]);
        return (int)Database::getInstance()->lastInsertId();
    }

    public static function update(int $id, ?string $password, ?string $name, ?string $role, ?int $status): void
    {
        $sets = [];
        $params = [];
        if ($password !== null && $password !== '') {
            $sets[] = 'password_hash = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        if ($name !== null) {
            $sets[] = 'name = ?';
            $params[] = $name;
        }
        if ($role !== null) {
            $sets[] = 'role = ?';
            $params[] = $role;
        }
        if ($status !== null) {
            $sets[] = 'status = ?';
            $params[] = $status;
        }
        if (empty($sets)) return;
        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::getInstance()->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
    }
}
