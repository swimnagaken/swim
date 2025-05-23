<?php
// enhanced_competition.php - 改良版大会記録ページ
require_once 'config/config.php';

// ページタイトル
$page_title = "大会記録";

// ログイン必須
requireLogin();

// アクションの取得
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$competitionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ヘッダーの読み込み
include 'includes/header.php';
?>

<!-- Chart.js読み込み -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if ($action === 'view' && $competitionId > 0): ?>
    <!-- 大会詳細表示（改良版） -->
    <?php
    // 大会情報の取得
    $competition = null;
    $results = [];
    
    try {
        $db = getDbConnection();
        
        // 大会情報を取得
        $stmt = $db->prepare("
            SELECT * FROM competitions 
            WHERE competition_id = ? AND user_id = ?
        ");
        $stmt->execute([$competitionId, $_SESSION['user_id']]);
        $competition = $stmt->fetch();
        
        if ($competition) {
            // 競技結果を取得（新しいスキーマ対応）
            $stmt = $db->prepare("
                SELECT r.*, 
                       -- ラップタイム数もカウント
                       (SELECT COUNT(*) FROM lap_times lt WHERE lt.result_id = r.result_id) as lap_count
                FROM race_results r
                WHERE r.competition_id = ?
                ORDER BY r.is_personal_best DESC, r.stroke_type_new, r.distance_meters, r.total_time_centiseconds
            ");
            $stmt->execute([$competitionId]);
            $results = $stmt->fetchAll();
            
            // 各結果のラップタイムも取得
            foreach ($results as &$result) {
                $stmt = $db->prepare("
                    SELECT * FROM lap_times
                    WHERE result_id = ?
                    ORDER BY lap_number
                ");
                $stmt->execute([$result['result_id']]);
                $result['lap_times'] = $stmt->fetchAll();
            }
        }
    } catch (PDOException $e) {
        error_log('大会詳細取得エラー: ' . $e->getMessage());
    }
    
    if (!$competition) {
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">';
        echo '指定された大会が見つからないか、アクセス権がありません。';
        echo '</div>';
        echo '<div class="mt-4"><a href="competition.php" class="text-blue-600 hover:text-blue-800"><i class="fas fa-arrow-left mr-1"></i> 大会一覧に戻る</a></div>';
    } else {
    ?>
    
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">大会詳細</h1>
        <div class="flex space-x-3">
            <a href="competition.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i> 大会一覧
            </a>
            <button onclick="exportResults()" class="text-green-600 hover:text-green-800">
                <i class="fas fa-download mr-1"></i> エクスポート
            </button>
        </div>
    </div>
    
    <!-- 大会情報カード -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-xl font-semibold mb-2"><?php echo h($competition['competition_name']); ?></h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">開催日:</span>
                        <span class="font-medium"><?php echo date('Y年n月j日', strtotime($competition['competition_date'])); ?></span>
                    </div>
                    <?php if (!empty($competition['location'])): ?>
                    <div>
                        <span class="text-gray-600">場所:</span>
                        <span class="font-medium"><?php echo h($competition['location']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div>
                        <span class="text-gray-600">記録数:</span>
                        <span class="font-medium"><?php echo count($results); ?> 件</span>
                    </div>
                </div>
                <?php if (!empty($competition['notes'])): ?>
                <div class="mt-3">
                    <span class="text-gray-600 text-sm">メモ:</span>
                    <div class="bg-gray-50 p-2 rounded mt-1 text-sm">
                        <?php echo nl2br(h($competition['notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 競技結果一覧 -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">競技結果</h2>
            <a href="#add-result-form" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-plus mr-1"></i> 新規記録を追加
            </a>
        </div>
        
        <?php if (empty($results)): ?>
        <div class="text-center py-8">
            <p class="text-gray-500 mb-4">まだ競技結果が記録されていません。</p>
            <a href="#add-result-form" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                最初の記録を追加
            </a>
        </div>
        <?php else: ?>
        
        <!-- フィルター -->
        <div class="mb-4 flex flex-wrap gap-2">
            <button onclick="filterResults('all')" class="filter-btn active px-3 py-1 rounded-full text-sm border">すべて</button>
            <button onclick="filterResults('official')" class="filter-btn px-3 py-1 rounded-full text-sm border">公式記録</button>
            <button onclick="filterResults('personal_best')" class="filter-btn px-3 py-1 rounded-full text-sm border">自己ベスト</button>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="py-3 px-4 text-left">種目</th>
                        <th class="py-3 px-4 text-left">タイム</th>
                        <th class="py-3 px-4 text-left">順位</th>
                        <th class="py-3 px-4 text-left">記録種別</th>
                        <th class="py-3 px-4 text-left">ラップ</th>
                        <th class="py-3 px-4 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $strokeNames = [
                        'butterfly' => 'バタフライ',
                        'backstroke' => '背泳ぎ',
                        'breaststroke' => '平泳ぎ',
                        'freestyle' => '自由形',
                        'medley' => '個人メドレー'
                    ];
                    
                    $poolTypeNames = [
                        'SCM' => '短水路',
                        'LCM' => '長水路'
                    ];
                    
                    $recordTypeNames = [
                        'competition' => '公式大会',
                        'time_trial' => 'タイム測定会',
                        'practice' => '練習記録',
                        'relay_split' => 'リレーのスプリット'
                    ];
                    
                    foreach ($results as $result): 
                        // タイム表示のフォーマット
                        $timeDisplay = formatCentisecondsToTime($result['total_time_centiseconds']);
                        
                        // 種目表示
                        $eventDisplay = $result['distance_meters'] . 'm' . 
                                      ($strokeNames[$result['stroke_type_new']] ?? $result['stroke_type_new']) . 
                                      '(' . $poolTypeNames[$result['pool_type']] . ')';
                    ?>
                    <tr class="border-b result-row" 
                        data-official="<?php echo $result['is_official'] ? 'true' : 'false'; ?>"
                        data-personal-best="<?php echo $result['is_personal_best'] ? 'true' : 'false'; ?>">
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <span><?php echo h($eventDisplay); ?></span>
                                <?php if ($result['is_personal_best']): ?>
                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    <i class="fas fa-trophy mr-1"></i> PB
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-medium text-lg"><?php echo h($timeDisplay); ?></div>
                            <?php if ($result['reaction_time_centiseconds']): ?>
                            <div class="text-xs text-gray-500">
                                RT: <?php echo formatCentisecondsToTime($result['reaction_time_centiseconds']); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <?php echo $result['rank'] ? $result['rank'] . '位' : '-'; ?>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex flex-col">
                                <span class="text-sm"><?php echo h($recordTypeNames[$result['record_type']] ?? $result['record_type']); ?></span>
                                <span class="text-xs <?php echo $result['is_official'] ? 'text-blue-600' : 'text-gray-500'; ?>">
                                    <?php echo $result['is_official'] ? '公式' : '非公式'; ?>
                                </span>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <?php if ($result['lap_count'] > 0): ?>
                            <button onclick="showLapTimes(<?php echo $result['result_id']; ?>)" 
                                    class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-stopwatch mr-1"></i> ラップ表示
                            </button>
                            <?php else: ?>
                            <span class="text-gray-400 text-sm">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex space-x-2">
                                <button onclick="showProgressChart('<?php echo $result['stroke_type_new']; ?>', <?php echo $result['distance_meters']; ?>, '<?php echo $result['pool_type']; ?>')" 
                                        class="text-green-600 hover:text-green-800" title="進歩グラフ">
                                    <i class="fas fa-chart-line"></i>
                                </button>
                                <form method="POST" action="api/enhanced_competition.php" class="inline-block" 
                                      onsubmit="return confirm('この記録を削除してもよろしいですか？');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                                    <input type="hidden" name="action" value="delete_result">
                                    <input type="hidden" name="result_id" value="<?php echo $result['result_id']; ?>">
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
        <?php endif; ?>
    </div>
    
    <!-- 記録追加フォーム -->
    <div id="add-result-form" class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">新しい記録を追加</h2>
        
        <form id="competition-result-form" method="POST" action="api/enhanced_competition.php">
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            <input type="hidden" name="action" value="add_result">
            <input type="hidden" name="competition_id" value="<?php echo $competitionId; ?>">
            
            <!-- 基本情報 -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <!-- プール種別 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="pool_type">プール種別 <span class="text-red-500">*</span></label>
                    <select id="pool_type" name="pool_type" class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                        <option value="">選択してください</option>
                        <option value="SCM">短水路 (25m)</option>
                        <option value="LCM">長水路 (50m)</option>
                    </select>
                </div>
                
                <!-- 泳法 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="stroke_type">泳法 <span class="text-red-500">*</span></label>
                    <select id="stroke_type" name="stroke_type" class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                        <option value="">選択してください</option>
                        <option value="butterfly">バタフライ</option>
                        <option value="backstroke">背泳ぎ</option>
                        <option value="breaststroke">平泳ぎ</option>
                        <option value="freestyle">自由形</option>
                        <option value="medley">個人メドレー</option>
                    </select>
                </div>
                
                <!-- 距離 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="distance_meters">距離 <span class="text-red-500">*</span></label>
                    <select id="distance_meters" name="distance_meters" class="w-full border border-gray-300 rounded-md px-3 py-