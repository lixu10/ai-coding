<?php
session_start();
$config = require('../../../config/config.php');
header('Content-Type: application/json');

// 添加调试日志
error_log("Admins API called. Action: " . ($_GET['action'] ?? 'none'));

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
            $stmt = $pdo->query('SELECT * FROM admins ORDER BY id ASC');
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Found " . count($admins) . " admins");
            echo json_encode(['success' => true, 'data' => $admins]);
            break;

        case 'add':
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($username) || empty($password)) {
                echo json_encode(['success' => false, 'message' => '用户名和密码不能为空。']);
                exit;
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$username, $password_hash]);
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => '无效的管理员ID。']);
                exit;
            }

            // 不允许删除自己
            if ($id == $_SESSION['admin_id']) {
                echo json_encode(['success' => false, 'message' => '不能删除当前登录的管理员。']);
                exit;
            }

            $stmt = $pdo->prepare('DELETE FROM admins WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'change_password':
            $id = $_SESSION['admin_id'];
            $old_password = $_POST['old_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            
            if (strlen($new_password) < 6) {
                echo json_encode(['success' => false, 'message' => '新密码至少6个字符']);
                exit;
            }
            
            $stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ?');
            $stmt->execute([$id]);
            $admin = $stmt->fetch();
            
            if (!password_verify($old_password, $admin['password_hash'])) {
                echo json_encode(['success' => false, 'message' => '当前密码错误']);
                exit;
            }
            
            $stmt = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $id]);
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