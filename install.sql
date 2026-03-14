-- AI聊天系统数据库安装脚本

-- 创建数据库
CREATE DATABASE IF NOT EXISTS aichat111 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 使用数据库
USE aichat111;

-- 用户表
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    oidc_sub VARCHAR(255) UNIQUE,  -- OIDC唯一标识
    email VARCHAR(255),
    nickname VARCHAR(100),
    avatar_url TEXT,
    level INT DEFAULT 1,
    experience INT DEFAULT 0,
    daily_token_used INT DEFAULT 0,
    last_reset_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 设备表(游客)
CREATE TABLE devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id VARCHAR(255) UNIQUE,
    daily_token_used INT DEFAULT 0,
    last_reset_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 对话历史表
CREATE TABLE conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,  -- NULL表示游客
    device_id VARCHAR(255) NULL,
    title VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 消息表
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT,
    role ENUM('user', 'assistant', 'system'),
    content TEXT,
    tokens INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
);

-- 签到记录表
CREATE TABLE sign_in_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    sign_date DATE,
    experience_gained INT,
    continuous_days INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user_date (user_id, sign_date)
);

-- Token使用日志
CREATE TABLE token_usage_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    device_id VARCHAR(255) NULL,
    conversation_id INT,
    tokens_used INT,
    model VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at)
);

-- 后台配置表
CREATE TABLE system_configs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(100) UNIQUE,
    config_value JSON,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 接口配置表
CREATE TABLE api_configs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),  -- 接口名称
    api_url VARCHAR(255),  -- API地址
    api_key VARCHAR(255),  -- API密钥(加密存储)
    custom_headers JSON,  -- 自定义Headers
    timeout INT DEFAULT 60,  -- 超时设置
    is_enabled BOOLEAN DEFAULT TRUE,  -- 是否启用
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 模型配置表
CREATE TABLE model_configs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    display_name VARCHAR(100),  -- 模型名称(显示用)
    model_key VARCHAR(100),  -- 模型标识(API调用用)
    api_config_id INT,  -- 所属接口
    billing_multiplier DECIMAL(3,2) DEFAULT 1.00,  -- 计费倍率
    is_enabled BOOLEAN DEFAULT TRUE,  -- 是否启用
    sort_order INT DEFAULT 0,  -- 排序权重
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (api_config_id) REFERENCES api_configs(id)
);

-- 管理员表
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255),
    role VARCHAR(50) DEFAULT 'admin',  -- admin, super_admin
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 插入默认管理员账户 (用户名: admin, 密码: password123)
INSERT INTO admins (username, password_hash, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- 插入默认系统配置
INSERT INTO system_configs (config_key, config_value) VALUES 
('chat_settings', JSON_OBJECT('force_login', FALSE, 'guest_daily_token_limit', 10000, 'user_daily_token_limit', 50000, 'enable_turnstile', FALSE, 'turnstile_duration', 30)),
('level_config', JSON_OBJECT('lv1', JSON_OBJECT('token_limit', 50000, 'exp_needed', 0), 'lv2', JSON_OBJECT('token_limit', 100000, 'exp_needed', 100), 'lv3', JSON_OBJECT('token_limit', 200000, 'exp_needed', 300), 'lv4', JSON_OBJECT('token_limit', 500000, 'exp_needed', 600), 'lv5', JSON_OBJECT('token_limit', 1000000, 'exp_needed', 1000))),
('signin_config', JSON_OBJECT('type', 'fixed', 'fixed_value', 10, 'min_value', 5, 'max_value', 15, 'enable_turnstile', FALSE));