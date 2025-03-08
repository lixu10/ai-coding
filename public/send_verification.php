<?php
session_start();
// 根据实际目录结构调整路径
$vendorDir = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorDir)) {
    error_log("Vendor directory not found at: " . $vendorDir);
    echo json_encode(['success' => false, 'message' => '系统配置错误，请联系管理员']);
    exit;
}
require $vendorDir;
$config = require('../config/config.php');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => '请输入邮箱地址']);
    exit;
}

// 检查邮箱格式
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
    exit;
}

// 检查邮箱域名是否在白名单中
$domain = strtolower(substr(strrchr($email, "@"), 1));
if (!in_array($domain, $config['mail']['allowed_domains'])) {
    echo json_encode([
        'success' => false, 
        'message' => '您的邮箱还没有被邀请注册，暂时无法注册'
    ]);
    exit;
}

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
    
    // 检查邮箱是否已被使用
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '该邮箱已被注册']);
        exit;
    }

    // 生成验证码
    $code = sprintf('%06d', mt_rand(0, 999999));
    $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    
    error_log("Generated verification code: " . $code);
    error_log("Expires at: " . $expires);

    // 保存验证码到会话
    $_SESSION['verification'] = [
        'email' => $email,
        'code' => $code,
        'expires' => $expires
    ];

    error_log("Session verification data: " . print_r($_SESSION['verification'], true));

    // 发送邮件
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.qq.com';  // QQ邮箱SMTP服务器
    $mail->SMTPAuth = true;
    $mail->Username = $config['mail']['username'];
    $mail->Password = $config['mail']['password'];
    $mail->SMTPSecure = 'ssl';  // 使用SSL
    $mail->Port = 465;  // SSL端口
    $mail->CharSet = 'UTF-8';

    // 开启调试模式，查看具体错误信息
    $mail->SMTPDebug = 2;  // 临时开启调试
    $mail->Debugoutput = function($str, $level) {
        error_log("SMTP Debug: $str");
    };

    $mail->setFrom($config['mail']['username'], $config['mail']['from_name']);
    $mail->addAddress($email);
    $mail->Subject = $config['mail']['verification_subject'];
    $mail->Body = "您的验证码是：{$code}\n\n此验证码将在5分钟后过期。";

    $mail->send();
    echo json_encode(['success' => true, 'message' => '验证码已发送，请查收']);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '发送失败，请稍后重试']);
} 