<?php
session_start();
$config = require('../config/config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 检查用户积分
    $stmt = $pdo->prepare('SELECT points FROM users WHERE id = ? FOR UPDATE');
    $pdo->beginTransaction();
    $stmt->execute([$_SESSION['user_id']]);
    $points = $stmt->fetchColumn();

    if ($points < 10) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '积分不足，需要10积分']);
        exit;
    }

    // 获取请求数据
    $data = json_decode(file_get_contents('php://input'), true);
    $type = $data['type'] ?? 'text';
    $content = $data['content'] ?? '';

    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => '请提供题目内容']);
        exit;
    }

    // 准备API请求
    $api_url = rtrim($config['openai']['api_url'], '/').'/v1/chat/completions';
    $prompt = '';
    $model = '';

    // 根据不同类型构建提示词和选择模型
    switch ($type) {
        case 'text':
            $prompt = "你是一个题目解析器。请分析下面的算法题目内容，并将结果格式化为指定的JSON格式。注意：请确保返回的是合法的JSON字符串，不要添加任何其他文字。\n\n" . $content;
            $model = 'gpt-4o'; // 文字解析使用原来的模型
            break;
            
        case 'link':
            // 获取链接内容
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $content);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $html = curl_exec($ch);
            
            if (curl_errno($ch)) {
                throw new Exception('获取链接内容失败：' . curl_error($ch));
            }
            curl_close($ch);
            
            // 使用DOM解析HTML
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);
            
            // 尝试查找常见的题目内容容器
            $contentNodes = $xpath->query("//div[contains(@class, 'problem-content') or contains(@class, 'description') or contains(@class, 'statement')]");
            if ($contentNodes->length === 0) {
                // 如果找不到特定容器，获取body内的所有文本
                $contentNodes = $xpath->query("//body");
            }
            
            $problem_text = '';
            foreach ($contentNodes as $node) {
                $problem_text .= trim($node->textContent) . "\n";
            }
            
            if (empty($problem_text)) {
                throw new Exception('无法从链接中提取题目内容');
            }

            $prompt = "你是一个题目解析器。请分析下面的算法题目内容，并将结果格式化为指定的JSON格式。注意：请确保返回的是合法的JSON字符串，不要添加任何其他文字。\n\n" . $problem_text;
            $model = 'gpt-4o'; // 链接解析使用原来的模型
            break;
            
        case 'image':
            // 获取图片URL
            $imageUrl = $content;
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $imageUrl = 'https://' . $_SERVER['HTTP_HOST'] . $content;
            }
            
            // 使用百度OCR API识别图片文字
            $api_key = $config['baidu']['api_key'];
            $secret_key = $config['baidu']['secret_key'];
            
            // 获取access token
            $token_url = "https://aip.baidubce.com/oauth/2.0/token?grant_type=client_credentials&client_id={$api_key}&client_secret={$secret_key}";
            $token_response = file_get_contents($token_url);
            $token_data = json_decode($token_response, true);
            if (!isset($token_data['access_token'])) {
                throw new Exception('获取百度OCR token失败');
            }
            
            // 调用通用文字识别（高精度版）
            $ocr_url = "https://aip.baidubce.com/rest/2.0/ocr/v1/accurate_basic?access_token=" . $token_data['access_token'];
            
            // 读取图片内容并进行base64编码
            $image_content = file_get_contents(__DIR__ . $content);
            if ($image_content === false) {
                throw new Exception('读取图片失败');
            }
            
            $post_data = http_build_query([
                'image' => base64_encode($image_content)
            ]);
            
            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $post_data
                ]
            ];
            
            $context = stream_context_create($options);
            $ocr_response = file_get_contents($ocr_url, false, $context);
            $ocr_data = json_decode($ocr_response, true);
            
            if (!isset($ocr_data['words_result'])) {
                throw new Exception('OCR识别失败');
            }
            
            // 将OCR结果组合成文本
            $text_content = '';
            foreach ($ocr_data['words_result'] as $word) {
                $text_content .= $word['words'] . "\n";
            }
            
            if (empty($text_content)) {
                throw new Exception('未能识别出文字内容');
            }
            
            // 使用GPT解析识别出的文字
            $prompt = "你是一个题目解析器。请分析下面的算法题目内容，并将结果格式化为指定的JSON格式。注意：请确保返回的是合法的JSON字符串，不要添加任何其他文字。\n\n" . $text_content;
            $model = 'gpt-4o';
            break;
            
        default:
            throw new Exception('不支持的解析类型');
    }

    // 添加格式化指令
    $prompt .= "\n\n请严格按照以下JSON格式返回（不要添加任何其他文字，只返回JSON）：
    {
        \"title\": \"题目描述：\\n[完整的题目内容，包括标题和描述]\",
        \"input_content\": \"输入格式描述\",
        \"output_content\": \"输出格式描述\",
        \"samples\": [
            {
                \"input\": \"输入样例1\",
                \"output\": \"输出样例1\"
            }
        ],
        \"time_limit\": 1000,
        \"memory_limit\": 65536,
        \"user_code\": null,
        \"additional_requirements\": null
    }

    注意事项：
    1. 必须返回合法的JSON格式
    2. 所有字符串值都要用双引号
    3. 数字不需要引号
    4. null值不需要引号
    5. 保持原文的换行，在字符串中使用\\n表示换行
    6. 如果找不到对应内容，相应字段返回null
    7. 不要在JSON前后添加任何说明文字";

    // 构建API请求
    $request_body = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => isset($type) && $type === 'image' ? 2000 : 4096
    ];

    // 发送请求
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['openai']['api_key']
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log('API请求失败，HTTP状态码: ' . $http_code);
        error_log('API响应内容: ' . $response);
        throw new Exception('API请求失败');
    }

    $response_data = json_decode($response, true);
    if (!isset($response_data['choices'][0]['message']['content'])) {
        error_log('API响应格式错误，完整响应: ' . print_r($response_data, true));
        throw new Exception('API响应格式错误');
    }

    // 记录完整的API返回内容
    error_log('=== API完整响应 ===');
    error_log(print_r($response_data, true));
    error_log('=== API返回的content ===');
    error_log($response_data['choices'][0]['message']['content']);

    // 在处理返回内容时添加更详细的日志
    $content = trim($response_data['choices'][0]['message']['content']);

    // 检查内容是否为空
    if (empty($content)) {
        error_log('API返回的content为空');
        throw new Exception('API返回内容为空');
    }

    // 尝试查找JSON内容
    $json_start = strpos($content, '{');
    $json_end = strrpos($content, '}');

    if ($json_start === false || $json_end === false) {
        error_log('未找到JSON标记，原始内容: ' . $content);
        // 尝试查找可能的JSON内容
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $content = $matches[0];
            error_log('通过正则找到的可能JSON内容: ' . $content);
        } else {
            throw new Exception('返回内容格式错误：未找到有效的JSON');
        }
    } else {
        $content = substr($content, $json_start, $json_end - $json_start + 1);
        error_log('提取的JSON内容: ' . $content);
    }

    // 尝试解析JSON
    $parsed_data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON解析错误: ' . json_last_error_msg());
        error_log('尝试解析的内容: ' . $content);
        
        // 尝试修复常见的JSON格式问题
        $fixed_content = $content;
        $fixed_content = str_replace("'", '"', $fixed_content); // 替换单引号
        $fixed_content = preg_replace('/,(\s*[}\]])/m', '$1', $fixed_content); // 移除尾随逗号
        $fixed_content = preg_replace('/\r?\n/m', '\\n', $fixed_content); // 处理换行符
        $fixed_content = preg_replace('/\\\\([^n"])/m', '\\\\\\\\$1', $fixed_content); // 处理转义字符
        
        error_log('尝试修复后的JSON: ' . $fixed_content);
        
        $parsed_data = json_decode($fixed_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('修复后仍然解析失败: ' . json_last_error_msg());
            throw new Exception('解析结果格式错误: ' . json_last_error_msg());
        }
    }

    // 记录解析后的数据
    error_log('=== 解析后的数据 ===');
    error_log(print_r($parsed_data, true));

    // 验证必要字段
    $required_fields = ['title', 'input_content', 'output_content', 'samples'];
    foreach ($required_fields as $field) {
        if (!isset($parsed_data[$field])) {
            error_log('缺少必要字段: ' . $field);
            error_log('当前数据结构: ' . print_r($parsed_data, true));
            throw new Exception('解析结果缺少必要字段: ' . $field);
        }
    }

    // 验证samples数组
    if (!is_array($parsed_data['samples']) || empty($parsed_data['samples'])) {
        error_log('samples字段格式错误或为空');
        error_log('samples内容: ' . print_r($parsed_data['samples'] ?? null, true));
        throw new Exception('解析结果格式错误：samples必须是非空数组');
    }

    foreach ($parsed_data['samples'] as $index => $sample) {
        if (!isset($sample['input']) || !isset($sample['output'])) {
            error_log('样例 ' . $index . ' 格式错误: ' . print_r($sample, true));
            throw new Exception('解析结果格式错误：样例格式不正确');
        }
    }

    // 在成功解析后，删除上传的图片
    if ($type === 'image' && isset($imageUrl)) {
        // 获取图片的物理路径
        $image_path = __DIR__ . $content;
        if (file_exists($image_path)) {
            unlink($image_path);
            // 尝试删除空目录
            $dir = dirname($image_path);
            if (is_dir($dir) && count(glob("$dir/*")) === 0) {
                rmdir($dir);
            }
        }
    }

    // 扣除积分
    $stmt = $pdo->prepare('UPDATE users SET points = points - 10 WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'data' => $parsed_data
    ]);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    // 如果是图片解析失败，也要删除上传的图片
    if ($type === 'image' && isset($content)) {
        $image_path = __DIR__ . $content;
        if (file_exists($image_path)) {
            unlink($image_path);
            // 尝试删除空目录
            $dir = dirname($image_path);
            if (is_dir($dir) && count(glob("$dir/*")) === 0) {
                rmdir($dir);
            }
        }
    }
    
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '解析失败：' . $e->getMessage()]);
} 