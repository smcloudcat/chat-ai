<?php
/**
 * OIDC调试工具
 */

require_once 'includes/config.php';
require_once 'includes/database.php';

// 简单的身份验证，仅用于调试
if (!isset($_GET['debug']) || $_GET['debug'] !== 'true') {
    die('Access denied. Add ?debug=true to URL.');
}

echo "<h2>OIDC Configuration Debug</h2>\n";

echo "<h3>Basic Configuration:</h3>\n";
echo "<ul>\n";
echo "<li>Well-Known URL: " . OIDC_WELL_KNOWN_URL . "</li>\n";
echo "<li>Client ID: " . OIDC_CLIENT_ID . "</li>\n";
echo "<li>Redirect URI: " . OIDC_REDIRECT_URI . "</li>\n";
echo "</ul>\n";

echo "<h3>Testing Well-Known Endpoint:</h3>\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, OIDC_WELL_KNOWN_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'AI-Chat-System-Debug/1.0');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<p style='color: red;'>cURL Error: " . htmlspecialchars($error) . "</p>\n";
} else {
    echo "<p>HTTP Status Code: $httpCode</p>\n";
    
    if ($httpCode === 200) {
        $config = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "<h4>Discovered Configuration:</h4>\n";
            echo "<ul>\n";
            echo "<li>Authorization Endpoint: " . htmlspecialchars($config['authorization_endpoint'] ?? 'N/A') . "</li>\n";
            echo "<li>Token Endpoint: " . htmlspecialchars($config['token_endpoint'] ?? 'N/A') . "</li>\n";
            echo "<li>Userinfo Endpoint: " . htmlspecialchars($config['userinfo_endpoint'] ?? 'N/A') . "</li>\n";
            echo "<li>End Session Endpoint: " . htmlspecialchars($config['end_session_endpoint'] ?? 'N/A') . "</li>\n";
            echo "</ul>\n";
            
            // 尝试访问userinfo端点
            echo "<h3>Testing Userinfo Endpoint:</h3>\n";
            echo "<p>NOTE: This will fail without a valid access token, which is expected.</p>\n";
        } else {
            echo "<p style='color: red;'>JSON Parse Error: " . htmlspecialchars(json_last_error_msg()) . "</p>\n";
            echo "<p>Raw response: " . htmlspecialchars(substr($response, 0, 500)) . "...</p>\n";
        }
    } else {
        echo "<p style='color: red;'>Failed to fetch well-known configuration. HTTP Status: $httpCode</p>\n";
        echo "<p>Response: " . htmlspecialchars(substr($response, 0, 500)) . "...</p>\n";
    }
}

echo "<h3>PHP Environment:</h3>\n";
echo "<ul>\n";
echo "<li>PHP Version: " . PHP_VERSION . "</li>\n";
echo "<li>cURL Enabled: " . (function_exists('curl_version') ? 'Yes' : 'No') . "</li>\n";
echo "<li>OpenSSL Enabled: " . (extension_loaded('openssl') ? 'Yes' : 'No') . "</li>\n";
echo "<li>allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'Enabled' : 'Disabled') . "</li>\n";
echo "</ul>\n";

echo "<h3>Network Test:</h3>\n";
$testUrls = [
    'https://httpbin.org/get',
    'https://oauth.lwcat.cn/.well-known/openid_configuration'
];

foreach ($testUrls as $url) {
    echo "<p>Testing: $url</p>\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'AI-Chat-System-Debug/1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "<p style='color: red;'>  Error: " . htmlspecialchars($error) . "</p>\n";
    } else {
        echo "<p>  HTTP Status: $httpCode</p>\n";
    }
}

echo "<p><strong>Note:</strong> This debug script should be removed from production environments.</p>\n";