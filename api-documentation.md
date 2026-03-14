# AI聊天系统 API 文档

## 概述

AI聊天系统提供了一套完整的API接口，支持用户认证、聊天、模型管理等功能。

## 基础信息

- 基础URL: `http://your-domain.com/api`
- 内容类型: `application/json`
- 字符编码: `UTF-8`

## 认证API

### OIDC登录
- **POST** `/auth/login`
- 重定向到OIDC提供商进行用户认证

### OIDC回调
- **GET** `/auth/callback`
- OIDC认证完成后的回调接口

### 登出
- **POST** `/auth/logout`
- 用户登出

## 聊天API

### 发送消息
- **POST** `/chat/completions`
- 发送消息并获取AI回复

**请求体:**
```json
{
  "model": "gpt-3.5-turbo",
  "messages": [
    {
      "role": "user",
      "content": "你好"
    }
  ],
  "stream": false,
  "conversation_id": 123
}
```

**响应:**
```json
{
  "id": "chat-xxx",
  "object": "chat.completion",
  "created": 1234567890,
  "model": "gpt-3.5-turbo",
  "choices": [
    {
      "index": 0,
      "message": {
        "role": "assistant",
        "content": "你好！有什么我可以帮你的吗？"
      },
      "finish_reason": "stop"
    }
  ],
  "usage": {
    "prompt_tokens": 10,
    "completion_tokens": 20,
    "total_tokens": 30
  }
}
```

### 创建对话
- **POST** `/chat/conversations`
- 创建新的对话

**请求体:**
```json
{
  "title": "新对话"
}
```

**响应:**
```json
{
  "success": true,
  "conversation_id": 123
}
```

### 获取对话列表
- **GET** `/chat/conversations`
- 获取用户对话列表

**响应:**
```json
{
  "success": true,
  "conversations": [
    {
      "id": 123,
      "title": "新对话",
      "created_at": "2023-01-01 12:00:00"
    }
  ]
}
```

### 获取对话消息
- **GET** `/chat/messages?conversation_id=123`
- 获取指定对话的消息记录

**响应:**
```json
{
  "success": true,
  "messages": [
    {
      "id": 1,
      "role": "user",
      "content": "你好",
      "tokens": 2,
      "created_at": "2023-01-01 12:00:00"
    }
  ]
}
```

### 创建消息
- **POST** `/chat/messages`
- 创建消息记录

**请求体:**
```json
{
  "conversation_id": 123,
  "role": "user",
  "content": "你好",
  "tokens": 2
}
```

## Token相关API

### 获取Token使用情况
- **GET** `/chat/token-usage`
- 获取当前用户Token使用情况

**响应:**
```json
{
  "success": true,
  "usage": {
    "used": 1000,
    "limit": 50000,
    "remaining": 49000,
    "usage_percent": 2.0
  },
  "settings": {}
}
```

## 用户相关API

### 签到
- **GET/POST** `/user/signin`
- 用户签到功能

**GET响应:**
```json
{
  "success": true,
  "signed_in_today": true,
  "continuous_days": 5,
  "config": {}
}
```

**POST响应:**
```json
{
  "success": true,
  "message": "签到成功",
  "experience_gained": 10,
  "continuous_days": 6
}
```

## 后台管理API

### 仪表板统计
- **GET** `/admin/dashboard-stats`
- 获取系统统计信息

### 聊天设置
- **GET/POST** `/admin/chat-settings`
- 管理聊天相关设置

### API配置管理
- **GET/POST/PUT/DELETE** `/admin/api-configs`
- 管理API配置

### 模型配置管理
- **GET/POST/PUT/DELETE** `/admin/model-configs`
- 管理模型配置

### 用户管理
- **GET** `/admin/users`
- 获取用户列表

- **GET/PUT** `/admin/user-detail?id=123`
- 获取或更新用户详情

### Token统计
- **GET** `/admin/token-stats`
- 获取Token使用统计

## 工具API

### 系统配置
- **GET** `/utils/configs`
- 获取系统配置

### Turnstile验证
- **POST** `/utils/turnstile`
- 验证Turnstile令牌

## 错误码

| 错误码 | 说明 |
|--------|------|
| 400 | 请求参数错误 |
| 401 | 未授权访问 |
| 403 | 权限不足 |
| 404 | 资源不存在 |
| 405 | 请求方法不允许 |
| 500 | 服务器内部错误 |

## 安全措施

- 所有API请求都需要适当的认证
- 实现了CSRF保护
- 实现了请求频率限制
- 敏感信息加密存储
- 输入验证和清理