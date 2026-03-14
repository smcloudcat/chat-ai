# AI聊天系统 部署指南

## 环境要求

### 服务器要求
- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- Web服务器 (Apache/Nginx)
- cURL 扩展
- OpenSSL 扩展
- JSON 扩展
- PDO 扩展

### PHP配置要求
- `memory_limit` 至少 256M
- `upload_max_filesize` 适当设置
- `post_max_size` 适当设置
- `max_execution_time` 适当设置

## 部署步骤

### 1. 克隆代码
```bash
git clone <repository-url>
cd chat-ai
```

### 2. 配置数据库

#### 创建数据库
```sql
CREATE DATABASE aichat111 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 执行安装脚本
```bash
mysql -u username -p database_name < install.sql
```

### 3. 配置文件设置

编辑 `includes/config.php` 文件，根据您的环境更新以下配置：

```php
// 数据库配置
define('DB_HOST', 'your_mysql_host:port');
define('DB_NAME', 'aichat111');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// OIDC配置
define('OIDC_WELL_KNOWN_URL', 'https://oauth.lwcat.cn/.well-known/openid-configuration');
define('OIDC_CLIENT_ID', 'your_client_id');
define('OIDC_CLIENT_SECRET', 'your_client_secret');
define('OIDC_REDIRECT_URI', 'https://your-domain.com/api/auth/callback.php');
```

### 4. Web服务器配置

#### Apache配置示例
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/chat-ai

    <Directory /path/to/chat-ai>
        AllowOverride All
        Require all granted
    </Directory>

    # 重写规则
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</VirtualHost>
```

#### Nginx配置示例
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/chat-ai;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 5. 目录权限设置

确保以下目录具有适当的写入权限：
```bash
# 如果需要日志记录
chmod 755 logs/
# 如果有上传功能
chmod 755 uploads/
```

### 6. 环境变量配置（可选）

如果使用环境变量，创建 `.env` 文件：
```
DB_HOST=your_mysql_host:port
DB_NAME=aichat111
DB_USER=your_db_username
DB_PASS=your_db_password
TURNSTILE_SITE_KEY=your_turnstile_site_key
TURNSTILE_SECRET_KEY=your_turnstile_secret_key
```

### 7. SSL配置（推荐）

为确保安全，建议配置SSL证书：
```bash
# 使用Let's Encrypt
sudo certbot --nginx -d your-domain.com
```

## OIDC配置

### OIDC提供商设置
- Well-Known URL: `https://oauth.lwcat.cn/.well-known/openid-configuration`
- Client ID: `CATAI`
- Client Secret: `55666`
- 重定向URL: `https://your-domain.com/api/auth/callback.php`

## 管理员账户

### 初始管理员账户
- 用户名: `admin`
- 密码: `password123` (请在首次登录后立即更改)

### 创建新管理员
通过数据库直接插入管理员记录：
```sql
INSERT INTO admins (username, password_hash, role, is_active) 
VALUES ('new_admin', '$2y$10$...', 'admin', 1);
```

## 安全配置

### 1. 保护敏感目录
确保以下目录无法通过Web访问：
- `includes/`
- `api/` (部分API需要保护)

### 2. 设置适当的文件权限
```bash
# 设置文件权限
find /path/to/chat-ai -type f -exec chmod 644 {} \;
find /path/to/chat-ai -type d -exec chmod 755 {} \;
```

### 3. 配置防火墙
限制对数据库端口的访问，只允许本地访问。

## 性能优化

### 1. 启用OPcache
在 `php.ini` 中启用OPcache：
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

### 2. 数据库优化
- 定期优化数据库表
- 添加适当的索引
- 配置查询缓存

### 3. CDN配置
考虑使用CDN来加速静态资源加载。

## 监控和日志

### 1. 错误日志
错误日志将记录在PHP错误日志中，也可以配置自定义日志：

### 2. 访问日志
Web服务器会记录访问日志。

## 备份策略

### 数据库备份
```bash
mysqldump -u username -p database_name > backup.sql
```

### 文件备份
```bash
tar -czf files-backup.tar.gz /path/to/chat-ai
```

## 故障排除

### 常见问题

1. **500错误**
   - 检查PHP错误日志
   - 确认文件权限
   - 检查配置文件

2. **数据库连接错误**
   - 检查数据库配置
   - 确认数据库服务运行
   - 检查数据库用户权限

3. **OIDC登录失败**
   - 检查回调URL配置
   - 确认OIDC提供商设置

### 调试模式
在开发环境中，可以启用调试模式：
```php
// 在配置文件中设置
define('DEBUG_MODE', true);
```

## 更新和维护

### 代码更新
```bash
git pull origin main
# 检查是否有数据库迁移需求
```

### 数据库迁移
如果有数据库结构变更，需要执行相应的SQL脚本。

## 健康检查

系统提供健康检查端点：
- `GET /api/health` - 检查系统健康状态

## 性能监控

- 数据库连接池配置
- 内存使用监控
- 请求响应时间监控