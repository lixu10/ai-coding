<?php
session_start();  // 移到最开始
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置错误处理
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    file_put_contents('generate.log', date('Y-m-d H:i:s') . " PHP错误: [$errno] $errstr in $errfile on line $errline\n", FILE_APPEND);
});

// 记录请求开始
file_put_contents('generate.log', "\n" . date('Y-m-d H:i:s') . " ====== 新的请求开始 ======\n", FILE_APPEND);
file_put_contents('generate.log', "POST数据: " . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents('generate.log', "SESSION数据: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

$config = require('../config/config.php');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// 如果是预检请求，直接返回
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$data = $_POST;

// 获取并验证必填字段
$title = trim($data['title'] ?? '');
$input_content = trim($data['input_content'] ?? '');
$output_content = trim($data['output_content'] ?? '');
$sample_inputs = $data['sample_input'] ?? [];
$sample_outputs = $data['sample_output'] ?? [];
$language = trim($data['language'] ?? '');
$other_language = trim($data['other_language'] ?? '');
$user_code = trim($data['user_code'] ?? '');
$time_limit = intval($data['time_limit'] ?? 1000);
$memory_limit = intval($data['memory_limit'] ?? 65536);
$pseudo_code = filter_var($data['pseudo_code'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$add_comments = filter_var($data['add_comments'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$normal_naming = filter_var($data['normal_naming'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$additional_requirements = trim($data['additional_requirements'] ?? '');
$model_name = trim($data['model'] ?? '');

// 验证必填字段
if (empty($title) || empty($input_content) || empty($output_content) || empty($language) || empty($model_name)) {
    echo json_encode(['success' => false, 'message' => '请填写所有必填字段。']);
    exit;
}

// 如果开启伪代码模式但未填写代码
if ($pseudo_code && empty($user_code)) {
    echo json_encode(['success' => false, 'message' => '开启伪代码模式时必须填写"你的代码"']);
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

    // 获取模型消耗的积分
    $stmt = $pdo->prepare('SELECT points_consumption FROM models WHERE name = ?');
    $stmt->execute([$model_name]);
    $points_consumption = $stmt->fetchColumn();

    if ($points_consumption === false) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '无效的模型']);
        exit;
    }

    if ($points < $points_consumption) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '积分不足，无法生成代码。']);
        exit;
    }

    // 扣除积分
    $stmt = $pdo->prepare('UPDATE users SET points = points - ? WHERE id = ?');
    $stmt->execute([$points_consumption, $_SESSION['user_id']]);
    $pdo->commit();

    // 检查用户状态
    $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $status = $stmt->fetchColumn();
    
    if ($status === 'banned') {
        echo json_encode(['success' => false, 'message' => '账号已被封禁，无法使用生成功能']);
        exit;
    }

    // 构建样例部分
    $samples = [];
    for ($i = 0; $i < count($sample_inputs); $i++) {
        if (!empty($sample_inputs[$i]) && !empty($sample_outputs[$i])) {
            $samples[] = [
                'input' => $sample_inputs[$i],
                'output' => $sample_outputs[$i],
            ];
        }
    }

    // 修改提示词构建部分
    $prompt = "你是一个经验丰富的算法专家和编程导师。请你帮我解决以下编程题目，并提供详细的分析和代码实现。请确保你的回答严格按照指定的JSON格式。

    题目描述：
    {$title}

    输入格式：
    {$input_content}

    输出格式：
    {$output_content}

    测试样例：";

    foreach ($samples as $index => $sample) {
        $num = $index + 1;
        $prompt .= "\n样例{$num}：
    输入：{$sample['input']}
    输出：{$sample['output']}";
    }

    $prompt .= "\n\n执行限制：
    - 时间限制：{$time_limit}毫秒
    - 内存限制：{$memory_limit}KB

    请严格按照以下JSON格式提供完整的解答。确保JSON格式正确，所有字段都必须存在：
    {
        \"thinking_process\": \"请详细描述以下内容：
            1. 题目的关键信息和要求
            2. 需要处理的边界情况
            3. 可能遇到的难点
            4. 解题的基本思路\",

        \"solution_approach\": \"请详细说明以下内容：
            1. 具体的解题步骤
            2. 算法的选择原因
            3. 时间复杂度分析
            4. 空间复杂度分析
            5. 性能优化考虑\",

        \"related_knowledge\": \"请列出解决此题目需要掌握的以下知识点：
            1. 相关的数据结构
            2. 算法思想
            3. 编程语言特性
            4. 常见的解题技巧\",

        \"code\": \"请提供完整的{$language}语言代码实现。代码必须：
            1. 严格按照题目要求实现功能
            2. 确保能通过所有测试样例
            3. 符合时间和内存限制
            4. 代码结构清晰\"";

    if (!empty($user_code)) {
        $prompt .= ",\n    \"code_bugs\": \"请详细分析提供的代码存在的以下问题：
            1. 逻辑错误
            2. 边界情况处理
            3. 性能问题
            4. 可能导致不通过的原因\"";
        $prompt .= "\n}\n\n我的代码：\n{$user_code}";
    } else {
        $prompt .= ",\n    \"code_bugs\": null\n}";
    }

    if ($pseudo_code) {
        $prompt .= "\n\n特别说明 - 伪代码要求：
    1. 修改代码的整体结构和实现逻辑
    2. 使用不同的函数名和变量名
    3. 改变代码组织方式
    4. 保持功能完全正确
    5. 避免与原代码过度相似";
    }

    if ($add_comments) {
        $prompt .= "\n\n代码注释要求：
    1. 在关键逻辑处添加清晰的注释
    2. 解释重要算法步骤
    3. 说明关键变量的用途
    4. 标注复杂逻辑的实现思路";
    } else {
        $prompt .= "\n\n代码要求：
    1. 不要添加任何注释
    2. 代码本身要足够清晰易懂
    3. 使用有意义的变量名和函数名";
    }

    if ($normal_naming) {
        $prompt .= "\n\n命名规范要求：
    1. 使用全小写字母
    2. 变量名长度不超过6个字符
    3. 可以使用中文拼音首字母
    4. 使用符合中国程序员习惯的命名方式
    5. 保持命名简单且有意义";
    }

    if (!empty($additional_requirements)) {
        $prompt .= "\n\n额外要求：
    {$additional_requirements}";
    }

    $prompt .= "\n\n请确保你的回答：
    1. 严格遵循JSON格式
    2. 包含所有必需字段
    3. 内容详细且准确
    4. 代码完整可运行
    5. 符合所有特定要求";

    $api_url = rtrim($config['openai']['api_url'], '/').'/v1/chat/completions';
    $api_key = $config['openai']['api_key'];
    $model = $config['openai']['model'];

    $request_body = [
        'model' => $model_name,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.2,
        'top_p' => 0.9,
        'max_tokens' => 2000,
        'presence_penalty' => 0,
        'frequency_penalty' => 0,
        'stream' => false
    ];

    // 在调用前添加日志
    file_put_contents('generate.log', date('Y-m-d H:i:s') . " 开始调用API\n", FILE_APPEND);
    file_put_contents('generate.log', "API URL: " . $api_url . "\n", FILE_APPEND);
    file_put_contents('generate.log', "请求体: " . json_encode($request_body, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        file_put_contents('generate.log', "CURL错误: " . $curl_error . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => '请求OpenAI API失败: ' . $curl_error]);
        curl_close($ch);
        exit;
    }

    if ($http_code !== 200) {
        file_put_contents('generate.log', "HTTP错误: " . $http_code . "\n响应: " . $response . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'API返回错误状态码: ' . $http_code]);
        curl_close($ch);
        exit;
    }
    curl_close($ch);

    $response_data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        file_put_contents('generate.log', "JSON解析错误: " . json_last_error_msg() . "\n响应: " . $response . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'API响应格式错误']);
        exit;
    }

    file_put_contents('generate.log', date('Y-m-d H:i:s') . " API响应: " . json_encode($response_data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

    if (isset($response_data['choices'][0]['message']['content'])) {
        try {
            $content = $response_data['choices'][0]['message']['content'];
            
            // 尝试查找 JSON 内容
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $json_str = $matches[0];
                $parsed_response = json_decode($json_str, true);
                
                if (json_last_error() === JSON_ERROR_NONE && 
                    isset($parsed_response['code']) && 
                    isset($parsed_response['thinking_process']) && 
                    isset($parsed_response['solution_approach']) && 
                    isset($parsed_response['related_knowledge'])) {
                    
                    // 将所有文本字段转换为JSON字符串格式
                    $samples_json = json_encode($samples, JSON_UNESCAPED_UNICODE);
                    $thinking_process_json = json_encode($parsed_response['thinking_process'], JSON_UNESCAPED_UNICODE);
                    $solution_approach_json = json_encode($parsed_response['solution_approach'], JSON_UNESCAPED_UNICODE);
                    $related_knowledge_json = json_encode($parsed_response['related_knowledge'], JSON_UNESCAPED_UNICODE);
                    $code_bugs_json = isset($parsed_response['code_bugs']) ? 
                        json_encode($parsed_response['code_bugs'], JSON_UNESCAPED_UNICODE) : null;
                    
                    // 准备数据库插入
                    $stmt = $pdo->prepare('INSERT INTO code_requests (
                        user_id, title, input_content, output_content, samples, 
                        time_limit, memory_limit, user_code, language, 
                        generated_code, thinking_process, solution_approach, 
                        related_knowledge, code_bugs, pseudo_code, add_comments, 
                        normal_naming, additional_requirements, model, points_consumed
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )');

                    $stmt->execute([
                        $_SESSION['user_id'], 
                        $title, 
                        $input_content, 
                        $output_content,
                        $samples_json,
                        $time_limit, 
                        $memory_limit, 
                        $user_code, 
                        $language,
                        $parsed_response['code'],
                        $thinking_process_json,
                        $solution_approach_json,
                        $related_knowledge_json,
                        $code_bugs_json,
                        $pseudo_code ? 1 : 0,
                        $add_comments ? 1 : 0,
                        $normal_naming ? 1 : 0,
                        $additional_requirements,
                        $model_name,
                        $points_consumption
                    ]);

                    echo json_encode([
                        'success' => true,
                        'code' => $parsed_response['code'],
                        'thinking_process' => $parsed_response['thinking_process'],
                        'solution_approach' => $parsed_response['solution_approach'],
                        'related_knowledge' => $parsed_response['related_knowledge'],
                        'code_bugs' => $parsed_response['code_bugs'] ?? null,
                        'points_consumed' => $points_consumption
                    ]);
                } else {
                    // 记录具体的解析错误
                    $error_msg = json_last_error_msg();
                    file_put_contents('generate.log', date('Y-m-d H:i:s') . " JSON验证失败: {$error_msg}\n", FILE_APPEND);
                    file_put_contents('generate.log', "解析的JSON内容: " . print_r($parsed_response, true) . "\n", FILE_APPEND);
                    throw new Exception('响应格式不完整或无效');
                }
            } else {
                // 记录未找到JSON的原始响应
                file_put_contents('generate.log', date('Y-m-d H:i:s') . " 未找到JSON内容\n", FILE_APPEND);
                file_put_contents('generate.log', "原始响应: {$content}\n", FILE_APPEND);
                throw new Exception('响应中未找到JSON格式内容');
            }
        } catch (Exception $e) {
            file_put_contents('generate.log', date('Y-m-d H:i:s') . " 处理错误: " . $e->getMessage() . "\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => '生成失败: ' . $e->getMessage()]);
        }
    } else {
        file_put_contents('generate.log', date('Y-m-d H:i:s') . " 响应中没有content\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => '无效的API响应']);
    }
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
    exit;
}
?> 