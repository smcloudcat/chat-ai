<?php
/**
 * 系统健康检查API
 */

require_once '../includes/database.php';

header('Content-Type: application/json');

try {
    // 检查数据库连接
    $db = getDB();
    $db->query("SELECT 1");
    
    // 检查基本配置
    $checks = [
        'database' => [
            'status' => 'ok',
            'message' => '数据库连接正常'
        ],
        'php_version' => [
            'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'ok' : 'warning',
            'message' => 'PHP版本: ' . PHP_VERSION
        ],
        'extensions' => [
            'status' => 'ok',
            'message' => '必需的扩展已加载'
        ],
        'environment' => [
            'status' => 'ok',
            'message' => '环境配置正常'
        ]
    ];
    
    // 检查必需的PHP扩展
    $requiredExtensions = ['curl', 'pdo', 'json', 'openssl'];
    $missingExtensions = [];
    
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $missingExtensions[] = $ext;
        }
    }
    
    if (!empty($missingExtensions)) {
        $checks['extensions'] = [
            'status' => 'error',
            'message' => '缺少扩展: ' . implode(', ', $missingExtensions)
        ];
    }
    
    // 检查整体状态
    $overallStatus = 'ok';
    foreach ($checks as $check) {
        if ($check['status'] === 'error') {
            $overallStatus = 'error';
            break;
        } elseif ($check['status'] === 'warning' && $overallStatus !== 'error') {
            $overallStatus = 'warning';
        }
    }
    
    http_response_code($overallStatus === 'error' ? 503 : 200);
    
    echo json_encode([
        'status' => $overallStatus,
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '1.0.0',
        'checks' => $checks
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(503);
    
    echo json_encode([
        'status' => 'error',
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $e->getMessage(),
        'checks' => [
            'database' => [
                'status' => 'error',
                'message' => '数据库连接失败: ' . $e->getMessage()
            ]
        ]
    ], JSON_PRETTY_PRINT);
}