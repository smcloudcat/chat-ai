<?php
/**
 * OIDC回调API
 */

require_once '../../includes/auth.php';

header('Content-Type: application/json');

try {
    $auth = new OIDCAuth();
    $user = $auth->handleCallback();
    
    // 返回成功响应
    echo json_encode([
        'success' => true,
        'user' => $user,
        'message' => '登录成功'
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}