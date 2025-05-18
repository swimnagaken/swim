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
    
    <div class="text-center py-8">
        <p class="text-gray-500 mb-6">
            まだデータがありません。<br>練習を記録すると、統計情報が表示されます。
        </p>
        <a href="practice.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg inline-flex items-center">
            <i class="fas fa-plus mr-2"></i>
            練習を記録する
        </a>
    </div>
</div>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>