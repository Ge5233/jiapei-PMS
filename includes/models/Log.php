<?php
/**
 * Log 模型
 */
class Log
{
    /**
     * 记录操作日志（兼容老代码，底层调用 logAction 全局函数）
     * 规范用法是直接调 logAction()；这里保留方法名以防外部代码误用
     */
    public static function record(string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void
    {
        logAction($action, $targetType, $targetId, $details);
    }

    public static function list(int $page = 1, int $pageSize = 30): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $pageSize;
        $total = (int)Database::getInstance()->query("SELECT COUNT(*) FROM operation_logs")->fetchColumn();
        $stmt = Database::getInstance()->prepare(
            "SELECT * FROM operation_logs ORDER BY id DESC LIMIT $pageSize OFFSET $offset"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }
}
