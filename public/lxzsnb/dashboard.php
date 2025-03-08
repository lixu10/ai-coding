<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
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
    <div class="admin-container">
        <nav class="admin-nav">
            <h2>管理菜单</h2>
            <ul>
                <li><a href="#" data-page="users">用户管理</a></li>
                <li><a href="#" data-page="records">生成记录</a></li>
                <li><a href="#" data-page="admins">管理员</a></li>
                <li><a href="#" data-page="models">模型管理</a></li>
                <li><a href="#" data-page="recharge-codes">充值码管理</a></li>
                <li><a href="#" id="admin-logout">退出登录</a></li>
            </ul>
        </nav>
        <main class="admin-content">
            <div id="users-page" class="admin-page">
                <h2>用户管理</h2>
                <div class="table-container">
                    <table id="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>用户名</th>
                                <th>积分</th>
                                <th>状态</th>
                                <th>注册时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div id="records-page" class="admin-page" style="display:none;">
                <h2>生成记录管理</h2>
                <div class="table-container">
                    <table id="records-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>用户</th>
                                <th>标题</th>
                                <th>语言</th>
                                <th>生成时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div id="admins-page" class="admin-page" style="display:none;">
                <h2>管理员管理</h2>
                <button id="add-admin-btn" class="action-btn">添加管理员</button>
                <div class="table-container">
                    <table id="admins-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>用户名</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div id="models-page" class="admin-page" style="display:none;">
                <h2>模型管理</h2>
                <button id="add-model-btn" class="action-btn">添加模型</button>
                <div class="table-container">
                    <table id="models-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>模型名称</th>
                                <th>排序</th>
                                <th>积分消耗</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div id="recharge-codes-page" class="admin-page" style="display:none;">
                <h2>充值码管理</h2>
                
                <!-- 添加标签页切换 -->
                <div class="tab-nav">
                    <button class="tab-btn active" data-tab="codes-list">充值码列表</button>
                    <button class="tab-btn" data-tab="recharge-records">充值记录</button>
                </div>
                
                <!-- 充值码列表标签页 -->
                <div id="codes-list" class="tab-content active">
                    <div class="form-group">
                        <label for="generate-count">生成数量</label>
                        <input type="number" id="generate-count" min="1" value="1">
                        
                        <label for="points-amount">充值积分</label>
                        <input type="number" id="points-amount" min="1" value="100">
                        
                        <label for="use-times">可用次数</label>
                        <input type="number" id="use-times" min="1" value="1">
                        
                        <button id="generate-codes-btn" class="action-btn">生成充值码</button>
                    </div>
                    <div class="table-container">
                        <table id="recharge-codes-table">
                            <thead>
                                <tr>
                                    <th>充值码</th>
                                    <th>积分</th>
                                    <th>剩余次数</th>
                                    <th>总次数</th>
                                    <th>创建时间</th>
                                    <th>创建者</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                
                <!-- 充值记录标签页 -->
                <div id="recharge-records" class="tab-content">
                    <div class="table-container">
                        <table id="recharge-records-table">
                            <thead>
                                <tr>
                                    <th>用户</th>
                                    <th>充值码</th>
                                    <th>充值积分</th>
                                    <th>充值时间</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        // 添加当前管理员ID到全局变量
        const currentAdminId = <?= $_SESSION['admin_id'] ?>;
    </script>
    <script src="assets/js/admin.js"></script>
</body>
</html> 