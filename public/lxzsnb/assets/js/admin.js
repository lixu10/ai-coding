// 首先在全局作用域定义这些函数
window.loadAdmins = function() {
    console.log('加载管理员列表...');
    fetch('./api/admins.php?action=list')
        .then(response => response.json())
        .then(data => {
            console.log('管理员数据:', data);
            if (data.success) {
                const tbody = document.querySelector('#admins-table tbody');
                if (!tbody) {
                    console.error('找不到管理员表格tbody元素');
                    return;
                }
                tbody.innerHTML = data.data.map(admin => `
                    <tr>
                        <td>${admin.id}</td>
                        <td>${escapeHtml(admin.username)}</td>
                        <td>${new Date(admin.created_at).toLocaleString()}</td>
                        <td>
                            ${admin.id != currentAdminId ? `
                                <button onclick="deleteAdmin(${admin.id})" class="action-btn delete-btn">
                                    删除
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `).join('');
            } else {
                console.error('加载管理员失败:', data.message);
            }
        })
        .catch(error => {
            console.error('加载管理员时出错:', error);
        });
};

window.loadModels = function() {
    console.log('加载模型列表...');
    fetch('./api/models.php?action=list')
        .then(response => response.json())
        .then(data => {
            console.log('模型数据:', data);
            if (data.success) {
                const tbody = document.querySelector('#models-table tbody');
                if (!tbody) {
                    console.error('找不到模型表格tbody元素');
                    return;
                }
                tbody.innerHTML = data.data.map(model => `
                    <tr>
                        <td>${model.id}</td>
                        <td>${escapeHtml(model.name)}</td>
                        <td>
                            <input type="number" value="${model.sort_order}" 
                                onchange="updateModelSort(${model.id}, this.value)">
                        </td>
                        <td>
                            <input type="number" value="${model.points_consumption}" 
                                onchange="updateModelPoints(${model.id}, this.value)">
                        </td>
                        <td>${new Date(model.created_at).toLocaleString()}</td>
                        <td>
                            <button onclick="deleteModel(${model.id})" class="action-btn delete-btn">
                                删除
                            </button>
                        </td>
                    </tr>
                `).join('');
            } else {
                console.error('加载模型失败:', data.message);
            }
        })
        .catch(error => {
            console.error('加载模型时出错:', error);
        });
};

document.addEventListener('DOMContentLoaded', function() {
    // 页面切换
    document.querySelectorAll('[data-page]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const pageId = this.dataset.page;
            document.querySelectorAll('.admin-page').forEach(page => {
                page.style.display = 'none';
            });
            document.getElementById(pageId + '-page').style.display = 'block';
            loadPageData(pageId);
        });
    });

    // 退出登录
    document.getElementById('admin-logout')?.addEventListener('click', function(e) {
        e.preventDefault();
        fetch('api/logout.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'index.php';
                }
            });
    });

    // 加载页面数据
    function loadPageData(page) {
        console.log('加载页面:', page);
        switch(page) {
            case 'users':
                loadUsers();
                break;
            case 'records':
                loadRecords();
                break;
            case 'admins':
                window.loadAdmins();
                break;
            case 'models':
                window.loadModels();
                break;
            case 'recharge-codes':
                loadRechargeCodes();
                break;
        }
    }

    // 加载用户列表
    function loadUsers() {
        fetch('api/users.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.querySelector('#users-table tbody');
                    tbody.innerHTML = data.data.map(user => `
                        <tr>
                            <td>${user.id}</td>
                            <td>${escapeHtml(user.username)}</td>
                            <td>
                                <input type="number" value="${user.points}" 
                                    onchange="updatePoints(${user.id}, this.value)">
                            </td>
                            <td>${user.status}</td>
                            <td>${new Date(user.created_at).toLocaleString()}</td>
                            <td>
                                <button onclick="toggleUserStatus(${user.id}, '${user.status}')" 
                                    class="action-btn ${user.status === 'active' ? 'ban-btn' : 'edit-btn'}">
                                    ${user.status === 'active' ? '封禁' : '解封'}
                                </button>
                                <button onclick="resetPassword(${user.id})" class="action-btn edit-btn">
                                    重置密码
                                </button>
                            </td>
                        </tr>
                    `).join('');
                }
            });
    }

    // 加载记录列表
    function loadRecords() {
        fetch('api/records.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.querySelector('#records-table tbody');
                    tbody.innerHTML = data.data.map(record => `
                        <tr>
                            <td>${record.id}</td>
                            <td>${escapeHtml(record.username)}</td>
                            <td>${escapeHtml(record.title)}</td>
                            <td>${escapeHtml(record.language)}</td>
                            <td>${new Date(record.created_at).toLocaleString()}</td>
                            <td>
                                <button onclick="viewRecord(${record.id})" class="action-btn edit-btn">
                                    查看
                                </button>
                                <button onclick="deleteRecord(${record.id})" class="action-btn delete-btn">
                                    删除
                                </button>
                            </td>
                        </tr>
                    `).join('');
                }
            });
    }

    // 用户相关操作
    window.updatePoints = function(userId, points) {
        fetch('api/users.php?action=update', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${userId}&points=${points}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('积分更新成功');
            }
        });
    };

    window.toggleUserStatus = function(userId, currentStatus) {
        if (!confirm(`确定要${currentStatus === 'active' ? '封禁' : '解封'}该用户吗？`)) return;
        
        fetch('api/users.php?action=ban', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${userId}&status=${currentStatus}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadUsers();
            }
        });
    };

    window.resetPassword = function(userId) {
        const newPassword = prompt('请输入新密码（至少6个字符）：');
        if (!newPassword || newPassword.length < 6) {
            alert('密码至少需要6个字符！');
            return;
        }

        fetch('api/users.php?action=reset_password', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${userId}&password=${encodeURIComponent(newPassword)}`
        })
        .then(response => response.json())
        .then(data => {
            alert(data.success ? '密码重置成功' : data.message);
        });
    };

    // 记录相关操作
    window.viewRecord = function(recordId) {
        fetch(`api/records.php?action=detail&id=${recordId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const record = data.data;
                    const content = `
                        <div class="record-detail">
                            <h3>详细信息</h3>
                            <p><strong>标题：</strong>${escapeHtml(record.title)}</p>
                            <p><strong>用户：</strong>${escapeHtml(record.username)}</p>
                            <p><strong>语言：</strong>${escapeHtml(record.language)}</p>
                            <p><strong>输入说明：</strong>${escapeHtml(record.input_content)}</p>
                            <p><strong>输出说明：</strong>${escapeHtml(record.output_content)}</p>
                            <p><strong>时间限制：</strong>${record.time_limit}ms</p>
                            <p><strong>内存限制：</strong>${record.memory_limit}kb</p>
                            <h4>生成的代码：</h4>
                            <pre><code>${escapeHtml(record.generated_code)}</code></pre>
                        </div>
                    `;
                    // 使用模态框显示内容
                    showModal(content);
                }
            });
    };

    window.deleteRecord = function(recordId) {
        if (!confirm('确定要删除这条记录吗？')) return;

        fetch('api/records.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${recordId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadRecords();
            }
        });
    };

    // 添加模型
    document.getElementById('add-model-btn')?.addEventListener('click', function() {
        const name = prompt('请输入模型名称：');
        if (!name) return;

        const sort_order = prompt('请输入排序值（数字越大越靠前）：', '0');
        if (sort_order === null) return;

        const points_consumption = prompt('请输入积分消耗：', '10');
        if (points_consumption === null) return;

        fetch('api/models.php?action=add', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `name=${encodeURIComponent(name)}&sort_order=${sort_order}&points_consumption=${points_consumption}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadModels();
            } else {
                alert(data.message || '添加失败');
            }
        });
    });

    // 更新模型排序
    window.updateModelSort = function(id, sort_order) {
        fetch('./api/models.php?action=update', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${id}&sort_order=${sort_order}`
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('更新失败');
                loadModels();
            }
        });
    };

    // 更新模型积分消耗
    window.updateModelPoints = function(id, points) {
        fetch('./api/models.php?action=update', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${id}&points_consumption=${points}`
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('更新失败');
                loadModels();
            }
        });
    };

    // 删除模型
    window.deleteModel = function(id) {
        if (!confirm('确定要删除这个模型吗？')) return;

        fetch('./api/models.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadModels();
            } else {
                alert(data.message || '删除失败');
            }
        });
    };

    // 确保 escapeHtml 函数在全局可用
    window.escapeHtml = function(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    };

    // 初始加载用户列表
    loadUsers();

    // 处理登录表单
    const loginForm = document.getElementById('admin-login-form');
    const loginMessage = document.getElementById('login-message');

    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            fetch('api/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username, password })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    loginMessage.textContent = data.message || '登录失败';
                }
            })
            .catch(error => {
                loginMessage.textContent = '系统错误，请稍后再试';
            });
        });
    }

    // 修改其他 API 调用的路径
    window.deleteAdmin = function(id) {
        if (!confirm('确定要删除这个管理员吗？')) return;
        fetch('./api/admins.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadAdmins();
            } else {
                alert(data.message || '删除失败');
            }
        });
    };

    // 在现有代码中添加充值码管理功能
    function loadRechargeCodes() {
        console.log('加载充值码列表...');
        fetch('api/recharge_codes.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.querySelector('#recharge-codes-table tbody');
                    if (!tbody) {
                        console.error('找不到充值码表格tbody元素');
                        return;
                    }
                    tbody.innerHTML = data.data.map(code => `
                        <tr>
                            <td>${escapeHtml(code.code)}</td>
                            <td>${code.points}</td>
                            <td>${code.remaining_uses}</td>
                            <td>${code.total_uses}</td>
                            <td>${new Date(code.created_at).toLocaleString()}</td>
                            <td>${escapeHtml(code.creator_name || '')}</td>
                        </tr>
                    `).join('');
                } else {
                    console.error('加载充值码失败:', data.message);
                }
            })
            .catch(error => {
                console.error('加载充值码时出错:', error);
            });
    }

    // 生成充值码
    document.getElementById('generate-codes-btn')?.addEventListener('click', function() {
        const count = document.getElementById('generate-count').value;
        const points = document.getElementById('points-amount').value;
        const uses = document.getElementById('use-times').value;

        const formData = new FormData();
        formData.append('count', count);
        formData.append('points', points);
        formData.append('uses', uses);

        fetch('api/recharge_codes.php?action=generate', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message + '\n\n生成的充值码：\n' + data.codes.join('\n'));
                loadRechargeCodes();
            } else {
                alert(data.message || '生成失败');
            }
        })
        .catch(error => {
            console.error('生成充值码时出错:', error);
            alert('生成失败，请稍后重试');
        });
    });

    // 在现有代码中添加充值记录相关功能
    function loadRechargeRecords() {
        console.log('加载充值记录...');
        fetch('api/recharge_codes.php?action=records')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.querySelector('#recharge-records-table tbody');
                    if (!tbody) {
                        console.error('找不到充值记录表格tbody元素');
                        return;
                    }
                    tbody.innerHTML = data.data.map(record => `
                        <tr>
                            <td>${escapeHtml(record.username)}</td>
                            <td>${escapeHtml(record.code)}</td>
                            <td>${record.points}</td>
                            <td>${new Date(record.created_at).toLocaleString()}</td>
                        </tr>
                    `).join('');
                } else {
                    console.error('加载充值记录失败:', data.message);
                }
            })
            .catch(error => {
                console.error('加载充值记录时出错:', error);
            });
    }

    // 标签页切换
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // 更新按钮状态
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // 更新内容显示
            const tabId = this.dataset.tab;
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');
            
            // 加载相应的数据
            if (tabId === 'recharge-records') {
                loadRechargeRecords();
            } else {
                loadRechargeCodes();
            }
        });
    });
}); 