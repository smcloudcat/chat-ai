<?php
/**
 * Cloudflare Turnstile 验证API
 */

require_once '../../includes/utils/session.php';
require_once '../../includes/utils/turnstile.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            verifyTurnstile();
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
 * 验证Turnstile令牌
 */
function verifyTurnstile() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (empty($input['token'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing token']);
        return;
    }
    
    $token = $input['token'];
    $turnstile = new Turnstile();
    
    try {
        $result = $turnstile->verify($token, $_SERVER['REMOTE_ADDR']);
        
        if ($turnstile->isSuccess($result)) {
            // 验证成功，可以设置会话或其他操作
            $_SESSION['turnstile_verified'] = time();
            
            echo json_encode([
                'success' => true,
                'message' => '验证成功',
                'expires_at' => time() + 1800 // 30分钟有效期
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => '验证失败',
                'error_codes' => $turnstile->getErrorCodes($result)
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => '验证过程中发生错误: ' . $e->getMessage()
        ]);
    }
}