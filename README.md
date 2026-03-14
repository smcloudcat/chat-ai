# AI聊天系统

基于PHP开发的AI聊天Web应用，支持OpenAI-completions格式接口对接，包含前台用户系统和后台管理系统。

## 项目特性

- OIDC用户认证系统
- 类似ChatGPT的对话界面
- Markdown渲染和代码高亮
- 上下文管理
- 历史记录存储
- Token用量实时显示
- 用户等级与经验系统
- 签到系统
- 后台管理系统
- Cloudflare Turnstile人机验证

## 技术栈

- **前端**: HTML + layui + JavaScript
- **后端**: PHP
- **数据库**: MySQL

## 安装说明

1. 克隆项目到本地
2. 配置数据库连接信息
3. 运行数据库安装脚本
4. 配置Web服务器

## 目录结构

```
chat-ai/
├── api/                    # API接口文件
│   ├── auth/              # 认证相关接口
│   ├── chat/              # 聊天相关接口
│   ├── admin/             # 后台管理接口
│   └── utils/             # 工具类接口
├── assets/                # 静态资源
│   ├── css/               # 样式文件
│   ├── js/                # JavaScript文件
│   └── images/            # 图片资源
├── includes/              # PHP包含文件
│   ├── config.php         # 配置文件
│   ├── database.php       # 数据库连接
│   ├── auth.php           # 认证类
│   ├── models/            # 数据模型
│   └── utils/             # 工具函数
├── views/                 # 前端页面模板
│   ├── frontend/          # 前台页面
│   └── backend/           # 后台页面
├── admin/                 # 后台管理页面
├── index.php              # 前台入口文件
├── install.sql            # 数据库安装脚本
└── README.md              # 项目说明
```

## 配置要求

- PHP 7.4+
- MySQL 5.7+
- Web服务器 (Apache/Nginx)