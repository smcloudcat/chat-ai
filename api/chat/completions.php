<?php
/**
 * 聊天完成API接口
 * 对应OpenAI completions格式
 */

require_once '../../includes/utils/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

header('Content-Type: application/json');

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => ['message' => 'Method not allowed', 'type' => 'invalid_request_error', 'code' => 'method_not_allowed']]);
    exit;
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Invalid JSON', 'type' => 'invalid_request_error', 'code' => 'invalid_json']]);
    exit;
}

// 验证必需参数
if (!isset($input['messages']) || !is_array($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Missing messages', 'type' => 'invalid_request_error', 'code' => 'missing_messages']]);
    exit;
}

$model = $input['model'] ?? 'gpt-3.5-turbo';
$messages = $input['messages'];
$max_tokens = $input['max_tokens'] ?? 1000;
$temperature = $input['temperature'] ?? 0.7;
$stream = $input['stream'] ?? false;
$conversation_id = $input['conversation_id'] ?? null;  // 添加对话ID参数

try {
    // 检查用户token限制
    $identity = SessionManager::getCurrentIdentity();
    $tokenUsage = getTokenUsage($identity);
    $tokenLimit = getTokenLimit();
    
    if ($tokenUsage >= $tokenLimit && $tokenLimit > 0) {
        http_response_code(403);
        echo json_encode(['error' => ['message' => 'Token limit exceeded', 'type' => 'insufficient_quota', 'code' => 'token_limit_exceeded']]);
        exit;
    }

    if ($stream) {
        // 流式响应
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        // 模拟流式响应
        $response = generateStreamResponse($messages, $model);
        echo $response;
    } else {
        // 非流式响应
        $response = generateResponse($messages, $model, $max_tokens, $temperature);
        
        // 记录token使用
        $tokensUsed = $response['usage']['total_tokens'];
        recordTokenUsage($identity, $tokensUsed, $model, $conversation_id);
        
        echo json_encode($response);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => ['message' => $e->getMessage(), 'type' => 'server_error', 'code' => 'internal_error']]);
}

/**
 * 生成非流式响应
 */
function generateResponse($messages, $model, $max_tokens, $temperature) {
    // 这里应该调用实际的AI模型API
    // 为演示目的，我们生成模拟响应
    
    // 实际实现中，这里会调用真实的AI模型API
    // 例如：curl请求到OpenAI API或其他兼容的API
    
    $content = "这是模拟的AI回复。在实际部署中，这里会显示来自AI模型的真实回复。";
    
    // 计算token数（简化计算）
    $prompt_tokens = 0;
    foreach ($messages as $message) {
        $prompt_tokens += str_word_count($message['content']);
    }
    
    $completion_tokens = str_word_count($content);
    $total_tokens = $prompt_tokens + $completion_tokens;
    
    return [
        'id' => 'chat-' . bin2hex(random_bytes(16)),
        'object' => 'chat.completion',
        'created' => time(),
        'model' => $model,
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => $content
                ],
                'finish_reason' => 'stop'
            ]
        ],
        'usage' => [
            'prompt_tokens' => $prompt_tokens,
            'completion_tokens' => $completion_tokens,
            'total_tokens' => $total_tokens
        ]
    ];
}

/**
 * 生成流式响应
 */
function generateStreamResponse($messages, $model) {
    // 流式响应的模拟实现
    $content = "这是模拟的AI回复。在实际部署中，这里会显示来自AI模型的真实回复。";
    $words = explode(' ', $content);
    
    foreach ($words as $index => $word) {
        $data = [
            'id' => 'chat-' . bin2hex(random_bytes(16)),
            'object' => 'chat.completion.chunk',
            'created' => time(),
            'model' => $model,
            'choices' => [
                [
                    'index' => 0,
                    'delta' => [
                        'content' => $word . ($index < count($words) - 1 ? ' ' : '')
                    ],
                    'finish_reason' => $index === count($words) - 1 ? 'stop' : null
                ]
            ]
        ];
        
        echo "data: " . json_encode($data) . "\n\n";
        ob_flush();
        flush();
        
        // 模拟延迟
        usleep(100000); // 0.1秒
    }
    
    // 发送结束标记
    $endData = [
        'id' => 'chat-' . bin2hex(random_bytes(16)),
        'object' => 'chat.completion.chunk',
        'created' => time(),
        'model' => $model,
        'choices' => [
            [
                'index' => 0,
                'delta' => [],
                'finish_reason' => 'stop'
            ]
        ]
    ];
    
    echo "data: " . json_encode($endData) . "\n\n";
    echo "data: [DONE]\n\n";
    ob_flush();
    flush();
}

/**
 * 获取当前token使用量
 */
function getTokenUsage($identity) {
    $db = getDB();
    
    if ($identity['type'] === 'user') {
        // 获取用户当日token使用量
        $stmt = $db->prepare("
            SELECT SUM(tokens_used) as total_tokens 
            FROM token_usage_logs 
            WHERE user_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$identity['id']]);
        $result = $stmt->fetch();
        
        return $result['total_tokens'] ?? 0;
    } else {
        // 获取设备当日token使用量
        $stmt = $db->prepare("
            SELECT SUM(tokens_used) as total_tokens 
            FROM token_usage_logs 
            WHERE device_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$identity['id']]);
        $result = $stmt->fetch();
        
        return $result['total_tokens'] ?? 0;
    }
}

/**
 * 获取token限制
 */
function getTokenLimit() {
    $db = getDB();
    
    // 获取系统配置
    $stmt = $db->prepare("SELECT config_value FROM system_configs WHERE config_key = 'chat_settings'");
    $stmt->execute();
    $config = $stmt->fetch();
    
    if ($config) {
        $settings = json_decode($config['config_value'], true);
        
        if (SessionManager::isUserLoggedIn()) {
            return $settings['user_daily_token_limit'] ?? 50000;
        } else {
            return $settings['guest_daily_token_limit'] ?? 10000;
        }
    }
    
    // 默认限制
    if (SessionManager::isUserLoggedIn()) {
        return 50000;
    } else {
        return 10000;
    }
}

/**
 * 记录token使用
 */
function recordTokenUsage($identity, $tokensUsed, $model, $conversation_id = null) {
    $db = getDB();
    
    try {
        if ($identity['type'] === 'user') {
            $stmt = $db->prepare("
                INSERT INTO token_usage_logs (user_id, conversation_id, tokens_used, model, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$identity['id'], $conversation_id, $tokensUsed, $model]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO token_usage_logs (device_id, conversation_id, tokens_used, model, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$identity['id'], $conversation_id, $tokensUsed, $model]);
        }
    } catch (Exception $e) {
        error_log("记录token使用失败: " . $e->getMessage());
    }
}