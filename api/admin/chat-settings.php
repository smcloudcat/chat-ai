<?php
/**
 * 聊天设置API
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
            // 获取聊天设置
            getChatSettings();
            break;
            
        case 'POST':
            // 更新聊天设置
            updateChatSettings();
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
 * 获取聊天设置
 */
function getChatSettings() {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("SELECT config_value FROM system_configs WHERE config_key = 'chat_settings'");
        $stmt->execute();
        $config = $stmt->fetch();
        
        if ($config) {
            $settings = json_decode($config['config_value'], true);
        } else {
            // 默认设置
            $settings = [
                'force_login' => false,
                'guest_daily_token_limit' => 10000,
                'user_daily_token_limit' => 50000,
                'enable_turnstile' => false,
                'turnstile_duration' => 30
            ];
        }
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
    } catch (Exception $e) {
        throw new Exception("获取聊天设置失败: " . $e->getMessage());
    }
}

/**
 * 更新聊天设置
 */
function updateChatSettings() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    $db = getDB();
    
    try {
        // 验证输入
        $settings = [
            'force_login' => (bool)($input['force_login'] ?? false),
            'guest_daily_token_limit' => (int)($input['guest_daily_token_limit'] ?? 10000),
            'user_daily_token_limit' => (int)($input['user_daily_token_limit'] ?? 50000),
            'enable_turnstile' => (bool)($input['enable_turnstile'] ?? false),
            'turnstile_duration' => (int)($input['turnstile_duration'] ?? 30)
        ];
        
        // 检查是否已存在配置
        $stmt = $db->prepare("SELECT id FROM system_configs WHERE config_key = ?");
        $stmt->execute(['chat_settings']);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // 更新现有配置
            $stmt = $db->prepare("
                UPDATE system_configs 
                SET config_value = ?, updated_at = NOW() 
                WHERE config_key = ?
            ");
            $stmt->execute([json_encode($settings), 'chat_settings']);
        } else {
            // 插入新配置
            $stmt = $db->prepare("
                INSERT INTO system_configs (config_key, config_value, updated_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute(['chat_settings', json_encode($settings)]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => '聊天设置已更新'
        ]);
    } catch (Exception $e) {
        throw new Exception("更新聊天设置失败: " . $e->getMessage());
    }
}