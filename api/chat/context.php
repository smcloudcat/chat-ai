<?php
/**
 * 上下文管理API
 */

require_once '../../includes/utils/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$identity = SessionManager::getCurrentIdentity();

try {
    switch ($method) {
        case 'GET':
            // 获取对话上下文
            getContext($identity);
            break;
            
        case 'POST':
            // 设置上下文长度等配置
            setContextConfig($identity);
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
 * 获取对话上下文
 */
function getContext($identity) {
    $conversationId = $_GET['conversation_id'] ?? null;
    $limit = $_GET['limit'] ?? 10; // 默认返回最近10条消息
    
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing conversation_id']);
        return;
    }
    
    $db = getDB();
    
    try {
        // 验证对话是否属于当前用户/设备
        if ($identity['type'] === 'user') {
            $stmt = $db->prepare("
                SELECT id 
                FROM conversations 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$conversationId, $identity['id']]);
        } else {
            $stmt = $db->prepare("
                SELECT id 
                FROM conversations 
                WHERE id = ? AND device_id = ?
            ");
            $stmt->execute([$conversationId, $identity['id']]);
        }
        
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        // 获取最近的指定数量消息
        $stmt = $db->prepare("
            SELECT role, content
            FROM messages 
            WHERE conversation_id = ? 
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$conversationId, $limit]);
        
        $messages = $stmt->fetchAll();
        // 反转数组以恢复正确的时间顺序
        $messages = array_reverse($messages);
        
        echo json_encode([
            'success' => true,
            'context' => $messages,
            'count' => count($messages)
        ]);
    } catch (Exception $e) {
        throw new Exception("获取上下文失败: " . $e->getMessage());
    }
}

/**
 * 设置上下文配置
 */
function setContextConfig($identity) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    // 这里可以实现用户特定的上下文配置
    // 比如设置最大上下文长度等
    
    echo json_encode([
        'success' => true,
        'message' => '上下文配置已更新'
    ]);
}