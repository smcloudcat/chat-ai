<?php
/**
 * OIDC认证类
 */

require_once 'config.php';
require_once 'database.php';
require_once 'utils/oidc_helper.php';

class OIDCAuth {
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $wellKnownUrl;
    private $providerConfig;

    public function __construct() {
        $this->clientId = OIDC_CLIENT_ID;
        $this->clientSecret = OIDC_CLIENT_SECRET;
        $this->redirectUri = OIDC_REDIRECT_URI;
        $this->wellKnownUrl = OIDC_WELL_KNOWN_URL;
        $this->providerConfig = $this->getProviderConfig();
    }

    /**
     * 获取OIDC提供者配置
     */
    private function getProviderConfig() {
        if (isset($_SESSION['oidc_provider_config']) &&
            $_SESSION['oidc_provider_config_expires'] > time()) {
            return $_SESSION['oidc_provider_config'];
        }

        // 使用辅助工具自动发现配置
        $result = OidcHelper::discoverProviderConfig();
        
        if (!$result['success']) {
            throw new Exception('无法发现OIDC提供者配置: ' . $result['error']);
        }
        
        // 更新wellKnownUrl为实际发现的URL
        $this->wellKnownUrl = $result['discovered_url'];
        
        // 缓存配置1小时
        $_SESSION['oidc_provider_config'] = $result['config'];
        $_SESSION['oidc_provider_config_expires'] = time() + 3600;

        return $result['config'];
    }

    /**
     * 生成随机字符串
     */
    private function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * 生成PKCE代码挑战
     */
    private function generateCodeChallenge($codeVerifier) {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    /**
     * 重定向到OIDC登录页面
     */
    public function redirectToLogin() {
        $state = $this->generateRandomString();
        $codeVerifier = $this->generateRandomString(64);
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        // 保存state和code_verifier到session
        $_SESSION['oidc_state'] = $state;
        $_SESSION['oidc_code_verifier'] = $codeVerifier;

        $authUrl = $this->providerConfig['authorization_endpoint'] . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256'
        ]);

        header('Location: ' . $authUrl);
        exit;
    }

    /**
     * 处理OIDC回调
     */
    public function handleCallback() {
        // 验证state参数
        if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oidc_state']) {
            throw new Exception('无效的state参数');
        }

        // 验证code参数
        if (!isset($_GET['code'])) {
            throw new Exception('未收到授权码');
        }

        $code = $_GET['code'];
        $codeVerifier = $_SESSION['oidc_code_verifier'];

        // 交换授权码获取token
        $tokenResponse = $this->exchangeCodeForToken($code, $codeVerifier);

        // 获取用户信息，同时传递access_token和id_token
        $idToken = $tokenResponse['id_token'] ?? null;
        $userInfo = $this->getUserInfo($tokenResponse['access_token'], $idToken);

        // 创建或更新用户
        $user = $this->createOrUpdateUser($userInfo);

        // 设置会话
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_info'] = $user;
        $_SESSION['login_time'] = time();

        // 清理临时数据
        unset($_SESSION['oidc_state']);
        unset($_SESSION['oidc_code_verifier']);

        return $user;
    }

    /**
     * 交换授权码获取token
     */
    private function exchangeCodeForToken($code, $codeVerifier) {
        $tokenEndpoint = $this->providerConfig['token_endpoint'];

        $postData = http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $codeVerifier
        ]);

        // 使用cURL替代file_get_contents
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenEndpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: AI-Chat-System/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('cURL错误 (token exchange): ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('获取访问令牌失败，HTTP状态码: ' . $httpCode . ', 响应: ' . $response);
        }

        if ($response === false) {
            throw new Exception('无法获取访问令牌');
        }

        $tokenData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('访问令牌解析失败: ' . json_last_error_msg());
        }

        if (isset($tokenData['error'])) {
            throw new Exception('获取访问令牌失败: ' . $tokenData['error'] . ' - ' . ($tokenData['error_description'] ?? ''));
        }

        return $tokenData;
    }

    /**
     * 获取用户信息
     */
    private function getUserInfo($accessToken, $idToken = null) {
        // 使用辅助工具获取用户信息，它会处理各种可能的情况
        return OidcHelper::getUserInfo($accessToken, $this->providerConfig, $idToken);
    }

    /**
     * 创建或更新用户
     */
    private function createOrUpdateUser($userInfo) {
        $db = getDB();

        // 检查用户是否已存在
        $stmt = $db->prepare("SELECT * FROM users WHERE oidc_sub = ?");
        $stmt->execute([$userInfo['sub']]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            // 更新用户信息
            $updateStmt = $db->prepare(
                "UPDATE users SET email = ?, nickname = ?, avatar_url = ?, updated_at = NOW() WHERE oidc_sub = ?"
            );
            $updateStmt->execute([
                $userInfo['email'] ?? null,
                $userInfo['nickname'] ?? $userInfo['name'] ?? null,
                $userInfo['picture'] ?? null,
                $userInfo['sub']
            ]);

            // 获取更新后的用户信息
            $stmt = $db->prepare("SELECT * FROM users WHERE oidc_sub = ?");
            $stmt->execute([$userInfo['sub']]);
            return $stmt->fetch();
        } else {
            // 创建新用户
            $insertStmt = $db->prepare(
                "INSERT INTO users (oidc_sub, email, nickname, avatar_url, created_at) VALUES (?, ?, ?, ?, NOW())"
            );
            $insertStmt->execute([
                $userInfo['sub'],
                $userInfo['email'] ?? null,
                $userInfo['nickname'] ?? $userInfo['name'] ?? null,
                $userInfo['picture'] ?? null
            ]);

            // 获取新创建的用户信息
            $userId = $db->lastInsertId();
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        }
    }

    /**
     * 检查用户是否已登录
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_info']);
    }

    /**
     * 获取当前登录用户信息
     */
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        return $_SESSION['user_info'];
    }

    /**
     * 登出
     */
    public static function logout() {
        unset($_SESSION['user_id']);
        unset($_SESSION['user_info']);
        unset($_SESSION['login_time']);
    }

    /**
     * 获取用户ID
     */
    public static function getCurrentUserId() {
        return self::isLoggedIn() ? $_SESSION['user_id'] : null;
    }
}

// 初始化会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}