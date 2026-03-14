<?php
/**
 * OIDC登录API
 */

require_once '../../includes/auth.php';

try {
    $auth = new OIDCAuth();
    $auth->redirectToLogin();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}