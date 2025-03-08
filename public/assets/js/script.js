document.addEventListener('DOMContentLoaded', function () {
    console.log('JavaScript 已加载');
    const authModal = document.getElementById('auth-modal');
    const authForm = document.getElementById('auth-form');
    const loginButton = document.getElementById('login-button');
    const registerButton = document.getElementById('register-button');
    const authMessage = document.getElementById('auth-message');
    const closeButton = document.getElementById('close-button');

    const languageSelect = document.getElementById('language');
    const otherLanguageInput = document.getElementById('other_language');

    // 检查元素是否存在
    if (languageSelect) {
        // 显示或隐藏其他语言输入
        languageSelect.addEventListener('change', function () {
            if (this.value === 'other') {
                otherLanguageInput.style.display = 'block';
                otherLanguageInput.required = true;
            } else {
                otherLanguageInput.style.display = 'none';
                otherLanguageInput.required = false;
            }
        });
    }

    // 添加样例行
    const addSampleButton = document.getElementById('add-sample');
    const samplesContainer = document.getElementById('samples-container');

    // 检查元素是否存在
    if (addSampleButton && samplesContainer) {
        addSampleButton.addEventListener('click', function () {
            const sampleRow = document.createElement('div');
            sampleRow.className = 'sample-row';
            sampleRow.innerHTML = `
                <textarea name="sample_input[]" placeholder="输入样例" class="sample-input"></textarea>
                <textarea name="sample_output[]" placeholder="输出样例" class="sample-output"></textarea>
                <button type="button" class="remove-sample">-</button>
            `;
            samplesContainer.appendChild(sampleRow);
            
            // 绑定高度同步
            const input = sampleRow.querySelector('.sample-input');
            const output = sampleRow.querySelector('.sample-output');
            bindHeightSync(input, output);
        });

        // 移除样例行
        samplesContainer.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('remove-sample')) {
                e.target.parentElement.remove();
            }
        });
    }

    // 处理登录/注册切换
    const registerFields = document.querySelector('.register-fields');
    const emailInput = document.getElementById('email');
    const verificationCodeInput = document.getElementById('verification-code');
    let isRegistering = false;

    registerButton?.addEventListener('click', function() {
        if (!isRegistering) {
            // 切换到注册模式
            isRegistering = true;
            registerFields.style.display = 'block';
            emailInput.required = true;
            this.textContent = '返回登录';
            loginButton.textContent = '注册';
            // 重置表单
            authForm.reset();
            authMessage.textContent = '';
        } else {
            // 切换回登录模式
            isRegistering = false;
            registerFields.style.display = 'none';
            emailInput.required = false;
            verificationCodeInput.required = false;
            this.textContent = '注册';
            loginButton.textContent = '登录';
            // 重置表单
            authForm.reset();
            authMessage.textContent = '';
        }
    });

    // 处理表单提交
    if (authForm) {
        authForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();

            // 添加加载状态
            loginButton.disabled = true;
            registerButton.disabled = true;
            authMessage.textContent = isRegistering ? '注册中...' : '登录中...';

            if (isRegistering) {
                // 注册流程
                const email = emailInput.value.trim();
                const verificationCode = verificationCodeInput.value.trim();

                console.log('Submitting registration with:', {
                    username: username,
                    email: email,
                    verification_code: verificationCode
                });

                fetch('register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        username, 
                        password, 
                        email,
                        verification_code: verificationCode 
                    })
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Registration response:', data);
                    authMessage.textContent = data.message;
                    if (data.success) {
                        authMessage.style.color = '#2ecc71';
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        authMessage.style.color = '#ff4757';
                    }
                })
                .catch((error) => {
                    console.error('Registration error:', error);
                    authMessage.textContent = '请求失败，请稍后再试。';
                    authMessage.style.color = '#ff4757';
                });
            } else {
                // 登录流程
                fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                })
                .then(response => response.json())
                .then(data => {
                    authMessage.textContent = data.message;
                    if (data.success) {
                        location.reload();
                    }
                })
                .catch(() => {
                    authMessage.textContent = '请求失败，请稍后再试。';
                })
                .finally(() => {
                    loginButton.disabled = false;
                    registerButton.disabled = false;
                });
            }
        });
    }

    // 处理邮箱输入和验证码发送
    emailInput?.addEventListener('input', function() {
        const verificationGroup = document.querySelector('.verification-group');
        verificationGroup.style.display = this.value ? 'block' : 'none';
    });

    const sendCodeBtn = document.getElementById('send-code-btn');
    sendCodeBtn?.addEventListener('click', function() {
        const email = emailInput.value.trim();
        if (!email) {
            authMessage.textContent = '请输入邮箱地址';
            return;
        }

        // 禁用按钮并开始倒计时
        let countdown = 60;
        this.disabled = true;
        const originalText = this.textContent;
        const timer = setInterval(() => {
            this.textContent = `${countdown}秒后重试`;
            countdown--;
            if (countdown < 0) {
                clearInterval(timer);
                this.disabled = false;
                this.textContent = originalText;
            }
        }, 1000);

        fetch('send_verification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                authMessage.textContent = '验证码已发送，请查收邮件';
                authMessage.style.color = '#2ecc71';  // 成功提示使用绿色
            } else {
                authMessage.textContent = data.message;
                authMessage.style.color = '#ff4757';  // 错误提示使用红色
            }
        })
        .catch(() => {
            authMessage.textContent = '发送失败，请稍后重试';
            authMessage.style.color = '#ff4757';
        });
    });

    // 禁用关闭按钮
    // 如果需要允许关闭，可以调整相关样式和逻辑
    // 目前关闭按钮隐藏，用户无法关闭模态框

    // 处理伪代码复选框
    const pseudoCodeCheckbox = document.getElementById('pseudo_code');
    const userCodeTextarea = document.getElementById('user_code');

    if (pseudoCodeCheckbox && userCodeTextarea) {
        pseudoCodeCheckbox.addEventListener('change', function() {
            if (this.checked) {
                userCodeTextarea.required = true;
                userCodeTextarea.classList.add('required-field');
                // 添加红色星号到标签
                const label = document.querySelector('label[for="user_code"]');
                if (!label.querySelector('.required')) {
                    label.innerHTML += '<span class="required">*</span>';
                }
            } else {
                userCodeTextarea.required = false;
                userCodeTextarea.classList.remove('required-field');
                // 移除红色星号
                const label = document.querySelector('label[for="user_code"]');
                const requiredSpan = label.querySelector('.required');
                if (requiredSpan) {
                    requiredSpan.remove();
                }
            }
        });
    }

    // 处理代码生成表单
    const codeForm = document.getElementById('code-form');
    const resultContainer = document.getElementById('result');

    // 检查元素是否存在
    if (codeForm && resultContainer) {
        codeForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            console.log('提交事件被触发');

            // 检查模型选择
            const modelSelect = document.getElementById('model');
            if (!modelSelect.value) {
                alert('请选择模型');
                return;
            }

            // 检查伪代码模式
            const pseudoCode = document.getElementById('pseudo_code').checked;
            const userCode = document.getElementById('user_code').value.trim();
            
            if (pseudoCode && !userCode) {
                alert('开启伪代码模式时必须填写"你的代码"');
                return;
            }

            // 显示加载状态
            const generateButton = document.getElementById('generate-button');
            generateButton.disabled = true;
            generateButton.textContent = '生成中...';
            resultContainer.innerHTML = '<div style="text-align: center;">正在生成代码，请稍候...</div>';

            // 检查必填字段
            const title = document.getElementById('title').value.trim();
            const input_content = document.getElementById('input_content').value.trim();
            const output_content = document.getElementById('output_content').value.trim();
            const language = document.getElementById('language').value.trim();
            const other_language = document.getElementById('other_language').value.trim();

            if (!title || !input_content || !output_content || !language || (language === 'other' && !other_language)) {
                alert('请填写所有必填字段。');
                generateButton.disabled = false;
                generateButton.textContent = '开始生成';
                return;
            }

            const formData = new FormData(codeForm);

            // 确保复选框的值正确传递
            formData.set('pseudo_code', document.getElementById('pseudo_code').checked.toString());
            formData.set('add_comments', document.getElementById('add_comments').checked.toString());
            formData.set('normal_naming', document.getElementById('normal_naming').checked.toString());

            // 输出表单数据到控制台以便调试
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }

            // 在生成代码时添加其他要求
            const additionalRequirements = document.getElementById('additional_requirements').value.trim();
            if (additionalRequirements) {
                formData.append('additional_requirements', additionalRequirements);
            }

            try {
                const response = await fetch('generate.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                await handleGenerateResponse(response);
            } catch (error) {
                console.error('请求错误:', error);
                resultContainer.innerHTML = `<div style="color: red;">请求失败: ${escapeHtml(error.message)}</div>`;
            } finally {
                // 恢复按钮状态
                generateButton.disabled = false;
                generateButton.textContent = '开始生成';
            }
        });
    } else {
        console.error('找不到必要的表单元素:', {
            codeForm: !!codeForm,
            resultContainer: !!resultContainer
        });
    }

    // 转义HTML以防止XSS
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function getLanguageClass(language) {
        switch(language.toLowerCase()) {
            case 'c':
                return 'c';
            case 'cpp':
                return 'cpp';
            case 'python':
                return 'python';
            case 'java':
                return 'java';
            case 'javascript':
                return 'javascript';
            case 'php':
                return 'php';
            default:
                return language.toLowerCase();
        }
    }

    // 修改历史记录加载函数
    function loadHistory() {
        fetch('get_history.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const historyList = document.getElementById('history-list');
                    if (historyList) {
                        historyList.innerHTML = data.data.map(item => `
                            <div class="history-item" data-id="${item.id}">
                                <h3>${escapeHtml(item.title)}</h3>
                                <div class="history-item-details">
                                    <p><strong>语言:</strong> ${escapeHtml(item.language)}</p>
                                    <p><strong>时间限制:</strong> ${item.time_limit}ms</p>
                                    <p><strong>内存限制:</strong> ${item.memory_limit}kb</p>
                                    <p><strong>生成时间:</strong> ${new Date(item.created_at).toLocaleString()}</p>
                                </div>
                                <div class="history-item-action">点击查看详情</div>
                            </div>
                        `).join('');

                        // 添加点击事件监听器
                        historyList.addEventListener('click', function(e) {
                            const historyItem = e.target.closest('.history-item');
                            if (historyItem) {
                                const id = historyItem.dataset.id;
                                // 显示加载状态
                                historyItem.style.opacity = '0.5';
                                
                                loadHistoryDetail(id)
                                    .then(() => {
                                        // 恢复透明度
                                        historyItem.style.opacity = '1';
                                    })
                                    .catch(() => {
                                        // 出错时也恢复透明度
                                        historyItem.style.opacity = '1';
                                    });
                            }
                        });
                    }
                }
            });
    }

    // 修改历史记录点击处理函数，返回 Promise
    function loadHistoryDetail(id) {
        return fetch(`get_history_detail.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const record = data.data;
                    
                    // 填充表单数据
                    document.getElementById('title').value = record.title;
                    document.getElementById('input_content').value = record.input_content;
                    document.getElementById('output_content').value = record.output_content;
                    document.getElementById('language').value = record.language;
                    document.getElementById('time_limit').value = record.time_limit;
                    document.getElementById('memory_limit').value = record.memory_limit;
                    document.getElementById('pseudo_code').checked = record.pseudo_code === '1';
                    document.getElementById('add_comments').checked = record.add_comments === '1';
                    document.getElementById('normal_naming').checked = record.normal_naming === '1';
                    
                    // 如果有用户代码，也填充
                    if (record.user_code) {
                        document.getElementById('user_code').value = record.user_code;
                    }
                    
                    // 清空现有样例
                    const samplesContainer = document.getElementById('samples-container');
                    samplesContainer.innerHTML = '';
                    
                    // 添加样例数据
                    const samples = JSON.parse(record.samples);
                    samples.forEach((sample, index) => {
                        const sampleRow = document.createElement('div');
                        sampleRow.className = 'sample-row';
                        sampleRow.innerHTML = `
                            <textarea name="sample_input[]" placeholder="输入样例" class="sample-input">${escapeHtml(sample.input)}</textarea>
                            <textarea name="sample_output[]" placeholder="输出样例" class="sample-output">${escapeHtml(sample.output)}</textarea>
                            <button type="button" class="remove-sample"${index === 0 ? ' style="display:none;"' : ''}>-</button>
                        `;
                        samplesContainer.appendChild(sampleRow);
                        
                        // 绑定高度同步
                        const input = sampleRow.querySelector('.sample-input');
                        const output = sampleRow.querySelector('.sample-output');
                        bindHeightSync(input, output);
                    });

                    // 显示生成的代码
                    const resultContainer = document.getElementById('result');
                    resultContainer.innerHTML = `<pre class="line-numbers"><code class="language-${getLanguageClass(record.language)}">${escapeHtml(record.generated_code)}</code></pre>`;
                    Prism.highlightAll();

                    // 滚动到表单顶部
                    document.querySelector('.form-container').scrollIntoView({ behavior: 'smooth' });
                }
            });
    }

    // 处理退出登录
    document.querySelector('.logout-btn')?.addEventListener('click', function() {
        fetch('logout.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
    });

    // 处理修改密码
    const passwordModal = document.getElementById('password-modal');
    const passwordForm = document.getElementById('password-form');
    const passwordMessage = document.getElementById('password-message');
    const changePasswordBtn = document.querySelector('.change-password-btn');
    const closePasswordBtn = passwordModal?.querySelector('.close-button');

    changePasswordBtn?.addEventListener('click', function() {
        if (passwordModal) {
            passwordModal.style.display = 'block';
        }
    });

    closePasswordBtn?.addEventListener('click', function() {
        if (passwordModal) {
            passwordModal.style.display = 'none';
        }
    });

    passwordForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        const oldPassword = document.getElementById('old-password').value;
        const newPassword = document.getElementById('new-password').value;
        const confirmPassword = document.getElementById('confirm-password').value;

        if (newPassword !== confirmPassword) {
            passwordMessage.textContent = '两次输入的新密码不一致';
            return;
        }

        fetch('change_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                old_password: oldPassword,
                new_password: newPassword
            })
        })
        .then(response => response.json())
        .then(data => {
            passwordMessage.textContent = data.message;
            if (data.success) {
                setTimeout(() => {
                    passwordModal.style.display = 'none';
                    passwordForm.reset();
                }, 1500);
            }
        });
    });

    // 初始加载历史记录
    loadHistory();

    // 加载模型列表
    function loadModels() {
        console.log('开始加载模型列表');
        const loadingElement = document.getElementById('model-loading');
        if (loadingElement) {
            loadingElement.style.display = 'block';
        }

        fetch('api/get_models.php')
            .then(response => {
                console.log('收到响应:', response);
                return response.json();
            })
            .then(data => {
                console.log('解析的数据:', data);
                if (data.success) {
                    const modelSelect = document.getElementById('model');
                    if (!modelSelect) {
                        console.error('找不到模型选择框元素');
                        return;
                    }
                    
                    if (!Array.isArray(data.data)) {
                        console.error('模型数据不是数组:', data.data);
                        return;
                    }

                    modelSelect.innerHTML = data.data.map(model => 
                        `<option value="${escapeHtml(model.name)}" data-points="${model.points_consumption}">
                            ${escapeHtml(model.name)} (${model.points_consumption}积分)
                        </option>`
                    ).join('');
                    
                    console.log('模型列表加载完成');
                } else {
                    console.error('加载模型失败:', data.message);
                }
            })
            .catch(error => {
                console.error('加载模型时出错:', error);
            })
            .finally(() => {
                if (loadingElement) {
                    loadingElement.style.display = 'none';
                }
            });
    }

    loadModels(); // 加载模型列表

    // 处理充值功能
    const pointsDisplay = document.querySelector('.points');
    const rechargeModal = document.getElementById('recharge-modal');
    const rechargeForm = document.getElementById('recharge-form');
    const rechargeMessage = document.getElementById('recharge-message');
    const closeRechargeBtn = rechargeModal?.querySelector('.close-button');

    pointsDisplay?.addEventListener('click', function() {
        if (rechargeModal) {
            rechargeModal.style.display = 'block';
        }
    });

    closeRechargeBtn?.addEventListener('click', function() {
        if (rechargeModal) {
            rechargeModal.style.display = 'none';
        }
    });

    rechargeForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        const code = document.getElementById('recharge-code').value.trim();
        
        if (!code) {
            showRechargeMessage('请输入充值码', 'error');
            return;
        }

        fetch('recharge.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showRechargeMessage(data.message, 'success');
                // 更新积分显示
                pointsDisplay.textContent = `剩余积分: ${data.points}`;
                // 重置表单并关闭模态框
                setTimeout(() => {
                    rechargeForm.reset();
                    rechargeModal.style.display = 'none';
                }, 1500);
            } else {
                showRechargeMessage(data.message, 'error');
            }
        })
        .catch(() => {
            showRechargeMessage('请求失败，请稍后重试', 'error');
        });
    });

    function showRechargeMessage(message, type) {
        rechargeMessage.textContent = message;
        rechargeMessage.className = type;
    }

    // 工具函数
    const utils = {
        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        },

        getLanguageClass(language) {
            const languageMap = {
                'cpp': 'cpp',
                'c': 'c',
                'python': 'python',
                'java': 'java',
                'javascript': 'javascript'
            };
            return languageMap[language.toLowerCase()] || 'plaintext';
        },

        async fetchJson(url, options = {}) {
            try {
                const response = await fetch(url, {
                    ...options,
                    headers: {
                        'Content-Type': 'application/json',
                        ...options.headers
                    }
                });
                const data = await response.json();
                return { success: true, data };
            } catch (error) {
                console.error('Fetch error:', error);
                return { success: false, error };
            }
        }
    };

    // UI 组件
    const UI = {
        showMessage(element, message, type = 'error') {
            element.textContent = message;
            element.className = type;
            element.style.color = type === 'success' ? '#2ecc71' : '#ff4757';
        },

        setLoading(element, isLoading) {
            element.classList.toggle('loading', isLoading);
            element.disabled = isLoading;
        },

        showModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'block';
            requestAnimationFrame(() => modal.classList.add('show'));
        },

        hideModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        },

        createRipple(event) {
            const button = event.currentTarget;
            const ripple = document.createElement('span');
            const rect = button.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            
            ripple.style.width = ripple.style.height = `${size}px`;
            ripple.style.left = `${event.clientX - rect.left - size/2}px`;
            ripple.style.top = `${event.clientY - rect.top - size/2}px`;
            ripple.className = 'ripple';
            
            button.appendChild(ripple);
            ripple.addEventListener('animationend', () => ripple.remove());
        }
    };

    // 表单处理
    const Forms = {
        validateForm(form) {
            let isValid = true;
            form.querySelectorAll('[required]').forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    this.showInputError(input);
                } else {
                    this.hideInputError(input);
                }
            });
            return isValid;
        },

        showInputError(input) {
            input.classList.add('error');
            const message = input.dataset.error || '此字段是必填的';
            let error = input.nextElementSibling;
            
            if (!error?.classList.contains('error-message')) {
                error = document.createElement('div');
                error.className = 'error-message';
                input.parentNode.insertBefore(error, input.nextSibling);
            }
            error.textContent = message;
        },

        hideInputError(input) {
            input.classList.remove('error');
            const error = input.nextElementSibling;
            if (error?.classList.contains('error-message')) {
                error.remove();
            }
        },

        async handleSubmit(form, url, options = {}) {
            const submitButton = form.querySelector('[type="submit"]');
            UI.setLoading(submitButton, true);

            try {
                const formData = new FormData(form);
                const response = await utils.fetchJson(url, {
                    method: 'POST',
                    body: options.processData ? options.processData(formData) : formData
                });

                if (options.onSuccess && response.success) {
                    await options.onSuccess(response.data);
                } else if (options.onError && !response.success) {
                    await options.onError(response.error);
                }
            } catch (error) {
                console.error('Form submission error:', error);
                if (options.onError) {
                    await options.onError(error);
                }
            } finally {
                UI.setLoading(submitButton, false);
            }
        }
    };

    // 深色模式
    const DarkMode = {
        init() {
            const toggle = document.createElement('button');
            toggle.className = 'dark-mode-toggle';
            toggle.innerHTML = '🌓';
            document.body.appendChild(toggle);

            toggle.addEventListener('click', () => this.toggle());
            this.applyPreference();
        },

        toggle() {
            document.documentElement.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', 
                document.documentElement.classList.contains('dark-mode')
            );
        },

        applyPreference() {
            if (localStorage.getItem('darkMode') === 'true' || 
                window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark-mode');
            }
        }
    };

    // 初始化
    document.addEventListener('DOMContentLoaded', function() {
        // 初始化深色模式
        DarkMode.init();

        // 添加波纹效果
        document.querySelectorAll('.btn, .action-btn').forEach(button => {
            button.addEventListener('click', UI.createRipple);
        });

        // 初始化模态框
        document.querySelectorAll('.modal').forEach(modal => {
            const closeBtn = modal.querySelector('.close-button');
            closeBtn?.addEventListener('click', () => UI.hideModal(modal.id));
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) UI.hideModal(modal.id);
            });
        });

        // 初始化表单验证
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', e => {
                e.preventDefault();
                if (Forms.validateForm(form)) {
                    // 处理表单提交
                    const handler = form.dataset.handler;
                    if (handler && formHandlers[handler]) {
                        formHandlers[handler](form);
                    }
                }
            });
        });

        // 点击模态框外部关闭
        window.addEventListener('click', function(event) {
            if (event.target === passwordModal) {
                passwordModal.style.display = 'none';
            }
            if (event.target === rechargeModal) {
                rechargeModal.style.display = 'none';
            }
        });

        // 检查代码容器是否需要滚动
        function checkCodeScroll() {
            const preElement = document.querySelector('.result-container pre');
            if (preElement) {
                if (preElement.scrollWidth > preElement.clientWidth) {
                    preElement.classList.add('has-scroll');
                } else {
                    preElement.classList.remove('has-scroll');
                }
            }
        }

        // 在代码生成后调用
        function updateCodeDisplay(code) {
            const resultContainer = document.getElementById('result');
            if (resultContainer) {
                resultContainer.innerHTML = `<pre class="line-numbers"><code class="language-${getLanguageClass(language)}">${code}</code></pre>`;
                Prism.highlightAll();
                checkCodeScroll();
            }
        }

        // 监听窗口大小变化
        window.addEventListener('resize', checkCodeScroll);
    });

    // 处理题目解析按钮点击
    document.getElementById('analysis-btn')?.addEventListener('click', function() {
        const modal = document.getElementById('analysis-modal');
        if (!window.currentAnalysis) {
            alert('没有可用的题目解析');
            return;
        }
        
        // 填充解析内容
        document.getElementById('thinking-process').innerHTML = marked.parse(window.currentAnalysis.thinking_process || '');
        document.getElementById('solution-approach').innerHTML = marked.parse(window.currentAnalysis.solution_approach || '');
        document.getElementById('related-knowledge').innerHTML = marked.parse(window.currentAnalysis.related_knowledge || '');
        
        // 处理代码问题分析
        const bugsSection = document.getElementById('bugs-section');
        const codeBugs = document.getElementById('code-bugs');
        if (window.currentAnalysis.code_bugs) {
            bugsSection.style.display = 'block';
            codeBugs.innerHTML = marked.parse(window.currentAnalysis.code_bugs);
        } else {
            bugsSection.style.display = 'none';
        }
        
        modal.style.display = 'block';
    });

    // 处理在线运行按钮点击
    document.getElementById('run-code-btn')?.addEventListener('click', function() {
        const runContainer = document.getElementById('run-container');
        if (runContainer) {
            runContainer.style.display = 'block';
            // 隐藏验证容器
            const verifyContainer = document.getElementById('verify-container');
            if (verifyContainer) {
                verifyContainer.style.display = 'none';
            }
        }
    });

    // 处理立即验证按钮点击
    document.getElementById('verify-code-btn')?.addEventListener('click', function() {
        const verifyContainer = document.getElementById('verify-container');
        if (verifyContainer) {
            verifyContainer.style.display = 'block';
            // 隐藏运行容器
            const runContainer = document.getElementById('run-container');
            if (runContainer) {
                runContainer.style.display = 'none';
            }
        }
    });

    // 关闭按钮处理
    document.querySelectorAll('.close-button').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });

    // 点击模态框外部关闭
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });

    // 同步输入输出框高度
    function bindHeightSync(input, output) {
        const updateHeight = () => {
            const height = Math.max(input.scrollHeight, output.scrollHeight);
            input.style.height = `${height}px`;
            output.style.height = `${height}px`;
        };

        input.addEventListener('input', updateHeight);
        output.addEventListener('input', updateHeight);
    }

    // 初始化所有样例行的高度同步
    function initSampleHeightSync() {
        document.querySelectorAll('.sample-row').forEach(row => {
            const input = row.querySelector('.sample-input');
            const output = row.querySelector('.sample-output');
            if (input && output) {
                bindHeightSync(input, output);
            }
        });
    }

    // 修改验证结果显示函数
    function displayVerifyResults(results) {
        const tbody = document.getElementById('verify-results');
        if (!results || results.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">没有验证结果</td></tr>';
            return;
        }
        
        // 定义状态样式
        const statusStyles = {
            'AC': 'color: #2ecc71; font-weight: bold;', // 通过 - 绿色
            'WA': 'color: #e74c3c; font-weight: bold;', // 答案错误 - 红色
            'TLE': 'color: #f39c12; font-weight: bold;', // 超时 - 橙色
            'MLE': 'color: #f39c12; font-weight: bold;', // 内存超限 - 橙色
            'PE': 'color: #f1c40f; font-weight: bold;',  // 格式错误 - 黄色
            'RE': 'color: #e74c3c; font-weight: bold;'   // 运行时错误 - 红色
        };

        tbody.innerHTML = results.map(result => {
            const timeClass = result.time_exceeded ? 'exceeded' : 'normal';
            const memoryClass = result.memory_exceeded ? 'exceeded' : 'normal';
            
            return `
                <tr>
                    <td><div class="sample-cell">${formatText(result.input)}</div></td>
                    <td><div class="sample-cell">${formatText(result.expected)}</div></td>
                    <td><div class="sample-cell">${formatText(result.actual)}</div></td>
                    <td style="${statusStyles[result.status] || ''}">${result.status}</td>
                    <td><div class="sample-cell">${formatText(result.message)}</div></td>
                    <td class="performance ${timeClass}">
                        ${result.execution_time.toFixed(3)}ms
                        ${result.time_exceeded ? '（超时）' : ''}
                    </td>
                    <td class="performance ${memoryClass}">
                        ${formatMemory(result.memory_used)}
                        ${result.memory_exceeded ? '（超限）' : ''}
                    </td>
                </tr>
            `;
        }).join('');
    }

    // 添加格式化内存显示函数
    function formatMemory(memory) {
        if (memory >= 1024) {
            return `${(memory/1024).toFixed(2)}MB`;
        }
        return `${memory.toFixed(2)}KB`;
    }

    // 添加文本格式化函数
    function formatText(text) {
        if (!text) return '';
        return text
            .split('\n')
            .map(line => escapeHtml(line))
            .join('<br>');
    }

    // 修改显示历史记录的函数
    function displayHistoryItem(historyData) {
        // ... 其他代码 ...
        const pureCode = extractPureCode(historyData.generated_code);
        const codeElement = document.querySelector('#history-code-display');
        if (codeElement) {
            codeElement.textContent = pureCode;
            if (window.Prism) {
                Prism.highlightElement(codeElement);
            }
        }
        // ... 其他代码 ...
    }

    // 页面加载完成后初始化
    document.addEventListener('DOMContentLoaded', function() {
        initSampleHeightSync();
        
        // 绑定添加样例按钮
        document.getElementById('add-sample')?.addEventListener('click', addSampleRow);
    });

    // 解析功能相关代码
    const parseModal = document.getElementById('parse-modal');
    const parseProblemBtn = document.getElementById('parse-problem-btn');
    const closeParseBtn = parseModal?.querySelector('.close-button');
    const problemTextArea = document.getElementById('problem-text');
    const startParseBtn = document.getElementById('start-parse-btn');
    const clearParseBtn = document.getElementById('clear-parse-btn');
    const parseMessage = document.getElementById('parse-message');

    // 标签页切换
    const parseTabs = document.querySelectorAll('.parse-tab');
    const parsePanels = document.querySelectorAll('.parse-panel');
    
    parseTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const targetPanel = tab.dataset.tab;
            
            // 更新标签页状态
            parseTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            // 更新面板显示
            parsePanels.forEach(panel => {
                panel.classList.remove('active');
                if (panel.id === `${targetPanel}-panel`) {
                    panel.classList.add('active');
                }
            });
            
            // 重置消息
            parseMessage.textContent = '';
        });
    });

    // 图片上传相关功能
    const uploadArea = document.getElementById('upload-area');
    const imageInput = document.getElementById('image-input');
    const previewArea = document.getElementById('preview-area');
    const previewImage = document.getElementById('preview-image');
    const removeImageBtn = document.getElementById('remove-image');

    // 点击上传
    uploadArea?.addEventListener('click', () => {
        imageInput.click();
    });

    // 拖拽上传
    uploadArea?.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea?.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });

    uploadArea?.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });

    // 文件选择处理
    imageInput?.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    // 粘贴上传
    document.addEventListener('paste', (e) => {
        const activePanel = document.querySelector('.parse-panel.active');
        if (activePanel?.id === 'image-panel') {
            const items = e.clipboardData.items;
            for (let item of items) {
                if (item.type.indexOf('image') !== -1) {
                    const file = item.getAsFile();
                    handleFiles([file]);
                    break;
                }
            }
        }
    });

    // 移除图片
    removeImageBtn?.addEventListener('click', () => {
        previewArea.style.display = 'none';
        uploadArea.style.display = 'block';
        imageInput.value = '';
        document.getElementById('image-url').value = '';
    });

    // 文件处理函数
    function handleFiles(files) {
        const file = files[0];
        if (!file) return;

        // 验证文件类型和大小
        if (!file.type.match('image.*')) {
            alert('请上传图片文件');
            return;
        }

        // 限制为5MB
        if (file.size > 5 * 1024 * 1024) {
            alert('图片大小不能超过5MB');
            return;
        }

        // 创建图片对象以检查尺寸和优化
        const img = new Image();
        img.onload = function() {
            // 限制最大尺寸为2000x2000
            if (this.width > 2000 || this.height > 2000) {
                alert('图片尺寸不能超过2000x2000像素');
                return;
            }

            // 创建canvas进行图片优化
            const canvas = document.createElement('canvas');
            let width = this.width;
            let height = this.height;

            // 如果图片太大，进行等比缩放
            const maxSize = 1200;
            if (width > maxSize || height > maxSize) {
                if (width > height) {
                    height = Math.round(height * maxSize / width);
                    width = maxSize;
                } else {
                    width = Math.round(width * maxSize / height);
                    height = maxSize;
                }
            }

            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(this, 0, 0, width, height);

            // 转换为Blob
            canvas.toBlob(async (blob) => {
                // 创建FormData
                const formData = new FormData();
                formData.append('image', blob, 'problem.jpg');

                try {
                    // 上传图片到服务器
                    const response = await fetch('upload_image.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (!result.success) {
                        throw new Error(result.message);
                    }

                    // 保存图片URL到隐藏字段
                    const imageUrlInput = document.getElementById('image-url');
                    imageUrlInput.value = result.url;

                    // 预览图片
                    previewImage.src = URL.createObjectURL(blob);
                    uploadArea.style.display = 'none';
                    previewArea.style.display = 'block';
                } catch (error) {
                    alert('图片上传失败：' + error.message);
                }
            }, 'image/jpeg', 0.8);
        };

        // 读取文件
        const reader = new FileReader();
        reader.onload = (e) => {
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    // 开始解析按钮处理
    startParseBtn?.addEventListener('click', async function() {
        const activePanel = document.querySelector('.parse-panel.active');
        let content = '';
        let type = '';
        
        try {
            // 添加加载状态
            this.classList.add('loading');
            this.disabled = true;
            parseMessage.textContent = '正在解析...';
            parseMessage.className = '';
            
            // 根据当前面板获取内容
            switch (activePanel.id) {
                case 'text-panel':
                    content = document.getElementById('problem-text').value.trim();
                    if (!content) {
                        throw new Error('请输入题目内容');
                    }
                    type = 'text';
                    break;
                    
                case 'link-panel':
                    content = document.getElementById('problem-link').value.trim();
                    if (!content) {
                        throw new Error('请输入题目链接');
                    }
                    if (!content.startsWith('http://') && !content.startsWith('https://')) {
                        throw new Error('请输入有效的网址');
                    }
                    type = 'link';
                    break;
                    
                case 'image-panel':
                    const imageUrl = document.getElementById('image-url')?.value;
                    if (!imageUrl) {
                        throw new Error('请上传题目图片');
                    }
                    content = imageUrl;
                    type = 'image';
                    break;
                    
                default:
                    throw new Error('未知的解析类型');
            }

            // 发送解析请求
            const response = await fetch('parse_problem.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ type, content })
            });
            
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message);
            }

            // 填充表单
            fillFormWithParsedData(result.data);
            
            // 显示成功消息
            parseMessage.textContent = '解析成功！已自动填充表单';
            parseMessage.className = 'success';
            
            // 延迟关闭模态框
            setTimeout(() => {
                parseModal.style.display = 'none';
                resetParseForm();
            }, 1500);

        } catch (error) {
            parseMessage.textContent = error.message;
            parseMessage.className = 'error';
        } finally {
            // 移除加载状态
            this.classList.remove('loading');
            this.disabled = false;
        }
    });

    // 清空按钮
    clearParseBtn?.addEventListener('click', function() {
        resetParseForm();
    });

    // 关闭模态框
    closeParseBtn?.addEventListener('click', function() {
        parseModal.style.display = 'none';
        resetParseForm();
    });

    // 打开模态框
    parseProblemBtn?.addEventListener('click', function() {
        parseModal.style.display = 'block';
    });

    // 重置解析表单
    function resetParseForm() {
        document.getElementById('problem-text').value = '';
        document.getElementById('problem-link').value = '';
        document.getElementById('image-url').value = '';
        const previewArea = document.getElementById('preview-area');
        const uploadArea = document.getElementById('upload-area');
        if (previewArea && uploadArea) {
            previewArea.style.display = 'none';
            uploadArea.style.display = 'block';
        }
        const imageInput = document.getElementById('image-input');
        if (imageInput) {
            imageInput.value = '';
        }
        const parseMessage = document.getElementById('parse-message');
        if (parseMessage) {
            parseMessage.textContent = '';
            parseMessage.className = '';
        }
    }

    // 填充表单数据
    function fillFormWithParsedData(data) {
        document.getElementById('title').value = data.title.replace('题目描述：\n', '');
        document.getElementById('input_content').value = data.input_content || '';
        document.getElementById('output_content').value = data.output_content || '';
        
        // 填充样例
        const samplesContainer = document.getElementById('samples-container');
        samplesContainer.innerHTML = '';
        
        if (data.samples && data.samples.length > 0) {
            data.samples.forEach((sample, index) => {
                const row = document.createElement('div');
                row.className = 'sample-row';
                row.innerHTML = `
                    <textarea name="sample_input[]" class="sample-input">${sample.input || ''}</textarea>
                    <textarea name="sample_output[]" class="sample-output">${sample.output || ''}</textarea>
                    <button type="button" class="remove-sample"${index === 0 ? ' style="display:none;"' : ''}>-</button>
                `;
                samplesContainer.appendChild(row);
            });
        } else {
            // 添加一个空的样例行
            const emptyRow = document.createElement('div');
            emptyRow.className = 'sample-row';
            emptyRow.innerHTML = `
                <textarea name="sample_input[]" class="sample-input"></textarea>
                <textarea name="sample_output[]" class="sample-output"></textarea>
                <button type="button" class="remove-sample" style="display:none;">-</button>
            `;
            samplesContainer.appendChild(emptyRow);
        }
        
        // 填充其他字段
        if (data.time_limit) {
            document.getElementById('time_limit').value = data.time_limit;
        }
        if (data.memory_limit) {
            document.getElementById('memory_limit').value = data.memory_limit;
        }
        if (data.user_code) {
            document.getElementById('user_code').value = data.user_code;
        }
        if (data.additional_requirements) {
            document.getElementById('additional_requirements').value = data.additional_requirements;
        }
    }

    // 添加 showMessage 函数定义
    function showMessage(message, type = 'info') {
        const resultElement = document.getElementById('result');
        if (resultElement) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.textContent = message;
            resultElement.insertAdjacentElement('beforebegin', messageDiv);
            
            // 3秒后自动移除消息
            setTimeout(() => {
                messageDiv.remove();
            }, 3000);
        }
    }

    // 修改 handleGenerateResponse 函数
    async function handleGenerateResponse(response) {
        try {
            const data = await response.json();
            if (data.success) {
                // 保存解析数据到全局变量
                window.currentAnalysis = {
                    thinking_process: data.thinking_process,
                    solution_approach: data.solution_approach,
                    related_knowledge: data.related_knowledge,
                    code_bugs: data.code_bugs
                };
                
                // 获取当前语言
                const languageSelect = document.getElementById('language');
                const currentLanguage = languageSelect.value === 'other' ? 
                    document.getElementById('other_language').value : 
                    languageSelect.value;
                
                // 显示代码
                const resultElement = document.getElementById('result');
                resultElement.innerHTML = `<pre><code class="language-${currentLanguage}">${escapeHtml(data.code)}</code></pre>`;
                if (window.Prism) {
                    Prism.highlightElement(resultElement.querySelector('code'));
                }
                
                // 更新积分显示
                const pointsElement = document.querySelector('.points');
                if (pointsElement) {
                    const currentPoints = parseInt(pointsElement.textContent.match(/\d+/)[0]);
                    pointsElement.textContent = `剩余积分: ${currentPoints - data.points_consumed}`;
                }
                
                showMessage('代码生成成功!', 'success');
            } else {
                showMessage(data.message || '生成失败', 'error');
            }
        } catch (error) {
            console.error('处理响应时出错:', error);
            showMessage('处理响应时出错', 'error');
        }
    }
}); 