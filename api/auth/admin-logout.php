<?php
/**
 * 管理员登出API
 */

require_once '../../includes/utils/session.php';

header('Content-Type: application/json');

try {
    // 清除管理员会话
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_info']);
    
    echo json_encode([
        'success' => true,
        'message' => '登出成功'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}