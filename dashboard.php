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

// 月間の練習データを取得
$monthlyStats = [
    'total_distance' => 0,
    'session_count' => 0,
    'avg_distance' => 0
];

try {
    $db = getDbConnection();
    
    // 月の最初と最後の日付
    $startDate = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
    $lastDay = date('t', strtotime($startDate)); // 月の最終日を取得
    $endDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $lastDay);
    
    // 月間の総距離と練習セッション数を取得
    $stmt = $db->prepare("
        SELECT SUM(total_distance) as total, COUNT(*) as count
        FROM practice_sessions
        WHERE user_id = ? AND practice_date BETWEEN ? AND ?
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $result = $stmt->fetch();
    
    if ($result && $result['count'] > 0) {
        $monthlyStats['total_distance'] = (int)$result['total'];
        $monthlyStats['session_count'] = (int)$result['count'];
        if ($monthlyStats['session_count'] > 0) {
            $monthlyStats['avg_distance'] = round($monthlyStats['total_distance'] / $monthlyStats['session_count']);
        }
    }
    
    // 最近の練習記録を取得
    $stmt = $db->prepare("
        SELECT p.*, pl.pool_name
        FROM practice_sessions p
        LEFT JOIN pools pl ON p.pool_id = pl.pool_id
        WHERE p.user_id = ?
        ORDER BY p.practice_date DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentPractices = $stmt->fetchAll();
    
    // 継続状況の計算
    // ある日に練習があれば1、なければ0を設定した配列を作成
    $practiceDays = [];
    
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
        $practiceDays[strtotime($date)] = 1;
    }
    
    // 現在の継続日数を計算
    $currentStreak = 0;
    $today = strtotime(date('Y-m-d'));
    $maxStreak = 0;
    
    // 現在の継続日数を計算（最大過去30日まで）
    for ($i = 0; $i <= 30; $i++) {
        $checkDate = strtotime(date('Y-m-d', strtotime("-$i days")));
        if (isset($practiceDays[$checkDate])) {
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

    // 月間目標の取得
    $monthlyGoal = null;
    $stmt = $db->prepare("
        SELECT * FROM monthly_goals
        WHERE user_id = ? AND year = ? AND month = ?
    ");
    $stmt->execute([$userId, $currentYear, $currentMonth]);
    $monthlyGoal = $stmt->fetch();
    
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
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <!-- 今月の練習概要 -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4 flex items-center">
            <i class="fas fa-calendar-check text-blue-600 mr-2"></i>
            今月の練習
        </h2>
        <div class="grid grid-cols-2 gap-4">
            <div class="text-center p-3 bg-blue-50 rounded-lg">
                <p class="text-gray-600 text-sm">合計距離</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo number_format($monthlyStats['total_distance']); ?>m</p>
            </div>
            <div class="text-center p-3 bg-green-50 rounded-lg">
                <p class="text-gray-600 text-sm">練習回数</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $monthlyStats['session_count']; ?>回</p>
            </div>
        </div>
        <?php if ($monthlyGoal && ($monthlyGoal['distance_goal'] > 0 || $monthlyGoal['sessions_goal'] > 0)): ?>
        <div class="mt-4">
            <?php if ($monthlyGoal['distance_goal'] > 0): ?>
            <p class="text-gray-600 text-sm">距離目標達成率</p>
            <div class="bg-gray-200 rounded-full h-4 mt-2">
                <?php $distancePercentage = min(100, round(($monthlyStats['total_distance'] / $monthlyGoal['distance_goal']) * 100)); ?>
                <div class="bg-blue-600 rounded-full h-4" style="width: <?php echo $distancePercentage; ?>%"></div>
            </div>
            <p class="text-right text-sm mt-1"><?php echo $distancePercentage; ?>% 完了</p>
            <?php endif; ?>
            
            <?php if ($monthlyGoal['sessions_goal'] > 0): ?>
            <p class="text-gray-600 text-sm mt-3">練習回数目標達成率</p>
            <div class="bg-gray-200 rounded-full h-4 mt-2">
                <?php $sessionsPercentage = min(100, round(($monthlyStats['session_count'] / $monthlyGoal['sessions_goal']) * 100)); ?>
                <div class="bg-green-600 rounded-full h-4" style="width: <?php echo $sessionsPercentage; ?>%"></div>
            </div>
            <p class="text-right text-sm mt-1"><?php echo $sessionsPercentage; ?>% 完了</p>
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
                <p class="text-2xl font-bold text-orange-600"><?php echo $currentStreak; ?>日</p>
            </div>
            <div class="text-center p-3 bg-purple-50 rounded-lg">
                <p class="text-gray-600 text-sm">最長記録</p>
                <p class="text-2xl font-bold text-purple-600"><?php echo $maxStreak; ?>日</p>
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
                    $hasPractice = isset($practiceDays[strtotime($checkDate)]);
                    
                    $bgClass = $isToday ? 'bg-blue-100 border-blue-400' : ($hasPractice ? 'bg-green-100 border-green-400' : 'bg-gray-100');
                    echo '<div class="text-center w-8 h-8 flex items-center justify-center rounded-full ' . $bgClass . ' border">' . $days[$i] . '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- 自己ベスト -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4 flex items-center">
            <i class="fas fa-trophy text-yellow-500 mr-2"></i>
            自己ベスト
        </h2>
        <div class="text-center py-8">
            <p class="text-gray-500">
                まだ記録がありません。<br>大会の結果を記録しましょう。
            </p>
        </div>
    </div>
</div>

<!-- 最近の練習 -->
<div class="bg-white rounded-lg shadow-md p-6 mb-8">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold">
            <i class="fas fa-history text-blue-600 mr-2"></i>
            最近の練習
        </h2>
        <a href="practice.php" class="text-blue-600 hover:text-blue-800 flex items-center">
            すべて見る <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    
    <?php if (empty($recentPractices)): ?>
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
                <?php foreach ($recentPractices as $practice): ?>
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

<?php
// フッターの読み込み
include 'includes/footer.php';
?>