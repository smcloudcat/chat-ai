<?php
/**
 * 系统配置文件
 */

// 数据库配置
define('DB_HOST', 'mysql6.sqlpub.com:3311');
define('DB_NAME', 'aichat111');
define('DB_USER', 'aichat111');
define('DB_PASS', 'qkiXNCGapK9dsYN6');
define('DB_CHARSET', 'utf8mb4');

// OIDC配置
// 尝试使用連字符格式（標準格式）
define('OIDC_WELL_KNOWN_URL', 'https://oauth.lwcat.cn/.well-known/openid-configuration');
define('OIDC_CLIENT_ID', 'CATAI');
define('OIDC_CLIENT_SECRET', '55666');
define('OIDC_REDIRECT_URI', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api/auth/callback.php');

// 会话配置
define('SESSION_LIFETIME', 7 * 24 * 60 * 60); // 7天

// Token配置
define('GUEST_DAILY_TOKEN_LIMIT', 10000);
define('USER_DAILY_TOKEN_LIMIT', 50000);

// 系统配置
define('SITE_NAME', 'AI聊天系统');
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']);

// API配置
define('DEFAULT_TIMEOUT', 60);

// 加密密钥（在实际部署时应使用更安全的密钥）
define('ENCRYPTION_KEY', 'chat_ai_encryption_key_32_chars!');

// Turnstile配置（如果需要）
// define('TURNSTILE_SITE_KEY', '');
// define('TURNSTILE_SECRET_KEY', '');