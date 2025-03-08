<?php
session_start();
$config = require('../config/config.php');

header('Content-Type: application/json');

// 添加错误日志记录
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', 'verify_code_errors.log');

// 定义状态常量
define('STATUS_AC', 'AC');  // Accepted
define('STATUS_WA', 'WA');  // Wrong Answer
define('STATUS_TLE', 'TLE'); // Time Limit Exceed
define('STATUS_MLE', 'MLE'); // Memory Limit Exceed
define('STATUS_PE', 'PE');   // Presentation Error
define('STATUS_RE', 'RE');   // Runtime Error

// 格式化输出函数
function formatOutput($output) {
    // 移除行尾空格和空行
    $lines = explode("\n", trim($output));
    $lines = array_map('rtrim', $lines);
    // 移除连续的空行
    $lines = array_filter($lines, function($line) {
        return $line !== '';
    });
    return implode("\n", $lines);
}

// 检查是否为格式错误
function isPresentationError($actual, $expected) {
    // 移除所有空格和换行符后比较
    $cleanActual = preg_replace('/\s+/', '', $actual);
    $cleanExpected = preg_replace('/\s+/', '', $expected);
    return $cleanActual === $cleanExpected;
}

// 记录请求信息
error_log("收到验证请求: " . file_get_contents('php://input'));

if (!isset($_SESSION['user_id'])) {
    error_log("用户未登录");
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 获取POST数据
$data = json_decode(file_get_contents('php://input'), true);
$code = $data['code'] ?? '';
$samples = $data['samples'] ?? [];
$language = $data['language'] ?? '';

if (empty($code) || empty($samples)) {
    error_log("缺少必要参数");
    echo json_encode(['success' => false, 'message' => '代码和样例不能为空']);
    exit;
}

// 检查语言支持
$supported_languages = ['c', 'cpp', 'python'];
if (!in_array($language, $supported_languages)) {
    error_log("不支持的语言: $language");
    echo json_encode(['success' => false, 'message' => '不支持的编程语言']);
    exit;
}

// 创建临时目录
$temp_dir = sys_get_temp_dir() . '/code_verify_' . uniqid();
mkdir($temp_dir);

try {
    // 根据语言选择文件扩展名和编译/运行命令
    switch ($language) {
        case 'cpp':
            $file_name = 'main.cpp';
            $exec_name = 'main';
            $compile_cmd = "PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin " .
                          "g++ -O2 -Wall -std=c++11 " .
                          "-B/usr/lib/gcc/ -B/usr/lib64/gcc/ " .
                          "-L/usr/lib64 -L/usr/lib " .
                          "-I/usr/lib/gcc/x86_64-redhat-linux/4.8.5/include " .
                          "-Wl,-rpath,/usr/lib64 -Wl,-rpath,/usr/lib -lm " .
                          "{$temp_dir}/{$file_name} -o {$temp_dir}/{$exec_name} 2>&1";
            break;
        case 'c':
            $file_name = 'main.c';
            $exec_name = 'main';
            $compile_cmd = "PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin " .
                          "gcc -O2 -Wall -std=c11 " .
                          "-B/usr/lib/gcc/ -B/usr/lib64/gcc/ " .
                          "-L/usr/lib64 -L/usr/lib " .
                          "-I/usr/lib/gcc/x86_64-redhat-linux/4.8.5/include " .
                          "-Wl,-rpath,/usr/lib64 -Wl,-rpath,/usr/lib -lm " .
                          "{$temp_dir}/{$file_name} -o {$temp_dir}/{$exec_name} 2>&1";
            break;
        case 'python':
            $file_name = 'main.py';
            $compile_cmd = '';
            break;
        default:
            throw new Exception('不支持的编程语言');
    }

    // 保存代码到文件
    file_put_contents("{$temp_dir}/{$file_name}", $code);
    
    // 如果需要编译
    if ($compile_cmd) {
        $compile_output = [];
        $compile_return = 0;
        exec($compile_cmd, $compile_output, $compile_return);
        
        if ($compile_return !== 0) {
            throw new Exception('编译错误：' . implode("\n", $compile_output));
        }
    }

    $results = [];
    foreach ($samples as $sample) {
        $input = $sample['input'] ?? '';
        $expected = $sample['output'] ?? '';

        // 保存输入到文件
        file_put_contents("{$temp_dir}/input.txt", $input);

        // 准备运行命令
        if ($language === 'python') {
            $program_cmd = "python3 {$temp_dir}/{$file_name}";
        } else {
            $program_cmd = "{$temp_dir}/{$exec_name}";
        }

        // 使用 PHP 的时间和内存测量
        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);
        
        // 运行程序并捕获输出
        $cmd = "ulimit -t 5; ulimit -v 67108864; " . 
               $program_cmd . " < {$temp_dir}/input.txt";
        
        $output = [];
        $return_var = 0;
        exec($cmd . " 2>&1", $output, $return_var);
        
        // 计算执行时间（毫秒）
        $execution_time = (microtime(true) - $start_time) * 1000;
        
        // 使用 /usr/bin/time 获取更准确的内存使用
        $time_cmd = "/usr/bin/time -f '%M' " . $program_cmd . 
                    " < {$temp_dir}/input.txt 2>&1 1>/dev/null";
        $time_output = [];
        exec($time_cmd, $time_output);
        
        // 获取内存使用
        $memory_used = 0;
        foreach ($time_output as $line) {
            if (is_numeric(trim($line))) {
                $memory_used = (float)trim($line);
                break;
            }
        }
        
        // 如果没有获取到内存使用，使用PHP的测量
        if ($memory_used === 0) {
            $memory_used = (memory_get_usage(true) - $start_memory) / 1024;
        }

        // 检查是否超时或超出内存限制
        $time_exceeded = $execution_time > 1000; // 1秒
        $memory_exceeded = $memory_used > 65536; // 64MB

        // 比较输出
        $actual_output = implode("\n", $output);
        $actual_output = rtrim($actual_output); // 移除末尾空白字符
        $expected = rtrim($expected); // 移除末尾空白字符

        // 标准化换行符
        $actual_output = str_replace("\r\n", "\n", $actual_output);
        $expected = str_replace("\r\n", "\n", $expected);

        // 获取程序运行状态
        $status = STATUS_AC; // 默认为通过
        $message = '通过';

        // 检查运行时错误
        if ($return_var !== 0) {
            $status = STATUS_RE;
            $message = '运行时错误';
        }
        // 检查超时
        else if ($execution_time > 1000) {
            $status = STATUS_TLE;
            $message = '超出时间限制';
        }
        // 检查内存超限
        else if ($memory_used > 65536) {
            $status = STATUS_MLE;
            $message = '超出内存限制';
        }
        else {
            // 格式化输出进行比较
            $formatted_actual = formatOutput($actual_output);
            $formatted_expected = formatOutput($expected);

            if ($formatted_actual !== $formatted_expected) {
                if (isPresentationError($actual_output, $expected)) {
                    $status = STATUS_PE;
                    $message = '格式错误';
                } else {
                    $status = STATUS_WA;
                    $message = '答案错误';
                }
            }
        }

        $results[] = [
            'input' => $input,
            'expected' => $expected,
            'actual' => $actual_output,
            'status' => $status,
            'execution_time' => round($execution_time, 3), // 保留3位小数
            'memory_used' => round($memory_used, 2),
            'time_exceeded' => $execution_time > 1000,
            'memory_exceeded' => $memory_used > 65536,
            'message' => $message
        ];
    }

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);

} catch (Exception $e) {
    error_log("处理样例时出错: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // 清理临时文件
    array_map('unlink', glob("{$temp_dir}/*"));
    rmdir($temp_dir);
} 