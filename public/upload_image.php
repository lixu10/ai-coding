<?php
session_start();
$config = require('../config/config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

try {
    if (!isset($_FILES['image'])) {
        throw new Exception('未找到上传的图片');
    }

    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('图片上传失败');
    }

    // 创建上传目录
    $upload_dir = __DIR__ . '/uploads/' . date('Y/m/d');
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // 生成唯一文件名
    $filename = uniqid() . '_' . time() . '.jpg';
    $filepath = $upload_dir . '/' . $filename;

    // 移动上传的文件
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('保存图片失败');
    }

    // 返回图片URL
    $url = '/uploads/' . date('Y/m/d') . '/' . $filename;
    echo json_encode([
        'success' => true,
        'url' => $url
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 