// API基礎URL
const API_BASE_URL = 'http://localhost/restaurant-queue-system/api-proxy.php/api';

// 存儲JWT令牌
let authToken = localStorage.getItem('authToken');
let currentUser = JSON.parse(localStorage.getItem('currentUser') || 'null');

// 當頁面加載完成時執行
document.addEventListener('DOMContentLoaded', function() {
    // 初始化頁面
    refreshQueueStatus();
    setInterval(refreshQueueStatus, 30000); // 每30秒刷新一次候位狀態
    
    // 設置當前日期為默認值
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('record-date').value = today;
    document.getElementById('stats-start-date').value = today;
    document.getElementById('stats-end-date').value = today;
    
    // 動態創建輸入框，避免瀏覽器自動填充
    const searchContainer = document.getElementById('ticket-search-container');
    const checkButton = document.getElementById('check-ticket');
    
    // 創建輸入框
    const input = document.createElement('input');
    input.type = 'search';
    input.className = 'form-control';
    input.id = 'ticket-id';
    input.placeholder = '請輸入候位號碼';
    input.autocomplete = 'new-password'; // 使用 new-password 更能避免自動填充
    input.name = 'ticket-search-' + Math.random().toString(36).substring(2, 15); // 隨機名稱避免自動填充
    
    // 將輸入框插入到按鈕前面
    searchContainer.insertBefore(input, checkButton);
    
    // 如果已登入，顯示員工控制面板
    if (authToken) {
        document.getElementById('login-form').classList.add('d-none');
        document.getElementById('onsite-queue-form').classList.remove('d-none');
        document.getElementById('staff-controls').classList.remove('d-none');
        document.getElementById('admin-panel').classList.remove('d-none');
        
        // 顯示登出按鈕
        document.getElementById('logout-btn').classList.remove('d-none');
        
        // 刷新員工頁面數據
        refreshStaffData();
    }
    
    // 綁定事件處理函數
    bindEventHandlers();
});

// 刷新候位狀態
function refreshQueueStatus() {
    fetch(`${API_BASE_URL}/queue/status`)
        .then(response => response.json())
        .then(data => {
            // 更新顯示
            document.getElementById('current-small').textContent = data.current_number_small;
            document.getElementById('current-medium').textContent = data.current_number_medium;
            document.getElementById('current-large').textContent = data.current_number_large;
            
            document.getElementById('waiting-small').textContent = data.waiting_count_small;
            document.getElementById('waiting-medium').textContent = data.waiting_count_medium;
            document.getElementById('waiting-large').textContent = data.waiting_count_large;
            
            // 如果已登入，也更新員工頁面
            if (authToken) {
                document.getElementById('staff-current-small').textContent = data.current_number_small;
                document.getElementById('staff-current-medium').textContent = data.current_number_medium;
                document.getElementById('staff-current-large').textContent = data.current_number_large;
                
                document.getElementById('staff-waiting-small').textContent = data.waiting_count_small;
                document.getElementById('staff-waiting-medium').textContent = data.waiting_count_medium;
                document.getElementById('staff-waiting-large').textContent = data.waiting_count_large;
            }
        })
        .catch(error => {
            console.error('獲取候位狀態失敗:', error);
        });
}

// 刷新員工頁面數據
function refreshStaffData() {
    // 獲取等待中的客人列表
    fetch(`${API_BASE_URL}/records?status=waiting`, {
        headers: {
            'Authorization': `Bearer ${authToken}`
        }
    })
    .then(response => response.json())
    .then(data => {
        if (Array.isArray(data)) {
            const tableBody = document.querySelector('#waiting-tickets-table tbody');
            tableBody.innerHTML = '';
            
            data.forEach(ticket => {
                const row = document.createElement('tr');
                
                // 根據桌型設置不同的背景色
                let tableTypeText = '';
                let badgeClass = '';
                switch (ticket.table_type_id) {
                    case 1:
                        tableTypeText = '小桌 (1-2人)';
                        badgeClass = 'bg-primary';
                        break;
                    case 2:
                        tableTypeText = '中桌 (3-4人)';
                        badgeClass = 'bg-success';
                        break;
                    case 3:
                        tableTypeText = '大桌 (5人以上)';
                        badgeClass = 'bg-warning text-dark';
                        break;
                }
                
                row.innerHTML = `
                    <td>${ticket.ticket_number}</td>
                    <td><span class="badge ${badgeClass}">${tableTypeText}</span></td>
                    <td>${ticket.party_size}</td>
                    <td>${ticket.phone_number}</td>
                    <td>${formatDateTime(ticket.queue_time)}</td>
                    <td>
                        <button class="btn btn-sm btn-success seat-btn" data-id="${ticket.id}">入座</button>
                        <button class="btn btn-sm btn-danger no-show-btn" data-id="${ticket.id}">未到</button>
                    </td>
                `;
                
                tableBody.appendChild(row);
            });
            
            // 綁定按鈕事件
            document.querySelectorAll('.seat-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    updateTicketStatus(this.dataset.id, 'seated');
                });
            });
            
            document.querySelectorAll('.no-show-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    updateTicketStatus(this.dataset.id, 'no_show');
                });
            });
        }
    })
    .catch(error => {
        console.error('獲取等待中的客人列表失敗:', error);
    });
}

// 更新候位票狀態
function updateTicketStatus(ticketId, status) {
    fetch(`${API_BASE_URL}/queue/ticket/${ticketId}/status`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${authToken}`
        },
        body: JSON.stringify({ status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert('更新失敗: ' + data.error);
        } else {
            alert('更新成功');
            refreshStaffData();
            refreshQueueStatus();
        }
    })
    .catch(error => {
        console.error('更新候位票狀態失敗:', error);
        alert('更新失敗，請稍後再試');
    });
}

// 綁定事件處理函數
function bindEventHandlers() {
    // 查詢候位票
    document.getElementById('check-ticket').addEventListener('click', function() {
        const ticketId = document.getElementById('ticket-id').value;
        if (!ticketId) {
            alert('請輸入候位號碼ID');
            return;
        }
        
        fetch(`${API_BASE_URL}/queue/ticket/${ticketId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('查詢失敗: ' + data.error);
                    return;
                }
                
                // 顯示候位詳情
                document.getElementById('ticket-details').classList.remove('d-none');
                document.getElementById('ticket-number').textContent = data.ticket_number;
                
                let tableType = '';
                switch (data.table_type_id) {
                    case 1:
                        tableType = '小桌 (1-2人)';
                        break;
                    case 2:
                        tableType = '中桌 (3-4人)';
                        break;
                    case 3:
                        tableType = '大桌 (5人以上)';
                        break;
                }
                
                document.getElementById('table-type').textContent = tableType;
                document.getElementById('party-size').textContent = data.party_size + '人';
                
                let status = '';
                switch (data.status) {
                    case 'waiting':
                        status = '等待中';
                        break;
                    case 'seated':
                        status = '已入座';
                        break;
                    case 'no_show':
                        status = '未到';
                        break;
                    case 'cancelled':
                        status = '已取消';
                        break;
                }
                
                document.getElementById('ticket-status').textContent = status;
                document.getElementById('queue-time').textContent = formatDateTime(data.queue_time);
            })
            .catch(error => {
                console.error('查詢候位票失敗:', error);
                alert('查詢失敗，請稍後再試');
            });
    });
    
    // 遠端取號 - 請求驗證碼
    document.getElementById('request-verification-btn').addEventListener('click', function() {
        const phoneNumber = document.getElementById('remote-phone-number').value;
        if (!phoneNumber) {
            alert('請輸入手機號碼');
            return;
        }
        
        fetch(`${API_BASE_URL}/verification/request`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ phone_number: phoneNumber })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(`錯誤: ${data.error}`);
            } else {
                document.getElementById('step1').style.display = 'block';
                document.getElementById('step2').classList.remove('d-none');
                document.getElementById('code-sent-alert').classList.remove('d-none');
                // 顯示驗證碼在彈窗中，方便測試
                alert(`驗證碼: ${data.code}\n有效期至: ${data.expires_at}`);
                // 自動填入驗證碼，方便測試
                document.getElementById('verification-code').value = data.code;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('發送驗證碼時出錯');
        });
    });
    
    // 遠端取號 - 驗證驗證碼
    document.getElementById('verify-code').addEventListener('click', function() {
        const phoneNumber = document.getElementById('remote-phone-number').value;
        const code = document.getElementById('verification-code').value;
        
        if (!phoneNumber || !code) {
            alert('請輸入手機號碼和驗證碼');
            return;
        }
        
        fetch(`${API_BASE_URL}/verification/verify`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                phone_number: phoneNumber,
                code: code
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('code-error-alert').classList.remove('d-none');
                alert(`驗證失敗: ${data.error}`);
                return;
            }
            
            if (data.verified) {
                document.getElementById('code-error-alert').classList.add('d-none');
                document.getElementById('step3').classList.remove('d-none');
                alert('驗證成功！請繼續選擇用餐人數');
            } else {
                document.getElementById('code-error-alert').classList.remove('d-none');
                alert('驗證碼無效或已過期');
            }
        })
        .catch(error => {
            console.error('驗證碼驗證失敗:', error);
            alert('驗證失敗，請稍後再試');
        });
    });
    
    // 遠端取號 - 創建候位票
    document.getElementById('create-remote-ticket').addEventListener('click', function() {
        const phoneNumber = document.getElementById('remote-phone-number').value;
        const code = document.getElementById('verification-code').value;
        const partySize = document.getElementById('party-size-select').value;
        
        fetch(`${API_BASE_URL}/queue/remote`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                phone_number: phoneNumber,
                verification_code: code,
                party_size: parseInt(partySize)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('取號失敗: ' + data.error);
                return;
            }
            
            // 顯示取號成功
            document.getElementById('step3').classList.add('d-none');
            document.getElementById('step4').classList.remove('d-none');
            document.getElementById('remote-ticket-number').textContent = data.ticket_number;
            
            // 獲取當前叫號和等待人數
            let currentNumber = 0;
            let waitingCount = data.waiting_count_at_creation;
            
            switch (data.table_type_id) {
                case 1:
                    currentNumber = document.getElementById('current-small').textContent;
                    break;
                case 2:
                    currentNumber = document.getElementById('current-medium').textContent;
                    break;
                case 3:
                    currentNumber = document.getElementById('current-large').textContent;
                    break;
            }
            
            document.getElementById('remote-current-number').textContent = currentNumber;
            document.getElementById('remote-waiting-count').textContent = waitingCount;
            
            // 刷新候位狀態
            refreshQueueStatus();
        })
        .catch(error => {
            console.error('創建候位票失敗:', error);
            alert('取號失敗，請稍後再試');
        });
    });
    
    // 遠端取號 - 返回
    document.getElementById('back-to-start').addEventListener('click', function() {
        // 隱藏所有步驟，只顯示步驟1
        document.getElementById('step1').classList.remove('d-none');
        document.getElementById('step2').classList.add('d-none');
        document.getElementById('step3').classList.add('d-none');
        document.getElementById('step4').classList.add('d-none');
        
        // 清空輸入欄位
        document.getElementById('remote-phone-number').value = '';
        document.getElementById('verification-code').value = '';
        
        // 切換到候位狀態頁面
        document.getElementById('status-tab').click();
    });
    
    // 登入表單提交
    document.getElementById('login-form').addEventListener('submit', function(event) {
        event.preventDefault();
        
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        fetch(`${API_BASE_URL}/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username, password })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('登入失敗: ' + data.error);
                return;
            }
            
            // 存儲令牌和用戶信息
            authToken = data.token;
            currentUser = data.employee;
            localStorage.setItem('authToken', authToken);
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            
            // 隱藏登入表單，顯示員工控制面板
            document.getElementById('login-form').classList.add('d-none');
            document.getElementById('onsite-queue-form').classList.remove('d-none');
            document.getElementById('staff-controls').classList.remove('d-none');
            document.getElementById('admin-panel').classList.remove('d-none');
            
            // 刷新員工頁面數據
            refreshStaffData();
        })
        .catch(error => {
            console.error('登入失敗:', error);
            alert('登入失敗，請稍後再試');
        });
    });
    
    // 叫號按鈕事件處理
    document.getElementById('call-next-small').addEventListener('click', function() {
        callNextCustomer(1);
    });
    
    document.getElementById('call-next-medium').addEventListener('click', function() {
        callNextCustomer(2);
    });
    
    document.getElementById('call-next-large').addEventListener('click', function() {
        callNextCustomer(3);
    });
    
    // 現場取號
    document.getElementById('create-onsite-ticket').addEventListener('click', function() {
        const phoneNumber = document.getElementById('onsite-phone').value;
        const partySize = document.getElementById('onsite-party-size').value;
        
        if (!phoneNumber) {
            alert('請輸入客戶手機號碼');
            return;
        }
        
        fetch(`${API_BASE_URL}/queue/onsite`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${authToken}`
            },
            body: JSON.stringify({
                phone_number: phoneNumber,
                party_size: parseInt(partySize)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('取號失敗: ' + data.error);
                return;
            }
            
            // 顯示取號成功
            document.getElementById('onsite-success').classList.remove('d-none');
            document.getElementById('onsite-ticket-number').textContent = data.ticket_number;
            
            // 清空輸入
            document.getElementById('onsite-phone').value = '';
            
            // 刷新候位狀態和員工頁面數據
            refreshQueueStatus();
            refreshStaffData();
            
            // 3秒後隱藏成功提示
            setTimeout(function() {
                document.getElementById('onsite-success').classList.add('d-none');
            }, 3000);
        })
        .catch(error => {
            console.error('創建候位票失敗:', error);
            alert('取號失敗，請稍後再試');
        });
    });
    
    // 叫號按鈕事件處理已在上方定義
    
    // 查詢候位記錄
    document.getElementById('search-records').addEventListener('click', function() {
        const date = document.getElementById('record-date').value;
        const status = document.getElementById('record-status').value;
        
        let url = `${API_BASE_URL}/records?date=${date}`;
        if (status) {
            url += `&status=${status}`;
        }
        
        fetch(url, {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('查詢失敗: ' + data.error);
                return;
            }
            
            const tableBody = document.querySelector('#records-table tbody');
            tableBody.innerHTML = '';
            
            if (Array.isArray(data)) {
                data.forEach(ticket => {
                    const row = document.createElement('tr');
                    
                    let tableTypeText = '';
                    switch (ticket.table_type_id) {
                        case 1:
                            tableTypeText = '小桌 (1-2人)';
                            break;
                        case 2:
                            tableTypeText = '中桌 (3-4人)';
                            break;
                        case 3:
                            tableTypeText = '大桌 (5人以上)';
                            break;
                    }
                    
                    let statusText = '';
                    switch (ticket.status) {
                        case 'waiting':
                            statusText = '等待中';
                            break;
                        case 'seated':
                            statusText = '已入座';
                            break;
                        case 'no_show':
                            statusText = '未到';
                            break;
                        case 'cancelled':
                            statusText = '已取消';
                            break;
                    }
                    
                    row.innerHTML = `
                        <td>${ticket.ticket_number}</td>
                        <td>${tableTypeText}</td>
                        <td>${ticket.party_size}</td>
                        <td>${ticket.phone_number}</td>
                        <td>${formatDateTime(ticket.queue_time)}</td>
                        <td>${ticket.seated_time ? formatDateTime(ticket.seated_time) : '-'}</td>
                        <td>${statusText}</td>
                        <td>${ticket.is_remote ? '遠端' : '現場'}</td>
                    `;
                    
                    tableBody.appendChild(row);
                });
            }
        })
        .catch(error => {
            console.error('查詢候位記錄失敗:', error);
            alert('查詢失敗，請稍後再試');
        });
    });
    
    // 刷新黑名單
    function refreshBlacklist() {
        fetch(`${API_BASE_URL}/blacklist`, {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('獲取黑名單失敗: ' + data.error);
                return;
            }
            
            const tableBody = document.querySelector('#blacklist-table tbody');
            tableBody.innerHTML = '';
            
            if (Array.isArray(data)) {
                data.forEach(customer => {
                    const row = document.createElement('tr');
                    
                    row.innerHTML = `
                        <td>${customer.id}</td>
                        <td>${customer.phone_number}</td>
                        <td>${customer.no_show_count}</td>
                        <td>${formatDateTime(customer.created_at)}</td>
                        <td>
                            <button class="btn btn-sm btn-warning remove-blacklist-btn" data-id="${customer.id}">移出黑名單</button>
                        </td>
                    `;
                    
                    tableBody.appendChild(row);
                });
                
                // 綁定移出黑名單按鈕事件
                document.querySelectorAll('.remove-blacklist-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        updateBlacklistStatus(this.dataset.id, false);
                    });
                });
            }
        })
        .catch(error => {
            console.error('獲取黑名單失敗:', error);
            alert('獲取失敗，請稍後再試');
        });
    }
    
    // 黑名單標籤點擊事件，自動刷新黑名單
    document.getElementById('blacklist-tab').addEventListener('click', function() {
        if (authToken) {
            refreshBlacklist();
        }
    });
    
    // 現場取號(員工)標籤點擊事件，自動刷新等待的客人列表
    document.getElementById('onsite-tab').addEventListener('click', function() {
        if (authToken) {
            refreshStaffData();
        }
    });
    
    // 候位狀態標籤點擊事件，清除輸入框的值
    document.getElementById('status-tab').addEventListener('click', function() {
        document.getElementById('ticket-id').value = '';
    });
    
    // 後台管理標籤點擊事件，檢查用戶角色
    document.getElementById('admin-tab').addEventListener('click', function(event) {
        // 如果沒有登入，不需要做任何事情，因為登入表單會顯示
        if (!authToken || !currentUser) {
            return;
        }
        
        // 檢查用戶角色是否為 admin
        if (currentUser.role !== 'admin') {
            // 防止頁面切換
            event.preventDefault();
            
            // 顯示提示信息
            alert('只有管理員可以訪問後台管理頁面\n請使用管理員帳號登入');
            
            // 執行登出操作
            // 清除本地存儲中的授權令牌和用戶信息
            localStorage.removeItem('authToken');
            localStorage.removeItem('currentUser');
            
            // 重置全局變量
            authToken = null;
            currentUser = null;
            
            // 隱藏員工控制面板
            document.getElementById('login-form').classList.remove('d-none');
            document.getElementById('onsite-queue-form').classList.add('d-none');
            document.getElementById('staff-controls').classList.add('d-none');
            document.getElementById('admin-panel').classList.add('d-none');
            
            // 隱藏登出按鈕
            document.getElementById('logout-btn').classList.add('d-none');
            
            // 清空登入表單
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';
            document.getElementById('login-error').classList.add('d-none');
            
            // 切換到候位狀態頁面
            document.getElementById('status-tab').click();
        }
    });
    
    // 登入按鈕點擊事件
    document.getElementById('login-btn').addEventListener('click', function() {
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        if (!username || !password) {
            alert('請輸入用戶名和密碼');
            return;
        }
        
        fetch(`${API_BASE_URL}/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                username: username,
                password: password
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('login-error').classList.remove('d-none');
                console.error('登入失敗:', data.error);
                return;
            }
            
            // 存儲授權令牌和用戶信息
            authToken = data.token;
            currentUser = data.employee;
            localStorage.setItem('authToken', authToken);
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            
            // 顯示員工控制面板
            document.getElementById('login-form').classList.add('d-none');
            document.getElementById('onsite-queue-form').classList.remove('d-none');
            document.getElementById('staff-controls').classList.remove('d-none');
            document.getElementById('admin-panel').classList.remove('d-none');
            
            // 顯示登出按鈕
            document.getElementById('logout-btn').classList.remove('d-none');
            
            // 刷新員工頁面數據
            refreshStaffData();
        })
        .catch(error => {
            console.error('登入失敗:', error);
            document.getElementById('login-error').classList.remove('d-none');
        });
    });
    
    // 刷新黑名單按鈕點擊事件
    document.getElementById('refresh-blacklist').addEventListener('click', function() {
        refreshBlacklist();
    });
    
    // 登出按鈕點擊事件
    document.getElementById('logout-btn').addEventListener('click', function() {
        // 清除本地存儲中的授權令牌和用戶信息
        localStorage.removeItem('authToken');
        localStorage.removeItem('currentUser');
        
        // 重置全局變量
        authToken = null;
        currentUser = null;
        
        // 隱藏員工控制面板
        document.getElementById('login-form').classList.remove('d-none');
        document.getElementById('onsite-queue-form').classList.add('d-none');
        document.getElementById('staff-controls').classList.add('d-none');
        document.getElementById('admin-panel').classList.add('d-none');
        
        // 隱藏登出按鈕
        document.getElementById('logout-btn').classList.add('d-none');
        
        // 清空登入表單
        document.getElementById('username').value = '';
        document.getElementById('password').value = '';
        document.getElementById('login-error').classList.add('d-none');
        
        // 切換到候位狀態頁面
        document.getElementById('status-tab').click();
    });
    
    // 手動新增黑名單
    document.getElementById('add-to-blacklist').addEventListener('click', function() {
        const phoneNumber = document.getElementById('add-blacklist-phone').value.trim();
        
        if (!phoneNumber) {
            alert('請輸入電話號碼');
            return;
        }
        
        // 先檢查客戶是否存在，如果不存在則先創建
        fetch(`${API_BASE_URL}/customer/check`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${authToken}`
            },
            body: JSON.stringify({ phone_number: phoneNumber })
        })
        .then(response => response.json())
        .then(data => {
            // 無論客戶是否存在，都將其加入黑名單
            return fetch(`${API_BASE_URL}/blacklist/add`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${authToken}`
                },
                body: JSON.stringify({ phone_number: phoneNumber })
            });
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('加入黑名單失敗: ' + data.error);
                return;
            }
            
            alert('已成功加入黑名單');
            document.getElementById('add-blacklist-phone').value = '';
            refreshBlacklist();
        })
        .catch(error => {
            console.error('獲取黑名單失敗:', error);
            alert('獲取失敗，請稍後再試');
        });
    });
    
    // 查詢統計數據
    document.getElementById('search-stats').addEventListener('click', function() {
        const startDate = document.getElementById('stats-start-date').value;
        const endDate = document.getElementById('stats-end-date').value;
        
        fetch(`${API_BASE_URL}/statistics?start_date=${startDate}&end_date=${endDate}`, {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('獲取統計數據失敗: ' + data.error);
                return;
            }
            
            // 繪製平均候位時間圖表
            const waitTimeCtx = document.getElementById('wait-time-chart').getContext('2d');
            new Chart(waitTimeCtx, {
                type: 'bar',
                data: {
                    labels: ['小桌 (1-2人)', '中桌 (3-4人)', '大桌 (5人以上)'],
                    datasets: [
                        {
                            label: '平均候位時間 (分鐘)',
                            data: [
                                data.avg_wait_time.small || 0,
                                data.avg_wait_time.medium || 0,
                                data.avg_wait_time.large || 0
                            ],
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.5)',
                                'rgba(75, 192, 192, 0.5)',
                                'rgba(255, 206, 86, 0.5)'
                            ],
                            borderColor: [
                                'rgba(54, 162, 235, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(255, 206, 86, 1)'
                            ],
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // 繪製取號方式分佈圖表
            const queueTypeCtx = document.getElementById('queue-type-chart').getContext('2d');
            new Chart(queueTypeCtx, {
                type: 'pie',
                data: {
                    labels: ['遠端取號', '現場取號'],
                    datasets: [
                        {
                            data: [
                                data.queue_type_distribution.remote || 0,
                                data.queue_type_distribution.onsite || 0
                            ],
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.5)',
                                'rgba(255, 99, 132, 0.5)'
                            ],
                            borderColor: [
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 99, 132, 1)'
                            ],
                            borderWidth: 1
                        }
                    ]
                }
            });
            
            // 繪製候位票狀態分佈圖表
            const statusCtx = document.getElementById('status-chart').getContext('2d');
            new Chart(statusCtx, {
                type: 'pie',
                data: {
                    labels: ['等待中', '已入座', '未到', '已取消'],
                    datasets: [
                        {
                            data: [
                                data.status_distribution.waiting || 0,
                                data.status_distribution.seated || 0,
                                data.status_distribution.no_show || 0,
                                data.status_distribution.cancelled || 0
                            ],
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.5)',
                                'rgba(75, 192, 192, 0.5)',
                                'rgba(255, 99, 132, 0.5)',
                                'rgba(255, 206, 86, 0.5)'
                            ],
                            borderColor: [
                                'rgba(54, 162, 235, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(255, 99, 132, 1)',
                                'rgba(255, 206, 86, 1)'
                            ],
                            borderWidth: 1
                        }
                    ]
                }
            });
            
            // 繪製每小時候位票數量圖表
            const hourlyCtx = document.getElementById('hourly-chart').getContext('2d');
            const hourLabels = [];
            const hourData = [];
            
            for (let i = 0; i < 24; i++) {
                hourLabels.push(`${i}:00`);
                hourData.push(data.hourly_distribution[i] || 0);
            }
            
            new Chart(hourlyCtx, {
                type: 'line',
                data: {
                    labels: hourLabels,
                    datasets: [
                        {
                            label: '每小時候位票數量',
                            data: hourData,
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            fill: true
                        }
                    ]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('獲取統計數據失敗:', error);
            alert('獲取失敗，請稍後再試');
        });
    });
}

// 叫下一位客人
function callNextCustomer(tableTypeId) {
    fetch(`${API_BASE_URL}/queue/next/${tableTypeId}`, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${authToken}`
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert('叫號失敗: ' + data.error);
            return;
        }
        
        alert(`成功叫號: ${data.ticket_number}號`);
        refreshQueueStatus();
        refreshStaffData();
    })
    .catch(error => {
        console.error('叫號失敗:', error);
        alert('叫號失敗，請稍後再試');
    });
}

// 更新黑名單狀態
function updateBlacklistStatus(customerId, blacklisted) {
    fetch(`${API_BASE_URL}/blacklist/${customerId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${authToken}`
        },
        body: JSON.stringify({ blacklisted })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert('更新失敗: ' + data.error);
            return;
        }
        
        alert('更新成功');
        document.getElementById('refresh-blacklist').click();
    })
    .catch(error => {
        console.error('更新黑名單狀態失敗:', error);
        alert('更新失敗，請稍後再試');
    });
}

// 格式化日期時間
function formatDateTime(dateTimeStr) {
    if (!dateTimeStr) return '-';
    
    const date = new Date(dateTimeStr);
    return date.toLocaleString('zh-TW', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    });
}
