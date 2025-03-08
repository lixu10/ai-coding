<?php
session_start();
$config = require('../../../config/config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'list':
            // 获取充值码列表
            $stmt = $pdo->query('
                SELECT r.*, a.username as creator_name 
                FROM recharge_codes r 
                LEFT JOIN admins a ON r.created_by = a.id 
                ORDER BY r.created_at DESC
            ');
            $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $codes]);
            break;

        case 'generate':
            // 生成新的充值码
            $count = intval($_POST['count'] ?? 1);
            $points = intval($_POST['points'] ?? 100);
            $uses = intval($_POST['uses'] ?? 1);

            if ($count < 1 || $count > 100) {
                echo json_encode(['success' => false, 'message' => '生成数量必须在1-100之间']);
                exit;
            }

            if ($points < 1) {
                echo json_encode(['success' => false, 'message' => '充值积分必须大于0']);
                exit;
            }

            if ($uses < 1) {
                echo json_encode(['success' => false, 'message' => '使用次数必须大于0']);
                exit;
            }

            $generatedCodes = [];
            $stmt = $pdo->prepare('
                INSERT INTO recharge_codes (code, points, remaining_uses, total_uses, created_by) 
                VALUES (?, ?, ?, ?, ?)
            ');

            for ($i = 0; $i < $count; $i++) {
                $code = strtoupper(bin2hex(random_bytes(8))); // 生成16位随机字符
                $stmt->execute([$code, $points, $uses, $uses, $_SESSION['admin_id']]);
                $generatedCodes[] = $code;
            }

            echo json_encode([
                'success' => true, 
                'message' => "成功生成{$count}个充值码",
                'codes' => $generatedCodes
            ]);
            break;

        case 'records':
            // 获取充值记录列表
            $stmt = $pdo->query('
                SELECT r.*, u.username, rc.code 
                FROM recharge_records r 
                JOIN users u ON r.user_id = u.id 
                JOIN recharge_codes rc ON r.code_id = rc.id 
                ORDER BY r.created_at DESC
            ');
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $records]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => '未知的操作']);
            break;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
} 