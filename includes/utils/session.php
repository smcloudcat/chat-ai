<?php
/**
 * 会话管理工具类
 */

class SessionManager {
    /**
     * 初始化会话
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            // 设置会话参数
            ini_set('session.cookie_httponly', 1);  // 防止XSS攻击
            ini_set('session.use_strict_mode', 1); // 严格模式
            
            session_start();
        }
    }

    /**
     * 生成设备ID（用于未登录用户）
     */
    public static function generateDeviceId() {
        // 检查是否已存在设备ID
        if (isset($_COOKIE['device_id'])) {
            return $_COOKIE['device_id'];
        }

        // 生成新的设备ID
        $deviceId = bin2hex(random_bytes(16)); // 32位十六进制字符串
        
        // 设置cookie，有效期7天
        setcookie('device_id', $deviceId, time() + (7 * 24 * 60 * 60), '/', '', false, true);
        
        return $deviceId;
    }

    /**
     * 获取设备ID
     */
    public static function getDeviceId() {
        if (isset($_COOKIE['device_id'])) {
            return $_COOKIE['device_id'];
        }
        
        return self::generateDeviceId();
    }

    /**
     * 检查用户是否为登录用户
     */
    public static function isUserLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_info']);
    }

    /**
     * 获取当前用户ID（登录用户）或设备ID（未登录用户）
     */
    public static function getCurrentIdentity() {
        if (self::isUserLoggedIn()) {
            return [
                'type' => 'user',
                'id' => $_SESSION['user_id']
            ];
        } else {
            return [
                'type' => 'device',
                'id' => self::getDeviceId()
            ];
        }
    }

    /**
     * 获取当前用户ID
     */
    public static function getCurrentUserId() {
        if (self::isUserLoggedIn()) {
            return $_SESSION['user_id'];
        }
        return null;
    }

    /**
     * 获取当前设备ID
     */
    public static function getCurrentDeviceId() {
        return self::getDeviceId();
    }
}

// 初始化会话
SessionManager::init();