// assets/js/dashboard_charts.js
document.addEventListener('DOMContentLoaded', function() {
    /**
     * ダッシュボードデータをAPIから読み込む
     * @param {string} period - 表示期間 (1m, 3m, 6m, 1y, all)
     */
    window.loadDashboardData = function(period) {
        fetch(`api/analysis.php?period=${period}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDashboardData(data.data);
                } else {
                    console.error('データ取得エラー:', data.error);
                }
            })
            .catch(error => {
                console.error('API呼び出しエラー:', error);
            });
    };

    /**
     * ダッシュボードデータの表示を更新
     * @param {Object} data - ダッシュボードデータ
     */
    function updateDashboardData(data) {
        // サマリーカードの更新
        updateSummaryCards(data.summary);

        // グラフの更新
        createDistanceTrendChart(data.trend);
        createStrokeDistributionChart(data.strokes);
        createGoalAchievementChart(data.goals);
        createHeatmapCalendar(data.heatmap);

        // 詳細データの更新
        updateBestSessions(data.best_sessions);
        updateStrokeDetails(data.stroke_details);
    }

    /**
     * サマリーカードの情報を更新
     * @param {Object} summaryData - サマリーデータ
     */
    function updateSummaryCards(summaryData) {
        document.getElementById('total-distance').textContent = numberWithCommas(summaryData.total_distance) + ' m';
        document.getElementById('session-count').textContent = summaryData.session_count + ' 回';
        document.getElementById('avg-distance').textContent = numberWithCommas(summaryData.avg_distance) + ' m';
        document.getElementById('goal-achievement').textContent = summaryData.goal_achievement + '%';
    }

    /**
     * 距離トレンドチャートを作成
     * @param {Array} trendData - トレンドデータ
     */
    function createDistanceTrendChart(trendData) {
        const ctx = document.getElementById('distance-trend-chart');
        if (!ctx) return;

        // 既存のチャートがある場合は破棄
        if (window.distanceTrendChart instanceof Chart) {
            window.distanceTrendChart.destroy();
        }

        // データの整形
        const labels = trendData.map(item => {
            // ISO形式の日付を年/月/日形式に変換
            const date = new Date(item.date);
            return `${date.getMonth() + 1}/${date.getDate()}`;
        });

        const data = {
            labels: labels,
            datasets: [{
                label: '練習距離',
                data: trendData.map(item => item.distance),
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1,
                tension: 0.3,
                yAxisID: 'y'
            }, {
                label: '練習時間',
                data: trendData.map(item => item.duration ? item.duration / 60 : 0), // 分→時間に変換
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 2,
                pointRadius: 3,
                type: 'line',
                tension: 0.3,
                yAxisID: 'y1'
            }]
        };

        window.distanceTrendChart = new Chart(ctx, {
            type: 'bar',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: '距離 (m)'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                        title: {
                            display: true,
                            text: '時間 (時)'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                if (context.datasetIndex === 0) {
                                    return `${label}: ${numberWithCommas(context.raw)} m`;
                                } else {
                                    // 時間を時:分形式に変換
                                    const hours = Math.floor(context.raw);
                                    const minutes = Math.round((context.raw - hours) * 60);
                                    return `${label}: ${hours}:${minutes.toString().padStart(2, '0')}`;
                                }
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * 泳法割合チャートを作成
     * @param {Array} strokeData - 泳法データ
     */
    function createStrokeDistributionChart(strokeData) {
        const ctx = document.getElementById('stroke-distribution-chart');
        if (!ctx || !strokeData || strokeData.length === 0) return;

        // 既存のチャートがある場合は破棄
        if (window.strokeDistributionChart instanceof Chart) {
            window.strokeDistributionChart.destroy();
        }

        // 泳法名のマッピング
        const strokeNames = {
            'freestyle': '自由形',
            'backstroke': '背泳ぎ',
            'breaststroke': '平泳ぎ',
            'butterfly': 'バタフライ',
            'im': '個人メドレー',
            'kick': 'キック',
            'pull': 'プル',
            'drill': 'ドリル',
            'other': 'その他'
        };

        // 色の設定
        const strokeColors = [
            'rgba(59, 130, 246, 0.8)', // 青 (freestyle)
            'rgba(239, 68, 68, 0.8)',  // 赤 (backstroke)
            'rgba(16, 185, 129, 0.8)', // 緑 (breaststroke)
            'rgba(245, 158, 11, 0.8)', // オレンジ (butterfly)
            'rgba(139, 92, 246, 0.8)', // 紫 (im)
            'rgba(75, 85, 99, 0.8)',   // グレー (other)
            'rgba(236, 72, 153, 0.8)', // ピンク (kick)
            'rgba(14, 165, 233, 0.8)', // ライトブルー (pull)
            'rgba(168, 85, 247, 0.8)'  // 明るい紫 (drill)
        ];

        const data = {
            labels: strokeData.map(item => strokeNames[item.stroke_type] || item.stroke_type),
            datasets: [{
                data: strokeData.map(item => item.distance),
                backgroundColor: strokeColors.slice(0, strokeData.length),
                borderWidth: 1,
                hoverOffset: 15
            }]
        };

        window.strokeDistributionChart = new Chart(ctx, {
            type: 'doughnut',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${numberWithCommas(value)}m (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * 目標達成チャートを作成
     * @param {Array} goalData - 目標データ
     */
    function createGoalAchievementChart(goalData) {
        const ctx = document.getElementById('goal-achievement-chart');
        if (!ctx || !goalData) return;

        // 既存のチャートがある場合は破棄
        if (window.goalAchievementChart instanceof Chart) {
            window.goalAchievementChart.destroy();
        }

        // 月名の配列
        const monthNames = [
            '1月', '2月', '3月', '4月', '5月', '6月',
            '7月', '8月', '9月', '10月', '11月', '12月'
        ];

        const data = {
            labels: goalData.map(item => monthNames[item.month - 1]),
            datasets: [
                {
                    label: '距離目標達成率',
                    data: goalData.map(item => item.distance_percentage),
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.6
                },
                {
                    label: '練習回数達成率',
                    data: goalData.map(item => item.sessions_percentage),
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.6
                }
            ]
        };

        window.goalAchievementChart = new Chart(ctx, {
            type: 'bar',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: '達成率 (%)'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw || 0;
                                return `${label}: ${value}%`;
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * ヒートマップカレンダーを作成
     * @param {Array} heatmapData - ヒートマップデータ
     */
    function createHeatmapCalendar(heatmapData) {
        const container = document.getElementById('heatmap-calendar');
        if (!container || !heatmapData || heatmapData.length === 0) return;

        // コンテナをクリア
        container.innerHTML = '';

        // 月名の配列
        const monthNames = [
            '1月', '2月', '3月', '4月', '5月', '6月',
            '7月', '8月', '9月', '10月', '11月', '12月'
        ];

        // 曜日の配列
        const weekdays = ['日', '月', '火', '水', '木', '金', '土'];

        // 今日の日付
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // 6週間分の日付を生成（現在の月を表示するため）
        const calendarDays = [];
        const startDate = new Date(today);
        startDate.setDate(1); // 当月の1日
        startDate.setDate(startDate.getDate() - startDate.getDay()); // 最初の日曜日に調整

        for (let i = 0; i < 42; i++) {
            const currentDate = new Date(startDate);
            currentDate.setDate(startDate.getDate() + i);
            calendarDays.push({
                date: currentDate,
                dateString: formatDate(currentDate),
                distance: 0,
                weekday: currentDate.getDay(),
                inCurrentMonth: currentDate.getMonth() === today.getMonth()
            });
        }

        // ヒートマップデータを日付と合わせる
        heatmapData.forEach(item => {
            const index = calendarDays.findIndex(day => day.dateString === item.date);
            if (index !== -1) {
                calendarDays[index].distance = item.distance;
            }
        });

        // カレンダーのヘッダー部分（月と曜日）
        const calendarHeader = document.createElement('div');
        calendarHeader.className = 'mb-2';
        calendarHeader.innerHTML = `
            <div class="text-center font-semibold mb-2">
                ${monthNames[today.getMonth()]} ${today.getFullYear()}
            </div>
            <div class="grid grid-cols-7 gap-1 text-center">
                ${weekdays.map(day => `<div class="text-xs font-medium">${day}</div>`).join('')}
            </div>
        `;
        container.appendChild(calendarHeader);

        // カレンダーのグリッド部分
        const calendarGrid = document.createElement('div');
        calendarGrid.className = 'grid grid-cols-7 gap-1';
        
        // 最大距離を計算（色の濃さに使用）
        const maxDistance = Math.max(...calendarDays.map(day => day.distance));

        // カレンダーのセルを生成
        calendarDays.forEach(day => {
            const cell = document.createElement('div');
            
            // 色の強度を計算（0.1～1.0の範囲）
            let intensity = day.distance > 0 ? 0.1 + (day.distance / maxDistance) * 0.9 : 0;
            
            let bgColor = 'transparent';
            let textColor = 'text-gray-400';
            
            if (day.inCurrentMonth) {
                textColor = 'text-gray-700';
                
                if (isSameDay(day.date, today)) {
                    bgColor = 'bg-blue-100';
                    textColor = 'text-blue-800 font-medium';
                } else if (intensity > 0) {
                    bgColor = `rgba(16, 185, 129, ${intensity})`;
                    textColor = intensity > 0.5 ? 'text-white' : 'text-gray-800';
                }
            }
            
            cell.className = `text-center py-1 text-xs rounded ${textColor}`;
            cell.style.backgroundColor = bgColor;
            
            // ツールチップ属性（マウスオーバー時に表示される情報）
            if (day.distance > 0) {
                cell.setAttribute('title', `${formatDate(day.date, 'YYYY年MM月DD日')}: ${numberWithCommas(day.distance)}m`);
            }
            
            cell.textContent = day.date.getDate();
            calendarGrid.appendChild(cell);
        });
        
        container.appendChild(calendarGrid);
    }

    /**
     * 最長距離練習セッションの表示を更新
     * @param {Array} bestSessions - ベストセッションデータ
     */
    function updateBestSessions(bestSessions) {
        const container = document.getElementById('best-distance-sessions');
        if (!container) return;

        if (!bestSessions || bestSessions.length === 0) {
            container.innerHTML = '<p class="text-center text-gray-500 py-3">データがありません</p>';
            return;
        }

        let html = '<div class="space-y-3">';
        
        bestSessions.forEach((session, index) => {
            const date = new Date(session.practice_date);
            const formattedDate = `${date.getFullYear()}/${date.getMonth() + 1}/${date.getDate()}`;
            
            html += `
                <div class="border rounded p-3 ${index === 0 ? 'bg-yellow-50 border-yellow-300' : 'bg-gray-50'}">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-medium">${numberWithCommas(session.total_distance)} m</div>
                            <div class="text-xs text-gray-500">${formattedDate}</div>
                        </div>
                        <a href="practice.php?action=view&id=${session.session_id}" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
    }

    /**
     * 泳法別距離の表示を更新
     * @param {Array} strokeDetails - 泳法詳細データ
     */
    function updateStrokeDetails(strokeDetails) {
        const container = document.getElementById('stroke-distance-table');
        if (!container) return;

        if (!strokeDetails || strokeDetails.length === 0) {
            container.innerHTML = '<p class="text-center text-gray-500 py-3">データがありません</p>';
            return;
        }

        // 泳法名のマッピング
        const strokeNames = {
            'freestyle': '自由形',
            'backstroke': '背泳ぎ',
            'breaststroke': '平泳ぎ',
            'butterfly': 'バタフライ',
            'im': '個人メドレー',
            'kick': 'キック',
            'pull': 'プル',
            'drill': 'ドリル',
            'other': 'その他'
        };

        // 総距離を計算
        const totalDistance = strokeDetails.reduce((sum, item) => sum + item.distance, 0);

        let html = `
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="py-2 px-3 text-left text-xs">泳法</th>
                        <th class="py-2 px-3 text-right text-xs">距離</th>
                        <th class="py-2 px-3 text-center text-xs">割合</th>
                    </tr>
                </thead>
                <tbody>
        `;

        strokeDetails.forEach(stroke => {
            const percentage = totalDistance > 0 ? Math.round((stroke.distance / totalDistance) * 100) : 0;
            const strokeName = strokeNames[stroke.stroke_type] || stroke.stroke_type;
            
            html += `
                <tr class="border-b">
                    <td class="py-2 px-3 text-sm">${strokeName}</td>
                    <td class="py-2 px-3 text-right text-sm">${numberWithCommas(stroke.distance)} m</td>
                    <td class="py-2 px-3">
                        <div class="flex items-center">
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="bg-blue-600 h-1.5 rounded-full" style="width: ${percentage}%"></div>
                            </div>
                            <span class="ml-2 text-xs text-gray-600">${percentage}%</span>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
        `;
        
        container.innerHTML = html;
    }

    /**
     * 数値をカンマ区切りの文字列に変換
     * @param {number} x - 変換する数値
     * @return {string} カンマ区切りの文字列
     */
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    /**
     * 日付をフォーマットする
     * @param {Date} date - フォーマットする日付
     * @param {string} format - 出力形式 (デフォルト: 'YYYY-MM-DD')
     * @return {string} フォーマットされた日付文字列
     */
    function formatDate(date, format = 'YYYY-MM-DD') {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        
        return format
            .replace('YYYY', year)
            .replace('MM', month)
            .replace('DD', day);
    }

    /**
     * 2つの日付が同じ日かどうかを判定
     * @param {Date} date1 - 日付1
     * @param {Date} date2 - 日付2
     * @return {boolean} 同じ日ならtrue
     */
    function isSameDay(date1, date2) {
        return date1.getFullYear() === date2.getFullYear() &&
            date1.getMonth() === date2.getMonth() &&
            date1.getDate() === date2.getDate();
    }
});