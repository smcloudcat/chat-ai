<?php
/**
 * 历史记录管理API
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
            // 获取历史记录
            getHistory($identity);
            break;
            
        case 'DELETE':
            // 删除历史记录
            deleteHistory($identity);
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
 * 获取历史记录
 */
function getHistory($identity) {
    $type = $_GET['type'] ?? 'all'; // all, user, device
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    $db = getDB();
    
    try {
        $conditions = [];
        $params = [];
        
        // 根据身份类型添加条件
        if ($identity['type'] === 'user') {
            $conditions[] = "user_id = ?";
            $params[] = $identity['id'];
        } else {
            $conditions[] = "device_id = ?";
            $params[] = $identity['id'];
        }
        
        // 构建查询
        $whereClause = "";
        if (!empty($conditions)) {
            $whereClause = "WHERE " . implode(" AND ", $conditions);
        }
        
        // 获取总数
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM conversations $whereClause");
        $countStmt->execute($params);
        $totalCount = $countStmt->fetch()['total'];
        
        // 获取分页数据
        $stmt = $db->prepare("
            SELECT id, title, created_at
            FROM conversations 
            $whereClause
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmtParams = array_merge($params, [$limit, $offset]);
        $stmt->execute($stmtParams);
        
        $conversations = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'conversations' => $conversations,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limit)
            ]
        ]);
    } catch (Exception $e) {
        throw new Exception("获取历史记录失败: " . $e->getMessage());
    }
}

/**
 * 删除历史记录
 */
function deleteHistory($identity) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (!isset($input['conversation_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing conversation_id']);
        return;
    }
    
    $conversationId = $input['conversation_id'];
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
        
        // 删除对话及其消息
        $stmt = $db->prepare("DELETE FROM conversations WHERE id = ?");
        $result = $stmt->execute([$conversationId]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => '历史记录删除成功'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => '历史记录不存在'
            ]);
        }
    } catch (Exception $e) {
        throw new Exception("删除历史记录失败: " . $e->getMessage());
    }
}