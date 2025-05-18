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
                <p class="text-2xl font-bold text-blue-600">0m</p>
            </div>
            <div class="text-center p-3 bg-green-50 rounded-lg">
                <p class="text-gray-600 text-sm">練習回数</p>
                <p class="text-2xl font-bold text-green-600">0回</p>
            </div>
        </div>
        <div class="mt-4">
            <p class="text-gray-600 text-sm">目標達成率</p>
            <div class="bg-gray-200 rounded-full h-4 mt-2">
                <div class="bg-blue-600 rounded-full h-4" style="width: 0%"></div>
            </div>
            <p class="text-right text-sm mt-1">0% 完了</p>
        </div>
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
                <p class="text-2xl font-bold text-orange-600">0日</p>
            </div>
            <div class="text-center p-3 bg-purple-50 rounded-lg">
                <p class="text-gray-600 text-sm">最長記録</p>
                <p class="text-2xl font-bold text-purple-600">0日</p>
            </div>
        </div>
        <div class="mt-4">
            <p class="text-gray-600 text-sm">今週の練習日</p>
            <div class="flex justify-between mt-2">
                <?php
                $days = ['日', '月', '火', '水', '木', '金', '土'];
                foreach ($days as $index => $day) {
                    $isToday = date('w') == $index;
                    $bgClass = $isToday ? 'bg-blue-100 border-blue-400' : 'bg-gray-100';
                    echo '<div class="text-center w-8 h-8 flex items-center justify-center rounded-full ' . $bgClass . ' border">' . $day . '</div>';
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
    
    <div class="text-center py-8">
        <p class="text-gray-500 mb-6">
            まだ練習記録がありません。<br>新しい練習を記録しましょう。
        </p>
        <a href="practice.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg inline-flex items-center">
            <i class="fas fa-plus mr-2"></i>
            最初の練習を記録する
        </a>
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
    <a href="#" id="set-goal-btn" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 px-6 rounded-lg flex items-center">
        <i class="fas fa-bullseye mr-2"></i>
        月間目標を設定
    </a>
</div>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>