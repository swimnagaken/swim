// main.js - 共通JavaScriptファイル

document.addEventListener('DOMContentLoaded', function() {
    // モバイルナビゲーションの動作
    const mobileNavToggle = document.getElementById('mobile-nav-toggle');
    const mobileNav = document.getElementById('mobile-nav');
    
    if (mobileNavToggle && mobileNav) {
        mobileNavToggle.addEventListener('click', function() {
            mobileNav.classList.toggle('hidden');
        });
    }
    
    // CSRF トークンを取得する関数
    function getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute('content') : '';
    }
    
    // 通知メッセージの自動消去
    const notifications = document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-blue-100, .bg-yellow-100');
    notifications.forEach(notification => {
        setTimeout(() => {
            notification.style.transition = 'opacity 0.5s ease';
            notification.style.opacity = '0';
            setTimeout(() => {
                notification.remove();
            }, 500);
        }, 5000);
    });
    
    // 月間目標設定ボタンのイベントリスナー
    const setGoalBtn = document.getElementById('set-goal-btn');
    if (setGoalBtn) {
        setGoalBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // 目標設定モーダルの実装（将来的に追加）
            alert('この機能はまだ実装中です。');
        });
    }
});

// API呼び出し用ヘルパー関数
const api = {
    async get(endpoint, params = {}) {
        const url = new URL(endpoint, window.location.origin);
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
        
        try {
            const response = await fetch(url.toString());
            if (!response.ok) {
                throw new Error(`APIエラー: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error(`GET ${endpoint} エラー:`, error);
            throw error;
        }
    },
    
    async post(endpoint, data = {}) {
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: JSON.stringify(data)
            });
            
            if (!response.ok) {
                throw new Error(`APIエラー: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error(`POST ${endpoint} エラー:`, error);
            throw error;
        }
    }
};

// 日付フォーマット関数
function formatDate(date, format = 'YYYY-MM-DD') {
    const d = new Date(date);
    
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    
    return format
        .replace('YYYY', year)
        .replace('MM', month)
        .replace('DD', day);
}

// CSRF トークンを取得する関数
function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.getAttribute('content') : '';
}