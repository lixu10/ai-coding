<?php
session_start();
$config = require('../config/config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$code = trim($data['code'] ?? '');

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => '请输入充值码']);
    exit;
}

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 开始事务
    $pdo->beginTransaction();

    // 查找并锁定充值码
    $stmt = $pdo->prepare('SELECT id, points, remaining_uses FROM recharge_codes WHERE code = ? AND remaining_uses > 0 FOR UPDATE');
    $stmt->execute([$code]);
    $rechargeCode = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rechargeCode) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '无效的充值码或已被使用']);
        exit;
    }

    // 更新充值码使用次数
    $stmt = $pdo->prepare('UPDATE recharge_codes SET remaining_uses = remaining_uses - 1 WHERE id = ?');
    $stmt->execute([$rechargeCode['id']]);

    // 更新用户积分
    $stmt = $pdo->prepare('UPDATE users SET points = points + ? WHERE id = ?');
    $stmt->execute([$rechargeCode['points'], $_SESSION['user_id']]);

    // 记录充值记录
    $stmt = $pdo->prepare('INSERT INTO recharge_records (user_id, code_id, points) VALUES (?, ?, ?)');
    $stmt->execute([$_SESSION['user_id'], $rechargeCode['id'], $rechargeCode['points']]);

    // 获取最新积分
    $stmt = $pdo->prepare('SELECT points FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $currentPoints = $stmt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => "充值成功，获得{$rechargeCode['points']}积分",
        'points' => $currentPoints
    ]);

} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '系统错误，请稍后重试']);
} 