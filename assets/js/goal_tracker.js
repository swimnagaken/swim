// assets/js/goal_tracker.js
document.addEventListener('DOMContentLoaded', function() {
    /**
     * リアルタイム目標達成度の表示
     */
    function initGoalTracker() {
        const goalTrackers = document.querySelectorAll('.goal-tracker');
        if (goalTrackers.length === 0) return;
        
        goalTrackers.forEach(tracker => {
            updateGoalTracker(tracker);
        });
        
        // 定期的に更新（1日1回程度）
        if (localStorage.getItem('lastGoalUpdate') !== getCurrentDateString()) {
            updateAllGoalData();
            localStorage.setItem('lastGoalUpdate', getCurrentDateString());
        }
    }
    
    /**
     * 目標達成度を視覚的に表示
     */
    function updateGoalTracker(tracker) {
        const goalId = tracker.dataset.goalId;
        const goalType = tracker.dataset.goalType; // 'distance' or 'sessions'
        const goalValue = parseInt(tracker.dataset.goalValue) || 0;
        const currentValue = parseInt(tracker.dataset.currentValue) || 0;
        
        if (goalValue <= 0) {
            tracker.innerHTML = '<div class="text-gray-500 text-center">目標が設定されていません</div>';
            return;
        }
        
        // 達成率の計算
        const percentage = Math.min(100, Math.round((currentValue / goalValue) * 100));
        
        // 残り日数の計算
        const today = new Date();
        const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        const remainingDays = Math.ceil((endOfMonth - today) / (1000 * 60 * 60 * 24));
        
        // 1日あたりの必要量
        const remaining = Math.max(0, goalValue - currentValue);
        const perDay = remainingDays > 0 ? Math.ceil(remaining / remainingDays) : 0;
        
        // 視覚的な表示を構築
        let statusClass = 'bg-green-100 text-green-800';
        let statusIcon = 'fa-check-circle';
        let statusText = '順調に進行中';
        
        if (percentage < 30 && remainingDays < 15) {
            statusClass = 'bg-red-100 text-red-800';
            statusIcon = 'fa-exclamation-circle';
            statusText = '遅れています';
        } else if (percentage < 50 && remainingDays < 10) {
            statusClass = 'bg-yellow-100 text-yellow-800';
            statusIcon = 'fa-exclamation-triangle';
            statusText = '注意が必要です';
        }
        
        tracker.innerHTML = `
            <div class="mb-3">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-medium">${goalType === 'distance' ? '距離目標' : '練習回数'}</span>
                    <span class="text-sm font-medium">${percentage}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: ${percentage}%"></div>
                </div>
            </div>
            
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm">現在: ${numberWithCommas(currentValue)} ${goalType === 'distance' ? 'm' : '回'}</p>
                    <p class="text-sm">目標: ${numberWithCommas(goalValue)} ${goalType === 'distance' ? 'm' : '回'}</p>
                </div>
                <div>
                    <p class="text-sm">残り日数: ${remainingDays}日</p>
                    <p class="text-sm">1日あたり: ${perDay > 0 ? numberWithCommas(perDay) : '達成済み'} ${goalType === 'distance' ? 'm' : '回'}</p>
                </div>
            </div>
            
            <div class="mt-3 ${statusClass} px-3 py-2 rounded-lg text-sm">
                <i class="fas ${statusIcon} mr-1"></i> ${statusText}
            </div>
        `;
    }
    
    /**
     * 全ての目標データを更新
     */
    function updateAllGoalData() {
        fetch('api/goal.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.goals) {
                    // 最新データで目標トラッカーを更新
                    data.goals.forEach(goal => {
                        const distanceTracker = document.querySelector(`.goal-tracker[data-goal-id="${goal.goal_id}"][data-goal-type="distance"]`);
                        if (distanceTracker) {
                            distanceTracker.dataset.goalValue = goal.distance_goal;
                            distanceTracker.dataset.currentValue = goal.stats.current_distance;
                            updateGoalTracker(distanceTracker);
                        }
                        
                        const sessionsTracker = document.querySelector(`.goal-tracker[data-goal-id="${goal.goal_id}"][data-goal-type="sessions"]`);
                        if (sessionsTracker) {
                            sessionsTracker.dataset.goalValue = goal.sessions_goal;
                            sessionsTracker.dataset.currentValue = goal.stats.current_sessions;
                            updateGoalTracker(sessionsTracker);
                        }
                    });
                }
            })
            .catch(error => console.error('目標データ取得エラー:', error));
    }
    
    /**
     * 現在の日付文字列を取得（YYYY-MM-DD形式）
     */
    function getCurrentDateString() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    }
    
    /**
     * 数値をカンマ区切りの文字列に変換
     */
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // 初期化
    initGoalTracker();
});