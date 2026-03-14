<?php
/**
 * OIDC辅助工具类
 * 用于处理OIDC端点发现和错误处理
 */

class OidcHelper {
    private $wellKnownUrls = [
        'https://oauth.lwcat.cn/.well-known/openid-configuration',  // 标准格式
        'https://oauth.lwcat.cn/.well-known/openid_configuration',  // 替代格式
    ];
    
    /**
     * 自动发现OIDC提供者配置
     */
    public static function discoverProviderConfig($wellKnownUrls = null) {
        if ($wellKnownUrls === null) {
            $wellKnownUrls = [
                'https://oauth.lwcat.cn/.well-known/openid-configuration',
                'https://oauth.lwcat.cn/.well-known/openid_configuration',
            ];
        }
        
        $lastError = null;
        
        foreach ($wellKnownUrls as $url) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'AI-Chat-System/1.0');
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    $lastError = "cURL error for $url: " . $error;
                    continue;
                }
                
                if ($httpCode !== 200) {
                    $lastError = "HTTP $httpCode for $url";
                    continue;
                }
                
                $config = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $lastError = "JSON parse error for $url: " . json_last_error_msg();
                    continue;
                }
                
                // 验证必需的端点
                $requiredEndpoints = ['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'];
                $missingEndpoints = [];
                
                foreach ($requiredEndpoints as $endpoint) {
                    if (empty($config[$endpoint])) {
                        $missingEndpoints[] = $endpoint;
                    }
                }
                
                if (!empty($missingEndpoints)) {
                    $lastError = "Missing required endpoints for $url: " . implode(', ', $missingEndpoints);
                    continue;
                }
                
                // 成功找到配置
                return [
                    'success' => true,
                    'config' => $config,
                    'discovered_url' => $url
                ];
            } catch (Exception $e) {
                $lastError = "Exception for $url: " . $e->getMessage();
                continue;
            }
        }
        
        return [
            'success' => false,
            'error' => $lastError ?: 'No valid OIDC configuration found',
            'config' => null
        ];
    }
    
    /**
     * 测试访问令牌和用户信息端点
     */
    public static function testUserinfoEndpoint($userinfoEndpoint, $accessToken) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userinfoEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'User-Agent: AI-Chat-System/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error
        ];
    }
}