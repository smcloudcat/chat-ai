<?php
/**
 * 后台管理系统 - 登录页面
 */
require_once '../includes/utils/session.php';
require_once '../includes/database.php';

// 如果已经登录，重定向到首页
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $db = getDB();
        
        try {
            $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password_hash'])) {
                // 登录成功
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_info'] = [
                    'id' => $admin['id'],
                    'username' => $admin['username'],
                    'role' => $admin['role']
                ];
                
                // 更新最后登录时间
                $updateStmt = $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$admin['id']]);
                
                header('Location: index.php');
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        } catch (Exception $e) {
            $error = '登录失败：' . $e->getMessage();
        }
    } else {
        $error = '请输入用户名和密码';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - AI聊天系统后台</title>
    <link rel="stylesheet" href="https://cdn.lwcat.cn/layui/css/layui.css">
    <style>
        body {
            background-color: #f2f2f2;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        
        .login-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 400px;
        }
        
        .login-title {
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px;
            color: #333;
        }
        
        .layui-form-item {
            margin-bottom: 20px;
        }
        
        .login-btn {
            width: 100%;
        }
        
        .error-message {
            color: #ff4d4f;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="login-title">管理员登录</h2>
        
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form class="layui-form" method="post">
            <div class="layui-form-item">
                <label class="layui-form-label">用户名</label>
                <div class="layui-input-block">
                    <input type="text" name="username" required lay-verify="required" placeholder="请输入用户名" class="layui-input" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">密码</label>
                <div class="layui-input-block">
                    <input type="password" name="password" required lay-verify="required" placeholder="请输入密码" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <div class="layui-input-block">
                    <button class="layui-btn layui-btn-normal login-btn">登录</button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.lwcat.cn/layui/layui.js"></script>
    <script>
        layui.use(['form'], function() {
            var form = layui.form;
        });
    </script>
</body>
</html>