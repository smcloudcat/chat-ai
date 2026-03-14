<?php
/**
 * Token用量API
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
            // 获取Token使用情况
            getTokenUsage($identity);
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
 * 获取Token使用情况
 */
function getTokenUsage($identity) {
    $db = getDB();
    
    try {
        // 获取当日token使用量
        $usedTokens = getCurrentTokenUsage($identity);
        
        // 获取token限制
        $tokenLimit = getTokenLimit();
        
        // 获取系统配置
        $stmt = $db->prepare("SELECT config_value FROM system_configs WHERE config_key = 'chat_settings'");
        $stmt->execute();
        $config = $stmt->fetch();
        
        $chatSettings = [];
        if ($config) {
            $chatSettings = json_decode($config['config_value'], true);
        }
        
        // 计算剩余token
        $remainingTokens = max(0, $tokenLimit - $usedTokens);
        
        echo json_encode([
            'success' => true,
            'usage' => [
                'used' => $usedTokens,
                'limit' => $tokenLimit,
                'remaining' => $remainingTokens,
                'usage_percent' => $tokenLimit > 0 ? round(($usedTokens / $tokenLimit) * 100, 2) : 0
            ],
            'settings' => $chatSettings
        ]);
    } catch (Exception $e) {
        throw new Exception("获取Token使用情况失败: " . $e->getMessage());
    }
}

/**
 * 获取当前token使用量
 */
function getCurrentTokenUsage($identity) {
    $db = getDB();
    
    if ($identity['type'] === 'user') {
        // 获取用户当日token使用量
        $stmt = $db->prepare("
            SELECT SUM(tokens_used) as total_tokens 
            FROM token_usage_logs 
            WHERE user_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$identity['id']]);
        $result = $stmt->fetch();
        
        return $result['total_tokens'] ?? 0;
    } else {
        // 获取设备当日token使用量
        $stmt = $db->prepare("
            SELECT SUM(tokens_used) as total_tokens 
            FROM token_usage_logs 
            WHERE device_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$identity['id']]);
        $result = $stmt->fetch();
        
        return $result['total_tokens'] ?? 0;
    }
}

/**
 * 获取token限制
 */
function getTokenLimit() {
    $db = getDB();
    
    // 获取系统配置
    $stmt = $db->prepare("SELECT config_value FROM system_configs WHERE config_key = 'chat_settings'");
    $stmt->execute();
    $config = $stmt->fetch();
    
    if ($config) {
        $settings = json_decode($config['config_value'], true);
        
        if (SessionManager::isUserLoggedIn()) {
            return $settings['user_daily_token_limit'] ?? 50000;
        } else {
            return $settings['guest_daily_token_limit'] ?? 10000;
        }
    }
    
    // 默认限制
    if (SessionManager::isUserLoggedIn()) {
        return 50000;
    } else {
        return 10000;
    }
}