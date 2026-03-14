<?php
/**
 * 仪表板统计API
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

try {
    $db = getDB();
    
    // 获取总用户数
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $totalUsers = $stmt->fetch()['count'];
    
    // 获取今日消息数
    $stmt = $db->query("SELECT COUNT(*) as count FROM messages WHERE DATE(created_at) = CURDATE()");
    $todayMessages = $stmt->fetch()['count'];
    
    // 获取今日Token用量
    $stmt = $db->query("SELECT SUM(tokens_used) as total FROM token_usage_logs WHERE DATE(created_at) = CURDATE()");
    $todayTokensResult = $stmt->fetch();
    $todayTokens = $todayTokensResult['total'] ? (int)$todayTokensResult['total'] : 0;
    
    // 获取总对话数
    $stmt = $db->query("SELECT COUNT(*) as count FROM conversations");
    $totalConversations = $stmt->fetch()['count'];
    
    // 获取最新活动（最近的几个用户注册或对话）
    $stmt = $db->query("
        (SELECT 'user_registered' as type, nickname as name, created_at 
         FROM users 
         WHERE DATE(created_at) = CURDATE() 
         ORDER BY created_at DESC 
         LIMIT 5)
        UNION ALL
        (SELECT 'conversation_created' as type, title as name, created_at 
         FROM conversations 
         WHERE DATE(created_at) = CURDATE() 
         ORDER BY created_at DESC 
         LIMIT 5)
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $latestActivity = $stmt->fetchAll();
    
    $activityTexts = [];
    foreach ($latestActivity as $activity) {
        $typeText = $activity['type'] === 'user_registered' ? '用户注册' : '新对话';
        $activityTexts[] = sprintf(
            "%s - %s (%s)", 
            $typeText, 
            htmlspecialchars($activity['name']), 
            date('H:i:s', strtotime($activity['created_at']))
        );
    }
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_users' => $totalUsers,
            'today_messages' => $todayMessages,
            'today_tokens' => $todayTokens,
            'total_conversations' => $totalConversations,
            'latest_activity' => $activityTexts
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}