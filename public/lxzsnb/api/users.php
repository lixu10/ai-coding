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
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'list':
            $stmt = $pdo->query('SELECT id, username, points, status, created_at FROM users ORDER BY id DESC');
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
            
        case 'ban':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $status = $_POST['status'] === 'active' ? 'banned' : 'active';
            $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
            $stmt->execute([$status, $id]);
            echo json_encode(['success' => true]);
            break;
            
        case 'update':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $points = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_INT);
            if ($points !== false) {
                $stmt = $pdo->prepare('UPDATE users SET points = ? WHERE id = ?');
                $stmt->execute([$points, $id]);
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'reset_password':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $password = $_POST['password'] ?? '';
            if (strlen($password) >= 6) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([$hash, $id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => '密码至少6个字符']);
            }
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '数据库错误']);
} 