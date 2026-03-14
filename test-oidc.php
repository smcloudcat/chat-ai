<?php
/**
 * OIDC连接测试页面
 */

require_once 'includes/auth.php';
require_once 'includes/utils/oidc_helper.php';

// 简单的身份验证，仅用于测试
if (!isset($_GET['test']) || $_GET['test'] !== 'true') {
    die('Access denied. Add ?test=true to URL.');
}

echo "<h2>OIDC Connection Test</h2>\n";

try {
    echo "<h3>Testing OIDC Configuration Discovery...</h3>\n";
    
    $result = OidcHelper::discoverProviderConfig();
    
    if ($result['success']) {
        echo "<p style='color: green;'>✓ Successfully discovered OIDC configuration</p>\n";
        echo "<p>Discovered URL: " . htmlspecialchars($result['discovered_url']) . "</p>\n";
        
        echo "<h4>Discovered Endpoints:</h4>\n";
        echo "<ul>\n";
        echo "<li>Authorization: " . htmlspecialchars($result['config']['authorization_endpoint']) . "</li>\n";
        echo "<li>Token: " . htmlspecialchars($result['config']['token_endpoint']) . "</li>\n";
        echo "<li>Userinfo: " . htmlspecialchars($result['config']['userinfo_endpoint']) . "</li>\n";
        if (isset($result['config']['end_session_endpoint'])) {
            echo "<li>End Session: " . htmlspecialchars($result['config']['end_session_endpoint']) . "</li>\n";
        }
        echo "</ul>\n";
        
        // 测试访问各个端点（不带认证）
        echo "<h3>Testing Endpoints Accessibility...</h3>\n";
        
        // 测试Authorization端点
        echo "<p>Testing Authorization Endpoint...</p>\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $result['config']['authorization_endpoint']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AI-Chat-System-Test/1.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "<p>Authorization endpoint HTTP status: $httpCode</p>\n";
        
        // 测试Token端点
        echo "<p>Testing Token Endpoint...</p>\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $result['config']['token_endpoint']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AI-Chat-System-Test/1.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "<p>Token endpoint HTTP status: $httpCode</p>\n";
        
        // 测试Userinfo端点 (这会失败，因为我们没有有效的访问令牌)
        echo "<p>Testing Userinfo Endpoint (without token - expected to fail)...</p>\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $result['config']['userinfo_endpoint']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AI-Chat-System-Test/1.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "<p>Userinfo endpoint HTTP status: $httpCode (expected to fail without valid token)</p>\n";
        
        echo "<h3>Testing OIDC Authentication Flow...</h3>\n";
        echo "<p><a href='api/auth/login.php'>Click here to test OIDC login</a></p>\n";
        
    } else {
        echo "<p style='color: red;'>✗ Failed to discover OIDC configuration: " . htmlspecialchars($result['error']) . "</p>\n";
        
        // 尝试 with the original configured URL
        echo "<h3>Testing Original Configured URL...</h3>\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, OIDC_WELL_KNOWN_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AI-Chat-System-Test/1.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "<p>Original URL cURL error: " . htmlspecialchars($error) . "</p>\n";
        } else {
            echo "<p>Original URL HTTP status: $httpCode</p>\n";
            if ($httpCode === 200) {
                $config = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo "<p style='color: green;'>Original URL returned valid JSON</p>\n";
                } else {
                    echo "<p style='color: orange;'>Original URL returned invalid JSON: " . htmlspecialchars(json_last_error_msg()) . "</p>\n";
                }
            } else {
                echo "<p style='color: red;'>Original URL failed with HTTP $httpCode</p>\n";
                echo "<p>Response preview: " . htmlspecialchars(substr($response, 0, 200)) . "...</p>\n";
            }
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error during testing: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<h3>Current Configuration:</h3>\n";
echo "<ul>\n";
echo "<li>Well-Known URL: " . htmlspecialchars(OIDC_WELL_KNOWN_URL) . "</li>\n";
echo "<li>Client ID: " . htmlspecialchars(OIDC_CLIENT_ID) . "</li>\n";
echo "<li>Redirect URI: " . htmlspecialchars(OIDC_REDIRECT_URI) . "</li>\n";
echo "</ul>\n";

echo "<p><strong>Note:</strong> This test script should be removed from production environments.</p>\n";