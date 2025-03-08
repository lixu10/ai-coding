<?php
session_start();
$config = require('../../../config/config.php');
header('Content-Type: application/json');

// 添加调试日志
error_log("Models API called. Action: " . ($_GET['action'] ?? 'none'));

if (!isset($_SESSION['admin_id'])) {
    error_log("Admin not logged in");
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';
    error_log("Processing action: " . $action);

    switch ($action) {
        case 'list':
            $stmt = $pdo->query('SELECT * FROM models ORDER BY sort_order DESC');
            $models = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Found " . count($models) . " models");
            echo json_encode(['success' => true, 'data' => $models]);
            break;

        case 'add':
            $name = trim($_POST['name'] ?? '');
            $sort_order = intval($_POST['sort_order'] ?? 0);
            $points_consumption = intval($_POST['points_consumption'] ?? 10);

            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => '模型名称不能为空。']);
                exit;
            }

            $stmt = $pdo->prepare('INSERT INTO models (name, sort_order, points_consumption) VALUES (?, ?, ?)');
            $stmt->execute([$name, $sort_order, $points_consumption]);
            echo json_encode(['success' => true]);
            break;

        case 'update':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : null;
            $points_consumption = isset($_POST['points_consumption']) ? intval($_POST['points_consumption']) : null;

            if (!$id) {
                echo json_encode(['success' => false, 'message' => '无效的模型ID。']);
                exit;
            }

            $sql = 'UPDATE models SET ';
            $params = [];
            if ($sort_order !== null) {
                $sql .= 'sort_order = ?, ';
                $params[] = $sort_order;
            }
            if ($points_consumption !== null) {
                $sql .= 'points_consumption = ?, ';
                $params[] = $points_consumption;
            }
            $sql = rtrim($sql, ', ') . ' WHERE id = ?';
            $params[] = $id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => '无效的模型ID。']);
                exit;
            }

            $stmt = $pdo->prepare('DELETE FROM models WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => '未知的操作']);
            break;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
} 