/**
 * 聊天应用主类
 */
class ChatApp {
    constructor() {
        this.currentConversationId = null;
        this.currentModel = 'gpt-3.5-turbo';
        this.tokenUsage = 0;
        this.tokenLimit = 0;
        this.messages = [];
        this.isGenerating = false;
        
        // 绑定事件
        this.bindEvents();
    }
    
    /**
     * 初始化应用
     */
    init() {
        this.loadConversations();
        this.updateTokenDisplay();
        this.loadSystemConfigs();
        this.startTokenUsageUpdates();
    }
    
    /**
     * 绑定事件
     */
    bindEvents() {
        // 发送消息事件
        document.getElementById('send-btn').addEventListener('click', () => {
            this.sendMessage();
        });
        
        // 回车发送消息
        document.getElementById('message-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!this.isGenerating) {
                    this.sendMessage();
                }
            }
        });
        
        // 模型选择事件
        document.getElementById('model-select').addEventListener('change', (e) => {
            this.currentModel = e.target.value;
        });
        
        // 新对话按钮
        document.getElementById('new-chat-btn').addEventListener('click', () => {
            this.newConversation();
        });
        
        // 重置上下文按钮
        document.getElementById('reset-context-btn').addEventListener('click', () => {
            this.resetContext();
        });
        
        // 登录按钮
        document.getElementById('login-btn').addEventListener('click', () => {
            window.location.href = 'api/auth/login.php';
        });
        
        // 登出按钮
        document.getElementById('logout-btn').addEventListener('click', () => {
            this.logout();
        });
    }
    
    /**
     * 发送消息
     */
    async sendMessage() {
        const inputElement = document.getElementById('message-input');
        const message = inputElement.value.trim();
        
        if (!message) return;
        if (this.isGenerating) return;
        
        // 检查token限制
        if (this.tokenUsage >= this.tokenLimit && this.tokenLimit > 0) {
            alert('今日Token已用完，请明天再试或登录以获取更多配额');
            return;
        }
        
        // 创建新的对话（如果当前没有对话）
        if (!this.currentConversationId) {
            await this.createConversation(message.substring(0, 30) + (message.length > 30 ? '...' : ''));
        }
        
        // 显示用户消息
        this.displayMessage(message, 'user');
        inputElement.value = '';
        
        // 显示加载指示器
        this.showTypingIndicator();
        
        this.isGenerating = true;
        this.updateSendButtonState();
        
        try {
            // 调用API获取AI回复
            const response = await this.callChatAPI(message);
            
            // 隐藏加载指示器
            this.hideTypingIndicator();
            
            // 显示AI回复
            if (response.success) {
                this.displayMessage(response.content, 'assistant');
                
                // 更新token使用量
                if (response.tokens) {
                    this.tokenUsage += response.tokens;
                    this.updateTokenDisplay();
                }
                
                // 保存消息到数据库
                this.saveMessage(this.currentConversationId, 'user', message);
                this.saveMessage(this.currentConversationId, 'assistant', response.content);
            } else {
                this.displayMessage('抱歉，发生错误：' + response.error, 'assistant');
            }
        } catch (error) {
            // 隐藏加载指示器
            this.hideTypingIndicator();
            this.displayMessage('抱歉，发生错误：' + error.message, 'assistant');
        } finally {
            this.isGenerating = false;
            this.updateSendButtonState();
        }
    }
    
    /**
     * 调用聊天API
     */
    async callChatAPI(userMessage) {
        // 这里应该调用后端API
        // 为演示目的，我们使用模拟响应
        // 在实际实现中，这里会调用后端的聊天API
        
        // 获取上下文消息
        const contextMessages = this.getContextMessages();
        
        // 构造请求数据
        const requestData = {
            model: this.currentModel,
            messages: [
                ...contextMessages,
                { role: 'user', content: userMessage }
            ],
            stream: false
        };
        
        try {
            const response = await fetch('api/chat/completions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestData)
            });
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error.message || 'API调用失败');
            }
            
            return {
                success: true,
                content: data.choices[0].message.content,
                tokens: data.usage ? data.usage.total_tokens : 0
            };
        } catch (error) {
            console.error('API调用失败:', error);
            // 如果API调用失败，返回模拟响应
            return {
                success: false,
                error: error.message
            };
        }
    }
    
    /**
     * 获取上下文消息
     */
    getContextMessages() {
        // 返回最近的10条消息作为上下文
        return this.messages.slice(-10).map(msg => ({
            role: msg.role,
            content: msg.content
        }));
    }
    
    /**
     * 显示消息
     */
    displayMessage(content, role) {
        const chatMessages = document.getElementById('chat-messages');
        
        // 创建消息元素
        const messageElement = document.createElement('div');
        messageElement.classList.add('message');
        messageElement.classList.add(role + '-message');
        
        // 转换Markdown为HTML并应用代码高亮
        messageElement.innerHTML = this.renderMarkdown(content);
        
        chatMessages.appendChild(messageElement);
        
        // 应用代码高亮
        this.applyCodeHighlighting(messageElement);
        
        // 滚动到底部
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        // 保存到本地消息数组
        this.messages.push({
            role: role,
            content: content
        });
    }
    
    /**
     * 渲染Markdown
     */
    renderMarkdown(text) {
        // 使用更完善的Markdown渲染实现
        // 代码块
        text = text.replace(/```(\w+)?\n?([\s\S]*?)```/g, '<pre><code class="language-$1">$2</code></pre>');
        text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // 粗体
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // 斜体
        text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        // 标题
        text = text.replace(/^### (.*$)/gm, '<h3>$1</h3>');
        text = text.replace(/^## (.*$)/gm, '<h2>$1</h2>');
        text = text.replace(/^# (.*$)/gm, '<h1>$1</h1>');
        
        // 链接
        text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
        
        // 无序列表
        text = text.replace(/^\- (.*$)/gm, '<li>$1</li>');
        text = text.replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>');
        
        // 换行
        text = text.replace(/\n/g, '<br>');
        
        // 移除可能的空标签
        text = text.replace(/<ul><\/ul>/g, '');
        
        return text;
    }
    
    /**
     * 应用代码高亮
     */
    applyCodeHighlighting(element) {
        // 查找所有代码元素并应用高亮
        const codeBlocks = element.querySelectorAll('pre code');
        
        codeBlocks.forEach(block => {
            // 如果没有指定语言，尝试自动检测
            if (!block.classList.contains('language-') || block.className.indexOf('language-') === -1) {
                block.classList.add('language-none');
            }
            
            // 使用Prism.js进行高亮（如果存在）
            if (typeof Prism !== 'undefined') {
                Prism.highlightElement(block);
            }
        });
    }
    
    /**
     * 显示打字指示器
     */
    showTypingIndicator() {
        const chatMessages = document.getElementById('chat-messages');
        
        const typingElement = document.createElement('div');
        typingElement.id = 'typing-indicator';
        typingElement.classList.add('typing-indicator');
        typingElement.innerHTML = '<span></span><span></span><span></span>';
        
        chatMessages.appendChild(typingElement);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    /**
     * 隐藏打字指示器
     */
    hideTypingIndicator() {
        const typingElement = document.getElementById('typing-indicator');
        if (typingElement) {
            typingElement.remove();
        }
    }
    
    /**
     * 更新发送按钮状态
     */
    updateSendButtonState() {
        const sendBtn = document.getElementById('send-btn');
        if (this.isGenerating) {
            sendBtn.disabled = true;
            sendBtn.textContent = '生成中...';
        } else {
            sendBtn.disabled = false;
            sendBtn.textContent = '发送';
        }
    }
    
    /**
     * 创建新对话
     */
    async newConversation(title = '新对话') {
        // 保存当前对话（如果有内容）
        if (this.messages.length > 0 && this.currentConversationId) {
            await this.updateConversationTitle(this.currentConversationId, this.generateTitle());
        }
        
        // 清空消息
        this.messages = [];
        this.currentConversationId = null;
        
        // 清空聊天区域
        document.getElementById('chat-messages').innerHTML = '';
        document.getElementById('chat-title').textContent = title;
        
        // 显示欢迎消息
        this.displayMessage('您好！我是AI助手，有什么可以帮助您的吗？', 'assistant');
    }
    
    /**
     * 生成对话标题
     */
    generateTitle() {
        if (this.messages.length > 0) {
            const firstUserMessage = this.messages.find(msg => msg.role === 'user');
            if (firstUserMessage) {
                return firstUserMessage.content.substring(0, 30) + (firstUserMessage.content.length > 30 ? '...' : '');
            }
        }
        return '新对话';
    }
    
    /**
     * 重置上下文
     */
    resetContext() {
        if (this.messages.length > 0) {
            // 保留系统消息，清除其他消息
            this.messages = this.messages.filter(msg => msg.role === 'system');
            
            // 重新渲染消息
            this.renderMessages();
        }
    }
    
    /**
     * 重新渲染消息
     */
    renderMessages() {
        const chatMessages = document.getElementById('chat-messages');
        chatMessages.innerHTML = '';
        
        this.messages.forEach(msg => {
            this.displayMessage(msg.content, msg.role);
        });
        
        // 如果没有消息，显示欢迎消息
        if (this.messages.length === 0) {
            this.displayMessage('您好！我是AI助手，有什么可以帮助您的吗？', 'assistant');
        }
    }
    
    /**
     * 创建对话
     */
    async createConversation(title) {
        try {
            const response = await fetch('api/chat/conversations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    title: title
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.currentConversationId = data.conversation_id;
                document.getElementById('chat-title').textContent = title;
                
                // 添加到对话列表
                this.addConversationToList(data.conversation_id, title);
            }
        } catch (error) {
            console.error('创建对话失败:', error);
        }
    }
    
    /**
     * 保存消息
     */
    async saveMessage(conversationId, role, content) {
        try {
            await fetch('api/chat/messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    conversation_id: conversationId,
                    role: role,
                    content: content
                })
            });
        } catch (error) {
            console.error('保存消息失败:', error);
        }
    }
    
    /**
     * 加载对话列表
     */
    async loadConversations() {
        try {
            const response = await fetch('api/chat/conversations.php');
            const data = await response.json();
            
            const conversationsList = document.getElementById('conversations-list');
            conversationsList.innerHTML = '';
            
            if (data.conversations && data.conversations.length > 0) {
                data.conversations.forEach(conv => {
                    this.addConversationToList(conv.id, conv.title, conv.created_at);
                });
            } else {
                conversationsList.innerHTML = '<div class="layui-none">暂无对话历史</div>';
            }
        } catch (error) {
            console.error('加载对话列表失败:', error);
        }
    }
    
    /**
     * 添加对话到列表
     */
    addConversationToList(id, title, createdAt = null) {
        const conversationsList = document.getElementById('conversations-list');
        
        // 如果列表为空，清空"暂无对话历史"提示
        if (conversationsList.querySelector('.layui-none')) {
            conversationsList.innerHTML = '';
        }
        
        const item = document.createElement('div');
        item.classList.add('conversation-item');
        item.dataset.id = id;
        item.title = createdAt ? `${title} (${new Date(createdAt).toLocaleString()})` : title;
        item.textContent = title;
        
        // 点击切换对话
        item.addEventListener('click', () => {
            this.switchConversation(id, title);
        });
        
        conversationsList.prepend(item); // 添加到顶部
    }
    
    /**
     * 切换对话
     */
    async switchConversation(id, title) {
        // 保存当前对话标题（如果需要）
        if (this.currentConversationId && this.messages.length > 0) {
            await this.updateConversationTitle(this.currentConversationId, this.generateTitle());
        }
        
        this.currentConversationId = id;
        document.getElementById('chat-title').textContent = title;
        
        // 加载对话消息
        this.loadConversationMessages(id);
        
        // 更新活动状态
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        event.target.classList.add('active');
    }
    
    /**
     * 加载对话消息
     */
    async loadConversationMessages(conversationId) {
        try {
            const response = await fetch(`api/chat/messages.php?conversation_id=${conversationId}`);
            const data = await response.json();
            
            // 清空当前消息
            this.messages = [];
            document.getElementById('chat-messages').innerHTML = '';
            
            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    this.displayMessage(msg.content, msg.role);
                });
            } else {
                this.displayMessage('您好！我是AI助手，有什么可以帮助您的吗？', 'assistant');
            }
        } catch (error) {
            console.error('加载对话消息失败:', error);
            this.displayMessage('您好！我是AI助手，有什么可以帮助您的吗？', 'assistant');
        }
    }
    
    /**
     * 更新对话标题
     */
    async updateConversationTitle(conversationId, title) {
        try {
            await fetch(`api/chat/conversations.php`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: conversationId,
                    title: title
                })
            });
            
            // 更新侧边栏中的标题
            const item = document.querySelector(`.conversation-item[data-id="${conversationId}"]`);
            if (item) {
                item.textContent = title;
                item.title = title;
            }
        } catch (error) {
            console.error('更新对话标题失败:', error);
        }
    }
    
    /**
     * 更新Token显示
     */
    updateTokenDisplay() {
        document.getElementById('token-usage').textContent = this.tokenUsage;
        document.getElementById('token-limit').textContent = this.tokenLimit;
        
        // 根据使用情况更新样式
        const tokenInfo = document.querySelector('.token-info');
        tokenInfo.classList.remove('warning', 'danger');
        
        const usagePercent = this.tokenLimit > 0 ? (this.tokenUsage / this.tokenLimit) * 100 : 0;
        
        if (usagePercent >= 90) {
            tokenInfo.classList.add('danger');
        } else if (usagePercent >= 70) {
            tokenInfo.classList.add('warning');
        }
    }
    
    /**
     * 加载系统配置
     */
    async loadSystemConfigs() {
        try {
            const response = await fetch('api/chat/token-usage.php');
            const data = await response.json();
            
            if (data.success) {
                this.tokenUsage = data.usage.used;
                this.tokenLimit = data.usage.limit;
                
                this.updateTokenDisplay();
            }
        } catch (error) {
            console.error('加载Token使用情况失败:', error);
        }
    }
    
    /**
     * 定期更新Token使用情况
     */
    startTokenUsageUpdates() {
        // 每分钟更新一次Token使用情况
        setInterval(async () => {
            try {
                const response = await fetch('api/chat/token-usage.php');
                const data = await response.json();
                
                if (data.success) {
                    this.tokenUsage = data.usage.used;
                    this.tokenLimit = data.usage.limit;
                    
                    this.updateTokenDisplay();
                }
            } catch (error) {
                console.error('更新Token使用情况失败:', error);
            }
        }, 60000); // 60秒
    }
    
    /**
     * 登出
     */
    async logout() {
        try {
            await fetch('api/auth/logout.php', {
                method: 'POST'
            });
            
            // 重定向到当前页面以刷新状态
            window.location.reload();
        } catch (error) {
            console.error('登出失败:', error);
            window.location.reload();
        }
    }
}