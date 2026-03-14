<?php
/**
 * API配置管理API
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
$id = $_GET['id'] ?? null;

try {
    switch ($method) {
        case 'GET':
            // 获取API配置列表或单个配置
            if ($id) {
                getApiConfig($id);
            } else {
                getApiConfigs();
            }
            break;
            
        case 'POST':
            // 创建API配置
            createApiConfig();
            break;
            
        case 'PUT':
            // 更新API配置
            updateApiConfig();
            break;
            
        case 'DELETE':
            // 删除API配置
            deleteApiConfig();
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
 * 获取API配置列表
 */
function getApiConfigs() {
    $db = getDB();
    
    try {
        $stmt = $db->query("
            SELECT id, name, api_url, custom_headers, timeout, is_enabled, created_at, updated_at
            FROM api_configs
            ORDER BY created_at DESC
        ");
        $configs = $stmt->fetchAll();
        
        // 不返回API密钥
        foreach ($configs as &$config) {
            unset($config['api_key']);
        }
        
        echo json_encode([
            'success' => true,
            'configs' => $configs
        ]);
    } catch (Exception $e) {
        throw new Exception("获取API配置列表失败: " . $e->getMessage());
    }
}

/**
 * 获取单个API配置
 */
function getApiConfig($id) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT id, name, api_url, custom_headers, timeout, is_enabled, created_at, updated_at
            FROM api_configs
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $config = $stmt->fetch();
        
        if (!$config) {
            http_response_code(404);
            echo json_encode(['error' => 'API配置不存在']);
            return;
        }
        
        // 不返回API密钥
        unset($config['api_key']);
        
        echo json_encode([
            'success' => true,
            'config' => $config
        ]);
    } catch (Exception $e) {
        throw new Exception("获取API配置失败: " . $e->getMessage());
    }
}

/**
 * 创建API配置
 */
function createApiConfig() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    $db = getDB();
    
    try {
        // 验证必要字段
        if (empty($input['name']) || empty($input['api_url']) || empty($input['api_key'])) {
            http_response_code(400);
            echo json_encode(['error' => '缺少必要字段']);
            return;
        }
        
        // 加密存储API密钥
        $encryptedApiKey = encryptApiKey($input['api_key']);
        
        $stmt = $db->prepare("
            INSERT INTO api_configs (name, api_url, api_key, custom_headers, timeout, is_enabled, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $customHeaders = !empty($input['custom_headers']) ? json_encode($input['custom_headers']) : null;
        
        $result = $stmt->execute([
            $input['name'],
            $input['api_url'],
            $encryptedApiKey,
            $customHeaders,
            $input['timeout'] ?? 60,
            $input['is_enabled'] ?? 1
        ]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'API配置创建成功',
                'id' => $db->lastInsertId()
            ]);
        } else {
            throw new Exception("创建API配置失败");
        }
    } catch (Exception $e) {
        throw new Exception("创建API配置失败: " . $e->getMessage());
    }
}

/**
 * 更新API配置
 */
function updateApiConfig() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少配置ID']);
        return;
    }
    
    $db = getDB();
    
    try {
        // 获取现有配置
        $stmt = $db->prepare("SELECT * FROM api_configs WHERE id = ?");
        $stmt->execute([$input['id']]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => 'API配置不存在']);
            return;
        }
        
        // 如果提供了新的API密钥，则加密存储
        $apiKey = $existing['api_key']; // 默认使用现有的密钥
        if (!empty($input['api_key'])) {
            $apiKey = encryptApiKey($input['api_key']);
        }
        
        $stmt = $db->prepare("
            UPDATE api_configs 
            SET name = ?, api_url = ?, api_key = ?, custom_headers = ?, timeout = ?, is_enabled = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $customHeaders = !empty($input['custom_headers']) ? json_encode($input['custom_headers']) : null;
        
        $result = $stmt->execute([
            $input['name'] ?? $existing['name'],
            $input['api_url'] ?? $existing['api_url'],
            $apiKey,
            $customHeaders,
            $input['timeout'] ?? $existing['timeout'],
            $input['is_enabled'] ?? $existing['is_enabled'],
            $input['id']
        ]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'API配置更新成功'
            ]);
        } else {
            throw new Exception("更新API配置失败");
        }
    } catch (Exception $e) {
        throw new Exception("更新API配置失败: " . $e->getMessage());
    }
}

/**
 * 删除API配置
 */
function deleteApiConfig() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少配置ID']);
        return;
    }
    
    $db = getDB();
    
    try {
        // 检查是否有模型正在使用此API配置
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM model_configs WHERE api_config_id = ?");
        $stmt->execute([$input['id']]);
        $modelCount = $stmt->fetch()['count'];
        
        if ($modelCount > 0) {
            http_response_code(400);
            echo json_encode(['error' => '此API配置正在被模型使用，无法删除']);
            return;
        }
        
        $stmt = $db->prepare("DELETE FROM api_configs WHERE id = ?");
        $result = $stmt->execute([$input['id']]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'API配置删除成功'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'API配置不存在或删除失败'
            ]);
        }
    } catch (Exception $e) {
        throw new Exception("删除API配置失败: " . $e->getMessage());
    }
}

/**
 * 加密API密钥
 */
function encryptApiKey($apiKey) {
    // 这里应该使用更安全的加密方法
    // 为了简单起见，我们使用base64编码，实际部署时应使用更强的加密
    return base64_encode($apiKey);
}

/**
 * 解密API密钥
 */
function decryptApiKey($encryptedApiKey) {
    // 对应的解密方法
    return base64_decode($encryptedApiKey);
}