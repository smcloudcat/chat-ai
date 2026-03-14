<?php
/**
 * 登出API
 */

require_once '../../includes/auth.php';

header('Content-Type: application/json');

try {
    OIDCAuth::logout();
    
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