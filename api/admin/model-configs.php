<?php
/**
 * 模型配置管理API
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
            // 获取模型配置列表或单个配置
            if ($id) {
                getModelConfig($id);
            } else {
                getModelConfigs();
            }
            break;
            
        case 'POST':
            // 创建模型配置
            createModelConfig();
            break;
            
        case 'PUT':
            // 更新模型配置
            updateModelConfig();
            break;
            
        case 'DELETE':
            // 删除模型配置
            deleteModelConfig();
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
 * 获取模型配置列表
 */
function getModelConfigs() {
    $db = getDB();
    
    try {
        $stmt = $db->query("
            SELECT m.id, m.display_name, m.model_key, m.api_config_id, m.billing_multiplier, m.is_enabled, m.sort_order, m.created_at, m.updated_at,
                   a.name as api_config_name
            FROM model_configs m
            LEFT JOIN api_configs a ON m.api_config_id = a.id
            ORDER BY m.sort_order ASC, m.created_at DESC
        ");
        $configs = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'configs' => $configs
        ]);
    } catch (Exception $e) {
        throw new Exception("获取模型配置列表失败: " . $e->getMessage());
    }
}

/**
 * 获取单个模型配置
 */
function getModelConfig($id) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT m.id, m.display_name, m.model_key, m.api_config_id, m.billing_multiplier, m.is_enabled, m.sort_order, m.created_at, m.updated_at,
                   a.name as api_config_name
            FROM model_configs m
            LEFT JOIN api_configs a ON m.api_config_id = a.id
            WHERE m.id = ?
        ");
        $stmt->execute([$id]);
        $config = $stmt->fetch();
        
        if (!$config) {
            http_response_code(404);
            echo json_encode(['error' => '模型配置不存在']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'config' => $config
        ]);
    } catch (Exception $e) {
        throw new Exception("获取模型配置失败: " . $e->getMessage());
    }
}

/**
 * 创建模型配置
 */
function createModelConfig() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    $db = getDB();
    
    try {
        // 验证必要字段
        if (empty($input['display_name']) || empty($input['model_key']) || empty($input['api_config_id'])) {
            http_response_code(400);
            echo json_encode(['error' => '缺少必要字段']);
            return;
        }
        
        // 验证API配置是否存在
        $stmt = $db->prepare("SELECT id FROM api_configs WHERE id = ?");
        $stmt->execute([$input['api_config_id']]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => '指定的API配置不存在']);
            return;
        }
        
        $stmt = $db->prepare("
            INSERT INTO model_configs (display_name, model_key, api_config_id, billing_multiplier, is_enabled, sort_order, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $result = $stmt->execute([
            $input['display_name'],
            $input['model_key'],
            $input['api_config_id'],
            $input['billing_multiplier'] ?? 1.00,
            $input['is_enabled'] ?? 1,
            $input['sort_order'] ?? 0
        ]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => '模型配置创建成功',
                'id' => $db->lastInsertId()
            ]);
        } else {
            throw new Exception("创建模型配置失败");
        }
    } catch (Exception $e) {
        throw new Exception("创建模型配置失败: " . $e->getMessage());
    }
}

/**
 * 更新模型配置
 */
function updateModelConfig() {
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
        $stmt = $db->prepare("SELECT * FROM model_configs WHERE id = ?");
        $stmt->execute([$input['id']]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => '模型配置不存在']);
            return;
        }
        
        // 验证API配置是否存在
        if (isset($input['api_config_id'])) {
            $stmt = $db->prepare("SELECT id FROM api_configs WHERE id = ?");
            $stmt->execute([$input['api_config_id']]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => '指定的API配置不存在']);
                return;
            }
        }
        
        $stmt = $db->prepare("
            UPDATE model_configs 
            SET display_name = ?, model_key = ?, api_config_id = ?, billing_multiplier = ?, is_enabled = ?, sort_order = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $input['display_name'] ?? $existing['display_name'],
            $input['model_key'] ?? $existing['model_key'],
            $input['api_config_id'] ?? $existing['api_config_id'],
            $input['billing_multiplier'] ?? $existing['billing_multiplier'],
            $input['is_enabled'] ?? $existing['is_enabled'],
            $input['sort_order'] ?? $existing['sort_order'],
            $input['id']
        ]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => '模型配置更新成功'
            ]);
        } else {
            throw new Exception("更新模型配置失败");
        }
    } catch (Exception $e) {
        throw new Exception("更新模型配置失败: " . $e->getMessage());
    }
}

/**
 * 删除模型配置
 */
function deleteModelConfig() {
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
        $stmt = $db->prepare("DELETE FROM model_configs WHERE id = ?");
        $result = $stmt->execute([$input['id']]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => '模型配置删除成功'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => '模型配置不存在或删除失败'
            ]);
        }
    } catch (Exception $e) {
        throw new Exception("删除模型配置失败: " . $e->getMessage());
    }
}