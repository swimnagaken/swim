<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// ページタイトル
$page_title = "大会記録";

// ログイン必須
requireLogin();

// アクションの取得（list, new, view, edit）
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$competitionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ヘッダーの読み込み
include 'includes/header.php';
?>

<?php if ($action === 'new'): ?>
    <!-- 新規大会記録フォーム -->
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">新しい大会記録</h1>
        <a href="competition.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-1"></i> 大会一覧に戻る
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" action="api/competition.php">
            <!-- CSRFトークン -->
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- 大会名 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="competition_name">大会名</label>
                    <input
                        type="text"
                        id="competition_name"
                        name="competition_name"
                        placeholder="第45回市民水泳大会"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                        required
                    >
                </div>
                
                <!-- 開催日 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="competition_date">開催日</label>
                    <input
                        type="date"
                        id="competition_date"
                        name="competition_date"
                        value="<?php echo date('Y-m-d'); ?>"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                        required
                    >
                </div>
                
                <!-- 開催場所 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="location">開催場所</label>
                    <input
                        type="text"
                        id="location"
                        name="location"
                        placeholder="市民プール"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                </div>
            </div>
            
            <!-- メモ -->
            <div class="mb-6">
                <label class="block text-gray-700 mb-2" for="notes">メモ</label>
                <textarea
                    id="notes"
                    name="notes"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 h-24"
                    placeholder="大会の様子や調子などのメモ..."
                ></textarea>
            </div>
            
           <!-- 種目と記録 -->
           <div class="mb-6">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-semibold">種目と記録</h3>
                    <p class="text-sm text-gray-600">※詳細情報は準備中です</p>
                </div>
                <div class="bg-gray-50 p-4 rounded-md">
                    <p class="text-center text-gray-500 py-4">種目と記録の詳細機能は準備中です。<br>現在は大会の基本情報のみ記録できます。</p>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg"
                >
                    大会を記録する
                </button>
            </div>
        </form>
    </div>
<?php elseif ($action === 'view' && $competitionId > 0): ?>
    <!-- 大会詳細表示 -->
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">大会詳細</h1>
        <a href="competition.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-1"></i> 大会一覧に戻る
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <p class="text-center text-gray-500 py-4">大会詳細の表示機能は準備中です。</p>
    </div>
<?php else: ?>
    <!-- 大会一覧 -->
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">大会記録</h1>
        <a href="competition.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> 新しい大会を記録
        </a>
    </div>
    
    <!-- タブ -->
    <div class="mb-6">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex">
                <a href="#" class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-4 border-b-2 font-medium text-sm">
                    大会一覧
                </a>
                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-4 border-b-2 font-medium text-sm">
                    自己ベスト記録
                </a>
            </nav>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="text-center py-8">
            <p class="text-gray-500 mb-6">
                まだ大会記録がありません。<br>新しい大会記録を追加しましょう。
            </p>
            <a href="competition.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                最初の大会記録を追加する
            </a>
        </div>
    </div>
<?php endif; ?>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>