<?php
session_start();
$config = require('../config/config.php');

header('Content-Type: application/json');

// 添加错误日志记录
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', 'code_run_errors.log');

// 修改安全检查函数
function checkCodeSecurity($code, $language) {
    // 真正危险的系统命令和操作
    $dangerous_patterns = [
        // 系统命令执行
        '/\b(system|popen|fork|execve|execl|execlp|execle|execv|execvp|execvpe|spawn|shellexec)\b/i',
        // 危险的网络操作
        '/\b(socket|connect|bind|listen|accept)\b/i',
        // 危险的文件操作
        '/\b(fopen|fwrite|fprintf|fscanf|ofstream|ifstream|fstream)\b/i',
    ];

    // Python 特定的危险模式
    $python_dangerous = [
        '/\b(os\.|subprocess\.|sys\.|shutil\.|glob\.|pickle\.|marshal\.|base64\.|codecs\.)/i',
        '/\b(__import__|exec|eval|compile)\b/i',
    ];

    // C/C++ 允许的标准库
    $cpp_allowed = [
        'stdio.h',
        'stdlib.h',
        'string.h',
        'math.h',
        'ctype.h',
        'time.h',
        'stdbool.h',
        'assert.h',
        'limits.h',
        'float.h',
        'iostream',
        'string',
        'vector',
        'algorithm',
        'cmath',
        'iomanip'
    ];

    if ($language === 'c' || $language === 'cpp') {
        // 基本语法检查
        $braces_count = 0;
        $in_string = false;
        $in_char = false;
        $in_comment = false;
        $prev_char = '';
        
        for ($i = 0; $i < strlen($code); $i++) {
            $char = $code[$i];
            
            // 跳过字符串内容
            if ($char === '"' && $prev_char !== '\\' && !$in_char && !$in_comment) {
                $in_string = !$in_string;
                continue;
            }
            
            // 跳过字符字面量
            if ($char === "'" && $prev_char !== '\\' && !$in_string && !$in_comment) {
                $in_char = !$in_char;
                continue;
            }
            
            // 跳过注释
            if ($prev_char === '/' && $char === '*' && !$in_string && !$in_char) {
                $in_comment = true;
                continue;
            }
            if ($prev_char === '*' && $char === '/' && $in_comment) {
                $in_comment = false;
                continue;
            }
            
            // 只在非字符串、非字符、非注释状态下计数
            if (!$in_string && !$in_char && !$in_comment) {
                if ($char === '{') {
                    $braces_count++;
                } elseif ($char === '}') {
                    $braces_count--;
                }
            }
            
            $prev_char = $char;
        }
        
        if ($braces_count !== 0) {
            throw new Exception('代码语法错误：大括号不匹配');
        }
        
        // 检查是否包含 main 函数和返回语句
        if (!preg_match('/\bmain\s*\([^)]*\)\s*{/', $code)) {
            throw new Exception('代码必须包含 main 函数');
        }
        
        if (!preg_match('/\breturn\s+[^;]*;/', $code)) {
            throw new Exception('main 函数必须包含 return 语句');
        }

        // 检查头文件
        preg_match_all('/#include\s*<([^>]+)>/', $code, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $header) {
                $header = trim($header);
                if (!in_array($header, $cpp_allowed)) {
                    throw new Exception("不允许使用的头文件: $header\n允许使用的头文件: " . implode(', ', $cpp_allowed));
                }
            }
        }

        // 检查危险函数和操作
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $code)) {
                throw new Exception('检测到不安全的系统调用或文件操作');
            }
        }

        // 允许的基本函数列表
        $allowed_functions = [
            'main',
            'printf',
            'scanf',
            'puts',
            'getchar',
            'putchar',
            'strlen',
            'strcpy',
            'strcat',
            'strcmp',
            'malloc',
            'free',
            'memcpy',
            'memset',
            'pow',
            'sqrt',
            'abs',
            'rand',
            'srand',
            // C++ 特定函数
            'cin',
            'cout',
            'endl',
            'push_back',
            'size',
            'begin',
            'end',
            'sort'
        ];

        // 提取所有函数调用
        preg_match_all('/\b([a-zA-Z_]\w*)\s*\(/', $code, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $func) {
                // 允许自定义函数（以小写字母开头）
                if (preg_match('/^[a-z]/', $func)) {
                    continue;
                }
                // 检查是否是允许的函数
                if (!in_array($func, $allowed_functions)) {
                    throw new Exception("不允许使用的函数: $func");
                }
            }
        }

        return true;
    }

    // Python 检查
    if ($language === 'python') {
        foreach ($python_dangerous as $pattern) {
            if (preg_match($pattern, $code)) {
                throw new Exception('检测到不安全的 Python 代码：系统或文件操作');
            }
        }
    }

    return true;
}

// 添加安全的代码示例函数
function getSecureCodeExample($language) {
    switch ($language) {
        case 'c':
            return <<<'CODE'
#include <stdio.h>
#include <stdlib.h>

int main() {
    // 您的代码
    printf("Hello, World!\n");
    return 0;
}
CODE;
        case 'cpp':
            return <<<'CODE'
#include <iostream>
using namespace std;

int main() {
    // 您的代码
    cout << "Hello, World!" << endl;
    return 0;
}
CODE;
        case 'python':
            return <<<'CODE'
# 您的代码
print("Hello, World!")
CODE;
    }
}

// 添加运行时限制函数
function setExecutionLimits() {
    // 设置内存限制 (64MB)
    ini_set('memory_limit', '64M');
    // 设置执行时间限制 (5秒)
    set_time_limit(5);
}

// 检查编译器是否可用
function checkCompilers() {
    // 直接检查可执行文件
    $compilers = [
        'gcc' => [
            'paths' => ['/usr/bin/gcc', '/usr/local/bin/gcc'],
            'version_cmd' => 'gcc --version'
        ],
        'g++' => [
            'paths' => ['/usr/bin/g++', '/usr/local/bin/g++'],
            'version_cmd' => 'g++ --version'
        ],
        'python3' => [
            'paths' => ['/usr/bin/python3', '/usr/local/bin/python3'],
            'version_cmd' => 'python3 --version'
        ]
    ];
    
    $missing = [];
    foreach ($compilers as $name => $info) {
        $found = false;
        foreach ($info['paths'] as $path) {
            if (file_exists($path) && is_executable($path)) {
                error_log("找到 $name: $path");
                // 检查版本
                exec($info['version_cmd'] . " 2>&1", $version_output, $version_return);
                if ($version_return === 0) {
                    error_log("$name 版本信息: " . implode("\n", $version_output));
                    $found = true;
                    break;
                }
            }
        }
        
        if (!$found) {
            $missing[] = $name;
            error_log("未找到可用的 $name");
        }
    }
    
    if (!empty($missing)) {
        error_log("缺少编译器: " . implode(', ', $missing));
        return false;
    }
    
    return true;
}

// 在编译 C/C++ 代码时添加更多编译选项
function getCompileCommand($compiler, $source, $output) {
    // 使用完整路径
    $compiler_path = '';
    switch ($compiler) {
        case 'gcc':
            $compiler_path = '/usr/bin/gcc';
            break;
        case 'g++':
            $compiler_path = '/usr/bin/g++';
            break;
    }
    
    if (!file_exists($compiler_path)) {
        throw new Exception("找不到编译器: $compiler_path");
    }
    
    // 基本编译选项
    $options = [
        '-O2',
        '-Wall',
        '-fno-asm',
        '-lm'
    ];
    
    if ($compiler === 'gcc') {
        $options[] = '-std=c11';
    } else {
        $options[] = '-std=c++11';
    }
    
    return "$compiler_path " . implode(' ', $options) . " -o $output $source 2>&1";
}

// 修改环境变量检查函数
function checkCompilerEnvironment() {
    error_log("检查编译器环境变量：");
    error_log("PATH=" . getenv('PATH'));
    error_log("LD_LIBRARY_PATH=" . getenv('LD_LIBRARY_PATH'));
    error_log("GCC_EXEC_PREFIX=" . getenv('GCC_EXEC_PREFIX'));
    
    // 设置基本环境变量
    putenv("PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin");
    
    // 列出编译器相关目录内容
    $check_paths = [
        '/usr/lib/gcc',
        '/usr/libexec/gcc',
        '/usr/local/lib/gcc',
        '/usr/bin',  // 检查链接器
        '/usr/lib64'  // 检查库文件
    ];
    
    foreach ($check_paths as $path) {
        if (is_dir($path)) {
            error_log("目录内容 $path:");
            $output = [];
            exec("ls -la $path 2>&1", $output);
            error_log(implode("\n", $output));
        }
    }
    
    // 检查链接器
    exec('which ld 2>&1', $ld_output, $ld_return);
    if ($ld_return === 0) {
        error_log("链接器位置: " . $ld_output[0]);
    } else {
        error_log("找不到链接器 ld");
    }
}

// 修改编译函数
function compileCppCode($file_path, $output_path, $is_cpp = false) {
    $compiler = $is_cpp ? 'g++' : 'gcc';
    $compile_output = [];
    $compile_return = 0;
    
    // 基本编译选项
    $options = [
        '-O2',                    // 优化级别
        '-Wall',                  // 启用警告
        '-fno-asm',              // 禁用内联汇编
        '-B/usr/lib/gcc/',       // 指定编译器组件路径
        '-B/usr/lib64/gcc/',     // 备用编译器组件路径
        '-L/usr/lib64',          // 添加库搜索路径
        '-L/usr/lib',            // 添加库搜索路径
        '-I/usr/lib/gcc/x86_64-redhat-linux/4.8.5/include', // gcc include 路径
        '-Wl,-rpath,/usr/lib64', // 指定运行时库路径
        '-Wl,-rpath,/usr/lib',   // 指定运行时库路径
        '-lm',                   // 链接数学库
        $is_cpp ? '-std=c++11' : '-std=c11'
    ];
    
    // 构建编译命令
    $cmd = "PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin " . 
           "/usr/bin/$compiler " . implode(' ', $options) . 
           " -o " . escapeshellarg($output_path) . 
           " " . escapeshellarg($file_path);
    
    error_log("执行编译命令: $cmd");
    exec($cmd . " 2>&1", $compile_output, $compile_return);
    
    if ($compile_return !== 0) {
        error_log("编译失败，输出: " . implode("\n", $compile_output));
        throw new Exception("编译错误：\n" . implode("\n", $compile_output));
    }
    
    return true;
}

// 修改运行函数
function runProgram($program_path, $input_file) {
    $output = [];
    $return_var = 0;
    
    // 使用 PHP 的时间和内存测量
    $start_time = microtime(true);
    $start_memory = memory_get_usage(true);
    
    // 运行程序并捕获输出
    $cmd = "ulimit -t 5; ulimit -v 67108864; " . 
           escapeshellarg($program_path) . " < " . escapeshellarg($input_file);
    
    exec($cmd . " 2>&1", $output, $return_var);
    
    // 计算执行时间（毫秒）
    $execution_time = (microtime(true) - $start_time) * 1000;
    
    // 使用 /usr/bin/time 获取更准确的内存使用
    $time_cmd = "/usr/bin/time -f '%M' " . escapeshellarg($program_path) . 
                " < " . escapeshellarg($input_file) . " 2>&1 1>/dev/null";
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
    
    return [
        'success' => $return_var === 0,
        'output' => implode("\n", $output),
        'execution_time' => max(0.1, round($execution_time, 2)), // 确保至少显示0.1ms
        'memory_used' => round($memory_used, 2)
    ];
}

function runInDocker($code, $input, $language) {
    $container_name = 'code_runner_' . uniqid();
    $temp_dir = sys_get_temp_dir() . '/code_run_' . uniqid();
    mkdir($temp_dir);
    
    try {
        // 写入代码和输入文件
        file_put_contents("$temp_dir/code." . ($language === 'python' ? 'py' : ($language === 'cpp' ? 'cpp' : 'c')), $code);
        file_put_contents("$temp_dir/input.txt", $input);
        
        // 构建 Docker 运行命令
        $cmd = sprintf(
            'docker run --name %s --rm --network none --memory=%s --cpus=%d -v %s:/tmp/code:ro code_runner:%s',
            escapeshellarg($container_name),
            '64m',
            1,
            escapeshellarg($temp_dir),
            escapeshellarg($language)
        );
        
        // 添加超时控制
        $cmd = "timeout 5 $cmd";
        
        exec($cmd . " 2>&1", $output, $return_var);
        
        return [
            'success' => $return_var === 0,
            'output' => implode("\n", $output)
        ];
    } finally {
        // 清理
        exec("docker rm -f $container_name 2>/dev/null");
        array_map('unlink', glob("$temp_dir/*"));
        rmdir($temp_dir);
    }
}

// 添加请求频率限制
function checkRateLimit($user_id) {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    $key = "code_run_limit:$user_id";
    $count = $redis->get($key) ?: 0;
    
    if ($count > 10) { // 每分钟最多10次请求
        throw new Exception('请求过于频繁，请稍后再试');
    }
    
    $redis->incr($key);
    $redis->expire($key, 60); // 60秒后过期
}

if (!checkCompilers()) {
    echo json_encode(['success' => false, 'message' => '服务器编译环境未准备就绪，请联系管理员']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$code = $data['code'] ?? '';
$input = $data['input'] ?? '';
$language = $data['language'] ?? '';

// 记录请求信息
error_log("运行代码请求: 语言=$language");

// 检查语言支持
$supported_languages = ['c', 'cpp', 'python'];
if (!in_array($language, $supported_languages)) {
    echo json_encode(['success' => false, 'message' => '不支持的编程语言']);
    exit;
}

// 创建临时文件
$temp_dir = sys_get_temp_dir() . '/code_run_' . uniqid();
if (!mkdir($temp_dir, 0777, true)) {
    error_log("无法创建临时目录: $temp_dir");
    echo json_encode(['success' => false, 'message' => '系统错误：无法创建临时目录']);
    exit;
}
chmod($temp_dir, 0777);

$result = ['success' => false, 'output' => '', 'error' => '', 'execution_time' => 0, 'memory_used' => 0];

try {
    // 检查代码安全性
    checkCodeSecurity($code, $language);
    
    // 设置执行限制
    setExecutionLimits();

    // 在运行代码之前调用环境检查
    checkCompilerEnvironment();

    // 检查请求频率限制
    checkRateLimit($_SESSION['user_id']);

    // 添加代码格式化函数
    $code = formatCode($code, $language);
    checkCodeSecurity($code, $language);

    switch ($language) {
        case 'python':
            $file_path = $temp_dir . '/code.py';
            if (!file_put_contents($file_path, $code)) {
                throw new Exception('无法写入代码文件');
            }
            chmod($file_path, 0777);
            
            $input_file = $temp_dir . '/input.txt';
            if (!file_put_contents($input_file, $input)) {
                throw new Exception('无法写入输入文件');
            }
            
            $python_result = runProgram('/usr/bin/python3 ' . escapeshellarg($file_path), $input_file);
            $result['success'] = $python_result['success'];
            $result['output'] = $python_result['output'];
            $result['execution_time'] = $python_result['execution_time'];
            $result['memory_used'] = $python_result['memory_used'];
            break;
            
        case 'c':
        case 'cpp':
            $ext = $language === 'c' ? 'c' : 'cpp';
            $file_path = $temp_dir . "/code.$ext";
            if (!file_put_contents($file_path, $code)) {
                throw new Exception('无法写入代码文件');
            }
            chmod($file_path, 0777);
            
            // 编译
            compileCppCode($file_path, "$temp_dir/program", $language === 'cpp');
            
            // 运行
            $input_file = $temp_dir . '/input.txt';
            if (!file_put_contents($input_file, $input)) {
                throw new Exception('无法写入输入文件');
            }
            
            $run_result = runProgram("$temp_dir/program", $input_file);
            $result['success'] = $run_result['success'];
            $result['output'] = $run_result['output'];
            $result['execution_time'] = $run_result['execution_time'];
            $result['memory_used'] = $run_result['memory_used'];
            break;
    }
} catch (Exception $e) {
    error_log("代码运行异常: " . $e->getMessage());
    $result['error'] = $e->getMessage();
} finally {
    // 清理临时文件
    try {
        array_map('unlink', glob("$temp_dir/*"));
        rmdir($temp_dir);
    } catch (Exception $e) {
        error_log("清理临时文件失败: " . $e->getMessage());
    }
}

echo json_encode($result);

// 修改 Python 执行命令
function getPythonCommand($script_path, $input_path) {
    $python_path = '/usr/bin/python3';
    if (!file_exists($python_path)) {
        throw new Exception("找不到 Python 解释器: $python_path");
    }
    return "$python_path $script_path < $input_path 2>&1";
}

// 修改代码格式化函数
function formatCode($code, $language) {
    if ($language === 'c' || $language === 'cpp') {
        // 移除代码末尾的 "Copy" 文本
        $code = preg_replace('/}[\s\n]*Copy[\s\n]*$/', '}', $code);
        
        // 移除多余的空行
        $code = preg_replace("/\n\s*\n\s*\n/", "\n\n", $code);
        
        // 移除行尾空白字符
        $code = preg_replace('/[ \t]+$/m', '', $code);
        
        // 确保代码以换行符结束
        if (substr($code, -1) !== "\n") {
            $code .= "\n";
        }
        
        // 确保最后一个大括号后有换行符
        if (preg_match('/}[^\n]*$/', $code)) {
            $code .= "\n";
        }
        
        // 移除 UTF-8 BOM
        $code = str_replace("\xEF\xBB\xBF", '', $code);
        
        // 统一换行符
        $code = str_replace(["\r\n", "\r"], "\n", $code);
        
        // 移除不可见字符
        $code = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $code);
    }
    
    return $code;
} 
