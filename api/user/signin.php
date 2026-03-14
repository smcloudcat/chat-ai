<?php
/**
 * 用户签到API
 */

require_once '../../includes/utils/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

// 检查用户是否登录
if (!OIDCAuth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => '请先登录']);
    exit;
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$userId = OIDCAuth::getCurrentUserId();

try {
    switch ($method) {
        case 'GET':
            // 获取签到信息
            getSigninInfo($userId);
            break;
            
        case 'POST':
            // 执行签到
            performSignin($userId);
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
 * 获取签到信息
 */
function getSigninInfo($userId) {
    $db = getDB();
    
    try {
        // 获取今天的签到记录
        $stmt = $db->prepare("
            SELECT * FROM sign_in_records 
            WHERE user_id = ? AND sign_date = CURDATE()
        ");
        $stmt->execute([$userId]);
        $todayRecord = $stmt->fetch();
        
        // 获取连续签到天数
        $continuousDays = getContinuousSigninDays($userId);
        
        // 获取签到配置
        $stmt = $db->prepare("SELECT config_value FROM system_configs WHERE config_key = 'signin_config'");
        $stmt->execute();
        $config = $stmt->fetch();
        
        $signinConfig = [];
        if ($config) {
            $signinConfig = json_decode($config['config_value'], true);
        }
        
        echo json_encode([
            'success' => true,
            'signed_in_today' => $todayRecord !== false,
            'continuous_days' => $continuousDays,
            'config' => $signinConfig
        ]);
    } catch (Exception $e) {
        throw new Exception("获取签到信息失败: " . $e->getMessage());
    }
}

/**
 * 执行签到
 */
function performSignin($userId) {
    $db = getDB();
    
    try {
        // 检查今天是否已签到
        $stmt = $db->prepare("
            SELECT id FROM sign_in_records 
            WHERE user_id = ? AND sign_date = CURDATE()
        ");
        $stmt->execute([$userId]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => '今日已签到']);
            return;
        }
        
        // 获取签到配置
        $stmt = $db->prepare("SELECT config_value FROM system_configs WHERE config_key = 'signin_config'");
        $stmt->execute();
        $config = $stmt->fetch();
        
        $signinConfig = [];
        if ($config) {
            $signinConfig = json_decode($config['config_value'], true);
        } else {
            // 默认配置
            $signinConfig = [
                'type' => 'fixed',
                'fixed_value' => 10,
                'min_value' => 5,
                'max_value' => 15,
                'enable_turnstile' => false
            ];
        }
        
        // 计算获得的经验值
        $experienceGained = calculateSigninExperience($signinConfig);
        
        // 获取连续签到天数
        $continuousDays = getContinuousSigninDays($userId);
        
        // 开始事务
        $db->beginTransaction();
        
        // 插入签到记录
        $stmt = $db->prepare("
            INSERT INTO sign_in_records (user_id, sign_date, experience_gained, continuous_days, created_at)
            VALUES (?, CURDATE(), ?, ?, NOW())
        ");
        $result = $stmt->execute([$userId, $experienceGained, $continuousDays + 1]);
        
        if (!$result) {
            $db->rollback();
            throw new Exception("签到记录插入失败");
        }
        
        // 更新用户经验
        require_once '../../includes/models/user.php';
        $userModel = new User();
        $addResult = $userModel->addExperience($userId, $experienceGained);
        
        if (!$addResult) {
            $db->rollback();
            throw new Exception("更新用户经验失败");
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '签到成功',
            'experience_gained' => $experienceGained,
            'continuous_days' => $continuousDays + 1
        ]);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        throw new Exception("签到失败: " . $e->getMessage());
    }
}

/**
 * 计算签到获得的经验值
 */
function calculateSigninExperience($config) {
    switch ($config['type'] ?? 'fixed') {
        case 'fixed':
            return $config['fixed_value'] ?? 10;
            
        case 'random':
            $min = $config['min_value'] ?? 5;
            $max = $config['max_value'] ?? 15;
            return rand($min, $max);
            
        case 'continuous':
            // 连续签到奖励，达到上限后保持
            $maxDays = $config['max_continuous_days'] ?? 7;
            $baseValue = $config['base_value'] ?? 1;
            $increment = $config['increment_value'] ?? 1;
            
            // 这里需要获取实际的连续天数，暂时返回基础值
            return $baseValue;
            
        default:
            return $config['fixed_value'] ?? 10;
    }
}

/**
 * 获取连续签到天数
 */
function getContinuousSigninDays($userId) {
    $db = getDB();
    
    // 这是一个简化的实现，实际的连续签到天数计算会更复杂
    // 需要检查用户连续签到的天数
    $stmt = $db->prepare("
        SELECT DATEDIFF(CURDATE(), MAX(sign_date)) as days_since_last
        FROM sign_in_records 
        WHERE user_id = ? AND sign_date < CURDATE()
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    $daysSinceLast = $result['days_since_last'];
    
    if ($daysSinceLast === null) {
        // 如果没有历史签到记录，返回0
        return 0;
    } elseif ($daysSinceLast > 1) {
        // 如果超过1天没有签到，连续天数重置为0
        return 0;
    } else {
        // 如果昨天签到了，查找连续天数
        $stmt = $db->prepare("
            SELECT continuous_days 
            FROM sign_in_records 
            WHERE user_id = ? AND sign_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ");
        $stmt->execute([$userId]);
        $yesterdayRecord = $stmt->fetch();
        
        return $yesterdayRecord ? $yesterdayRecord['continuous_days'] : 0;
    }
}