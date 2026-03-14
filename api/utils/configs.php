<?php
/**
 * 系统配置API
 */

require_once '../../includes/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // 获取系统配置
            getConfigs();
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
 * 获取系统配置
 */
function getConfigs() {
    $db = getDB();
    
    try {
        $stmt = $db->query("SELECT config_key, config_value FROM system_configs");
        $configs = [];
        
        while ($row = $stmt->fetch()) {
            $configs[$row['config_key']] = json_decode($row['config_value'], true);
        }
        
        echo json_encode([
            'success' => true,
            'configs' => $configs
        ]);
    } catch (Exception $e) {
        throw new Exception("获取配置失败: " . $e->getMessage());
    }
}