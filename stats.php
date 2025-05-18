<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// ページタイトル
$page_title = "統計";

// ログイン必須
requireLogin();

// 現在の年月を取得
$currentYear = date('Y');
$currentMonth = date('n');

// GETパラメータから年月を取得（指定がなければ現在の年月）
$year = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$month = isset($_GET['month']) ? (int)$_GET['month'] : $currentMonth;

// 有効な年月かチェック
if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
    $year = $currentYear;
    $month = $currentMonth;
}

// 前月と翌月の計算
$prevYear = $month == 1 ? $year - 1 : $year;
$prevMonth = $month == 1 ? 12 : $month - 1;
$nextYear = $month == 12 ? $year + 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;

// 月の名前
$monthNames = [
    1 => '1月', 2 => '2月', 3 => '3月', 4 => '4月', 5 => '5月', 6 => '6月',
    7 => '7月', 8 => '8月', 9 => '9月', 10 => '10月', 11 => '11月', 12 => '12月'
];

// 月間データの取得
$monthlyStats = [
    'total_distance' => 0,
    'session_count' => 0,
    'avg_distance' => 0,
    'stroke_data' => [],
    'daily_data' => []
];

try {
    $db = getDbConnection();
    
    // 月の最初と最後の日付
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $lastDay = date('t', strtotime($startDate)); // 月の最終日を取得
    $endDate = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
    
    // 月間の総距離と練習セッション数を取得
    $stmt = $db->prepare("
        SELECT SUM(total_distance) as total, COUNT(*) as count
        FROM practice_sessions
        WHERE user_id = ? AND practice_date BETWEEN ? AND ?
    ");
    $stmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
    $result = $stmt->fetch();
    
    if ($result && $result['count'] > 0) {
        $monthlyStats['total_distance'] = (int)$result['total'];
        $monthlyStats['session_count'] = (int)$result['count'];
        $monthlyStats['avg_distance'] = round($monthlyStats['total_distance'] / $monthlyStats['session_count']);
        
        // 泳法別の距離を取得
        $stmt = $db->prepare("
            SELECT ps.stroke_type, SUM(ps.total_distance) as stroke_distance
            FROM practice_sets ps
            JOIN practice_sessions s ON ps.session_id = s.session_id
            WHERE s.user_id = ? AND s.practice_date BETWEEN ? AND ?
            GROUP BY ps.stroke_type
            ORDER BY stroke_distance DESC
        ");
        $stmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
        $monthlyStats['stroke_data'] = $stmt->fetchAll();
        
        // 日別の練習距離を取得（グラフ用）
        $stmt = $db->prepare("
            SELECT practice_date, total_distance
            FROM practice_sessions
            WHERE user_id = ? AND practice_date BETWEEN ? AND ?
            ORDER BY practice_date
        ");
        $stmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
        $monthlyStats['daily_data'] = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log('統計データ取得エラー: ' . $e->getMessage());
    $error_message = 'データの取得中にエラーが発生しました。';
}

// 目標データの取得（実装済みの場合）
$monthlyGoal = null;
try {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT * FROM monthly_goals
        WHERE user_id = ? AND year = ? AND month = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $year, $month]);
    $monthlyGoal = $stmt->fetch();
} catch (PDOException $e) {
    error_log('目標データ取得エラー: ' . $e->getMessage());
}

// ヘッダーの読み込み
include 'includes/header.php';
?>

<!-- 月の選択 -->
<div class="flex justify-between items-center mb-6 bg-white rounded-lg shadow-md p-4">
    <a href="stats.php?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>" class="text-blue-600 hover:text-blue-800">
        <i class="fas fa-chevron-left mr-1"></i> 前月
    </a>
    
    <div class="text-center">
        <h2 class="text-xl font-semibold"><?php echo $year; ?>年<?php echo $monthNames[$month]; ?></h2>
    </div>
    
    <a href="stats.php?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>" class="text-blue-600 hover:text-blue-800">
        次月 <i class="fas fa-chevron-right ml-1"></i>
    </a>
</div>

<!-- 統計コンテンツ -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h1 class="text-2xl font-bold mb-6">統計</h1>
    
    <?php if ($monthlyStats['session_count'] > 0): ?>
    <!-- 統計データがある場合 -->
    
    <!-- サマリー情報 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-blue-50 rounded-lg p-6 text-center">
            <p class="text-gray-600 mb-2">総距離</p>
            <p class="text-3xl font-bold text-blue-600"><?php echo number_format($monthlyStats['total_distance']); ?> m</p>
            <?php if ($monthlyGoal && $monthlyGoal['distance_goal'] > 0): ?>
                <div class="mt-2">
                    <p class="text-sm text-gray-500">目標: <?php echo number_format($monthlyGoal['distance_goal']); ?> m</p>
                    <div class="bg-gray-200 rounded-full h-2 mt-1">
                        <?php $percentage = min(100, round(($monthlyStats['total_distance'] / $monthlyGoal['distance_goal']) * 100)); ?>
                        <div class="bg-blue-600 rounded-full h-2" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <p class="text-xs text-right mt-1"><?php echo $percentage; ?>% 達成</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="bg-green-50 rounded-lg p-6 text-center">
            <p class="text-gray-600 mb-2">練習回数</p>
            <p class="text-3xl font-bold text-green-600"><?php echo $monthlyStats['session_count']; ?> 回</p>
            <?php if ($monthlyGoal && $monthlyGoal['sessions_goal'] > 0): ?>
                <div class="mt-2">
                    <p class="text-sm text-gray-500">目標: <?php echo $monthlyGoal['sessions_goal']; ?> 回</p>
                    <div class="bg-gray-200 rounded-full h-2 mt-1">
                        <?php $percentage = min(100, round(($monthlyStats['session_count'] / $monthlyGoal['sessions_goal']) * 100)); ?>
                        <div class="bg-green-600 rounded-full h-2" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <p class="text-xs text-right mt-1"><?php echo $percentage; ?>% 達成</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="bg-purple-50 rounded-lg p-6 text-center">
            <p class="text-gray-600 mb-2">平均距離 / 練習</p>
            <p class="text-3xl font-bold text-purple-600"><?php echo number_format($monthlyStats['avg_distance']); ?> m</p>
        </div>
    </div>
    
    <!-- グラフエリア -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
        <!-- 日別練習距離グラフ -->
        <div>
            <h3 class="text-lg font-semibold mb-4">日別練習距離</h3>
            <div id="daily-distance-chart" class="h-64"></div>
        </div>
        
        <!-- 泳法割合グラフ -->
        <div>
            <h3 class="text-lg font-semibold mb-4">泳法割合</h3>
            <?php if (!empty($monthlyStats['stroke_data'])): ?>
                <div id="stroke-distribution-chart" class="h-64"></div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-12">泳法データがありません</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 詳細データテーブル -->
    <div>
        <h3 class="text-lg font-semibold mb-4">泳法別集計</h3>
        <?php if (!empty($monthlyStats['stroke_data'])): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="py-2 px-4 text-left">泳法</th>
                            <th class="py-2 px-4 text-left">距離</th>
                            <th class="py-2 px-4 text-left">割合</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $strokeNames = [
                            'freestyle' => '自由形',
                            'backstroke' => '背泳ぎ',
                            'breaststroke' => '平泳ぎ',
                            'butterfly' => 'バタフライ',
                            'im' => '個人メドレー',
                            'other' => 'その他'
                        ];
                        
                        foreach ($monthlyStats['stroke_data'] as $stroke): 
                            $percentage = round(($stroke['stroke_distance'] / $monthlyStats['total_distance']) * 100);
                        ?>
                        <tr class="border-b">
                            <td class="py-3 px-4"><?php echo h($strokeNames[$stroke['stroke_type']] ?? $stroke['stroke_type']); ?></td>
                            <td class="py-3 px-4"><?php echo number_format($stroke['stroke_distance']); ?> m</td>
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="w-32 bg-gray-200 rounded-full h-2.5 mr-2">
                                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <span><?php echo $percentage; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-center text-gray-500 py-4">泳法データがありません</p>
        <?php endif; ?>
    </div>
    
    <?php else: ?>
    <!-- データがない場合 -->
    <div class="text-center py-8">
        <p class="text-gray-500 mb-6">
            まだデータがありません。<br>練習を記録すると、統計情報が表示されます。
        </p>
        <a href="practice.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg inline-flex items-center">
            <i class="fas fa-plus mr-2"></i>
            練習を記録する
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($monthlyStats['session_count'] > 0): ?>
<!-- グラフ描画スクリプト -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 日別練習距離グラフ
    const dailyCtx = document.getElementById('daily-distance-chart').getContext('2d');
    
    const dailyData = {
        labels: [
            <?php 
            $days = [];
            foreach ($monthlyStats['daily_data'] as $dayData) {
                $date = new DateTime($dayData['practice_date']);
                $days[] = "'" . $date->format('j') . "日'";
            }
            echo implode(', ', $days);
            ?>
        ],
        datasets: [{
            label: '練習距離',
            data: [
                <?php 
                $distances = [];
                foreach ($monthlyStats['daily_data'] as $dayData) {
                    $distances[] = $dayData['total_distance'];
                }
                echo implode(', ', $distances);
                ?>
            ],
            backgroundColor: 'rgba(59, 130, 246, 0.5)',
            borderColor: 'rgba(59, 130, 246, 1)',
            borderWidth: 1
        }]
    };
    
    new Chart(dailyCtx, {
        type: 'bar',
        data: dailyData,
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
                x: {
                    title: {
                        display: true,
                        text: '日付'
                    }
                }
            }
        }
    });
    
    <?php if (!empty($monthlyStats['stroke_data'])): ?>
    // 泳法割合グラフ
    const strokeCtx = document.getElementById('stroke-distribution-chart').getContext('2d');
    
    const strokeNames = {
        'freestyle': '自由形',
        'backstroke': '背泳ぎ',
        'breaststroke': '平泳ぎ',
        'butterfly': 'バタフライ',
        'im': '個人メドレー',
        'other': 'その他'
    };
    
    const strokeColors = [
        'rgba(59, 130, 246, 0.8)',   // 青
        'rgba(16, 185, 129, 0.8)',   // 緑
        'rgba(239, 68, 68, 0.8)',    // 赤
        'rgba(245, 158, 11, 0.8)',   // オレンジ
        'rgba(139, 92, 246, 0.8)',   // 紫
        'rgba(75, 85, 99, 0.8)'      // グレー
    ];
    
    const strokeData = {
        labels: [
            <?php 
            $labels = [];
            foreach ($monthlyStats['stroke_data'] as $stroke) {
                $strokeName = $strokeNames[$stroke['stroke_type']] ?? $stroke['stroke_type'];
                $labels[] = "'" . $strokeName . "'";
            }
            echo implode(', ', $labels);
            ?>
        ],
        datasets: [{
            data: [
                <?php 
                $strokeDistances = [];
                foreach ($monthlyStats['stroke_data'] as $stroke) {
                    $strokeDistances[] = $stroke['stroke_distance'];
                }
                echo implode(', ', $strokeDistances);
                ?>
            ],
            backgroundColor: strokeColors.slice(0, <?php echo count($monthlyStats['stroke_data']); ?>),
            borderWidth: 1
        }]
    };
    
    new Chart(strokeCtx, {
        type: 'doughnut',
        data: strokeData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
    <?php endif; ?>
});
</script>
<?php endif; ?>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>