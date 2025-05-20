// assets/js/analytics_charts.js
document.addEventListener('DOMContentLoaded', function() {
    /**
     * 進捗グラフを作成する機能
     * 複数の指標を時系列で比較できるダッシュボード
     */
    function createProgressChart(containerId, userData) {
        const ctx = document.getElementById(containerId);
        if (!ctx) return;
        
        // 複数指標の比較グラフ
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: userData.dates,
                datasets: [
                    {
                        label: '泳力指数',
                        data: userData.skillIndex,
                        borderColor: 'rgba(59, 130, 246, 1)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: '累計距離 (km)',
                        data: userData.totalDistance.map(d => d/1000),
                        borderColor: 'rgba(16, 185, 129, 1)',
                        backgroundColor: 'transparent',
                        borderDash: [5, 5],
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: '泳力指数'
                        }
                    },
                    y1: {
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                        title: {
                            display: true,
                            text: '累計距離 (km)'
                        }
                    }
                }
            }
        });
    }
    
    /**
     * PDFレポート生成機能
     * 月間・年間の練習実績をPDF形式でエクスポート
     */
    window.generateReport = function(period) {
        // ローディング表示
        const loadingEl = document.createElement('div');
        loadingEl.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        loadingEl.innerHTML = `
            <div class="bg-white p-6 rounded-lg shadow-lg text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p>レポートを生成中...</p>
            </div>
        `;
        document.body.appendChild(loadingEl);
        
        // APIリクエスト
        fetch(`api/reports.php?period=${period}&format=pdf`)
            .then(response => response.blob())
            .then(blob => {
                // PDFファイルとしてダウンロード
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = `swim_report_${period}.pdf`;
                document.body.appendChild(a);
                a.click();
                
                // リソースの解放
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                document.body.removeChild(loadingEl);
                
                // 完了通知
                addNotification('レポートが生成されました', 'success');
            })
            .catch(error => {
                console.error('レポート生成エラー:', error);
                document.body.removeChild(loadingEl);
                addNotification('レポートの生成中にエラーが発生しました', 'error');
            });
    };
});