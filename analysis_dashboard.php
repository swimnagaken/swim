<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// ページタイトル
$page_title = "練習分析ダッシュボード";

// ログイン必須
requireLogin();

// ビュー期間の取得（デフォルトは過去3ヶ月）
$period = isset($_GET['period']) ? $_GET['period'] : '3m';

// ヘッダーの読み込み
include 'includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold">練習分析ダッシュボード</h1>
    <div>
        <form id="period-selector" class="inline-flex">
            <select id="period" name="period" class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="1m" <?php echo $period === '1m' ? 'selected' : ''; ?>>過去1ヶ月</option>
                <option value="3m" <?php echo $period === '3m' ? 'selected' : ''; ?>>過去3ヶ月</option>
                <option value="6m" <?php echo $period === '6m' ? 'selected' : ''; ?>>過去6ヶ月</option>
                <option value="1y" <?php echo $period === '1y' ? 'selected' : ''; ?>>過去1年</option>
                <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>全期間</option>
            </select>
        </form>
    </div>
</div>

<!-- サマリーカード -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm">合計距離</p>
                <h3 class="text-2xl font-bold text-blue-600" id="total-distance">読み込み中...</h3>
            </div>
            <div class="text-blue-500 bg-blue-100 p-3 rounded-full">
                <i class="fas fa-swimming-pool text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm">練習回数</p>
                <h3 class="text-2xl font-bold text-green-600" id="session-count">読み込み中...</h3>
            </div>
            <div class="text-green-500 bg-green-100 p-3 rounded-full">
                <i class="fas fa-calendar-check text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm">平均距離/練習</p>
                <h3 class="text-2xl font-bold text-purple-600" id="avg-distance">読み込み中...</h3>
            </div>
            <div class="text-purple-500 bg-purple-100 p-3 rounded-full">
                <i class="fas fa-tachometer-alt text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm">目標達成率</p>
                <h3 class="text-2xl font-bold text-yellow-600" id="goal-achievement">読み込み中...</h3>
            </div>
            <div class="text-yellow-500 bg-yellow-100 p-3 rounded-full">
                <i class="fas fa-bullseye text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- グラフエリア -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- 練習距離トレンド -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold mb-4">距離トレンド</h2>
        <div class="h-64" id="distance-trend-chart"></div>
    </div>

    <!-- 泳法割合 -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold mb-4">泳法割合</h2>
        <div class="h-64" id="stroke-distribution-chart"></div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- 月間目標達成状況 -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold mb-4">月間目標達成状況</h2>
        <div class="h-64" id="goal-achievement-chart"></div>
    </div>

    <!-- ヒートマップカレンダー -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold mb-4">練習頻度ヒートマップ</h2>
        <div id="heatmap-calendar"></div>
    </div>
</div>

<!-- 詳細データ -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">詳細分析</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 左側：ベスト練習 -->
        <div>
            <h3 class="text-md font-medium mb-3">最長距離の練習</h3>
            <div id="best-distance-sessions">
                <p class="text-center text-gray-500 py-3">読み込み中...</p>
            </div>
        </div>
        
        <!-- 右側：泳法別距離 -->
        <div>
            <h3 class="text-md font-medium mb-3">泳法別距離</h3>
            <div id="stroke-distance-table">
                <p class="text-center text-gray-500 py-3">読み込み中...</p>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js と Calendar Heatmap の読み込み -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-chart-matrix"></script>

<!-- ダッシュボード用JavaScript -->
<script src="assets/js/dashboard_charts.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 期間選択の変更イベント
    document.getElementById('period').addEventListener('change', function() {
        document.getElementById('period-selector').submit();
    });

    // データの読み込みと描画
    loadDashboardData('<?php echo $period; ?>');
});
</script>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>