<?php
session_start();
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>后台管理系统</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-login">
        <h1>后台管理系统</h1>
        <form id="admin-login-form">
            <div class="form-group">
                <label for="username">管理员账号</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">登录</button>
            <div id="login-message"></div>
        </form>
    </div>
    <script src="assets/js/admin.js"></script>
</body>
</html> 