<?php
/**
 * 用户模型类
 */

require_once '../database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * 获取用户信息
     */
    public function getUserById($id) {
        $stmt = $this->db->prepare("
            SELECT id, oidc_sub, email, nickname, avatar_url, level, experience,
                   daily_token_used, last_reset_date, is_active, created_at, updated_at
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * 更新用户等级和经验
     */
    public function updateUserLevel($userId, $newLevel, $newExperience) {
        $stmt = $this->db->prepare("
            UPDATE users
            SET level = ?, experience = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$newLevel, $newExperience, $userId]);
    }
    
    /**
     * 增加用户经验
     */
    public function addExperience($userId, $expAmount) {
        $user = $this->getUserById($userId);
        if (!$user) {
            return false;
        }
        
        $newExperience = $user['experience'] + $expAmount;
        $newLevel = $this->calculateLevelFromExperience($newExperience);
        
        return $this->updateUserLevel($userId, $newLevel, $newExperience);
    }
    
    /**
     * 根据经验计算等级
     */
    public function calculateLevelFromExperience($experience) {
        // 获取等级配置
        $levelConfig = $this->getLevelConfig();
        
        // 从高到低检查等级要求
        // 配置格式: ['lv1' => ['exp_needed' => 0, 'token_limit' => 50000], ...]
        $levels = [];
        foreach ($levelConfig as $key => $config) {
            $levelNum = intval(substr($key, 2)); // 提取lv后面的数字
            $levels[$levelNum] = $config;
        }
        
        krsort($levels); // 降序排列
        
        foreach ($levels as $levelNum => $config) {
            if ($experience >= $config['exp_needed']) {
                return $levelNum;
            }
        }
        
        // 默认返回1级
        return 1;
    }
    
    /**
     * 获取等级配置
     */
    public function getLevelConfig() {
        $stmt = $this->db->prepare("SELECT config_value FROM system_configs WHERE config_key = 'level_config'");
        $stmt->execute();
        $config = $stmt->fetch();
        
        if ($config) {
            return json_decode($config['config_value'], true);
        }
        
        // 默认等级配置
        return [
            'lv1' => ['exp_needed' => 0, 'token_limit' => 50000],
            'lv2' => ['exp_needed' => 100, 'token_limit' => 100000],
            'lv3' => ['exp_needed' => 300, 'token_limit' => 200000],
            'lv4' => ['exp_needed' => 600, 'token_limit' => 500000],
            'lv5' => ['exp_needed' => 1000, 'token_limit' => 1000000]
        ];
    }
    
    /**
     * 重置用户当日Token使用量
     */
    public function resetDailyTokenUsage($userId) {
        $stmt = $this->db->prepare("
            UPDATE users
            SET daily_token_used = 0, last_reset_date = CURDATE()
            WHERE id = ?
        ");
        return $stmt->execute([$userId]);
    }
    
    /**
     * 更新用户Token使用量
     */
    public function updateTokenUsage($userId, $tokensUsed) {
        $user = $this->getUserById($userId);
        if (!$user) {
            return false;
        }
        
        // 检查是否需要重置（新日期）
        if ($user['last_reset_date'] !== date('Y-m-d')) {
            $this->resetDailyTokenUsage($userId);
        }
        
        $stmt = $this->db->prepare("
            UPDATE users
            SET daily_token_used = daily_token_used + ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$tokensUsed, $userId]);
    }
    
    /**
     * 获取用户Token限制
     */
    public function getUserTokenLimit($userId) {
        $user = $this->getUserById($userId);
        if (!$user) {
            return 0;
        }
        
        // 获取等级配置
        $levelConfig = $this->getLevelConfig();
        $levelKey = 'lv' . $user['level'];
        
        if (isset($levelConfig[$levelKey]['token_limit'])) {
            return $levelConfig[$levelKey]['token_limit'];
        }
        
        // 默认返回1级的限制
        return $levelConfig['lv1']['token_limit'] ?? 50000;
    }
    
    /**
     * 检查用户Token是否超限
     */
    public function isTokenLimitExceeded($userId) {
        $user = $this->getUserById($userId);
        if (!$user) {
            return true; // 如果用户不存在，视为超限
        }
        
        $tokenLimit = $this->getUserTokenLimit($userId);
        return $user['daily_token_used'] >= $tokenLimit;
    }
    
    /**
     * 获取用户等级信息
     */
    public function getUserLevelInfo($userId) {
        $user = $this->getUserById($userId);
        if (!$user) {
            return null;
        }
        
        $levelConfig = $this->getLevelConfig();
        $levelKey = 'lv' . $user['level'];
        
        // 计算到下一级还需要的经验
        $nextLevel = $user['level'] + 1;
        $nextLevelKey = 'lv' . $nextLevel;
        $expForNextLevel = isset($levelConfig[$nextLevelKey]) ?
            $levelConfig[$nextLevelKey]['exp_needed'] - $user['experience'] : 0;
        
        return [
            'current_level' => $user['level'],
            'current_experience' => $user['experience'],
            'token_limit' => $this->getUserTokenLimit($userId),
            'next_level' => $nextLevel > 5 ? null : $nextLevel, // 最高等级为5
            'exp_for_next_level' => $nextLevel > 5 ? 0 : max(0, $expForNextLevel),
            'level_percentage' => $this->getLevelPercentage($user['level'], $user['experience'])
        ];
    }
    
    /**
     * 计算当前等级进度百分比
     */
    public function getLevelPercentage($currentLevel, $currentExp) {
        $levelConfig = $this->getLevelConfig();
        
        if ($currentLevel >= 5) {
            // 最高等级，显示100%
            return 100;
        }
        
        $currentLevelKey = 'lv' . $currentLevel;
        $nextLevelKey = 'lv' . ($currentLevel + 1);
        
        if (!isset($levelConfig[$currentLevelKey]) || !isset($levelConfig[$nextLevelKey])) {
            return 0;
        }
        
        $currentLevelExp = $levelConfig[$currentLevelKey]['exp_needed'];
        $nextLevelExp = $levelConfig[$nextLevelKey]['exp_needed'];
        
        // 计算当前等级内的进度
        $levelRange = $nextLevelExp - $currentLevelExp;
        $currentLevelProgress = $currentExp - $currentLevelExp;
        
        if ($levelRange <= 0) {
            return 100; // 如果配置有问题，返回100%
        }
        
        $percentage = ($currentLevelProgress / $levelRange) * 100;
        return min(100, max(0, $percentage)); // 限制在0-100之间
    }
}