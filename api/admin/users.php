<?php
/**
 * 用户管理API
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
            // 获取用户列表
            getUsers();
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
 * 获取用户列表
 */
function getUsers() {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    $db = getDB();
    
    try {
        // 获取总数
        $countStmt = $db->query("SELECT COUNT(*) as total FROM users");
        $totalCount = $countStmt->fetch()['total'];
        
        // 获取分页数据
        $stmt = $db->prepare("
            SELECT id, oidc_sub, email, nickname, avatar_url, level, experience, 
                   daily_token_used, last_reset_date, is_active, created_at, updated_at
            FROM users
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limit)
            ]
        ]);
    } catch (Exception $e) {
        throw new Exception("获取用户列表失败: " . $e->getMessage());
    }
}