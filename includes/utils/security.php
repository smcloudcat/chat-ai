<?php
/**
 * 安全工具类
 */

class Security {
    /**
     * 验证CSRF令牌
     */
    public static function verifyCsrfToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * 生成CSRF令牌
     */
    public static function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * 清理用户输入
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * 验证输入是否为有效URL
     */
    public static function isValidUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * 验证IP地址
     */
    public static function isValidIp($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * 限制请求频率
     */
    public static function rateLimit($key, $maxRequests, $timeWindow) {
        $cacheKey = "rate_limit_$key";
        $data = self::getCache($cacheKey);
        
        if (!$data) {
            $data = ['count' => 0, 'reset_time' => time() + $timeWindow];
        }
        
        if (time() > $data['reset_time']) {
            $data = ['count' => 0, 'reset_time' => time() + $timeWindow];
        }
        
        $data['count']++;
        
        self::setCache($cacheKey, $data, $timeWindow);
        
        return [
            'allowed' => $data['count'] <= $maxRequests,
            'remaining' => max(0, $maxRequests - $data['count']),
            'reset_time' => $data['reset_time']
        ];
    }
    
    /**
     * 检查是否需要Turnstile验证
     */
    public static function needsTurnstileVerification() {
        // 检查系统配置是否启用了Turnstile
        $db = getDB();
        $stmt = $db->prepare("SELECT config_value FROM system_configs WHERE config_key = 'chat_settings'");
        $stmt->execute();
        $config = $stmt->fetch();
        
        if ($config) {
            $settings = json_decode($config['config_value'], true);
            $enableTurnstile = $settings['enable_turnstile'] ?? false;
            $turnstileDuration = $settings['turnstile_duration'] ?? 30; // 分钟
            
            if ($enableTurnstile) {
                // 检查用户是否在有效验证期内
                if (isset($_SESSION['turnstile_verified'])) {
                    $validUntil = $_SESSION['turnstile_verified'] + ($turnstileDuration * 60);
                    return time() > $validUntil; // 验证已过期，需要重新验证
                }
                return true; // 没有验证记录，需要验证
            }
        }
        
        return false;
    }
    
    /**
     * 检查用户权限
     */
    public static function checkUserPermission($requiredLevel = 1) {
        // 这里可以实现更复杂的权限检查逻辑
        if (!OIDCAuth::isLoggedIn()) {
            return false;
        }
        
        $user = OIDCAuth::getCurrentUser();
        return $user['level'] >= $requiredLevel;
    }
    
    /**
     * 记录安全事件
     */
    public static function logSecurityEvent($event, $details = []) {
        $logData = [
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => date('Y-m-d H:i:s'),
            'details' => $details
        ];
        
        error_log('[SECURITY] ' . json_encode($logData));
    }
    
    /**
     * 简单的缓存实现（使用session）
     */
    private static function getCache($key) {
        $cacheKey = "security_cache_$key";
        if (isset($_SESSION[$cacheKey])) {
            $data = $_SESSION[$cacheKey];
            if (time() < $data['expires']) {
                return $data['value'];
            } else {
                unset($_SESSION[$cacheKey]);
            }
        }
        return null;
    }
    
    private static function setCache($key, $value, $ttl) {
        $cacheKey = "security_cache_$key";
        $_SESSION[$cacheKey] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
    }
}