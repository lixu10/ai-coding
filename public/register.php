<?php
session_start();
$config = require('../config/config.php');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// 验证必要字段
if (empty($data['username']) || empty($data['password']) || empty($data['email']) || empty($data['verification_code'])) {
    echo json_encode(['success' => false, 'message' => '所有字段都是必填的']);
    exit;
}

// 验证邮箱格式
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
    exit;
}

// 验证验证码前添加调试日志
error_log("Received verification data: " . print_r([
    'email' => $data['email'],
    'code' => $data['verification_code']
], true));

error_log("Session verification data: " . print_r($_SESSION['verification'] ?? 'not set', true));

// 验证验证码
if (!isset($_SESSION['verification'])) {
    error_log("Verification session not found");
    echo json_encode(['success' => false, 'message' => '验证码无效或已过期']);
    exit;
}

if ($_SESSION['verification']['email'] !== $data['email']) {
    error_log("Email mismatch: " . $_SESSION['verification']['email'] . " vs " . $data['email']);
    echo json_encode(['success' => false, 'message' => '验证码无效或已过期']);
    exit;
}

if ($_SESSION['verification']['code'] !== $data['verification_code']) {
    error_log("Code mismatch: " . $_SESSION['verification']['code'] . " vs " . $data['verification_code']);
    echo json_encode(['success' => false, 'message' => '验证码无效或已过期']);
    exit;
}

if (strtotime($_SESSION['verification']['expires']) < time()) {
    error_log("Code expired: " . $_SESSION['verification']['expires']);
    echo json_encode(['success' => false, 'message' => '验证码无效或已过期']);
    exit;
}

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 检查用户名是否已存在
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '用户名已被使用']);
        exit;
    }

    // 检查邮箱是否已存在
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '邮箱已被注册']);
        exit;
    }

    // 插入新用户
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, email, email_verified, points) VALUES (?, ?, ?, 1, ?)');
    $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $stmt->execute([
        $data['username'],
        $password_hash,
        $data['email'],
        $config['site']['initial_points']
    ]);

    // 清除验证码会话
    unset($_SESSION['verification']);

    // 设置登录会话
    $_SESSION['user_id'] = $pdo->lastInsertId();
    $_SESSION['username'] = $data['username'];

    echo json_encode(['success' => true, 'message' => '注册成功']);

} catch (PDOException $e) {
    error_log("数据库错误: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}
?> 