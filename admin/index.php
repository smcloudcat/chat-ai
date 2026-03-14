<?php
/**
 * 后台管理系统 - 入口页面
 */
require_once '../includes/utils/session.php';
require_once '../includes/auth.php';

// 检查是否为管理员
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

// 获取管理员信息
$adminInfo = $_SESSION['admin_info'] ?? [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理系统</title>
    <link rel="stylesheet" href="https://cdn.lwcat.cn/layui/css/layui.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f2f2f2;
        }
        
        #admin-container {
            display: flex;
            height: 100vh;
        }
        
        #sidebar {
            width: 200px;
            background-color: #393D49;
            color: white;
            height: 100vh;
            position: fixed;
            z-index: 100;
        }
        
        #sidebar .logo {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #444;
            font-size: 18px;
            font-weight: bold;
        }
        
        #sidebar .menu {
            padding-top: 20px;
        }
        
        #sidebar .menu-item {
            padding: 12px 20px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        #sidebar .menu-item:hover {
            background-color: #4E5465;
        }
        
        #sidebar .menu-item.active {
            background-color: #009688;
        }
        
        #main-content {
            flex: 1;
            margin-left: 200px;
            padding: 20px;
            overflow-y: auto;
        }
        
        #header {
            height: 60px;
            background-color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
        }
        
        .logout-btn {
            margin-left: 20px;
        }
        
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #009688;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .content-section {
            background-color: white;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: none;
        }
        
        .content-section.active {
            display: block;
        }
    </style>
</head>
<body>
    <div id="admin-container">
        <!-- 侧边栏 -->
        <div id="sidebar">
            <div class="logo">AI聊天系统后台</div>
            <div class="menu">
                <div class="menu-item active" data-section="dashboard">仪表板</div>
                <div class="menu-item" data-section="chat-settings">聊天设置</div>
                <div class="menu-item" data-section="api-config">接口管理</div>
                <div class="menu-item" data-section="model-config">模型管理</div>
                <div class="menu-item" data-section="user-management">用户管理</div>
                <div class="menu-item" data-section="token-stats">Token统计</div>
                <div class="menu-item" data-section="system-config">系统配置</div>
            </div>
        </div>
        
        <!-- 主内容区 -->
        <div id="main-content">
            <div id="header">
                <h2>后台管理系统</h2>
                <div class="admin-info">
                    <span>管理员: <?= htmlspecialchars($adminInfo['username'] ?? '未知') ?></span>
                    <button id="logout-btn" class="layui-btn layui-btn-danger logout-btn">退出登录</button>
                </div>
            </div>
            
            <!-- 仪表板 -->
            <div id="dashboard" class="content-section active">
                <h3>系统概览</h3>
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <div class="stat-number" id="total-users">0</div>
                        <div class="stat-label">总用户数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="today-messages">0</div>
                        <div class="stat-label">今日消息数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="today-tokens">0</div>
                        <div class="stat-label">今日Token用量</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="total-conversations">0</div>
                        <div class="stat-label">总对话数</div>
                    </div>
                </div>
                
                <div class="layui-row layui-col-space15">
                    <div class="layui-col-md6">
                        <div class="layui-card">
                            <div class="layui-card-header">系统信息</div>
                            <div class="layui-card-body">
                                <p>PHP版本: <?= PHP_VERSION ?></p>
                                <p>数据库: MySQL</p>
                                <p>当前时间: <?= date('Y-m-d H:i:s') ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="layui-col-md6">
                        <div class="layui-card">
                            <div class="layui-card-header">最新活动</div>
                            <div class="layui-card-body" id="latest-activity">
                                <!-- 最新活动将通过JavaScript加载 -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 聊天设置 -->
            <div id="chat-settings" class="content-section">
                <h3>聊天设置</h3>
                <div class="layui-card">
                    <div class="layui-card-body">
                        <form class="layui-form" id="chat-settings-form">
                            <div class="layui-form-item">
                                <label class="layui-form-label">强制登录</label>
                                <div class="layui-input-block">
                                    <input type="checkbox" name="force_login" id="force_login" lay-skin="switch" lay-text="开启|关闭">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">游客每日Token上限</label>
                                <div class="layui-input-block">
                                    <input type="number" name="guest_daily_token_limit" id="guest_daily_token_limit" class="layui-input" placeholder="游客每日Token上限">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">用户每日Token上限</label>
                                <div class="layui-input-block">
                                    <input type="number" name="user_daily_token_limit" id="user_daily_token_limit" class="layui-input" placeholder="用户每日Token上限">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">Turnstile验证</label>
                                <div class="layui-input-block">
                                    <input type="checkbox" name="enable_turnstile" id="enable_turnstile" lay-skin="switch" lay-text="开启|关闭">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <div class="layui-input-block">
                                    <button class="layui-btn" lay-submit lay-filter="chat-settings-submit">保存设置</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- 接口管理 -->
            <div id="api-config" class="content-section">
                <h3>接口管理</h3>
                <div class="layui-card">
                    <div class="layui-card-body">
                        <button class="layui-btn layui-btn-normal" id="add-api-btn">添加接口</button>
                        <table class="layui-table" id="api-config-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>接口名称</th>
                                    <th>API地址</th>
                                    <th>是否启用</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="api-config-tbody">
                                <!-- 接口配置将通过JavaScript加载 -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- 模型管理 -->
            <div id="model-config" class="content-section">
                <h3>模型管理</h3>
                <div class="layui-card">
                    <div class="layui-card-body">
                        <button class="layui-btn layui-btn-normal" id="add-model-btn">添加模型</button>
                        <table class="layui-table" id="model-config-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>显示名称</th>
                                    <th>模型标识</th>
                                    <th>所属接口</th>
                                    <th>计费倍率</th>
                                    <th>是否启用</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="model-config-tbody">
                                <!-- 模型配置将通过JavaScript加载 -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- 用户管理 -->
            <div id="user-management" class="content-section">
                <h3>用户管理</h3>
                <div class="layui-card">
                    <div class="layui-card-body">
                        <table class="layui-table" id="user-management-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>邮箱</th>
                                    <th>昵称</th>
                                    <th>等级</th>
                                    <th>经验</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="user-management-tbody">
                                <!-- 用户数据将通过JavaScript加载 -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Token统计 -->
            <div id="token-stats" class="content-section">
                <h3>Token使用统计</h3>
                <div class="layui-card">
                    <div class="layui-card-body">
                        <div class="layui-row layui-col-space15">
                            <div class="layui-col-md6">
                                <div class="layui-card">
                                    <div class="layui-card-header">按日期统计</div>
                                    <div class="layui-card-body">
                                        <canvas id="tokenChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="layui-col-md6">
                                <div class="layui-card">
                                    <div class="layui-card-header">按用户统计</div>
                                    <div class="layui-card-body">
                                        <canvas id="userTokenChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 系统配置 -->
            <div id="system-config" class="content-section">
                <h3>系统配置</h3>
                <div class="layui-card">
                    <div class="layui-card-body">
                        <form class="layui-form" id="system-config-form">
                            <div class="layui-form-item">
                                <label class="layui-form-label">站点名称</label>
                                <div class="layui-input-block">
                                    <input type="text" name="site_name" id="site_name" class="layui-input" placeholder="站点名称">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <div class="layui-input-block">
                                    <button class="layui-btn" lay-submit lay-filter="system-config-submit">保存配置</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 添加/编辑API配置的弹窗 -->
    <div id="api-config-modal" style="display:none; padding: 20px;">
        <form class="layui-form" id="api-config-form">
            <input type="hidden" name="id" id="api_id">
            <div class="layui-form-item">
                <label class="layui-form-label">接口名称</label>
                <div class="layui-input-block">
                    <input type="text" name="name" id="api_name" required lay-verify="required" placeholder="接口名称" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">API地址</label>
                <div class="layui-input-block">
                    <input type="text" name="api_url" id="api_url" required lay-verify="required" placeholder="API地址" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">API密钥</label>
                <div class="layui-input-block">
                    <input type="password" name="api_key" id="api_key" required lay-verify="required" placeholder="API密钥" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">自定义Headers</label>
                <div class="layui-input-block">
                    <textarea name="custom_headers" id="custom_headers" placeholder="自定义Headers (JSON格式)" class="layui-textarea"></textarea>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">超时设置</label>
                <div class="layui-input-block">
                    <input type="number" name="timeout" id="timeout" value="60" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">是否启用</label>
                <div class="layui-input-block">
                    <input type="checkbox" name="is_enabled" id="api_is_enabled" lay-skin="switch" lay-text="开启|关闭">
                </div>
            </div>
            <div class="layui-form-item">
                <div class="layui-input-block">
                    <button class="layui-btn" lay-submit lay-filter="api-config-submit">保存</button>
                    <button type="button" class="layui-btn layui-btn-primary" id="cancel-api-btn">取消</button>
                </div>
            </div>
        </form>
    </div>

    <!-- 添加/编辑模型配置的弹窗 -->
    <div id="model-config-modal" style="display:none; padding: 20px;">
        <form class="layui-form" id="model-config-form">
            <input type="hidden" name="id" id="model_id">
            <div class="layui-form-item">
                <label class="layui-form-label">显示名称</label>
                <div class="layui-input-block">
                    <input type="text" name="display_name" id="model_display_name" required lay-verify="required" placeholder="显示名称" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">模型标识</label>
                <div class="layui-input-block">
                    <input type="text" name="model_key" id="model_model_key" required lay-verify="required" placeholder="模型标识" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">所属接口</label>
                <div class="layui-input-block">
                    <select name="api_config_id" id="model_api_config_id" required lay-verify="required">
                        <option value="">请选择接口</option>
                        <!-- 接口选项将通过JavaScript加载 -->
                    </select>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">计费倍率</label>
                <div class="layui-input-block">
                    <input type="number" name="billing_multiplier" id="model_billing_multiplier" step="0.01" value="1.00" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">排序权重</label>
                <div class="layui-input-block">
                    <input type="number" name="sort_order" id="model_sort_order" value="0" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">是否启用</label>
                <div class="layui-input-block">
                    <input type="checkbox" name="is_enabled" id="model_is_enabled" lay-skin="switch" lay-text="开启|关闭">
                </div>
            </div>
            <div class="layui-form-item">
                <div class="layui-input-block">
                    <button class="layui-btn" lay-submit lay-filter="model-config-submit">保存</button>
                    <button type="button" class="layui-btn layui-btn-primary" id="cancel-model-btn">取消</button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.lwcat.cn/layui/layui.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        layui.use(['element', 'form', 'layer', 'util'], function() {
            var element = layui.element;
            var form = layui.form;
            var layer = layui.layer;
            var util = layui.util;
            
            // 菜单切换
            document.querySelectorAll('.menu-item').forEach(item => {
                item.addEventListener('click', function() {
                    const sectionId = this.getAttribute('data-section');
                    
                    // 更新菜单活动状态
                    document.querySelectorAll('.menu-item').forEach(menuItem => {
                        menuItem.classList.remove('active');
                    });
                    this.classList.add('active');
                    
                    // 显示对应内容区域
                    document.querySelectorAll('.content-section').forEach(section => {
                        section.classList.remove('active');
                    });
                    document.getElementById(sectionId).classList.add('active');
                    
                    // 根据需要加载数据
                    switch(sectionId) {
                        case 'dashboard':
                            loadDashboardStats();
                            break;
                        case 'chat-settings':
                            loadChatSettings();
                            break;
                        case 'api-config':
                            loadApiConfigs();
                            break;
                        case 'model-config':
                            loadModelConfigs();
                            break;
                        case 'user-management':
                            loadUsers();
                            break;
                    }
                });
            });
            
            // 退出登录
            document.getElementById('logout-btn').addEventListener('click', function() {
                layer.confirm('确定要退出登录吗？', function(index) {
                    fetch('../api/auth/admin-logout.php', {
                        method: 'POST'
                    }).then(response => response.json())
                      .then(data => {
                          if (data.success) {
                              window.location.href = 'login.php';
                          } else {
                              layer.msg('退出登录失败');
                          }
                      })
                      .catch(error => {
                          layer.msg('退出登录失败');
                      });
                    layer.close(index);
                });
            });
            
            // 加载仪表板统计
            function loadDashboardStats() {
                fetch('../api/admin/dashboard-stats.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('total-users').textContent = data.stats.total_users;
                            document.getElementById('today-messages').textContent = data.stats.today_messages;
                            document.getElementById('today-tokens').textContent = data.stats.today_tokens;
                            document.getElementById('total-conversations').textContent = data.stats.total_conversations;
                            
                            // 显示最新活动
                            const activityDiv = document.getElementById('latest-activity');
                            activityDiv.innerHTML = '';
                            if (data.stats.latest_activity && data.stats.latest_activity.length > 0) {
                                data.stats.latest_activity.forEach(activity => {
                                    const p = document.createElement('p');
                                    p.textContent = activity;
                                    activityDiv.appendChild(p);
                                });
                            } else {
                                activityDiv.textContent = '暂无最新活动';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('加载仪表板统计失败:', error);
                    });
            }
            
            // 加载聊天设置
            function loadChatSettings() {
                fetch('../api/admin/chat-settings.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.settings) {
                            document.getElementById('force_login').checked = data.settings.force_login || false;
                            document.getElementById('guest_daily_token_limit').value = data.settings.guest_daily_token_limit || 10000;
                            document.getElementById('user_daily_token_limit').value = data.settings.user_daily_token_limit || 50000;
                            document.getElementById('enable_turnstile').checked = data.settings.enable_turnstile || false;
                            
                            form.render('checkbox');
                        }
                    })
                    .catch(error => {
                        console.error('加载聊天设置失败:', error);
                    });
            }
            
            // 保存聊天设置
            form.on('submit(chat-settings-submit)', function(data) {
                fetch('../api/admin/chat-settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data.field)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        layer.msg('设置保存成功');
                    } else {
                        layer.msg('设置保存失败: ' + (result.error || '未知错误'));
                    }
                })
                .catch(error => {
                    layer.msg('请求失败');
                });
                
                return false;
            });
            
            // 加载API配置
            function loadApiConfigs() {
                fetch('../api/admin/api-configs.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const tbody = document.getElementById('api-config-tbody');
                            tbody.innerHTML = '';
                            
                            data.configs.forEach(config => {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td>${config.id}</td>
                                    <td>${config.name}</td>
                                    <td>${config.api_url}</td>
                                    <td>${config.is_enabled ? '是' : '否'}</td>
                                    <td>
                                        <button class="layui-btn layui-btn-xs edit-api" data-id="${config.id}">编辑</button>
                                        <button class="layui-btn layui-btn-danger layui-btn-xs delete-api" data-id="${config.id}">删除</button>
                                    </td>
                                `;
                                tbody.appendChild(row);
                            });
                            
                            // 绑定编辑和删除事件
                            document.querySelectorAll('.edit-api').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    const id = this.getAttribute('data-id');
                                    editApiConfig(id);
                                });
                            });
                            
                            document.querySelectorAll('.delete-api').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    const id = this.getAttribute('data-id');
                                    deleteApiConfig(id);
                                });
                            });
                        }
                    })
                    .catch(error => {
                        console.error('加载API配置失败:', error);
                    });
            }
            
            // 编辑API配置
            function editApiConfig(id) {
                fetch(`../api/admin/api-configs.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.config) {
                            const config = data.config;
                            document.getElementById('api_id').value = config.id;
                            document.getElementById('api_name').value = config.name;
                            document.getElementById('api_url').value = config.api_url;
                            document.getElementById('api_key').value = config.api_key || '';
                            document.getElementById('custom_headers').value = config.custom_headers ? JSON.stringify(config.custom_headers) : '';
                            document.getElementById('timeout').value = config.timeout || 60;
                            document.getElementById('api_is_enabled').checked = config.is_enabled;
                            
                            form.render('checkbox');
                            
                            layer.open({
                                type: 1,
                                title: '编辑API配置',
                                content: document.getElementById('api-config-modal'),
                                area: ['600px', '500px'],
                                cancel: function() {
                                    resetApiForm();
                                }
                            });
                        }
                    })
                    .catch(error => {
                        console.error('获取API配置失败:', error);
                    });
            }
            
            // 删除API配置
            function deleteApiConfig(id) {
                layer.confirm('确定要删除这个API配置吗？', function(index) {
                    fetch(`../api/admin/api-configs.php`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({id: id})
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            layer.msg('删除成功');
                            loadApiConfigs(); // 重新加载列表
                        } else {
                            layer.msg('删除失败: ' + (result.error || '未知错误'));
                        }
                    })
                    .catch(error => {
                        layer.msg('请求失败');
                    });
                    
                    layer.close(index);
                });
            }
            
            // 添加API配置
            document.getElementById('add-api-btn').addEventListener('click', function() {
                document.getElementById('api-config-form').reset();
                document.getElementById('api_id').value = '';
                document.getElementById('api_is_enabled').checked = true;
                
                form.render('checkbox');
                
                layer.open({
                    type: 1,
                    title: '添加API配置',
                    content: document.getElementById('api-config-modal'),
                    area: ['600px', '500px'],
                    cancel: function() {
                        resetApiForm();
                    }
                });
            });
            
            // 重置API表单
            function resetApiForm() {
                document.getElementById('api-config-form').reset();
            }
            
            // 保存API配置
            form.on('submit(api-config-submit)', function(data) {
                const configData = {...data.field};
                configData.is_enabled = document.getElementById('api_is_enabled').checked ? 1 : 0;
                
                // 解析自定义Headers
                if (configData.custom_headers) {
                    try {
                        configData.custom_headers = JSON.parse(configData.custom_headers);
                    } catch (e) {
                        layer.msg('自定义Headers格式错误');
                        return false;
                    }
                }
                
                const url = configData.id ? '../api/admin/api-configs.php' : '../api/admin/api-configs.php';
                const method = configData.id ? 'PUT' : 'POST';
                
                fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(configData)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        layer.msg(configData.id ? '更新成功' : '添加成功');
                        layer.closeAll('page');
                        loadApiConfigs(); // 重新加载列表
                        resetApiForm();
                    } else {
                        layer.msg((configData.id ? '更新' : '添加') + '失败: ' + (result.error || '未知错误'));
                    }
                })
                .catch(error => {
                    layer.msg('请求失败');
                });
                
                return false;
            });
            
            // 取消API配置
            document.getElementById('cancel-api-btn').addEventListener('click', function() {
                layer.closeAll('page');
                resetApiForm();
            });
            
            // 加载模型配置
            function loadModelConfigs() {
                fetch('../api/admin/model-configs.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const tbody = document.getElementById('model-config-tbody');
                            tbody.innerHTML = '';
                            
                            data.configs.forEach(config => {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td>${config.id}</td>
                                    <td>${config.display_name}</td>
                                    <td>${config.model_key}</td>
                                    <td>${config.api_config_name || '未知'}</td>
                                    <td>${config.billing_multiplier}</td>
                                    <td>${config.is_enabled ? '是' : '否'}</td>
                                    <td>
                                        <button class="layui-btn layui-btn-xs edit-model" data-id="${config.id}">编辑</button>
                                        <button class="layui-btn layui-btn-danger layui-btn-xs delete-model" data-id="${config.id}">删除</button>
                                    </td>
                                `;
                                tbody.appendChild(row);
                            });
                            
                            // 绑定编辑和删除事件
                            document.querySelectorAll('.edit-model').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    const id = this.getAttribute('data-id');
                                    editModelConfig(id);
                                });
                            });
                            
                            document.querySelectorAll('.delete-model').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    const id = this.getAttribute('data-id');
                                    deleteModelConfig(id);
                                });
                            });
                        }
                    })
                    .catch(error => {
                        console.error('加载模型配置失败:', error);
                    });
            }
            
            // 编辑模型配置
            function editModelConfig(id) {
                fetch(`../api/admin/model-configs.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.config) {
                            const config = data.config;
                            document.getElementById('model_id').value = config.id;
                            document.getElementById('model_display_name').value = config.display_name;
                            document.getElementById('model_model_key').value = config.model_key;
                            document.getElementById('model_api_config_id').value = config.api_config_id;
                            document.getElementById('model_billing_multiplier').value = config.billing_multiplier;
                            document.getElementById('model_sort_order').value = config.sort_order || 0;
                            document.getElementById('model_is_enabled').checked = config.is_enabled;
                            
                            form.render('select');
                            form.render('checkbox');
                            
                            layer.open({
                                type: 1,
                                title: '编辑模型配置',
                                content: document.getElementById('model-config-modal'),
                                area: ['600px', '500px'],
                                cancel: function() {
                                    resetModelForm();
                                }
                            });
                        }
                    })
                    .catch(error => {
                        console.error('获取模型配置失败:', error);
                    });
            }
            
            // 删除模型配置
            function deleteModelConfig(id) {
                layer.confirm('确定要删除这个模型配置吗？', function(index) {
                    fetch(`../api/admin/model-configs.php`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({id: id})
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            layer.msg('删除成功');
                            loadModelConfigs(); // 重新加载列表
                        } else {
                            layer.msg('删除失败: ' + (result.error || '未知错误'));
                        }
                    })
                    .catch(error => {
                        layer.msg('请求失败');
                    });
                    
                    layer.close(index);
                });
            }
            
            // 添加模型配置
            document.getElementById('add-model-btn').addEventListener('click', function() {
                document.getElementById('model-config-form').reset();
                document.getElementById('model_id').value = '';
                document.getElementById('model_is_enabled').checked = true;
                
                // 加载接口选项
                loadApiOptions();
                
                form.render('select');
                form.render('checkbox');
                
                layer.open({
                    type: 1,
                    title: '添加模型配置',
                    content: document.getElementById('model-config-modal'),
                    area: ['600px', '500px'],
                    cancel: function() {
                        resetModelForm();
                    }
                });
            });
            
            // 加载接口选项
            function loadApiOptions() {
                fetch('../api/admin/api-configs.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const select = document.getElementById('model_api_config_id');
                            select.innerHTML = '<option value="">请选择接口</option>';
                            
                            data.configs.forEach(config => {
                                const option = document.createElement('option');
                                option.value = config.id;
                                option.textContent = config.name;
                                select.appendChild(option);
                            });
                            
                            form.render('select');
                        }
                    })
                    .catch(error => {
                        console.error('加载接口选项失败:', error);
                    });
            }
            
            // 重置模型表单
            function resetModelForm() {
                document.getElementById('model-config-form').reset();
            }
            
            // 保存模型配置
            form.on('submit(model-config-submit)', function(data) {
                const configData = {...data.field};
                configData.is_enabled = document.getElementById('model_is_enabled').checked ? 1 : 0;
                
                const url = configData.id ? '../api/admin/model-configs.php' : '../api/admin/model-configs.php';
                const method = configData.id ? 'PUT' : 'POST';
                
                fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(configData)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        layer.msg(configData.id ? '更新成功' : '添加成功');
                        layer.closeAll('page');
                        loadModelConfigs(); // 重新加载列表
                        resetModelForm();
                    } else {
                        layer.msg((configData.id ? '更新' : '添加') + '失败: ' + (result.error || '未知错误'));
                    }
                })
                .catch(error => {
                    layer.msg('请求失败');
                });
                
                return false;
            });
            
            // 取消模型配置
            document.getElementById('cancel-model-btn').addEventListener('click', function() {
                layer.closeAll('page');
                resetModelForm();
            });
            
            // 加载用户列表
            function loadUsers() {
                fetch('../api/admin/users.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const tbody = document.getElementById('user-management-tbody');
                            tbody.innerHTML = '';
                            
                            data.users.forEach(user => {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td>${user.id}</td>
                                    <td>${user.email || ''}</td>
                                    <td>${user.nickname || ''}</td>
                                    <td>${user.level}</td>
                                    <td>${user.experience}</td>
                                    <td>${user.is_active ? '激活' : '禁用'}</td>
                                    <td>
                                        <button class="layui-btn layui-btn-xs layui-btn-normal view-user" data-id="${user.id}">查看</button>
                                        <button class="layui-btn layui-btn-xs edit-user" data-id="${user.id}">编辑</button>
                                    </td>
                                `;
                                tbody.appendChild(row);
                            });
                            
                            // 绑定查看和编辑事件
                            document.querySelectorAll('.view-user').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    const id = this.getAttribute('data-id');
                                    viewUser(id);
                                });
                            });
                            
                            document.querySelectorAll('.edit-user').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    const id = this.getAttribute('data-id');
                                    editUser(id);
                                });
                            });
                        }
                    })
                    .catch(error => {
                        console.error('加载用户列表失败:', error);
                    });
            }
            
            // 查看用户
            function viewUser(id) {
                layer.open({
                    type: 2,
                    title: '查看用户信息',
                    content: `user-detail.php?id=${id}`,
                    area: ['800px', '600px']
                });
            }
            
            // 编辑用户
            function editUser(id) {
                layer.open({
                    type: 2,
                    title: '编辑用户信息',
                    content: `edit-user.php?id=${id}`,
                    area: ['800px', '600px']
                });
            }
            
            // 初始化页面
            loadDashboardStats();
        });
    </script>
</body>
</html>