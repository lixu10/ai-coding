<?php
session_start();
$config = require('../config/config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$old_password = trim($data['old_password'] ?? '');
$new_password = trim($data['new_password'] ?? '');

if (empty($old_password) || empty($new_password)) {
    echo json_encode(['success' => false, 'message' => '密码不能为空']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => '新密码至少需要6个字符']);
    exit;
}

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 验证旧密码
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($old_password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => '当前密码错误']);
        exit;
    }

    // 更新密码
    $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([$new_password_hash, $_SESSION['user_id']]);

    echo json_encode(['success' => true, 'message' => '密码修改成功']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '数据库错误']);
} 