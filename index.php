<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// ページタイトル
$page_title = "ホーム";

// ヘッダーの読み込み
include 'includes/header.php';
?>

<!-- ヒーローセクション -->
<section class="bg-gradient-to-r from-blue-600 to-blue-400 text-white py-16 -mt-8">
    <div class="container mx-auto px-6">
        <div class="flex flex-col md:flex-row items-center">
            <div class="md:w-1/2 mb-8 md:mb-0">
                <h1 class="text-4xl font-bold mb-4">水泳練習を賢く管理しよう</h1>
                <p class="text-xl mb-6">スイムトラッカーは、スイマーのための練習記録・分析アプリです。日々の練習を記録して、あなたの成長を可視化します。</p>
                <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4">
                    <a href="register.php" class="bg-white text-blue-600 px-6 py-3 rounded-lg font-semibold hover:bg-blue-50 transition text-center">
                        無料で始める
                    </a>
                    <a href="#features" class="bg-transparent border-2 border-white px-6 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-600 transition text-center">
                        機能を見る
                    </a>
                </div>
            </div>
            <div class="md:w-1/2">
                <img src="https://source.unsplash.com/random/600x400/?swimming" alt="水泳選手のイメージ" class="w-full max-w-md mx-auto rounded-lg shadow-lg">
            </div>
        </div>
    </div>
</section>

<!-- 機能紹介セクション -->
<section id="features" class="py-16 bg-white">
    <div class="container mx-auto px-6">
        <h2 class="text-3xl font-bold text-center mb-12">スイムトラッカーの主な機能</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- 機能1 -->
            <div class="bg-blue-50 rounded-lg p-6 transition-transform transform hover:-translate-y-1">
                <div class="text-blue-600 text-4xl mb-4">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3">練習記録管理</h3>
                <p class="text-gray-700">
                    日々の練習メニューを簡単に記録できます。泳法、距離、セット内容など詳細なデータを保存し、練習の履歴を振り返ることができます。
                </p>
            </div>
            
            <!-- 機能2 -->
            <div class="bg-blue-50 rounded-lg p-6 transition-transform transform hover:-translate-y-1">
                <div class="text-blue-600 text-4xl mb-4">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3">データ視覚化</h3>
                <p class="text-gray-700">
                    累計練習距離や泳法割合をグラフで視覚化。月間の目標達成状況や練習の傾向を一目で確認でき、効率的なトレーニング計画に役立ちます。
                </p>
            </div>
            
            <!-- 機能3 -->
            <div class="bg-blue-50 rounded-lg p-6 transition-transform transform hover:-translate-y-1">
                <div class="text-blue-600 text-4xl mb-4">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3">大会記録管理</h3>
                <p class="text-gray-700">
                    大会や記録会の結果を記録。自己ベスト更新を追跡し、スプリットタイムの分析もできるため、効果的な目標設定や改善点の発見に役立ちます。
                </p>
            </div>
        </div>
    </div>
</section>

<!-- CTAセクション -->
<section class="py-16 bg-gradient-to-r from-blue-600 to-blue-400 text-white">
    <div class="container mx-auto px-6 text-center">
        <h2 class="text-3xl font-bold mb-6">今すぐ水泳練習の記録を始めよう</h2>
        <p class="text-xl mb-8 max-w-3xl mx-auto">
            スイムトラッカーで練習を記録し、あなたの成長を可視化しましょう。登録は簡単、完全無料で始められます。
        </p>
        <a href="register.php" class="bg-white text-blue-600 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-blue-50 transition inline-block">
            無料アカウントを作成する
        </a>
    </div>
</section>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>