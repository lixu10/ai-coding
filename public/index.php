<?php
session_start();
$config = require('../config/config.php');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($config['site']['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AI驱动的代码生成助手,帮助你快速生成高质量代码">
    <link rel="shortcut icon" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/toolbar/prism-toolbar.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-numbers/prism-line-numbers.min.css">
    <script>
        // 传递必要的配置到前端
        const config = {
            site: {
                initial_points: <?= $config['site']['initial_points'] ?>
            }
        };
    </script>
</head>
<body>
    <header>
        <div class="header-container">
            <a href="/" class="logo">
                <img src="assets/images/logo.png" alt="凌霄Code Logo">
                <h1><?= htmlspecialchars($config['site']['name']) ?></h1>
            </a>
            <?php if (isset($_SESSION['username'])): ?>
                <div class="user-menu">
                    <div class="user-info">
                        <span class="username">欢迎, <?= htmlspecialchars($_SESSION['username']) ?></span>
                        <?php
                            $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
                            $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
                            $stmt = $pdo->prepare('SELECT points FROM users WHERE id = ?');
                            $stmt->execute([$_SESSION['user_id']]);
                            $points = $stmt->fetchColumn();
                        ?>
                        <span class="points">剩余积分: <?= $points ?></span>
                    </div>
                    <div class="user-actions">
                        <button class="btn btn-secondary change-password-btn">修改密码</button>
                        <button class="btn btn-outline logout-btn">退出登录</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="hero-section">
                <div class="hero-content">
                    <h1>AI驱动的代码助手</h1>
                    <p>快速生成高质量代码,提升开发效率</p>
                    <div class="auth-container">
                        <form id="auth-form">
                            <div class="form-group">
                                <label for="username">用户名<span class="required">*</span></label>
                                <input type="text" id="username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="password">密码<span class="required">*</span></label>
                                <input type="password" id="password" name="password" required>
                            </div>
                            <!-- 邮箱和验证码部分默认隐藏，只在注册时显示 -->
                            <div class="register-fields" style="display: none;">
                                <div class="form-group">
                                    <label for="email">邮箱<span class="required">*</span></label>
                                    <input type="email" id="email" name="email">
                                </div>
                                <div class="form-group verification-group" style="display: none;">
                                    <label for="verification-code">验证码<span class="required">*</span></label>
                                    <div class="verification-input">
                                        <input type="text" id="verification-code" name="verification_code" maxlength="6">
                                        <button type="button" id="send-code-btn">发送验证码</button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-buttons">
                                <button type="submit" id="login-button">登录</button>
                                <button type="button" id="register-button">注册</button>
                            </div>
                            <div id="auth-message"></div>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="main-container">
                <div class="history-sidebar">
                    <h2>历史记录</h2>
                    <div class="history-list" id="history-list">
                        <!-- 历史记录将通过JavaScript动态加载 -->
                    </div>
                </div>
                <div class="content-area">
                    <div class="form-container">
                        <form id="code-form" method="post" action="generate.php" enctype="multipart/form-data" onsubmit="return false;">
                            <div class="form-group">
                                <label for="title">题目描述<span class="required">*</span></label>
                                <div class="title-group">
                                    <textarea id="title" name="title" rows="4" required></textarea>
                                    <button type="button" id="parse-problem-btn" class="btn btn-secondary">
                                        一键解析
                                    </button>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="input_content">输入内容<span class="required">*</span></label>
                                    <textarea id="input_content" name="input_content" rows="2" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="output_content">输出内容<span class="required">*</span></label>
                                    <textarea id="output_content" name="output_content" rows="2" required></textarea>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>输入输出样例</label>
                                <div id="samples-container">
                                    <div class="sample-row">
                                        <textarea name="sample_input[]" placeholder="输入样例" class="sample-input"></textarea>
                                        <textarea name="sample_output[]" placeholder="输出样例" class="sample-output"></textarea>
                                        <button type="button" class="remove-sample" style="display:none;">-</button>
                                    </div>
                                </div>
                                <button type="button" id="add-sample">添加样例</button>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="time_limit">时间限制（单位ms）</label>
                                    <input type="number" id="time_limit" name="time_limit" min="1" placeholder="默认 1000">
                                </div>
                                <div class="form-group">
                                    <label for="memory_limit">内存限制（单位kb）</label>
                                    <input type="number" id="memory_limit" name="memory_limit" min="1" placeholder="默认 65536">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="pseudo_code" name="pseudo_code">
                                        伪代码模式
                                    </label>
                                    <span class="help-text">开启后必须填写"你的代码"</span>
                                </div>
                                <div class="form-group checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="add_comments" name="add_comments">
                                        添加注释
                                    </label>
                                </div>
                                <div class="form-group checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="normal_naming" name="normal_naming">
                                        正常人取名
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="user_code">你的代码</label>
                                <textarea id="user_code" name="user_code" rows="6" placeholder="可选"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="language">选择语言<span class="required">*</span></label>
                                <select id="language" name="language" required>
                                    <option value="c">C</option>
                                    <option value="cpp">C++</option>
                                    <option value="python">Python</option>
                                    <option value="other">其他</option>
                                </select>
                                <input type="text" id="other_language" name="other_language" placeholder="请输入语言" style="display:none;">
                            </div>
                            <div class="form-group">
                                <label for="additional_requirements">其他要求</label>
                                <textarea id="additional_requirements" name="additional_requirements" rows="3" 
                                    placeholder="可选：在这里添加其他特殊要求"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="model">选择模型 <span class="required">*</span></label>
                                <select id="model" name="model" required>
                                    <!-- 选项将由 JavaScript 动态加载 -->
                                </select>
                                <div id="model-loading">加载中...</div>
                            </div>
                            <button type="submit" id="generate-button">开始生成</button>
                        </form>
                        <div class="result-container">
                            <h2>结果</h2>
                            <pre id="result"></pre>
                            <div class="code-actions">
                                <button type="button" id="analysis-btn" class="btn btn-secondary">题目解析</button>
                                <button type="button" id="run-code-btn" class="btn btn-primary">在线运行</button>
                                <button type="button" id="verify-code-btn" class="btn btn-secondary">立即验证</button>
                            </div>
                            
                            <!-- 在线运行界面 -->
                            <div id="run-container" style="display: none;">
                                <div class="run-interface">
                                    <div class="run-header">
                                        <h3>在线运行</h3>
                                        <button type="button" id="restart-code-btn" class="btn btn-outline">重新开始</button>
                                    </div>
                                    <div class="run-io">
                                        <div class="input-area">
                                            <label for="code-input">输入：</label>
                                            <textarea id="code-input" rows="4"></textarea>
                                        </div>
                                        <div class="output-area">
                                            <label>输出：</label>
                                            <pre id="code-output"></pre>
                                        </div>
                                    </div>
                                    <!-- 添加执行按钮 -->
                                    <div class="run-actions">
                                        <button type="button" id="execute-code-btn" class="btn btn-primary">执行代码</button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 验证结果界面 -->
                            <div id="verify-container" style="display: none;">
                                <h3>验证结果</h3>
                                <div class="verify-table-container">
                                    <table class="verify-table">
                                        <thead>
                                            <tr>
                                                <th>输入样例</th>
                                                <th>预期输出</th>
                                                <th>实际输出</th>
                                                <th>状态</th>
                                                <th>详细信息</th>
                                                <th>执行时间</th>
                                                <th>内存使用</th>
                                            </tr>
                                        </thead>
                                        <tbody id="verify-results">
                                            <!-- 验证结果将通过JavaScript动态添加 -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <div class="footer-container">
            <div class="footer-section">
                <h3>关于我们</h3>
                <ul>
                    <li><a href="https://xxx">站长博客</a></li>
                    <li><a href="https://xxx">团队成员</a></li>
                    <li><a href="https://xxx">加入我们</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>产品服务</h3>
                <ul>
                    <li><a href="https://xxx">AI Code</a></li>
                    <li><a href="https://xxx">AI官网</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>开发资源</h3>
                <ul>
                    <li><a href="https://xxx">API文档</a></li>
                    <li><a href="https://xxx">开发指南</a></li>
                    <li><a href="https://xxx">示例代码</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>联系我们</h3>
                <ul>
                    <li><a href="https://xxx">官方博客</a></li>
                    <li><a href="https://xxx">商务合作</a></li>
                    <li><a href="https://xxx">问题反馈</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($config['site']['name']) ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/toolbar/prism-toolbar.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-numbers/prism-line-numbers.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-c.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-cpp.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-python.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

    <!-- 修改密码模态框 -->
    <div id="password-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <div class="auth-container">
                <h2>修改密码</h2>
                <form id="password-form">
                    <div class="form-group">
                        <label for="old-password">当前密码</label>
                        <input type="password" id="old-password" name="old-password" required>
                    </div>
                    <div class="form-group">
                        <label for="new-password">新密码</label>
                        <input type="password" id="new-password" name="new-password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm-password">确认新密码</label>
                        <input type="password" id="confirm-password" name="confirm-password" required>
                    </div>
                    <button type="submit">确认修改</button>
                    <div id="password-message"></div>
                </form>
            </div>
        </div>
    </div>

    <!-- 充值模态框 -->
    <div id="recharge-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <div class="recharge-container">
                <h2>积分充值</h2>
                <form id="recharge-form">
                    <div class="form-group">
                        <label for="recharge-code">充值码</label>
                        <input type="text" id="recharge-code" name="recharge-code" required>
                    </div>
                    <button type="submit">确认充值</button>
                    <div id="recharge-message"></div>
                </form>
            </div>
        </div>
    </div>

    <!-- 解析模态框 -->
    <div id="parse-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>题目解析</h2>
            <div class="parse-container">
                <!-- 添加解析方式切换按钮 -->
                <div class="parse-tabs">
                    <button class="parse-tab active" data-tab="text">文字解析</button>
                    <button class="parse-tab" data-tab="link">链接解析</button>
                    <button class="parse-tab" data-tab="image">图片解析</button>
                </div>
                
                <!-- 文字解析面板 -->
                <div class="parse-panel active" id="text-panel">
                    <div class="form-group">
                        <label for="problem-text">请粘贴完整题目内容</label>
                        <textarea id="problem-text" rows="10" placeholder="在此粘贴题目内容..."></textarea>
                    </div>
                </div>
                
                <!-- 链接解析面板 -->
                <div class="parse-panel" id="link-panel">
                    <div class="form-group">
                        <label for="problem-link">请输入题目链接</label>
                        <input type="url" id="problem-link" placeholder="https://example.com/problem/123">
                        <p class="help-text">请确保链接可以在未登录状态下访问题目内容</p>
                    </div>
                </div>
                
                <!-- 图片解析面板 -->
                <div class="parse-panel" id="image-panel">
                    <div class="image-upload-container">
                        <input type="hidden" id="image-url" name="image-url">
                        <div class="upload-area" id="upload-area">
                            <div class="upload-prompt">
                                <i class="upload-icon"></i>
                                <p>拖拽图片到此处或点击上传</p>
                                <p class="sub-text">支持jpg、png格式，最大5MB</p>
                            </div>
                            <input type="file" id="image-input" accept="image/*" hidden>
                        </div>
                        <div class="preview-area" id="preview-area" style="display: none;">
                            <img id="preview-image" src="" alt="预览">
                            <button type="button" class="remove-image" id="remove-image">×</button>
                        </div>
                    </div>
                </div>

                <div class="parse-actions">
                    <button type="button" id="clear-parse-btn" class="btn btn-outline">清空</button>
                    <button type="button" id="start-parse-btn" class="btn btn-primary">开始解析 (消耗10积分)</button>
                </div>
                <div id="parse-message"></div>
            </div>
        </div>
    </div>
</body>
</html> 