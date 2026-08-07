<?php
/**
 * 数据库连接（PDO 单例）
 */

if (!defined('PMS_ENTRY')) {
    http_response_code(403);
    exit('Forbidden');
}

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $name = $_ENV['DB_NAME'] ?? '';
            $user = $_ENV['DB_USER'] ?? '';
            $pass = $_ENV['DB_PASS'] ?? '';
            $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=$charset";
            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException('数据库连接失败：' . $e->getMessage());
            }
        }
        return self::$instance;
    }

    /**
     * 不带数据库名的连接（用于安装时测试）
     */
    public static function getInstanceNoDb(): PDO
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        $user = $_ENV['DB_USER'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';

        $dsn = "mysql:host=$host;port=$port;charset=$charset";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * 执行 SQL 文件
     */
    public static function executeSqlFile(string $file): array
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("无法读取 SQL 文件：$file");
        }

        $statements = self::splitSql($sql);
        $executed = 0;
        $errors = [];
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--')) continue;
            try {
                self::getInstance()->exec($stmt);
                $executed++;
            } catch (PDOException $e) {
                $errors[] = "SQL 错误：{$e->getMessage()} | SQL: " . substr($stmt, 0, 100);
            }
        }
        return ['executed' => $executed, 'errors' => $errors];
    }

    private static function splitSql(string $sql): array
    {
        // 简单分句：按分号切（不处理引号内的分号，但对我们 schema 够用）
        return preg_split('/;\s*[\r\n]+/', $sql);
    }

    private function __construct() {}
    private function __clone() {}
}

/**
 * 全局快捷函数：获取 PDO 实例
 */
if (!function_exists('db')) {
    function db(): PDO
    {
        return Database::getInstance();
    }
}
