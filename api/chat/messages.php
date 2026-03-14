<?php
/**
 * 消息管理API
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
            // 获取对话消息
            getMessages($identity);
            break;
            
        case 'POST':
            // 创建消息
            createMessage($identity);
            break;
            
        case 'DELETE':
            // 删除消息
            deleteMessage($identity);
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
 * 获取对话消息
 */
function getMessages($identity) {
    $conversationId = $_GET['conversation_id'] ?? null;
    
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
        
        // 获取消息列表
        $stmt = $db->prepare("
            SELECT id, role, content, tokens, created_at 
            FROM messages 
            WHERE conversation_id = ? 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$conversationId]);
        
        $messages = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'messages' => $messages
        ]);
    } catch (Exception $e) {
        throw new Exception("获取消息失败: " . $e->getMessage());
    }
}

/**
 * 创建消息
 */
function createMessage($identity) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (!isset($input['conversation_id']) || !isset($input['role']) || !isset($input['content'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    $conversationId = $input['conversation_id'];
    $role = $input['role'];
    $content = $input['content'];
    $tokens = $input['tokens'] ?? 0;
    
    // 验证角色
    if (!in_array($role, ['user', 'assistant', 'system'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid role']);
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
        
        // 插入消息
        $stmt = $db->prepare("
            INSERT INTO messages (conversation_id, role, content, tokens, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$conversationId, $role, $content, $tokens]);
        
        $messageId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message_id' => $messageId
        ]);
    } catch (Exception $e) {
        throw new Exception("创建消息失败: " . $e->getMessage());
    }
}

/**
 * 删除消息
 */
function deleteMessage($identity) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id']);
        return;
    }
    
    $messageId = $input['id'];
    $db = getDB();
    
    try {
        // 获取消息所属的对话
        $stmt = $db->prepare("SELECT conversation_id FROM messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch();
        
        if (!$message) {
            http_response_code(404);
            echo json_encode(['error' => 'Message not found']);
            return;
        }
        
        // 验证对话是否属于当前用户/设备
        if ($identity['type'] === 'user') {
            $stmt = $db->prepare("
                SELECT c.id 
                FROM conversations c
                JOIN messages m ON c.id = m.conversation_id
                WHERE m.id = ? AND c.user_id = ?
            ");
            $stmt->execute([$messageId, $identity['id']]);
        } else {
            $stmt = $db->prepare("
                SELECT c.id 
                FROM conversations c
                JOIN messages m ON c.id = m.conversation_id
                WHERE m.id = ? AND c.device_id = ?
            ");
            $stmt->execute([$messageId, $identity['id']]);
        }
        
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        // 删除消息
        $stmt = $db->prepare("DELETE FROM messages WHERE id = ?");
        $result = $stmt->execute([$messageId]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => '消息删除成功'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => '消息不存在'
            ]);
        }
    } catch (Exception $e) {
        throw new Exception("删除消息失败: " . $e->getMessage());
    }
}