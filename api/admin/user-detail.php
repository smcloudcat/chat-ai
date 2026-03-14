<?php
/**
 * 用户详情API
 */

require_once '../../includes/utils/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

// 检查管理员权限
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(401);
    echo json_encode(['error' => '未授权访问']);
    exit;
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$userId = $_GET['id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => '缺少用户ID']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            // 获取用户详情
            getUserDetail($userId);
            break;
            
        case 'PUT':
            // 更新用户信息
            updateUserDetail($userId);
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
 * 获取用户详情
 */
function getUserDetail($userId) {
    $db = getDB();
    
    try {
        // 获取用户基本信息
        $stmt = $db->prepare("
            SELECT id, oidc_sub, email, nickname, avatar_url, level, experience, 
                   daily_token_used, last_reset_date, is_active, created_at, updated_at
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => '用户不存在']);
            return;
        }
        
        // 获取用户Token限制
        require_once '../../includes/models/user.php';
        $userModel = new User();
        $levelInfo = $userModel->getUserLevelInfo($userId);
        
        // 获取用户最近的对话
        $stmt = $db->prepare("
            SELECT id, title, created_at
            FROM conversations
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $recentConversations = $stmt->fetchAll();
        
        // 获取用户最近的Token使用记录
        $stmt = $db->prepare("
            SELECT tokens_used, model, created_at
            FROM token_usage_logs
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $recentTokenUsage = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'user' => $user,
            'level_info' => $levelInfo,
            'recent_conversations' => $recentConversations,
            'recent_token_usage' => $recentTokenUsage
        ]);
    } catch (Exception $e) {
        throw new Exception("获取用户详情失败: " . $e->getMessage());
    }
}

/**
 * 更新用户信息
 */
function updateUserDetail($userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    $db = getDB();
    
    try {
        // 获取当前用户信息
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $existingUser = $stmt->fetch();
        
        if (!$existingUser) {
            http_response_code(404);
            echo json_encode(['error' => '用户不存在']);
            return;
        }
        
        // 如果更新了等级，需要同步更新经验
        $updateLevel = false;
        $newLevel = $existingUser['level'];
        $newExperience = $existingUser['experience'];
        
        if (isset($input['level'])) {
            $updateLevel = true;
            $newLevel = (int)$input['level'];
            
            // 根据等级获取对应的经验值下限
            require_once '../../includes/models/user.php';
            $userModel = new User();
            $levelConfig = $userModel->getLevelConfig();
            $levelKey = 'lv' . $newLevel;
            
            if (isset($levelConfig[$levelKey])) {
                $newExperience = $levelConfig[$levelKey]['exp_needed'];
            }
        }
        
        // 更新用户信息
        $stmt = $db->prepare("
            UPDATE users 
            SET email = ?, nickname = ?, level = ?, experience = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $input['email'] ?? $existingUser['email'],
            $input['nickname'] ?? $existingUser['nickname'],
            $newLevel,
            $newExperience,
            $input['is_active'] ?? $existingUser['is_active'],
            $userId
        ]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => '用户信息更新成功'
            ]);
        } else {
            throw new Exception("更新用户信息失败");
        }
    } catch (Exception $e) {
        throw new Exception("更新用户信息失败: " . $e->getMessage());
    }
}