<?php
/**
 * AI聊天系统 - 前台界面
 */
require_once 'includes/utils/session.php';
require_once 'includes/auth.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI聊天系统</title>
    <link rel="stylesheet" href="https://cdn.lwcat.cn/layui/css/layui.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css">
    <link rel="stylesheet" href="assets/css/chat.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            background-color: #f5f5f5;
        }
        
        #chat-container {
            display: flex;
            height: 100vh;
            position: relative;
        }
        
        #sidebar {
            width: 260px;
            background-color: #fff;
            border-right: 1px solid #eee;
            display: flex;
            flex-direction: column;
            z-index: 10;
        }
        
        #sidebar-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        #sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        
        #main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        #chat-header {
            padding: 15px 20px;
            background-color: #fff;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        #chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background-color: #fafafa;
        }
        
        .message {
            margin-bottom: 20px;
            padding: 10px 15px;
            border-radius: 8px;
            max-width: 80%;
            word-wrap: break-word;
            line-height: 1.5;
        }
        
        .user-message {
            background-color: #007aff;
            color: white;
            margin-left: auto;
            text-align: right;
        }
        
        .assistant-message {
            background-color: white;
            border: 1px solid #eee;
            color: #333;
        }
        
        #input-area {
            padding: 15px;
            background-color: #fff;
            border-top: 1px solid #eee;
        }
        
        #message-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 80px;
            max-height: 200px;
        }
        
        #send-btn {
            margin-top: 10px;
            width: 100%;
        }
        
        .conversation-item {
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-item:hover {
            background-color: #f0f0f0;
        }
        
        .conversation-item.active {
            background-color: #e6f7ff;
            color: #1890ff;
        }
        
        .token-info {
            font-size: 12px;
            color: #999;
            text-align: center;
            padding: 5px 0;
        }
        
        .login-info {
            padding: 10px 15px;
            border-top: 1px solid #eee;
        }
        
        .login-btn {
            width: 100%;
            margin-bottom: 10px;
        }
        
        .logout-btn {
            width: 100%;
        }
        
        .model-selector {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div id="chat-container">
        <!-- 侧边栏 -->
        <div id="sidebar">
            <div id="sidebar-header">
                <h3 style="margin: 0; font-size: 18px;">AI聊天</h3>
                <div class="token-info">Token: <span id="token-usage">0</span>/<span id="token-limit">0</span></div>
            </div>
            <div id="sidebar-content">
                <div class="layui-form">
                    <div class="layui-form-item model-selector">
                        <label class="layui-form-label" style="padding: 0 0 0 5px;">模型</label>
                        <div class="layui-input-block">
                            <select id="model-select" class="layui-select">
                                <option value="gpt-3.5-turbo">GPT-3.5</option>
                                <option value="gpt-4">GPT-4</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="conversations-list">
                    <!-- 对话历史将通过JavaScript动态加载 -->
                </div>
            </div>
            <div class="login-info">
                <?php if (OIDCAuth::isLoggedIn()): ?>
                    <div style="margin-bottom: 10px;">
                        <img src="<?= htmlspecialchars(OIDCAuth::getCurrentUser()['avatar_url'] ?? 'assets/images/default-avatar.png') ?>" style="width: 30px; height: 30px; border-radius: 50%; vertical-align: middle;">
                        <span style="margin-left: 8px;"><?= htmlspecialchars(OIDCAuth::getCurrentUser()['nickname'] ?? OIDCAuth::getCurrentUser()['email'] ?? '用户') ?></span>
                    </div>
                    <button id="logout-btn" class="layui-btn layui-btn-danger layui-btn-sm logout-btn">退出登录</button>
                <?php else: ?>
                    <button id="login-btn" class="layui-btn layui-btn-normal layui-btn-sm login-btn">OIDC登录</button>
                    <div style="margin-top: 10px; font-size: 12px; color: #999;">未登录用户将使用设备ID存储数据</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 主内容区 -->
        <div id="main-content">
            <div id="chat-header">
                <h4 id="chat-title">新对话</h4>
                <div>
                    <button id="new-chat-btn" class="layui-btn layui-btn-primary layui-btn-sm">新对话</button>
                    <button id="reset-context-btn" class="layui-btn layui-btn-primary layui-btn-sm">重置上下文</button>
                </div>
            </div>
            <div id="chat-messages">
                <!-- 消息将通过JavaScript动态加载 -->
                <div class="message assistant-message">
                    您好！我是AI助手，有什么可以帮助您的吗？
                </div>
            </div>
            <div id="input-area">
                <textarea id="message-input" placeholder="输入消息..."></textarea>
                <button id="send-btn" class="layui-btn layui-btn-normal">发送</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.lwcat.cn/layui/layui.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    <script src="assets/js/chat.js"></script>
    <script>
        // 初始化Layui
        layui.use(['element', 'form'], function() {
            var element = layui.element;
            var form = layui.form;
            
            // 渲染表单元素
            form.render();
        });
        
        // 页面加载完成后初始化聊天应用
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化聊天应用
            window.chatApp = new ChatApp();
            window.chatApp.init();
        });
    </script>
</body>
</html>