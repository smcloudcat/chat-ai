<?php
/**
 * 对话管理API
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
            // 获取对话列表
            getConversations($identity);
            break;
            
        case 'POST':
            // 创建新对话
            createConversation($identity);
            break;
            
        case 'PUT':
            // 更新对话
            updateConversation($identity);
            break;
            
        case 'DELETE':
            // 删除对话
            deleteConversation($identity);
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
 * 获取对话列表
 */
function getConversations($identity) {
    $db = getDB();
    
    try {
        if ($identity['type'] === 'user') {
            // 获取用户对话列表
            $stmt = $db->prepare("
                SELECT id, title, created_at 
                FROM conversations 
                WHERE user_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$identity['id']]);
        } else {
            // 获取设备对话列表
            $stmt = $db->prepare("
                SELECT id, title, created_at 
                FROM conversations 
                WHERE device_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$identity['id']]);
        }
        
        $conversations = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'conversations' => $conversations
        ]);
    } catch (Exception $e) {
        throw new Exception("获取对话列表失败: " . $e->getMessage());
    }
}

/**
 * 创建对话
 */
function createConversation($identity) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (!isset($input['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing title']);
        return;
    }
    
    $db = getDB();
    
    try {
        if ($identity['type'] === 'user') {
            // 创建用户对话
            $stmt = $db->prepare("
                INSERT INTO conversations (user_id, title, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$identity['id'], $input['title']]);
        } else {
            // 创建设备对话
            $stmt = $db->prepare("
                INSERT INTO conversations (device_id, title, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$identity['id'], $input['title']]);
        }
        
        $conversationId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'conversation_id' => $conversationId
        ]);
    } catch (Exception $e) {
        throw new Exception("创建对话失败: " . $e->getMessage());
    }
}

/**
 * 更新对话
 */
function updateConversation($identity) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (!isset($input['id']) || !isset($input['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id or title']);
        return;
    }
    
    $db = getDB();
    
    try {
        if ($identity['type'] === 'user') {
            // 更新用户对话
            $stmt = $db->prepare("
                UPDATE conversations 
                SET title = ? 
                WHERE id = ? AND user_id = ?
            ");
            $result = $stmt->execute([$input['title'], $input['id'], $identity['id']]);
        } else {
            // 更新设备对话
            $stmt = $db->prepare("
                UPDATE conversations 
                SET title = ? 
                WHERE id = ? AND device_id = ?
            ");
            $result = $stmt->execute([$input['title'], $input['id'], $identity['id']]);
        }
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => '对话更新成功'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => '对话不存在或无权限修改'
            ]);
        }
    } catch (Exception $e) {
        throw new Exception("更新对话失败: " . $e->getMessage());
    }
}

/**
 * 删除对话
 */
function deleteConversation($identity) {
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
    
    $db = getDB();
    
    try {
        if ($identity['type'] === 'user') {
            // 删除用户对话
            $stmt = $db->prepare("
                DELETE FROM conversations 
                WHERE id = ? AND user_id = ?
            ");
            $result = $stmt->execute([$input['id'], $identity['id']]);
        } else {
            // 删除设备对话
            $stmt = $db->prepare("
                DELETE FROM conversations 
                WHERE id = ? AND device_id = ?
            ");
            $result = $stmt->execute([$input['id'], $identity['id']]);
        }
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => '对话删除成功'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => '对话不存在或无权限删除'
            ]);
        }
    } catch (Exception $e) {
        throw new Exception("删除对话失败: " . $e->getMessage());
    }
}