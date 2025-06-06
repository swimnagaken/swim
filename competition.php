<?php
// competition.php - 完全版大会記録ページ
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

/**
 * 1/100秒を時間文字列に変換
 */
function formatCentisecondsToTime($centiseconds) {
    if (!$centiseconds) return '0.00';
    
    $minutes = floor($centiseconds / 6000);
    $seconds = floor(($centiseconds % 6000) / 100);
    $cs = $centiseconds % 100;
    
    if ($minutes > 0) {
        return sprintf('%d:%02d.%02d', $minutes, $seconds, $cs);
    } else {
        return sprintf('%d.%02d', $seconds, $cs);
    }
}
?>

<!-- Chart.js読み込み -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if ($action === 'new'): ?>
    <!-- 統合版新規大会記録フォーム -->
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">新しい大会記録</h1>
        <a href="competition.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-1"></i> 大会一覧に戻る
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <form id="unified-competition-form" method="POST" action="api/competition.php">
            <!-- CSRFトークン -->
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            <input type="hidden" name="action" value="add_unified_competition">
            
            <!-- 大会情報セクション -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4 pb-2 border-b border-gray-200">大会情報</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- 大会名 -->
                    <div>
                        <label class="block text-gray-700 mb-2" for="competition_name">大会名 <span class="text-red-500">*</span></label>
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
                        <label class="block text-gray-700 mb-2" for="competition_date">開催日 <span class="text-red-500">*</span></label>
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
                
                <!-- 大会メモ -->
                <div class="mb-6">
                    <label class="block text-gray-700 mb-2" for="competition_notes">大会メモ</label>
                    <textarea
                        id="competition_notes"
                        name="competition_notes"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 h-20"
                        placeholder="大会の様子や全体的な感想など..."
                    ></textarea>
                </div>
            </div>
            
            <!-- 競技結果セクション -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4 pb-2 border-b border-gray-200">競技結果</h2>
                
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
                        <select id="distance_meters" name="distance_meters" class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                            <option value="">距離を選択</option>
                        </select>
                    </div>
                    
                    <!-- 種目名 -->
                    <div>
                        <label class="block text-gray-700 mb-2" for="event_name">種目名 <span class="text-red-500">*</span></label>
                        <input type="text" id="event_name" name="event_name" 
                               placeholder="例：男子、女子、混合、年代別など" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                    </div>
                    
                    <!-- 記録種別（4分類） -->
                    <div>
                        <label class="block text-gray-700 mb-2" for="record_type">記録種別 <span class="text-red-500">*</span></label>
                        <select id="record_type" name="record_type" class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                            <option value="">選択してください</option>
                            <option value="official">公認記録</option>
                            <option value="relay_split">リレーラップ</option>
                            <option value="unofficial">非公認記録</option>
                            <option value="practice">練習時測定</option>
                        </select>
                    </div>
                    
                    <!-- 順位 -->
                    <div>
                        <label class="block text-gray-700 mb-2" for="rank">順位（任意）</label>
                        <input type="number" id="rank" name="rank" min="1" max="99" 
                               placeholder="順位を入力" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                </div>
                
                <!-- タイム入力 -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium mb-3">タイム記録</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- 最終タイム -->
                        <div>
                            <label class="block text-gray-700 mb-2" for="final_time">最終タイム <span class="text-red-500">*</span></label>
                            <input type="text" id="final_time" name="final_time" 
                                   placeholder="例：1:23.45 または 23.45" 
                                   pattern="^(\d{1,2}:)?\d{1,2}\.\d{2}$"
                                   title="タイム形式: 秒.1/100秒 または 分:秒.1/100秒"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                            <div id="time-preview" class="text-sm mt-1"></div>
                        </div>
                        
                        <!-- リアクションタイム -->
                        <div>
                            <label class="block text-gray-700 mb-2" for="reaction_time">リアクションタイム（任意）</label>
                            <input type="text" id="reaction_time" name="reaction_time" 
                                   placeholder="例：0.65" 
                                   pattern="^\d\.\d{2}$"
                                   title="リアクションタイム形式: 0.65"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                    </div>
                </div>
                
                <!-- ラップタイム入力 -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium mb-3">ラップタイム（任意）</h3>
                    
                    <!-- ラップタイム入力方式選択 -->
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">入力方式</label>
                        <div class="flex space-x-4">
                            <label class="flex items-center">
                                <input type="radio" id="lap_input_method_split" name="lap_input_method" value="split" checked 
                                       class="h-4 w-4 text-blue-600">
                                <span class="ml-2">スプリットタイム（各区間のタイム）</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" id="lap_input_method_cumulative" name="lap_input_method" value="cumulative" 
                                       class="h-4 w-4 text-blue-600">
                                <span class="ml-2">累積タイム（その時点での合計タイム）</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- ラップタイム入力欄（動的生成） -->
                    <div id="lap_times_container">
                        <!-- JavaScriptで動的に生成 -->
                    </div>
                </div>
                
                <!-- 競技メモ -->
                <div class="mb-6">
                    <label class="block text-gray-700 mb-2" for="race_notes">競技メモ</label>
                    <textarea id="race_notes" name="race_notes" rows="3" 
                              placeholder="レース後の感想、気づいたこと、改善点など..." 
                              class="w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
                </div>
            </div>
            
            <!-- 追加競技結果セクション -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium">追加の競技結果</h3>
                    <button type="button" id="add-more-results" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-plus mr-1"></i> 競技を追加
                    </button>
                </div>
                <div id="additional-results-container">
                    <!-- 追加の競技結果がここに表示される -->
                </div>
                <p class="text-sm text-gray-600 mt-2">
                    <i class="fas fa-info-circle mr-1"></i>
                    同じ大会で複数の種目に出場した場合は、「競技を追加」ボタンで追加できます。
                </p>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="if(confirm('入力内容がリセットされます。よろしいですか？')) location.reload();" 
                        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg">
                    リセット
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    <i class="fas fa-save mr-1"></i> 大会記録を保存
                </button>
            </div>
        </form>
    </div>
    
    <!-- 追加競技結果用テンプレート -->
    <template id="additional-result-template">
        <div class="additional-result-item bg-gray-50 p-4 rounded-lg mb-4 border-l-4 border-blue-500">
            <div class="flex justify-between items-center mb-3">
                <h4 class="font-medium">競技 <span class="result-number">2</span></h4>
                <button type="button" class="remove-result text-red-600 hover:text-red-800">
                    <i class="fas fa-trash"></i> 削除
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                <!-- プール種別 -->
                <div>
                    <label class="block text-gray-700 mb-1 text-sm">プール種別 <span class="text-red-500">*</span></label>
                    <select name="additional_results[INDEX][pool_type]" class="pool-type-select w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required>
                        <option value="">選択してください</option>
                        <option value="SCM">短水路 (25m)</option>
                        <option value="LCM">長水路 (50m)</option>
                    </select>
                </div>
                
                <!-- 泳法 -->
                <div>
                    <label class="block text-gray-700 mb-1 text-sm">泳法 <span class="text-red-500">*</span></label>
                    <select name="additional_results[INDEX][stroke_type]" class="stroke-type-select w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required>
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
                    <label class="block text-gray-700 mb-1 text-sm">距離 <span class="text-red-500">*</span></label>
                    <select name="additional_results[INDEX][distance_meters]" class="distance-select w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required>
                        <option value="">距離を選択</option>
                    </select>
                </div>
                
                <!-- 種目名 -->
                <div>
                    <label class="block text-gray-700 mb-1 text-sm">種目名 <span class="text-red-500">*</span></label>
                    <input type="text" name="additional_results[INDEX][event_name]" 
                           placeholder="例：男子、女子、混合など" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required>
                </div>
                
                <!-- 記録種別 -->
                <div>
                    <label class="block text-gray-700 mb-1 text-sm">記録種別 <span class="text-red-500">*</span></label>
                    <select name="additional_results[INDEX][record_type]" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required>
                        <option value="">選択してください</option>
                        <option value="official">公認記録</option>
                        <option value="relay_split">リレーラップ</option>
                        <option value="unofficial">非公認記録</option>
                        <option value="practice">練習時測定</option>
                    </select>
                </div>
                
                <!-- 順位 -->
                <div>
                    <label class="block text-gray-700 mb-1 text-sm">順位（任意）</label>
                    <input type="number" name="additional_results[INDEX][rank]" min="1" max="99" 
                           placeholder="順位" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                </div>
            </div>
            
            <!-- タイム入力 -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 mb-1 text-sm">最終タイム <span class="text-red-500">*</span></label>
                    <input type="text" name="additional_results[INDEX][final_time]" 
                           placeholder="例：1:23.45 または 23.45" 
                           pattern="^(\d{1,2}:)?\d{1,2}\.\d{2}$"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-gray-700 mb-1 text-sm">リアクションタイム（任意）</label>
                    <input type="text" name="additional_results[INDEX][reaction_time]" 
                           placeholder="例：0.65" 
                           pattern="^\d\.\d{2}$"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                </div>
            </div>
            
            <!-- 競技メモ -->
            <div>
                <label class="block text-gray-700 mb-1 text-sm">競技メモ</label>
                <textarea name="additional_results[INDEX][race_notes]" rows="2" 
                          placeholder="この競技についてのメモ..." 
                          class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"></textarea>
            </div>
        </div>
    </template>

<?php elseif ($action === 'view' && $competitionId > 0): ?>
    <!-- 大会詳細表示 -->
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
            // 競技結果を取得（新旧両方のスキーマに対応）
            $stmt = $db->prepare("
                SELECT r.*, 
                       (SELECT COUNT(*) FROM lap_times lt WHERE lt.result_id = r.result_id) as lap_count
                FROM race_results r
                WHERE r.competition_id = ?
                ORDER BY 
                    r.is_personal_best DESC, 
                    COALESCE(r.stroke_type_new, r.stroke_type), 
                    COALESCE(r.distance_meters, r.distance), 
                    COALESCE(r.total_time_centiseconds, (r.time_minutes * 6000 + r.time_seconds * 100 + r.time_milliseconds / 10))
            ");
            $stmt->execute([$competitionId]);
            $results = $stmt->fetchAll();
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
            <button onclick="filterResults('all')" class="filter-btn active bg-blue-500 text-white px-3 py-1 rounded-full text-sm border">すべて</button>
            <button onclick="filterResults('official')" class="filter-btn border-gray-300 text-gray-700 px-3 py-1 rounded-full text-sm border">公認記録</button>
            <button onclick="filterResults('personal_best')" class="filter-btn border-gray-300 text-gray-700 px-3 py-1 rounded-full text-sm border">自己ベスト</button>
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
                        'medley' => '個人メドレー',
                        'im' => '個人メドレー',
                        'other' => 'その他'
                    ];
                    
                    $poolTypeNames = [
                        'SCM' => '短水路',
                        'LCM' => '長水路'
                    ];
                    
                    $recordTypeNames = [
                        'official' => '公認記録',
                        'relay_split' => 'リレーラップ',
                        'unofficial' => '非公認記録',
                        'practice' => '練習時測定',
                        'competition' => '公式大会',
                        'time_trial' => 'タイム測定会'
                    ];
                    
                    foreach ($results as $result): 
                        // 新旧スキーマ対応
                        $stroke_type = $result['stroke_type_new'] ?? $result['stroke_type'] ?? 'freestyle';
                        $distance = $result['distance_meters'] ?? $result['distance'] ?? 0;
                        $pool_type = $result['pool_type'] ?? 'SCM';
                        
                        // タイム表示の計算（新旧スキーマ対応）
                        if (!empty($result['total_time_centiseconds'])) {
                            $timeDisplay = formatCentisecondsToTime($result['total_time_centiseconds']);
                        } else {
                            // 旧形式からの変換
                            $minutes = $result['time_minutes'] ?? 0;
                            $seconds = $result['time_seconds'] ?? 0;
                            $milliseconds = $result['time_milliseconds'] ?? 0;
                            
                            if ($minutes > 0) {
                                $timeDisplay = sprintf('%d:%02d.%03d', $minutes, $seconds, $milliseconds);
                            } else {
                                $timeDisplay = sprintf('%d.%03d', $seconds, $milliseconds);
                            }
                        }
                        
                        // 種目表示
                        $eventDisplay = $distance . 'm' . 
                                      ($strokeNames[$stroke_type] ?? $stroke_type);
                        if ($pool_type) {
                            $eventDisplay .= '(' . ($poolTypeNames[$pool_type] ?? $pool_type) . ')';
                        }
                        
                        $is_official = $result['is_official'] ?? true;
                        $is_personal_best = $result['is_personal_best'] ?? false;
                        $record_type = $result['record_type'] ?? 'competition';
                    ?>
                    <tr class="border-b result-row" 
                        data-official="<?php echo $is_official ? 'true' : 'false'; ?>"
                        data-personal-best="<?php echo $is_personal_best ? 'true' : 'false'; ?>">
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <span><?php echo h($eventDisplay); ?></span>
                                <?php if ($is_personal_best): ?>
                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    <i class="fas fa-trophy mr-1"></i> PB
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-medium text-lg"><?php echo h($timeDisplay); ?></div>
                            <?php if (!empty($result['reaction_time_centiseconds'])): ?>
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
                                <span class="text-sm"><?php echo h($recordTypeNames[$record_type] ?? $record_type); ?></span>
                                <span class="text-xs <?php echo $is_official ? 'text-blue-600' : 'text-gray-500'; ?>">
                                    <?php echo $is_official ? '公式' : '非公式'; ?>
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
                                <?php if (!empty($result['stroke_type_new']) && !empty($result['distance_meters'])): ?>
                                <button onclick="showProgressChart('<?php echo $result['stroke_type_new']; ?>', <?php echo $result['distance_meters']; ?>, '<?php echo $result['pool_type']; ?>')" 
                                        class="text-green-600 hover:text-green-800" title="進歩グラフ">
                                    <i class="fas fa-chart-line"></i>
                                </button>
                                <?php endif; ?>
                                <form method="POST" action="api/competition.php" class="inline-block" 
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
        
        <form id="competition-result-form" method="POST" action="api/competition.php">
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
                    <select id="distance_meters" name="distance_meters" class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                        <option value="">距離を選択</option>
                    </select>
                </div>
                
                <!-- 種目名 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="event_name">種目名 <span class="text-red-500">*</span></label>
                    <input type="text" id="event_name" name="event_name" 
                           placeholder="例：男子、女子、混合など" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                </div>
                
                <!-- 記録種別 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="record_type">記録種別 <span class="text-red-500">*</span></label>
                    <select id="record_type" name="record_type" class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                        <option value="">選択してください</option>
                        <option value="official">公認記録</option>
                        <option value="relay_split">リレーラップ</option>
                        <option value="unofficial">非公認記録</option>
                        <option value="practice">練習時測定</option>
                    </select>
                </div>
                
                <!-- 順位 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="rank">順位（任意）</label>
                    <input type="number" id="rank" name="rank" min="1" max="99" 
                           placeholder="順位を入力" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
            </div>
            
            <!-- タイム入力 -->
            <div class="mb-6">
                <h3 class="text-lg font-medium mb-3">タイム記録</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- 最終タイム -->
                    <div>
                        <label class="block text-gray-700 mb-2" for="final_time">最終タイム <span class="text-red-500">*</span></label>
                        <input type="text" id="final_time" name="final_time" 
                               placeholder="例：1:23.45 または 23.45" 
                               pattern="^(\d{1,2}:)?\d{1,2}\.\d{2}$"
                               title="タイム形式: 秒.1/100秒 または 分:秒.1/100秒"
                               class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                        <div id="time-preview" class="text-sm mt-1"></div>
                    </div>
                    
                    <!-- リアクションタイム -->
                    <div>
                        <label class="block text-gray-700 mb-2" for="reaction_time">リアクションタイム（任意）</label>
                        <input type="text" id="reaction_time" name="reaction_time" 
                               placeholder="例：0.65" 
                               pattern="^\d\.\d{2}$"
                               title="リアクションタイム形式: 0.65"
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                </div>
            </div>
            
            <!-- ラップタイム入力 -->
            <div class="mb-6">
                <h3 class="text-lg font-medium mb-3">ラップタイム（任意）</h3>
                
                <!-- ラップタイム入力方式選択 -->
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">入力方式</label>
                    <div class="flex space-x-4">
                        <label class="flex items-center">
                            <input type="radio" id="lap_input_method_split" name="lap_input_method" value="split" checked 
                                   class="h-4 w-4 text-blue-600">
                            <span class="ml-2">スプリットタイム（各区間のタイム）</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" id="lap_input_method_cumulative" name="lap_input_method" value="cumulative" 
                                   class="h-4 w-4 text-blue-600">
                            <span class="ml-2">累積タイム（その時点での合計タイム）</span>
                        </label>
                    </div>
                </div>
                
                <!-- ラップタイム入力欄（動的生成） -->
                <div id="lap_times_container">
                    <!-- JavaScriptで動的に生成 -->
                </div>
            </div>
            
            <!-- メモ -->
            <div class="mb-6">
                <label class="block text-gray-700 mb-2" for="notes">メモ</label>
                <textarea id="notes" name="notes" rows="3" 
                          placeholder="レース後の感想や気づいたことなど..." 
                          class="w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('competition-result-form').reset(); location.reload();" 
                        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg">
                    リセット
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    記録を保存
                </button>
            </div>
        </form>
    </div>
    
    <?php } ?>
<?php else: ?>
    <!-- 大会一覧表示 -->
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
                <a href="#" id="toggle-personal-best" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-4 border-b-2 font-medium text-sm">
                    自己ベスト記録
                </a>
            </nav>
        </div>
    </div>
    
    <!-- 大会一覧表示 -->
    <div id="competitions-list" class="bg-white rounded-lg shadow-md p-6">
        <?php
        // 大会一覧を取得
        $competitions = [];
        try {
            $db = getDbConnection();
            $stmt = $db->prepare("
                SELECT c.*, COUNT(r.result_id) as result_count
                FROM competitions c
                LEFT JOIN race_results r ON c.competition_id = r.competition_id
                WHERE c.user_id = ?
                GROUP BY c.competition_id
                ORDER BY c.competition_date DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $competitions = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('大会一覧取得エラー: ' . $e->getMessage());
        }
        
        if (empty($competitions)):
        ?>
        <div class="text-center py-8">
            <p class="text-gray-500 mb-6">
                まだ大会記録がありません。<br>新しい大会記録を追加しましょう。
            </p>
            <a href="competition.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                最初の大会記録を追加する
            </a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="py-3 px-4 text-left">日付</th>
                        <th class="py-3 px-4 text-left">大会名</th>
                        <th class="py-3 px-4 text-left">場所</th>
                        <th class="py-3 px-4 text-left">記録数</th>
                        <th class="py-3 px-4 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($competitions as $competition): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <?php echo date('Y/m/d (', strtotime($competition['competition_date'])); ?>
                            <?php echo ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($competition['competition_date']))]; ?>
                            <?php echo ')'; ?>
                        </td>
                        <td class="py-3 px-4"><?php echo h($competition['competition_name']); ?></td>
                        <td class="py-3 px-4"><?php echo h($competition['location'] ?? '-'); ?></td>
                        <td class="py-3 px-4"><?php echo (int)$competition['result_count']; ?> 件</td>
                        <td class="py-3 px-4">
                            <a href="competition.php?action=view&id=<?php echo $competition['competition_id']; ?>" class="text-blue-600 hover:text-blue-800">
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
    
    <!-- 自己ベスト記録表示 -->
    <div id="personal-best-list" class="bg-white rounded-lg shadow-md p-6 hidden">
        <h2 class="text-xl font-semibold mb-4">自己ベスト記録</h2>
        
        <?php
        // 自己ベスト記録を取得（新旧スキーマ対応）
        $personalBests = [];
        try {
            $db = getDbConnection();
            $stmt = $db->prepare("
                SELECT r.*, c.competition_name, c.competition_date
                FROM race_results r
                JOIN competitions c ON r.competition_id = c.competition_id
                WHERE c.user_id = ? AND r.is_personal_best = 1
                ORDER BY 
                    COALESCE(r.stroke_type_new, r.stroke_type), 
                    COALESCE(r.distance_meters, r.distance)
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $personalBests = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('自己ベスト記録取得エラー: ' . $e->getMessage());
        }
        
        if (empty($personalBests)):
        ?>
        <div class="text-center py-8">
            <p class="text-gray-500 mb-6">
                まだ自己ベスト記録がありません。<br>大会結果に自己ベスト記録を追加しましょう。
            </p>
            <a href="competition.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                大会記録を追加する
            </a>
        </div>
        <?php else: ?>
        
        <!-- カテゴリ別に表示 -->
        <?php
        // 泳法ごとにグループ化
        $groupedRecords = [];
        $strokeNames = [
            'freestyle' => '自由形',
            'backstroke' => '背泳ぎ',
            'breaststroke' => '平泳ぎ',
            'butterfly' => 'バタフライ',
            'im' => '個人メドレー',
            'medley' => '個人メドレー',
            'other' => 'その他'
        ];
        
        foreach ($personalBests as $record) {
            $stroke = $record['stroke_type_new'] ?? $record['stroke_type'] ?? 'other';
            if (!isset($groupedRecords[$stroke])) {
                $groupedRecords[$stroke] = [];
            }
            $groupedRecords[$stroke][] = $record;
        }
        
        foreach ($groupedRecords as $stroke => $records):
            $strokeName = $strokeNames[$stroke] ?? $stroke;
        ?>
        <div class="mb-8">
            <h3 class="text-lg font-semibold mb-3 pb-1 border-b"><?php echo h($strokeName); ?></h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="py-2 px-4 text-left">距離</th>
                            <th class="py-2 px-4 text-left">タイム</th>
                            <th class="py-2 px-4 text-left">大会名</th>
                            <th class="py-2 px-4 text-left">日付</th>
                            <th class="py-2 px-4 text-left">詳細</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): 
                            // 距離の取得（新旧対応）
                            $distance = $record['distance_meters'] ?? $record['distance'] ?? 0;
                            
                            // タイム表示の計算（新旧スキーマ対応）
                            if (!empty($record['total_time_centiseconds'])) {
                                $timeDisplay = formatCentisecondsToTime($record['total_time_centiseconds']);
                            } else {
                                // 旧形式からの変換
                                $minutes = $record['time_minutes'] ?? 0;
                                $seconds = $record['time_seconds'] ?? 0;
                                $milliseconds = $record['time_milliseconds'] ?? 0;
                                
                                if ($minutes > 0) {
                                    $timeDisplay = sprintf('%d:%02d.%03d', $minutes, $seconds, $milliseconds);
                                } else {
                                    $timeDisplay = sprintf('%d.%03d', $seconds, $milliseconds);
                                }
                            }
                        ?>
                        <tr class="border-b">
                            <td class="py-3 px-4"><?php echo h($distance); ?>m</td>
                            <td class="py-3 px-4 font-medium"><?php echo h($timeDisplay); ?></td>
                            <td class="py-3 px-4"><?php echo h($record['competition_name']); ?></td>
                            <td class="py-3 px-4">
                                <?php echo date('Y/m/d', strtotime($record['competition_date'])); ?>
                            </td>
                            <td class="py-3 px-4">
                                <a href="competition.php?action=view&id=<?php echo $record['competition_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                    詳細
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const togglePersonalBest = document.getElementById('toggle-personal-best');
        const competitionsList = document.getElementById('competitions-list');
        const personalBestList = document.getElementById('personal-best-list');
        
        if (togglePersonalBest && competitionsList && personalBestList) {
            togglePersonalBest.addEventListener('click', function(e) {
                e.preventDefault();
                
                // タブの切り替え
                const tabs = document.querySelectorAll('nav a');
                tabs.forEach(tab => {
                    tab.classList.remove('border-blue-500', 'text-blue-600');
                    tab.classList.add('border-transparent', 'text-gray-500');
                });
                
                togglePersonalBest.classList.remove('border-transparent', 'text-gray-500');
                togglePersonalBest.classList.add('border-blue-500', 'text-blue-600');
                
                // コンテンツの切り替え
                competitionsList.classList.add('hidden');
                personalBestList.classList.remove('hidden');
            });
            
            // 大会一覧タブのクリックイベント
            const competitionsTab = document.querySelector('nav a:first-child');
            if (competitionsTab) {
                competitionsTab.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // タブの切り替え
                    const tabs = document.querySelectorAll('nav a');
                    tabs.forEach(tab => {
                        tab.classList.remove('border-blue-500', 'text-blue-600');
                        tab.classList.add('border-transparent', 'text-gray-500');
                    });
                    
                    competitionsTab.classList.remove('border-transparent', 'text-gray-500');
                    competitionsTab.classList.add('border-blue-500', 'text-blue-600');
                    
                    // コンテンツの切り替え
                    personalBestList.classList.add('hidden');
                    competitionsList.classList.remove('hidden');
                });
            }
        }
    });
    </script>
<?php endif; ?>

<!-- JavaScript読み込み -->
<script src="assets/js/competition_form.js"></script>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-QMTKRPLHDD"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-QMTKRPLHDD');
</script>