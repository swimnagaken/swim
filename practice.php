<?php
// 設定ファイルの読み込み
require_once 'config/config.php';
require_once 'includes/search_functions.php'; // 検索関数を読み込み

// ページタイトル
$page_title = "練習記録";

// ログイン必須
requireLogin();

// アクションの取得（list, new, view, edit, search）
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$sessionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 検索・フィルターが指定されているか確認
$isFiltered = false;
$filters = [];

// GETパラメータからフィルター条件を取得
if ($action === 'list' || $action === 'search') {
    // 日付範囲
    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = $_GET['date_from'];
        $isFiltered = true;
    }
    
    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = $_GET['date_to'];
        $isFiltered = true;
    }
    
    // 距離範囲
    if (!empty($_GET['distance_min'])) {
        $filters['distance_min'] = (int)$_GET['distance_min'];
        $isFiltered = true;
    }
    
    if (!empty($_GET['distance_max'])) {
        $filters['distance_max'] = (int)$_GET['distance_max'];
        $isFiltered = true;
    }
    
    // プール
    if (!empty($_GET['pool_id'])) {
        $filters['pool_id'] = (int)$_GET['pool_id'];
        $isFiltered = true;
    }
    
    // 泳法
    if (!empty($_GET['stroke_type'])) {
        $filters['stroke_type'] = $_GET['stroke_type'];
        $isFiltered = true;
    }
    
    // キーワード
    if (!empty($_GET['keyword'])) {
        $filters['keyword'] = $_GET['keyword'];
        $isFiltered = true;
    }
    
    // 並び順
    if (!empty($_GET['sort_by'])) {
        $filters['sort_by'] = $_GET['sort_by'];
    }
}

// ページネーション
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10; // 1ページあたりの表示件数

// フィルターオプションの取得
try {
    $db = getDbConnection();
    $filterOptions = getFilterOptions($db, $_SESSION['user_id']);
} catch (PDOException $e) {
    error_log('フィルターオプション取得エラー: ' . $e->getMessage());
    $filterOptions = [
        'pools' => [],
        'stroke_types' => [],
        'sort_options' => [],
        'distance_range' => ['min' => 0, 'max' => 10000]
    ];
}

// ヘッダーの読み込み
include 'includes/header.php';
?>

<?php if ($action === 'new'): ?>
    <!-- 新規練習記録フォーム - 既存コードを使用 -->
    <!-- ここは既存の新規練習記録フォームをそのまま表示 -->
    
<?php elseif ($action === 'view' && $sessionId > 0): ?>
    <!-- 練習詳細表示 - 既存コードを使用 -->
    <!-- ここは既存の練習詳細表示をそのまま表示 -->
    
<?php elseif ($action === 'edit' && $sessionId > 0): ?>
    <!-- 練習編集フォーム - 既存コードを使用 -->
    <!-- ここは既存の練習編集フォームをそのまま表示 -->
    
<?php else: ?>
    <!-- 練習一覧 & 検索フィルター -->
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">練習記録</h1>
        <div class="flex space-x-2">
            <button id="toggle-filter-btn" class="bg-blue-100 text-blue-600 hover:bg-blue-200 px-4 py-2 rounded-lg flex items-center">
                <i class="fas fa-filter mr-2"></i> 詳細検索
            </button>
            <a href="practice.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-plus mr-2"></i> 新しい練習を記録
            </a>
        </div>
    </div>
    
    <!-- 詳細フィルター -->
    <div id="filter-panel" class="bg-white rounded-lg shadow-md p-6 mb-6 <?php echo $isFiltered ? '' : 'hidden'; ?>">
        <h2 class="text-lg font-semibold mb-4">詳細検索</h2>
        
        <form id="filter-form" action="practice.php" method="GET">
            <input type="hidden" name="action" value="search">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- 日付範囲 -->
                <div>
                    <label for="date-from" class="block text-sm text-gray-600 mb-1">開始日</label>
                    <input 
                        type="date" 
                        id="date-from" 
                        name="date_from" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200"
                        value="<?php echo $filters['date_from'] ?? ''; ?>"
                    >
                </div>
                
                <div>
                    <label for="date-to" class="block text-sm text-gray-600 mb-1">終了日</label>
                    <input 
                        type="date" 
                        id="date-to" 
                        name="date_to" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200"
                        value="<?php echo $filters['date_to'] ?? ''; ?>"
                    >
                </div>
                
                <!-- プール選択 -->
                <div>
                    <label for="pool-id" class="block text-sm text-gray-600 mb-1">プール</label>
                    <select 
                        id="pool-id" 
                        name="pool_id" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200"
                    >
                        <option value="">すべて</option>
                        <?php foreach ($filterOptions['pools'] as $pool): ?>
                        <option 
                            value="<?php echo $pool['pool_id']; ?>"
                            <?php echo (isset($filters['pool_id']) && $filters['pool_id'] == $pool['pool_id']) ? 'selected' : ''; ?>
                        >
                            <?php echo h($pool['pool_name']); ?>
                            <?php echo $pool['is_favorite'] ? '⭐' : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- 距離範囲 -->
                <div>
                    <label for="distance-min" class="block text-sm text-gray-600 mb-1">最小距離 (m)</label>
                    <input 
                        type="number" 
                        id="distance-min" 
                        name="distance_min" 
                        min="0" 
                        step="100" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200"
                        value="<?php echo $filters['distance_min'] ?? ''; ?>"
                        placeholder="例: 1000"
                    >
                </div>
                
                <div>
                    <label for="distance-max" class="block text-sm text-gray-600 mb-1">最大距離 (m)</label>
                    <input 
                        type="number" 
                        id="distance-max" 
                        name="distance_max" 
                        min="0" 
                        step="100" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200"
                        value="<?php echo $filters['distance_max'] ?? ''; ?>"
                        placeholder="例: 5000"
                    >
                </div>
                
                <!-- 泳法 -->
                <div>
                    <label for="stroke-type" class="block text-sm text-gray-600 mb-1">泳法</label>
                    <select 
                        id="stroke-type" 
                        name="stroke_type" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200"
                    >
                        <option value="">すべて</option>
                        <?php foreach ($filterOptions['stroke_types'] as $value => $label): ?>
                        <option 
                            value="<?php echo $value; ?>"
                            <?php echo (isset($filters['stroke_type']) && $filters['stroke_type'] === $value) ? 'selected' : ''; ?>
                        ><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- キーワード検索 -->
                <div class="md:col-span-2">
                    <label for="keyword" class="block text-sm text-gray-600 mb-1">キーワード</label>
                    <input 
                        type="text" 
                        id="keyword" 
                        name="keyword" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200"
                        value="<?php echo $filters['keyword'] ?? ''; ?>"
                        placeholder="課題、メモなどを検索..."
                    >
                </div>
                
                <!-- 並び順 -->
                <div>
                    <label for="sort-by" class="block text-sm text-gray-600 mb-1">並び順</label>
                    <select 
                        id="sort-by" 
                        name="sort_by" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200"
                    >
                        <?php foreach ($filterOptions['sort_options'] as $value => $label): ?>
                        <option 
                            value="<?php echo $value; ?>"
                            <?php echo (isset($filters['sort_by']) && $filters['sort_by'] === $value) ? 'selected' : ''; ?>
                        ><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-between mt-4">
                <a href="practice.php" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times mr-1"></i> リセット
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md">
                    <i class="fas fa-search mr-1"></i> 検索
                </button>
            </div>
        </form>
    </div>
    
    <?php
    // 練習一覧を取得
    $searchResults = [];
    try {
        $db = getDbConnection();
        $searchResults = searchPractices($db, $_SESSION['user_id'], $filters, $page, $limit);
        $practices = $searchResults['practices'];
    } catch (PDOException $e) {
        error_log('練習一覧取得エラー: ' . $e->getMessage());
        $practices = [];
        $searchResults = [
            'total_count' => 0,
            'page' => 1,
            'limit' => $limit,
            'total_pages' => 0
        ];
    }
    ?>
    
    <!-- 検索結果サマリー（フィルター適用時のみ表示） -->
    <?php if ($isFiltered): ?>
    <div class="bg-blue-50 rounded-lg p-3 mb-4 text-sm">
        <div class="flex justify-between items-center">
            <div>
                <span class="font-semibold"><?php echo number_format($searchResults['total_count']); ?></span> 件の練習記録が見つかりました
                <?php if ($searchResults['total_count'] > 0): ?>
                （<?php echo $searchResults['page']; ?>/<?php echo $searchResults['total_pages']; ?> ページ）
                <?php endif; ?>
            </div>
            <a href="practice.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-times mr-1"></i> フィルターをクリア
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 練習一覧 -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <?php if (empty($practices)): ?>
        <div class="text-center py-8">
            <?php if ($isFiltered): ?>
            <p class="text-gray-500 mb-6">
                条件に一致する練習記録がありません。<br>検索条件を変更してください。
            </p>
            <?php else: ?>
            <p class="text-gray-500 mb-6">
                まだ練習記録がありません。<br>新しい練習を記録しましょう。
            </p>
            <a href="practice.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                最初の練習を記録する
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="py-3 px-4 text-left">日付</th>
                        <th class="py-3 px-4 text-left">プール</th>
                        <th class="py-3 px-4 text-left">距離</th>
                        <th class="py-3 px-4 text-left">時間</th>
                        <th class="py-3 px-4 text-left">課題</th>
                        <th class="py-3 px-4 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($practices as $practice): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <?php echo date('Y/m/d (', strtotime($practice['practice_date'])); ?>
                            <?php echo ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($practice['practice_date']))]; ?>
                            <?php echo ')'; ?>
                        </td>
                        <td class="py-3 px-4"><?php echo h($practice['pool_name'] ?? '-'); ?></td>
                        <td class="py-3 px-4"><?php echo number_format($practice['total_distance']); ?>m</td>
                        <td class="py-3 px-4">
                            <?php
                            if ($practice['duration']) {
                                $hours = floor($practice['duration'] / 60);
                                $minutes = $practice['duration'] % 60;
                                if ($hours > 0) {
                                    echo $hours . '時間';
                                    if ($minutes > 0) {
                                        echo ' ';
                                    }
                                }
                                if ($minutes > 0 || $hours === 0) {
                                    echo $minutes . '分';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="py-3 px-4 max-w-xs truncate"><?php echo h($practice['challenge'] ?? '-'); ?></td>
                        <td class="py-3 px-4">
                            <div class="flex space-x-2">
                                <a href="practice.php?action=view&id=<?php echo $practice['session_id']; ?>" class="text-blue-600 hover:text-blue-800" title="詳細">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="practice.php?action=edit&id=<?php echo $practice['session_id']; ?>" class="text-green-600 hover:text-green-800" title="編集">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="api/practice.php" class="inline-block" onsubmit="return confirm('この練習記録を削除してもよろしいですか？');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="session_id" value="<?php echo $practice['session_id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800" title="削除">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ページネーション -->
        <?php if ($searchResults['total_pages'] > 1): ?>
        <div class="mt-6 flex justify-center">
            <div class="flex space-x-1">
                <!-- 前のページへ -->
                <?php if ($searchResults['page'] > 1): ?>
                <a 
                    href="practice.php?<?php 
                        $params = array_merge(['page' => $searchResults['page'] - 1], $filters);
                        echo http_build_query($params);
                    ?>" 
                    class="px-4 py-2 border rounded-md hover:bg-gray-50"
                >
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php else: ?>
                <span class="px-4 py-2 border rounded-md text-gray-300 cursor-not-allowed">
                    <i class="fas fa-chevron-left"></i>
                </span>
                <?php endif; ?>
                
                <!-- ページ番号 -->
                <?php
                $startPage = max(1, $searchResults['page'] - 2);
                $endPage = min($searchResults['total_pages'], $searchResults['page'] + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <a 
                    href="practice.php?<?php 
                        $params = array_merge(['page' => $i], $filters);
                        echo http_build_query($params);
                    ?>" 
                    class="px-4 py-2 border rounded-md <?php echo ($i == $searchResults['page']) ? 'bg-blue-50 text-blue-600 font-medium' : 'hover:bg-gray-50'; ?>"
                >
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <!-- 次のページへ -->
                <?php if ($searchResults['page'] < $searchResults['total_pages']): ?>
                <a 
                    href="practice.php?<?php 
                        $params = array_merge(['page' => $searchResults['page'] + 1], $filters);
                        echo http_build_query($params);
                    ?>" 
                    class="px-4 py-2 border rounded-md hover:bg-gray-50"
                >
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php else: ?>
                <span class="px-4 py-2 border rounded-md text-gray-300 cursor-not-allowed">
                    <i class="fas fa-chevron-right"></i>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // フィルターパネルの表示切り替え
        const toggleFilterBtn = document.getElementById('toggle-filter-btn');
        const filterPanel = document.getElementById('filter-panel');
        
        if (toggleFilterBtn && filterPanel) {
            toggleFilterBtn.addEventListener('click', function() {
                filterPanel.classList.toggle('hidden');
            });
        }
    });
    </script>
<?php endif; ?>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>