
# AI聊天系统开发需求文档

## 项目概述
开发一个基于PHP的AI聊天Web应用，支持OpenAI-completions格式接口对接，包含前台用户系统和后台管理系统。操作尽量使用api实现

## 技术栈要求
- **前端**: HTML + 在基础layui（远程资源地址https://cdn.lwcat.cn/layui/layui.js,https://cdn.lwcat.cn/layui/css/layui.css)上美化 + JavaScript 
- **数据库**: MySQL (已提供远程连接)

## 详细功能需求

### 一、用户认证与OIDC集成

#### OIDC配置
- Well-Known URL: `https://oauth.lwcat.cn/.well-known/openid-configuration`
- Client ID: `CATAI`
- Client Secret: `55666`
- 支持OIDC标准授权码流程
- 自动获取用户信息(邮箱、昵称等)
- 支持OIDC登录/注册一体化

#### 会话管理
- 登录状态保持7天
- 未登录用户生成唯一设备ID存入localStorage

### 二、前台聊天功能

#### 聊天界面
- 类似ChatGPT的对话界面
- 支持Markdown渲染
- 代码高亮显示
- 对话历史侧边栏
- 模型选择下拉框(从后台配置获取)
- Token用量实时显示

#### 上下文管理
- 支持连续对话上下文
- 可配置上下文长度(如最近10条消息)
- 上下文重置功能

#### 历史记录
- **登录用户**: 对话历史保存到数据库
- **未登录用户**: 
  - 使用浏览器localStorage/IndexedDB存储
  - 支持本地历史记录管理(查看、删除、清空)
  - 存储容量限制提醒

#### Token限制展示
- 实时显示本次会话已用Token
- 显示每日剩余Token
- Token用尽提示

### 三、后台管理系统

#### 认证与权限
- 独立管理员登录(可配置超级管理员)
- RBAC权限控制

#### 聊天限制设置
| 设置项 | 说明 | 默认值 |
|--------|------|--------|
| 强制登录 | 是否必须登录才能聊天 | False |
| 游客每日Token上限 | 未登录用户每日可用Token | 10000 |
| 已登录用户每日Token上限 | 普通用户每日可用Token | 50000 |
| 首次聊天验证Turnstile | 是否开启CF人机验证 | False |
| Turnstile有效期 | 验证通过后有效时长(分钟) | 30 |

#### 接口管理
- 支持添加/编辑/删除OpenAI-completions格式接口
- 接口配置字段:
  - 接口名称(如"GPT-3.5", "GPT-4")
  - API地址
  - API Key
  - 支持自定义Headers
  - 超时设置(默认60秒)
  - 是否启用
  - 模型映射关系

#### 模型管理
- 添加/编辑/删除模型
- 模型字段:
  - 模型名称(显示用)
  - 模型标识(API调用用)
  - 所属接口(关联接口管理)
  - 计费倍率(1x, 2x等)
  - 是否启用
  - 排序权重

#### 用户等级与经验系统

##### 等级配置(5级)
| 等级 | 每日Token上限 | 升级所需经验 | 可后台配置 |
|------|--------------|------------|------------|
| Lv1  | 50000        | 0          | ✅ |
| Lv2  | 100000       | 100        | ✅ |
| Lv3  | 200000       | 300        | ✅ |
| Lv4  | 500000       | 600        | ✅ |
| Lv5  | 1000000      | 1000       | ✅ |

##### 签到系统
- **签到经验规则(可配置)**:
  1. 固定值: 每日获得固定经验
  2. 随机范围: 如5-15经验随机
  3. 累加模式: 连续签到经验递增(1,2,3...)，到达上限后保持
  
- 签到限制:
  - 每日只能签到一次
  - 可配置签到是否需要Turnstile验证
  - 签到成功经验即时生效

##### 用户管理
- 用户列表(显示OIDC用户信息)
- 搜索/筛选功能
- 查看用户详情:
  - 基本信息
  - 当前等级
  - 总经验值
  - 今日已用Token
  - 历史对话记录
- 操作功能:
  - 重置用户今日Token
  - 手动调整等级(经验值同步更新)
  - 封禁/解封用户
  - 查看Token使用日志

#### Token使用统计
- 实时监控面板
- 按用户/时间筛选
- 导出CSV报表
- 游客统计(按设备ID聚合)

### 四、数据库设计

#### 数据表结构

```sql
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
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 设备表(游客)
CREATE TABLE devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id VARCHAR(255) UNIQUE,
    daily_token_used INT DEFAULT 0,
    last_reset_date DATE,
    created_at TIMESTAMP
);

-- 对话历史表
CREATE TABLE conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,  -- NULL表示游客
    device_id VARCHAR(255) NULL,
    title VARCHAR(255),
    created_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 消息表
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT,
    role ENUM('user', 'assistant', 'system'),
    content TEXT,
    tokens INT,
    created_at TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
);

-- 签到记录表
CREATE TABLE sign_in_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    sign_date DATE,
    experience_gained INT,
    continuous_days INT,
    created_at TIMESTAMP,
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
    created_at TIMESTAMP,
    INDEX idx_user_date (user_id, created_at)
);

-- 后台配置表
CREATE TABLE system_configs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(100) UNIQUE,
    config_value JSON,
    updated_at TIMESTAMP
);
```

### 五、数据库连接信息
- **公网连接地址**: mysql6.sqlpub.com:3311
- **数据库名称**: aichat111
- **用户名**: aichat111
- **密码**: qkiXNCGapK9dsYN6

### 六、安全与防护

#### Cloudflare Turnstile集成
- 站点密钥和密钥对配置
- 前端验证集成
- 服务端验证接口
- 验证状态缓存(Redis/Memory)

#### 接口安全
- 速率限制(按IP/用户)
- SQL注入防护
- XSS防护
- CORS配置
- 敏感信息加密存储(API Keys)

### 七、非功能性需求

#### 性能要求
- 支持并发用户数: 100+
- 数据库连接池配置

#### 部署要求
- 环境变量配置
- 日志记录(访问日志、错误日志)
- 健康检查接口

#### 代码质量
- PEP 8规范
- 关键代码注释
- API文档(OpenAPI/Swagger)
- 单元测试(核心功能)

## 交付物要求
1. 完整可运行的php源代码
2. 数据库安装脚本
3. 部署文档
4. API接口文档

## 注意事项
- 确保OIDC认证流程正确实现
- Token计数需准确
- 游客数据存储需考虑隐私合规
- 后台配置修改需实时生效(可使用缓存)
- 错误处理需完善，避免暴露敏感信息