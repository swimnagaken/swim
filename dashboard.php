<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// ページタイトル
$page_title = "ダッシュボード";

// ログイン必須
requireLogin();

// ユーザー情報の取得
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// 現在の年月を取得
$currentYear = date('Y');
$currentMonth = date('n');
$currentDate = date('Y-m-d');

// ダッシュボードデータの初期化
$dashboardData = [
    // 月間サマリー
    'monthly' => [
        'total_distance' => 0,
        'session_count' => 0,
        'avg_distance' => 0,
        'best_distance' => 0,
        'best_date' => null,
    ],
    
    // 年間サマリー
    'yearly' => [
        'total_distance' => 0,
        'session_count' => 0,
        'avg_distance' => 0,
    ],
    
    // 累計サマリー
    'total' => [
        'total_distance' => 0,
        'session_count' => 0,
        'avg_distance' => 0,
    ],
    
    // 目標データ
    'goals' => [
        'distance_goal' => 0,
        'sessions_goal' => 0,
        'distance_achieved' => 0,
        'sessions_achieved' => 0,
        'distance_percentage' => 0,
        'sessions_percentage' => 0,
    ],
    
    // 練習履歴
    'recent_practices' => [],
    
    // カレンダーデータ
    'calendar_data' => [],
    
    // 継続状況
    'streak' => [
        'current' => 0,
        'max' => 0,
    ],
    
    // 自己ベスト
    'personal_bests' => [],
    
    // トレンドデータ
    'trend_data' => [],
];

try {
    $db = getDbConnection();
    
    // 月間データの取得
    $startOfMonth = date('Y-m-01');
    $endOfMonth = date('Y-m-t');
    
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as session_count,
            SUM(total_distance) as total_distance,
            MAX(total_distance) as best_distance,
            practice_date as best_date
        FROM practice_sessions
        WHERE user_id = ? AND practice_date BETWEEN ? AND ?
        GROUP BY practice_date
        ORDER BY total_distance DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $startOfMonth, $endOfMonth]);
    $monthlyBest = $stmt->fetch();
    
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as session_count,
            SUM(total_distance) as total_distance
        FROM practice_sessions
        WHERE user_id = ? AND practice_date BETWEEN ? AND ?
    ");
    $stmt->execute([$userId, $startOfMonth, $endOfMonth]);
    $monthlyTotal = $stmt->fetch();
    
    if ($monthlyTotal) {
        $dashboardData['monthly']['total_distance'] = (int)$monthlyTotal['total_distance'];
        $dashboardData['monthly']['session_count'] = (int)$monthlyTotal['session_count'];
        if ($dashboardData['monthly']['session_count'] > 0) {
            $dashboardData['monthly']['avg_distance'] = round($dashboardData['monthly']['total_distance'] / $dashboardData['monthly']['session_count']);
        }
        
        if ($monthlyBest) {
            $dashboardData['monthly']['best_distance'] = (int)$monthlyBest['best_distance'];
            $dashboardData['monthly']['best_date'] = $monthlyBest['best_date'];
        }
    }
    
    // 年間データの取得
    $startOfYear = date('Y-01-01');
    $endOfYear = date('Y-12-31');
    
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as session_count,
            SUM(total_distance) as total_distance
        FROM practice_sessions
        WHERE user_id = ? AND practice_date BETWEEN ? AND ?
    ");
    $stmt->execute([$userId, $startOfYear, $endOfYear]);
    $yearlyTotal = $stmt->fetch();
    
    if ($yearlyTotal) {
        $dashboardData['yearly']['total_distance'] = (int)$yearlyTotal['total_distance'];
        $dashboardData['yearly']['session_count'] = (int)$yearlyTotal['session_count'];
        if ($dashboardData['yearly']['session_count'] > 0) {
            $dashboardData['yearly']['avg_distance'] = round($dashboardData['yearly']['total_distance'] / $dashboardData['yearly']['session_count']);
        }
    }
    
    // 総合データの取得
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as session_count,
            SUM(total_distance) as total_distance
        FROM practice_sessions
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $total = $stmt->fetch();
    
    if ($total) {
        $dashboardData['total']['total_distance'] = (int)$total['total_distance'];
        $dashboardData['total']['session_count'] = (int)$total['session_count'];
        if ($dashboardData['total']['session_count'] > 0) {
            $dashboardData['total']['avg_distance'] = round($dashboardData['total']['total_distance'] / $dashboardData['total']['session_count']);
        }
    }
    
    // 月間目標の取得
    $stmt = $db->prepare("
        SELECT * FROM monthly_goals
        WHERE user_id = ? AND year = ? AND month = ?
    ");
    $stmt->execute([$userId, $currentYear, $currentMonth]);
    $monthlyGoal = $stmt->fetch();
    
    if ($monthlyGoal) {
        $dashboardData['goals']['distance_goal'] = (int)$monthlyGoal['distance_goal'];
        $dashboardData['goals']['sessions_goal'] = (int)$monthlyGoal['sessions_goal'];
        $dashboardData['goals']['distance_achieved'] = $dashboardData['monthly']['total_distance'];
        $dashboardData['goals']['sessions_achieved'] = $dashboardData['monthly']['session_count'];
        
        if ($dashboardData['goals']['distance_goal'] > 0) {
            $dashboardData['goals']['distance_percentage'] = min(100, round(($dashboardData['goals']['distance_achieved'] / $dashboardData['goals']['distance_goal']) * 100));
        }
        
        if ($dashboardData['goals']['sessions_goal'] > 0) {
            $dashboardData['goals']['sessions_percentage'] = min(100, round(($dashboardData['goals']['sessions_achieved'] / $dashboardData['goals']['sessions_goal']) * 100));
        }
    }
    
    // 最近の練習記録を取得
    $stmt = $db->prepare("
        SELECT p.*, pl.pool_name, pl.pool_length
        FROM practice_sessions p
        LEFT JOIN pools pl ON p.pool_id = pl.pool_id
        WHERE p.user_id = ?
        ORDER BY p.practice_date DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $dashboardData['recent_practices'] = $stmt->fetchAll();
    
    // カレンダーデータの取得（当月の練習日）
    $stmt = $db->prepare("
        SELECT practice_date, total_distance
        FROM practice_sessions
        WHERE user_id = ? AND YEAR(practice_date) = ? AND MONTH(practice_date) = ?
    ");
    $stmt->execute([$userId, $currentYear, $currentMonth]);
    $calendarData = $stmt->fetchAll();
    
    // カレンダーデータの整形
    $formattedCalendarData = [];
    foreach ($calendarData as $day) {
        $formattedCalendarData[$day['practice_date']] = $day['total_distance'];
    }
    $dashboardData['calendar_data'] = $formattedCalendarData;
    
    // 継続状況の計算
    $practicesDates = [];
    
    // 過去30日の練習状況を取得
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    $stmt = $db->prepare("
        SELECT practice_date
        FROM practice_sessions
        WHERE user_id = ? AND practice_date >= ?
        ORDER BY practice_date
    ");
    $stmt->execute([$userId, $thirtyDaysAgo]);
    $practiceDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 日付ごとの練習有無を記録
    foreach ($practiceDates as $date) {
        $practicesDates[strtotime($date)] = 1;
    }
    
    // 現在の継続日数を計算
    $currentStreak = 0;
    $today = strtotime(date('Y-m-d'));
    $maxStreak = 0;
    
    // 現在の継続日数を計算（最大過去30日まで）
    for ($i = 0; $i <= 30; $i++) {
        $checkDate = strtotime(date('Y-m-d', strtotime("-$i days")));
        if (isset($practicesDates[$checkDate])) {
            $currentStreak++;
        } else {
            break;
        }
    }
    
    // 過去のデータから最長継続日数を計算
    $tempStreak = 0;
    $prevDate = null;
    
    foreach ($practiceDates as $date) {
        $dateTs = strtotime($date);
        
        if ($prevDate === null) {
            $tempStreak = 1;
        } elseif ($dateTs == strtotime('+1 day', strtotime($prevDate))) {
            $tempStreak++;
        } else {
            $tempStreak = 1;
        }
        
        $maxStreak = max($maxStreak, $tempStreak);
        $prevDate = $date;
    }
    
    $dashboardData['streak']['current'] = $currentStreak;
    $dashboardData['streak']['max'] = $maxStreak;
    
    // 自己ベスト記録の取得
    $stmt = $db->prepare("
        SELECT r.*, c.competition_name, c.competition_date
        FROM race_results r
        JOIN competitions c ON r.competition_id = c.competition_id
        WHERE c.user_id = ? AND r.is_personal_best = 1
        ORDER BY r.stroke_type, r.distance
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $dashboardData['personal_bests'] = $stmt->fetchAll();
    
    // トレンドデータの取得（過去12週間のデータ）
    $twelveWeeksAgo = date('Y-m-d', strtotime('-12 weeks'));
    $stmt = $db->prepare("
        SELECT 
            YEARWEEK(practice_date, 1) as yearweek,
            MIN(practice_date) as week_start,
            SUM(total_distance) as weekly_distance,
            COUNT(*) as session_count
        FROM practice_sessions
        WHERE user_id = ? AND practice_date >= ?
        GROUP BY YEARWEEK(practice_date, 1)
        ORDER BY yearweek
    ");
    $stmt->execute([$userId, $twelveWeeksAgo]);
    $dashboardData['trend_data'] = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('ダッシュボードデータ取得エラー: ' . $e->getMessage());
}

// ヘッダーの読み込み
include 'includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold mb-2">ようこそ、<?php echo h($username); ?>さん</h1>
    <p class="text-gray-600">あなたの水泳練習データをここで管理しましょう。</p>
</div>

<!-- ダッシュボードコンテンツ -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- 今月の練習概要 -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4 flex items-center">
            <i class="fas fa-calendar-check text-blue-600 mr-2"></i>
            今月の練習
        </h2>
        <div class="grid grid-cols-2 gap-4">
            <div class="text-center p-3 bg-blue-50 rounded-lg">
                <p class="text-gray-600 text-sm">合計距離</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo number_format($dashboardData['monthly']['total_distance']); ?>m</p>
            </div>
            <div class="text-center p-3 bg-green-50 rounded-lg">
                <p class="text-gray-600 text-sm">練習回数</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $dashboardData['monthly']['session_count']; ?>回</p>
            </div>
        </div>
        <?php if ($dashboardData['goals']['distance_goal'] > 0 || $dashboardData['goals']['sessions_goal'] > 0): ?>
        <div class="mt-4">
            <?php if ($dashboardData['goals']['distance_goal'] > 0): ?>
            <p class="text-gray-600 text-sm">距離目標達成率</p>
            <div class="bg-gray-200 rounded-full h-4 mt-2">
                <div class="bg-blue-600 rounded-full h-4" style="width: <?php echo $dashboardData['goals']['distance_percentage']; ?>%"></div>
            </div>
            <p class="text-right text-sm mt-1"><?php echo $dashboardData['goals']['distance_percentage']; ?>% 完了</p>
            <?php endif; ?>
            
            <?php if ($dashboardData['goals']['sessions_goal'] > 0): ?>
            <p class="text-gray-600 text-sm mt-3">練習回数目標達成率</p>
            <div class="bg-gray-200 rounded-full h-4 mt-2">
                <div class="bg-green-600 rounded-full h-4" style="width: <?php echo $dashboardData['goals']['sessions_percentage']; ?>%"></div>
            </div>
            <p class="text-right text-sm mt-1"><?php echo $dashboardData['goals']['sessions_percentage']; ?>% 完了</p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="mt-4 text-center">
            <p class="text-gray-500 text-sm">
                月間目標が設定されていません。<br>
                <a href="profile.php" class="text-blue-600 hover:underline">プロフィールページ</a>から設定できます。
            </p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- 継続状況 -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4 flex items-center">
            <i class="fas fa-fire text-orange-500 mr-2"></i>
            継続状況
        </h2>
        <div class="grid grid-cols-2 gap-4">
            <div class="text-center p-3 bg-orange-50 rounded-lg">
                <p class="text-gray-600 text-sm">現在の継続</p>
                <p class="text-2xl font-bold text-orange-600"><?php echo $dashboardData['streak']['current']; ?>日</p>
            </div>
            <div class="text-center p-3 bg-purple-50 rounded-lg">
                <p class="text-gray-600 text-sm">最長記録</p>
                <p class="text-2xl font-bold text-purple-600"><?php echo $dashboardData['streak']['max']; ?>日</p>
            </div>
        </div>
        <div class="mt-4">
            <p class="text-gray-600 text-sm">今週の練習日</p>
            <div class="flex justify-between mt-2">
                <?php
                $days = ['日', '月', '火', '水', '木', '金', '土'];
                $today = date('w');
                $weekStart = date('Y-m-d', strtotime('-' . $today . ' days'));
                
                for ($i = 0; $i < 7; $i++) {
                    $checkDate = date('Y-m-d', strtotime($weekStart . ' +' . $i . ' days'));
                    $isToday = $i == $today;
                    $hasPractice = isset($dashboardData['calendar_data'][$checkDate]);
                    
                    $bgClass = $isToday ? 'bg-blue-100 border-blue-400' : ($hasPractice ? 'bg-green-100 border-green-400' : 'bg-gray-100');
                    echo '<div class="text-center w-8 h-8 flex items-center justify-center rounded-full ' . $bgClass . ' border">' . $days[$i] . '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- 練習カレンダー -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4 flex items-center">
            <i class="fas fa-calendar-alt text-indigo-600 mr-2"></i>
            <?php echo date('Y年n月'); ?>
        </h2>
        <div class="calendar-mini">
            <?php
            // 現在の月の日数
            $daysInMonth = date('t');
            // 月の初日の曜日 (0:日 - 6:土)
            $firstDayOfWeek = date('w', strtotime(date('Y-m-01')));
            
            $days = ['日', '月', '火', '水', '木', '金', '土'];
            
            // 曜日の表示
            echo '<div class="grid grid-cols-7 gap-1 text-center mb-1">';
            foreach ($days as $day) {
                echo '<div class="text-xs font-medium">' . $day . '</div>';
            }
            echo '</div>';
            
            // カレンダーグリッドの表示
            echo '<div class="grid grid-cols-7 gap-1">';
            
            // 前月の日を表示（空白セル）
            for ($i = 0; $i < $firstDayOfWeek; $i++) {
                echo '<div class="text-center py-1 text-xs text-gray-300"></div>';
            }
            
            // 今月の日を表示
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = date('Y-m-') . str_pad($day, 2, '0', STR_PAD_LEFT);
                $isToday = $date == date('Y-m-d');
                $hasPractice = isset($dashboardData['calendar_data'][$date]);
                
                $classes = 'text-center py-1 text-xs rounded-full w-6 h-6 flex items-center justify-center mx-auto';
                
                if ($isToday) {
                    $classes .= ' bg-blue-500 text-white';
                } elseif ($hasPractice) {
                    $classes .= ' bg-green-100 text-green-800';
                }
                
                echo '<div class="' . $classes . '">' . $day . '</div>';
            }
            
            // 翌月の日を表示（空白セル）
            $lastDayOfWeek = date('w', strtotime(date('Y-m-' . $daysInMonth)));
            $remainingDays = 6 - $lastDayOfWeek;
            
            for ($i = 0; $i < $remainingDays; $i++) {
                echo '<div class="text-center py-1 text-xs text-gray-300"></div>';
            }
            
            echo '</div>';
            ?>
        </div>
        <div class="mt-4 text-center">
            <a href="practice.php?action=new" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-plus-circle mr-1"></i> 練習を記録する
            </a>
        </div>
    </div>
</div>

<!-- トレンドグラフ -->
<div class="bg-white rounded-lg shadow-md p-6 mb-8">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">
            <i class="fas fa-chart-line text-blue-600 mr-2"></i>
            トレンド分析
        </h2>
        <a href="stats.php" class="text-blue-600 hover:text-blue-800 text-sm">
            詳細統計を見る <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    
    <?php if (count($dashboardData['trend_data']) > 1): ?>
    <div id="trend-chart" class="h-64"></div>
    <?php else: ?>
    <div class="text-center py-8">
        <p class="text-gray-500">
            まだ十分なデータがありません。<br>練習を続けるとトレンドが表示されます。
        </p>
    </div>
    <?php endif; ?>
</div>

<!-- 最近の練習 & 自己ベスト -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- 最近の練習 -->
    <div class="md:col-span-2 bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold">
                <i class="fas fa-history text-blue-600 mr-2"></i>
                最近の練習
            </h2>
            <a href="practice.php" class="text-blue-600 hover:text-blue-800 flex items-center">
                すべて見る <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <?php if (empty($dashboardData['recent_practices'])): ?>
        <div class="text-center py-8">
            <p class="text-gray-500 mb-6">
                まだ練習記録がありません。<br>新しい練習を記録しましょう。
            </p>
            <a href="practice.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                最初の練習を記録する
            </a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="py-3 px-4 text-left">日付</th>
                        <th class="py-3 px-4 text-left">プール</th>
                        <th class="py-3 px-4 text-left">距離</th>
                        <th class="py-3 px-4 text-left">課題</th>
                        <th class="py-3 px-4 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dashboardData['recent_practices'] as $practice): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <?php echo date('Y/m/d (', strtotime($practice['practice_date'])); ?>
                            <?php echo ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($practice['practice_date']))]; ?>
                            <?php echo ')'; ?>
                        </td>
                        <td class="py-3 px-4"><?php echo h($practice['pool_name'] ?? '-'); ?></td>
                        <td class="py-3 px-4"><?php echo number_format($practice['total_distance']); ?>m</td>
                        <td class="py-3 px-4 max-w-xs truncate"><?php echo h($practice['challenge'] ?? '-'); ?></td>
                        <td class="py-3 px-4">
                            <a href="practice.php?action=view&id=<?php echo $practice['session_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                詳細を見る
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- 自己ベスト -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold">
                <i class="fas fa-trophy text-yellow-500 mr-2"></i>
                自己ベスト
            </h2>
            <a href="competition.php?tab=personal-best" class="text-blue-600 hover:text-blue-800 flex items-center">
                すべて見る <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <?php if (empty($dashboardData['personal_bests'])): ?>
        <div class="text-center py-8">
            <p class="text-gray-500 mb-6">
                まだ自己ベスト記録がありません。<br>大会の結果を記録しましょう。
            </p>
            <a href="competition.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                大会を記録する
            </a>
        </div>
        <?php else: ?>
        <div class="space-y-4">
            <?php 
            $strokeNames = [
                'freestyle' => '自由形',
                'backstroke' => '背泳ぎ',
                'breaststroke' => '平泳ぎ',
                'butterfly' => 'バタフライ',
                'im' => '個人メドレー',
                'other' => 'その他'
            ];
            
            foreach ($dashboardData['personal_bests'] as $record): 
                // タイム表示の整形
                $minutes = $record['time_minutes'];
                $seconds = $record['time_seconds'];
                $milliseconds = $record['time_milliseconds'];
                
                $timeDisplay = '';
                if ($minutes > 0) {
                    $timeDisplay .= $minutes . ':';
                    $timeDisplay .= str_pad($seconds, 2, '0', STR_PAD_LEFT);
                } else {
                    $timeDisplay .= $seconds;
                }
                $timeDisplay .= '.' . str_pad($milliseconds, 3, '0', STR_PAD_LEFT);
            ?>
            <div class="p-3 bg-gray-50 rounded-lg">
                <div class="flex justify-between">
                    <div>
                        <span class="text-sm font-medium"><?php echo h($record['distance']); ?>m <?php echo h($strokeNames[$record['stroke_type']] ?? $record['stroke_type']); ?></span>
                    </div>
                    <div class="text-yellow-500">
                        <i class="fas fa-trophy"></i>
                    </div>
                </div>
                <div class="mt-1">
                    <span class="text-xl font-bold"><?php echo h($timeDisplay); ?></span>
                    <span class="text-xs text-gray-500 ml-2"><?php echo date('Y/m/d', strtotime($record['competition_date'])); ?></span>
                </div>
                <div class="mt-1 text-xs text-gray-600 truncate">
                    <?php echo h($record['competition_name']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- クイックアクション -->
<div class="flex flex-wrap gap-4">
    <a href="practice.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg flex items-center">
        <i class="fas fa-plus mr-2"></i>
        練習を記録する
    </a>
    <a href="competition.php?action=new" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg flex items-center">
        <i class="fas fa-trophy mr-2"></i>
        大会結果を追加
    </a>
    <a href="profile.php" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 px-6 rounded-lg flex items-center">
        <i class="fas fa-bullseye mr-2"></i>
        月間目標を設定
    </a>
</div>

<?php if (count($dashboardData['trend_data']) > 1): ?>
<!-- トレンドグラフ用スクリプト -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const trendCtx = document.getElementById('trend-chart').getContext('2d');
    
    const trendData = {
        labels: [
            <?php
            $labels = [];
            foreach ($dashboardData['trend_data'] as $week) {
                $weekStart = new DateTime($week['week_start']);
                $labels[] = "'" . $weekStart->format('n/j') . '週' . "'";
            }
            echo implode(', ', $labels);
            ?>
        ],
        datasets: [{
            label: '週間練習距離',
            data: [
                <?php
                $distances = [];
                foreach ($dashboardData['trend_data'] as $week) {
                    $distances[] = $week['weekly_distance'];
                }
                echo implode(', ', $distances);
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
                $counts = [];
                foreach ($dashboardData['trend_data'] as $week) {
                    $counts[] = $week['session_count'];
                }
                echo implode(', ', $counts);
                ?>
            ],
            backgroundColor: 'rgba(16, 185, 129, 0.5)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 1,
            type: 'line',
            yAxisID: 'y1'
        }]
    };
    
    new Chart(trendCtx, {
        type: 'bar',
        data: trendData,
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
                        text: '週'
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>