<?php
/**
 * Token使用统计API
 */

require_once '../../includes/utils/session.php';
require_once '../../includes/database.php';

// 检查管理员权限
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(401);
    echo json_encode(['error' => '未授权访问']);
    exit;
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // 获取Token使用统计
            getTokenStats();
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * 获取Token使用统计
 */
function getTokenStats() {
    $days = (int)($_GET['days'] ?? 7); // 默认获取最近7天的数据
    $startDate = date('Y-m-d', strtotime("-$days days"));
    
    $db = getDB();
    
    try {
        // 按日期统计Token使用量
        $stmt = $db->prepare("
            SELECT DATE(created_at) as date, SUM(tokens_used) as total_tokens, COUNT(*) as request_count
            FROM token_usage_logs
            WHERE DATE(created_at) >= ?
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$startDate]);
        $dailyStats = $stmt->fetchAll();
        
        // 按用户统计Token使用量（top 10）
        $stmt = $db->prepare("
            SELECT u.id, u.email, u.nickname, SUM(tul.tokens_used) as total_tokens
            FROM token_usage_logs tul
            LEFT JOIN users u ON tul.user_id = u.id
            WHERE DATE(tul.created_at) >= ?
            GROUP BY tul.user_id
            ORDER BY total_tokens DESC
            LIMIT 10
        ");
        $stmt->execute([$startDate]);
        $userStats = $stmt->fetchAll();
        
        // 获取总体统计
        $stmt = $db->prepare("
            SELECT 
                SUM(tokens_used) as total_tokens_used,
                COUNT(*) as total_requests,
                AVG(tokens_used) as avg_tokens_per_request
            FROM token_usage_logs
            WHERE DATE(created_at) >= ?
        ");
        $stmt->execute([$startDate]);
        $overallStats = $stmt->fetch();
        
        // 按模型统计
        $stmt = $db->prepare("
            SELECT model, SUM(tokens_used) as total_tokens, COUNT(*) as request_count
            FROM token_usage_logs
            WHERE DATE(created_at) >= ?
            GROUP BY model
            ORDER BY total_tokens DESC
        ");
        $stmt->execute([$startDate]);
        $modelStats = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'daily_stats' => $dailyStats,
            'user_stats' => $userStats,
            'overall_stats' => $overallStats,
            'model_stats' => $modelStats,
            'days' => $days
        ]);
    } catch (Exception $e) {
        throw new Exception("获取Token统计失败: " . $e->getMessage());
    }
}