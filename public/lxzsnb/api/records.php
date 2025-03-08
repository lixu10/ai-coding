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
            $stmt = $pdo->query('
                SELECT r.*, u.username 
                FROM code_requests r 
                JOIN users u ON r.user_id = u.id 
                ORDER BY r.created_at DESC
            ');
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
            
        case 'delete':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $stmt = $pdo->prepare('DELETE FROM code_requests WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;
            
        case 'detail':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            $stmt = $pdo->prepare('
                SELECT r.*, u.username 
                FROM code_requests r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.id = ?
            ');
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '数据库错误']);
} 