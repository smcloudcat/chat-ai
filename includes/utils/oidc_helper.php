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
                $requiredEndpoints = ['authorization_endpoint', 'token_endpoint'];
                $missingEndpoints = [];
                
                foreach ($requiredEndpoints as $endpoint) {
                    if (empty($config[$endpoint])) {
                        $missingEndpoints[] = $endpoint;
                    }
                }
                
                // userinfo_endpoint 不是绝对必需的，因为有时需要通过其他方式获取用户信息
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
    
    /**
     * 获取用户信息，如果标准userinfo端点不可用，则尝试其他方式
     */
    public static function getUserInfo($accessToken, $providerConfig, $idToken = null) {
        // 首先尝试标准的userinfo端点
        if (!empty($providerConfig['userinfo_endpoint'])) {
            $result = self::testUserinfoEndpoint($providerConfig['userinfo_endpoint'], $accessToken);
            
            if ($result['http_code'] === 200) {
                $userInfo = json_decode($result['response'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $userInfo;
                }
            }
        }
        
        // 如果有ID token，尝试从ID token中解析用户信息
        if ($idToken !== null) {
            $userInfo = self::parseIdToken($idToken);
            if ($userInfo !== null) {
                return $userInfo;
            }
        }
        
        // 如果所有方法都失败，抛出异常
        $errorMsg = !empty($result) ?
            "Userinfo endpoint failed with HTTP {$result['http_code']}. This OIDC provider may not support the userinfo endpoint." :
            "No userinfo endpoint provided by OIDC provider";
        throw new Exception($errorMsg);
    }
    
    /**
     * 解析ID token以获取用户信息
     */
    private static function parseIdToken($idToken) {
        $tokenParts = explode('.', $idToken);
        if (count($tokenParts) !== 3) {
            return null;
        }
        
        // 解码JWT payload（第二部分）
        // JWT使用base64url编码，需要转换为标准base64
        $payload = $tokenParts[1];
        // 补充base64编码缺少的等号
        $padding = 4 - (strlen($payload) % 4);
        if ($padding !== 4) {
            $payload .= str_repeat('=', $padding);
        }
        // 将URL安全的base64转换为标准base64
        $payload = str_replace(['-', '_'], ['+', '/'], $payload);
        
        $decoded = base64_decode($payload);
        if ($decoded === false) {
            return null;
        }
        
        $payloadData = json_decode($decoded, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        // 返回ID token中包含的用户信息
        return $payloadData;
    }
}
}