<?php
session_start();
$config = require('../config/config.php');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => '用户名和密码不能为空。']);
    exit;
}

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare('SELECT id, password_hash, status FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['status'] === 'banned') {
        echo json_encode(['success' => false, 'message' => '账号已被封禁']);
        exit;
    }

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $username;
        echo json_encode(['success' => true, 'message' => '登录成功。']);
    } else {
        echo json_encode(['success' => false, 'message' => '用户名或密码错误。']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '数据库错误。']);
}
?> 