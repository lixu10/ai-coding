<?php
session_start();
$config = require('../../config/config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 修改查询，返回所有需要的字段
    $stmt = $pdo->query('SELECT id, name, points_consumption FROM models ORDER BY sort_order DESC');
    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $models]);
} catch (PDOException $e) {
    // 添加错误日志
    error_log("Models fetch error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
} 