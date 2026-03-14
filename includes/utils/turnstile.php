<?php
/**
 * Cloudflare Turnstile 验证工具类
 */

class Turnstile {
    private $siteVerifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private $secretKey;
    
    public function __construct($secretKey = null) {
        // 从参数或环境变量获取密钥
        $this->secretKey = $secretKey ?: getenv('TURNSTILE_SECRET_KEY');
    }
    
    /**
     * 验证Turnstile令牌
     */
    public function verify($token, $remoteIp = null) {
        if (!$this->secretKey) {
            throw new Exception('Turnstile secret key not configured');
        }
        
        $data = [
            'secret' => $this->secretKey,
            'response' => $token,
            'remoteip' => $remoteIp
        ];
        
        // 使用cURL发送验证请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->siteVerifyUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Curl error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('HTTP error: ' . $httpCode);
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response');
        }
        
        return $result;
    }
    
    /**
     * 检查验证结果
     */
    public function isSuccess($result) {
        return isset($result['success']) && $result['success'] === true;
    }
    
    /**
     * 获取错误代码
     */
    public function getErrorCodes($result) {
        return $result['error-codes'] ?? [];
    }
}