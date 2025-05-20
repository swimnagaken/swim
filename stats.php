<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// ページタイトル
$page_title = "統計";

// ログイン必須
requireLogin();

// ビューモードの取得（month/year）
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'month';

// 現在の年月を取得
$currentYear = date('Y');
$currentMonth = date('n');

// GETパラメータから年月を取得（指定がなければ現在の年月）
$year = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$month = isset($_GET['view']) && $_GET['view'] === 'month' ? (isset($_GET['month']) ? (int)$_GET['month'] : $currentMonth) : $currentMonth;

// 有効な年をチェック
if ($year < 2000 || $year > 2100) {
    $year = $currentYear;
}

// 有効な月をチェック
if ($month !== null && ($month < 1 || $month > 12)) {
    $month = $currentMonth;
}

// 前月/前年と翌月/翌年の計算
if ($view_mode === 'month') {
    $prevYear = $month == 1 ? $year - 1 : $year;
    $prevMonth = $month == 1 ? 12 : $month - 1;
    $nextYear = $month == 12 ? $year + 1 : $year;
    $nextMonth = $month == 12 ? 1 : $month + 1;
} else {
    $prevYear = $year - 1;
    $prevMonth = null;
    $nextYear = $year + 1;
    $nextMonth = null;
}

// 月の名前
$monthNames = [
    1 => '1月', 2 => '2月', 3 => '3月', 4 => '4月', 5 => '5月', 6 => '6月',
    7 => '7月', 8 => '8月', 9 => '9月', 10 => '10月', 11 => '11月', 12 => '12月'
];

// 統計データの初期化
$stats = [
    'total_distance' => 0,
    'session_count' => 0,
    'avg_distance' => 0,
    'stroke_data' => [],
    'monthly_data' => [],
    'daily_data' => [],
    'goal_achievement' => []
];

// 統計データの取得
try {
    $db = getDbConnection();
    
    if ($view_mode === 'month') {
        // 月間統計データの取得
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
            $stats['total_distance'] = (int)$result['total'];
            $stats['session_count'] = (int)$result['count'];
            $stats['avg_distance'] = round($stats['total_distance'] / $stats['session_count']);
            
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
            $stats['stroke_data'] = $stmt->fetchAll();
            
            // 日別の練習距離を取得（グラフ用）
            $stmt = $db->prepare("
                SELECT practice_date, total_distance
                FROM practice_sessions
                WHERE user_id = ? AND practice_date BETWEEN ? AND ?
                ORDER BY practice_date
            ");
            $stmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
            $stats['daily_data'] = $stmt->fetchAll();
        }
        
        // 月間目標の取得
        $stmt = $db->prepare("
            SELECT * FROM monthly_goals
            WHERE user_id = ? AND year = ? AND month = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $year, $month]);
        $stats['monthly_goal'] = $stmt->fetch();
        
    } else {
        // 年間統計データの取得
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);
        
        // 年間の総距離と練習セッション数を取得
        $stmt = $db->prepare("
            SELECT SUM(total_distance) as total, COUNT(*) as count
            FROM practice_sessions
            WHERE user_id = ? AND practice_date BETWEEN ? AND ?
        ");
        $stmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
        $result = $stmt->fetch();
        
        if ($result) {
            $stats['total_distance'] = (int)$result['total'];
            $stats['session_count'] = (int)$result['count'];
            if ($stats['session_count'] > 0) {
                $stats['avg_distance'] = round($stats['total_distance'] / $stats['session_count']);
            }
            
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
            $stats['stroke_data'] = $stmt->fetchAll();
            
            // 月別の練習距離と回数を取得（グラフ用）
            $stmt = $db->prepare("
                SELECT MONTH(practice_date) as month, 
                       SUM(total_distance) as total_distance, 
                       COUNT(*) as session_count
                FROM practice_sessions
                WHERE user_id = ? AND YEAR(practice_date) = ?
                GROUP BY MONTH(practice_date)
                ORDER BY month
            ");
            $stmt->execute([$_SESSION['user_id'], $year]);
            $monthly_data = $stmt->fetchAll();
            
            // 全ての月のデータを初期化（データがない月も表示するため）
            $stats['monthly_data'] = [];
            for ($i = 1; $i <= 12; $i++) {
                $stats['monthly_data'][$i] = [
                    'month' => $i,
                    'total_distance' => 0,
                    'session_count' => 0
                ];
            }
            
            // 実際のデータで上書き
            foreach ($monthly_data as $data) {
                $stats['monthly_data'][$data['month']] = $data;
            }
            
            // 月間目標の達成率を取得
            $stmt = $db->prepare("
                SELECT mg.month, mg.distance_goal, mg.sessions_goal,
                       COALESCE(SUM(ps.total_distance), 0) as achieved_distance,
                       COUNT(DISTINCT ps.session_id) as achieved_sessions
                FROM monthly_goals mg
                LEFT JOIN practice_sessions ps ON 
                    ps.user_id = mg.user_id AND 
                    YEAR(ps.practice_date) = mg.year AND 
                    MONTH(ps.practice_date) = mg.month
                WHERE mg.user_id = ? AND mg.year = ?
                GROUP BY mg.month, mg.distance_goal, mg.sessions_goal
                ORDER BY mg.month
            ");
            $stmt->execute([$_SESSION['user_id'], $year]);
            $goalData = $stmt->fetchAll();
            
            // 目標達成率データの整形
            foreach ($goalData as $goal) {
                $distance_percentage = $goal['distance_goal'] > 0 
                    ? min(100, round(($goal['achieved_distance'] / $goal['distance_goal']) * 100)) 
                    : 0;
                
                $sessions_percentage = $goal['sessions_goal'] > 0 
                    ? min(100, round(($goal['achieved_sessions'] / $goal['sessions_goal']) * 100)) 
                    : 0;
                
                $stats['goal_achievement'][$goal['month']] = [
                    'month' => $goal['month'],
                    'distance_goal' => $goal['distance_goal'],
                    'sessions_goal' => $goal['sessions_goal'],
                    'achieved_distance' => $goal['achieved_distance'],
                    'achieved_sessions' => $goal['achieved_sessions'],
                    'distance_percentage' => $distance_percentage,
                    'sessions_percentage' => $sessions_percentage
                ];
            }
        }
    }
} catch (PDOException $e) {
    error_log('統計データ取得エラー: ' . $e->getMessage());
    $error_message = 'データの取得中にエラーが発生しました。';
}

// ヘッダーの読み込み
include 'includes/header.php';
?>

<!-- モード切替タブ -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <div class="flex items-center justify-center">
        <a href="stats.php?view=month&year=<?php echo $year; ?>&month=<?php echo $month ?: $currentMonth; ?>" class="py-2 px-6 <?php echo $view_mode === 'month' ? 'font-semibold text-blue-600 border-b-2 border-blue-500' : 'text-gray-600'; ?>">
            月間
        </a>
        <a href="stats.php?view=year&year=<?php echo $year; ?>" class="py-2 px-6 <?php echo $view_mode === 'year' ? 'font-semibold text-blue-600 border-b-2 border-blue-500' : 'text-gray-600'; ?>">
            年間
        </a>
    </div>
</div>

<!-- 期間選択 -->
<div class="flex justify-between items-center mb-6 bg-white rounded-lg shadow-md p-4">
    <?php if ($view_mode === 'month'): ?>
    <a href="stats.php?view=month&year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>" class="text-blue-600 hover:text-blue-800">
        <i class="fas fa-chevron-left mr-1"></i> 前月
    </a>
    
    <div class="text-center">
        <h2 class="text-xl font-semibold"><?php echo $year; ?>年<?php echo $monthNames[$month]; ?></h2>
    </div>
    
    <a href="stats.php?view=month&year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>" class="text-blue-600 hover:text-blue-800">
        次月 <i class="fas fa-chevron-right ml-1"></i>
    </a>
    <?php else: ?>
    <a href="stats.php?view=year&year=<?php echo $prevYear; ?>" class="text-blue-600 hover:text-blue-800">
        <i class="fas fa-chevron-left mr-1"></i> 前年
    </a>
    
    <div class="text-center">
        <h2 class="text-xl font-semibold"><?php echo $year; ?>年</h2>
    </div>
    
    <a href="stats.php?view=year&year=<?php echo $nextYear; ?>" class="text-blue-600 hover:text-blue-800">
        翌年 <i class="fas fa-chevron-right ml-1"></i>
    </a>
    <?php endif; ?>
</div>

<!-- 統計コンテンツ -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h1 class="text-2xl font-bold mb-6">統計</h1>
    
    <?php if (($view_mode === 'month' && $stats['session_count'] > 0) || 
              ($view_mode === 'year' && $stats['total_distance'] > 0)): ?>
    <!-- 統計データがある場合 -->
    
    <!-- サマリー情報 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-blue-50 rounded-lg p-6 text-center">
            <p class="text-gray-600 mb-2">総距離</p>
            <p class="text-3xl font-bold text-blue-600"><?php echo number_format($stats['total_distance']); ?> m</p>
            <?php if ($view_mode === 'month' && isset($stats['monthly_goal']) && $stats['monthly_goal'] && $stats['monthly_goal']['distance_goal'] > 0): ?>
                <div class="mt-2">
                    <p class="text-sm text-gray-500">目標: <?php echo number_format($stats['monthly_goal']['distance_goal']); ?> m</p>
                    <div class="bg-gray-200 rounded-full h-2 mt-1">
                        <?php $percentage = min(100, round(($stats['total_distance'] / $stats['monthly_goal']['distance_goal']) * 100)); ?>
                        <div class="bg-blue-600 rounded-full h-2" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <p class="text-xs text-right mt-1"><?php echo $percentage; ?>% 達成</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="bg-green-50 rounded-lg p-6 text-center">
            <p class="text-gray-600 mb-2">練習回数</p>
            <p class="text-3xl font-bold text-green-600"><?php echo $stats['session_count']; ?> 回</p>
            <?php if ($view_mode === 'month' && isset($stats['monthly_goal']) && $stats['monthly_goal'] && $stats['monthly_goal']['sessions_goal'] > 0): ?>
                <div class="mt-2">
                    <p class="text-sm text-gray-500">目標: <?php echo $stats['monthly_goal']['sessions_goal']; ?> 回</p>
                    <div class="bg-gray-200 rounded-full h-2 mt-1">
                        <?php $percentage = min(100, round(($stats['session_count'] / $stats['monthly_goal']['sessions_goal']) * 100)); ?>
                        <div class="bg-green-600 rounded-full h-2" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <p class="text-xs text-right mt-1"><?php echo $percentage; ?>% 達成</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="bg-purple-50 rounded-lg p-6 text-center">
            <p class="text-gray-600 mb-2">平均距離 / 練習</p>
            <p class="text-3xl font-bold text-purple-600"><?php echo number_format($stats['avg_distance']); ?> m</p>
        </div>
    </div>
    
    <!-- グラフエリア -->
    <div class="grid grid-cols-1 <?php echo $view_mode === 'month' ? 'md:grid-cols-2' : 'md:grid-cols-1'; ?> gap-8 mb-6">
        <?php if ($view_mode === 'month'): ?>
        <!-- 日別練習距離グラフ -->
        <div>
            <h3 class="text-lg font-semibold mb-4">日別練習距離</h3>
            <div id="daily-distance-chart" class="h-64"></div>
        </div>
        
        <!-- 泳法割合グラフ -->
        <div>
            <h3 class="text-lg font-semibold mb-4">泳法割合</h3>
            <?php if (!empty($stats['stroke_data'])): ?>
                <div id="stroke-distribution-chart" class="h-64"></div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-12">泳法データがありません</p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- 月別練習距離グラフ -->
        <div>
            <h3 class="text-lg font-semibold mb-4">月別練習距離</h3>
            <div id="monthly-distance-chart" class="h-64"></div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($view_mode === 'year'): ?>
    <!-- 年間グラフエリア -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
        <!-- 泳法割合グラフ -->
        <div>
            <h3 class="text-lg font-semibold mb-4">泳法割合（年間）</h3>
            <?php if (!empty($stats['stroke_data'])): ?>
                <div id="year-stroke-distribution-chart" class="h-64"></div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-12">泳法データがありません</p>
            <?php endif; ?>
        </div>
        
        <!-- 目標達成率グラフ -->
        <div>
            <h3 class="text-lg font-semibold mb-4">月間目標達成率</h3>
            <?php if (!empty($stats['goal_achievement'])): ?>
                <div id="goal-achievement-chart" class="h-64"></div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-12">目標データがありません</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 月別データテーブル -->
    <div class="mb-6">
        <h3 class="text-lg font-semibold mb-4">月別集計</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="py-2 px-4 text-left">月</th>
                        <th class="py-2 px-4 text-left">練習回数</th>
                        <th class="py-2 px-4 text-left">総距離</th>
                        <th class="py-2 px-4 text-left">平均距離</th>
                        <th class="py-2 px-4 text-left">目標達成率（距離）</th>
                        <th class="py-2 px-4 text-left">詳細</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['monthly_data'] as $month => $data): 
                        // データがある月のみ表示
                        if ($data['total_distance'] > 0):
                    ?>
                    <tr class="border-b">
                        <td class="py-3 px-4"><?php echo $monthNames[$month]; ?></td>
                        <td class="py-3 px-4"><?php echo $data['session_count']; ?> 回</td>
                        <td class="py-3 px-4"><?php echo number_format($data['total_distance']); ?> m</td>
                        <td class="py-3 px-4">
                            <?php 
                            $avg = $data['session_count'] > 0 ? round($data['total_distance'] / $data['session_count']) : 0;
                            echo number_format($avg); ?> m
                        </td>
                        <td class="py-3 px-4">
                            <?php if (isset($stats['goal_achievement'][$month])): ?>
                            <div class="flex items-center">
                                <div class="w-32 bg-gray-200 rounded-full h-2.5 mr-2">
                                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $stats['goal_achievement'][$month]['distance_percentage']; ?>%"></div>
                                </div>
                                <span><?php echo $stats['goal_achievement'][$month]['distance_percentage']; ?>%</span>
                            </div>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <a href="stats.php?view=month&year=<?php echo $year; ?>&month=<?php echo $month; ?>" class="text-blue-600 hover:text-blue-800">
                                詳細を見る
                            </a>
                        </td>
                    </tr>
                    <?php endif; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 詳細データテーブル (月間ビューの場合) -->
    <?php if ($view_mode === 'month' && !empty($stats['stroke_data'])): ?>
    <div>
        <h3 class="text-lg font-semibold mb-4">泳法別集計</h3>
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
                    
                    // この内容に変更
foreach ($stats['stroke_data'] as $stroke): 
    // stroke_typeキーが存在して空でないことを確認
    $stroke_type = isset($stroke['stroke_type']) && !empty($stroke['stroke_type']) ? $stroke['stroke_type'] : 'other';
    $percentage = round(($stroke['stroke_distance'] / $stats['total_distance']) * 100);
?>
<tr class="border-b">
    <td class="py-3 px-4"><?php echo h($strokeNames[$stroke_type] ?? $stroke_type); ?></td>
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
    </div>
    <?php endif; ?>
    
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

<?php if (($view_mode === 'month' && $stats['session_count'] > 0) || 
          ($view_mode === 'year' && $stats['total_distance'] > 0)): ?>
<!-- グラフ描画スクリプト -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // グラフの共通設定
    Chart.defaults.font.family = 'Helvetica, Arial, sans-serif';
    Chart.defaults.color = '#4b5563'; // text-gray-600
    
    <?php if ($view_mode === 'month'): ?>
    // 日別練習距離グラフ（月間ビュー）
    const dailyCtx = document.getElementById('daily-distance-chart')?.getContext('2d');
    
    if (dailyCtx) {
        const dailyData = {
            labels: [
                <?php 
                $days = [];
                foreach ($stats['daily_data'] as $dayData) {
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
                    foreach ($stats['daily_data'] as $dayData) {
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
    }
    
    // 泳法割合グラフ（月間ビュー）
    const strokeCtx = document.getElementById('stroke-distribution-chart')?.getContext('2d');
    
    if (strokeCtx && <?php echo !empty($stats['stroke_data']) ? 'true' : 'false'; ?>) {
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
                foreach ($stats['stroke_data'] as $stroke) {
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
                    foreach ($stats['stroke_data'] as $stroke) {
                        $strokeDistances[] = $stroke['stroke_distance'];
                    }
                    echo implode(', ', $strokeDistances);
                    ?>
                ],
                backgroundColor: strokeColors.slice(0, <?php echo count($stats['stroke_data']); ?>),
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
    }
    <?php else: ?>
    // 月別練習距離グラフ（年間ビュー）
    const monthlyCtx = document.getElementById('monthly-distance-chart')?.getContext('2d');
    
    if (monthlyCtx) {
        const monthlyData = {
            labels: ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
            datasets: [{
                label: '練習距離',
                data: [
                    <?php
                    $monthlyDistances = [];
                    for ($i = 1; $i <= 12; $i++) {
                        $monthlyDistances[] = $stats['monthly_data'][$i]['total_distance'] ?? 0;
                    }
                    echo implode(', ', $monthlyDistances);
                    ?>
                ],
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1,
                yAxisID: 'y'
            }, {
                label: '練習回数',
                data: [
                    <?php
                    $monthlySessions = [];
                    for ($i = 1; $i <= 12; $i++) {
                        $monthlySessions[] = $stats['monthly_data'][$i]['session_count'] ?? 0;
                    }
                    echo implode(', ', $monthlySessions);
                    ?>
                ],
                backgroundColor: 'rgba(16, 185, 129, 0.5)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 1,
                type: 'line',
                yAxisID: 'y1'
            }]
        };
        
        new Chart(monthlyCtx, {
            type: 'bar',
            data: monthlyData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: '距離 (m)'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        type: 'linear',
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                        title: {
                            display: true,
                            text: '練習回数'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: '月'
                        }
                    }
                }
            }
        });
    }
    
    // 泳法割合グラフ（年間ビュー）
    const yearStrokeCtx = document.getElementById('year-stroke-distribution-chart')?.getContext('2d');
    
    if (yearStrokeCtx && <?php echo !empty($stats['stroke_data']) ? 'true' : 'false'; ?>) {
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
        
        const yearStrokeData = {
            labels: [
                <?php 
                $labels = [];
                foreach ($stats['stroke_data'] as $stroke) {
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
                    foreach ($stats['stroke_data'] as $stroke) {
                        $strokeDistances[] = $stroke['stroke_distance'];
                    }
                    echo implode(', ', $strokeDistances);
                    ?>
                ],
                backgroundColor: strokeColors.slice(0, <?php echo count($stats['stroke_data']); ?>),
                borderWidth: 1
            }]
        };
        
        new Chart(yearStrokeCtx, {
            type: 'doughnut',
            data: yearStrokeData,
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
    }
    
    // 目標達成率グラフ
    const goalCtx = document.getElementById('goal-achievement-chart')?.getContext('2d');
    
    if (goalCtx && <?php echo !empty($stats['goal_achievement']) ? 'true' : 'false'; ?>) {
        const goalData = {
            labels: [
                <?php
                $months = [];
                foreach ($stats['goal_achievement'] as $month => $data) {
                    $months[] = "'" . $monthNames[$month] . "'";
                }
                echo implode(', ', $months);
                ?>
            ],
            datasets: [{
                label: '距離目標達成率',
                data: [
                    <?php
                    $distancePercentages = [];
                    foreach ($stats['goal_achievement'] as $data) {
                        $distancePercentages[] = $data['distance_percentage'];
                    }
                    echo implode(', ', $distancePercentages);
                    ?>
                ],
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }, {
                label: '回数目標達成率',
                data: [
                    <?php
                    $sessionPercentages = [];
                    foreach ($stats['goal_achievement'] as $data) {
                        $sessionPercentages[] = $data['sessions_percentage'];
                    }
                    echo implode(', ', $sessionPercentages);
                    ?>
                ],
                backgroundColor: 'rgba(16, 185, 129, 0.5)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 1
            }]
        };
        
        new Chart(goalCtx, {
            type: 'bar',
            data: goalData,
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
                    },
                    x: {
                        title: {
                            display: true,
                            text: '月'
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
});
</script>
<?php endif; ?>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>