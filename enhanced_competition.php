<?php
// enhanced_competition.php - 改良版大会記録ページ（完成版）
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
                        <option value="competition">公式大会</option>
                        <option value="time_trial">タイム測定会</option>
                        <option value="practice">練習記録</option>
                        <option value="relay_split">リレーのスプリット</option>
                    </select>
                </div>
                
                <!-- 公式記録チェック -->
                <div class="flex items-center mt-6">
                    <input type="checkbox" id="is_official" name="is_official" value="1" checked 
                           class="h-4 w-4 text-blue-600 rounded">
                    <label for="is_official" class="ml-2 text-gray-700">公式記録</label>
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
                
                <!-- 順位 -->
                <div class="mt-4">
                    <label class="block text-gray-700 mb-2" for="rank">順位（任意）</label>
                    <input type="number" id="rank" name="rank" min="1" max="99" 
                           placeholder="順位を入力" 
                           class="w-full md:w-32 border border-gray-300 rounded-md px-3 py-2">
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
                
                <!-- ラップ分析表示 -->
                <div id="lap_consistency"></div>
                
                <!-- ラップタイムプレビュー -->
                <div id="lap-preview"></div>
            </div>
            
            <!-- メモ -->
            <div class="mb-6">
                <label class="block text-gray-700 mb-2" for="notes">メモ</label>
                <textarea id="notes" name="notes" rows="3" 
                          placeholder="レース後の感想や気づいたことなど..." 
                          class="w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('competition-result-form').reset()" 
                        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg">
                    リセット
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    記録を保存
                </button>
            </div>
        </form>
    </div>
    
    <!-- JavaScript -->
    <script>
    // enhanced_competition_form.js - 改良版大会記録フォーム（完全版）

    document.addEventListener('DOMContentLoaded', function() {
        console.log("改良版大会記録フォームを初期化します");
        
        // フォーム要素の取得
        const poolTypeSelect = document.getElementById('pool_type');
        const strokeTypeSelect = document.getElementById('stroke_type');
        const distanceSelect = document.getElementById('distance_meters');
        const finalTimeInput = document.getElementById('final_time');
        const reactionTimeInput = document.getElementById('reaction_time');
        const lapInputMethodSelect = document.getElementById('lap_input_method_split');
        const lapTimesContainer = document.getElementById('lap_times_container');
        const isOfficialCheckbox = document.getElementById('is_official');
        const recordTypeSelect = document.getElementById('record_type');
        
        // イベント設定データ
        let eventConfigurations = [];
        
        // 距離設定（泳法別）
        const distanceOptions = {
            'butterfly': { 'SCM': [50, 100, 200], 'LCM': [50, 100, 200] },
            'backstroke': { 'SCM': [50, 100, 200], 'LCM': [50, 100, 200] },
            'breaststroke': { 'SCM': [50, 100, 200], 'LCM': [50, 100, 200] },
            'freestyle': { 'SCM': [50, 100, 200, 400, 800, 1500], 'LCM': [50, 100, 200, 400, 800, 1500] },
            'medley': { 'SCM': [100, 200, 400], 'LCM': [200, 400] } // 100m個人メドレーは短水路のみ
        };
        
        // 初期化
        init();
        
        function init() {
            setupEventListeners();
            updateDistanceOptions();
            updateRecordTypeOptions();
        }
        
        function setupEventListeners() {
            // プール種別変更時
            if (poolTypeSelect) {
                poolTypeSelect.addEventListener('change', updateDistanceOptions);
            }
            
            // 泳法変更時
            if (strokeTypeSelect) {
                strokeTypeSelect.addEventListener('change', updateDistanceOptions);
            }
            
            // 距離変更時
            if (distanceSelect) {
                distanceSelect.addEventListener('change', updateLapTimesInput);
            }
            
            // ラップ入力方式変更時
            const lapMethodRadios = document.querySelectorAll('input[name="lap_input_method"]');
            lapMethodRadios.forEach(radio => {
                radio.addEventListener('change', updateLapTimesInput);
            });
            
            // タイム入力フィールドのフォーマット検証
            if (finalTimeInput) {
                finalTimeInput.addEventListener('blur', validateTimeFormat);
                finalTimeInput.addEventListener('input', previewFormattedTime);
            }
            
            if (reactionTimeInput) {
                reactionTimeInput.addEventListener('blur', validateTimeFormat);
            }
            
            // 公式記録チェックボックス
            if (isOfficialCheckbox) {
                isOfficialCheckbox.addEventListener('change', updateRecordTypeOptions);
            }
            
            // フォーム送信時の検証
            const form = document.getElementById('competition-result-form');
            if (form) {
                form.addEventListener('submit', validateFormBeforeSubmit);
            }
        }
        
        function updateDistanceOptions() {
            if (!poolTypeSelect || !strokeTypeSelect || !distanceSelect) return;
            
            const poolType = poolTypeSelect.value;
            const strokeType = strokeTypeSelect.value;
            
            console.log(`距離オプション更新: pool=${poolType}, stroke=${strokeType}`);
            
            // 現在の選択をクリア
            distanceSelect.innerHTML = '<option value="">距離を選択</option>';
            
            if (!poolType || !strokeType) return;
            
            // 泳法とプール種別に応じた距離オプションを取得
            const distances = distanceOptions[strokeType] && distanceOptions[strokeType][poolType] ? 
                             distanceOptions[strokeType][poolType] : [];
            
            console.log('有効な距離:', distances);
            
            // オプションを追加
            distances.forEach(distance => {
                const option = document.createElement('option');
                option.value = distance;
                option.textContent = `${distance}m`;
                distanceSelect.appendChild(option);
            });
            
            // 距離が選択されたらラップタイム入力を更新
            updateLapTimesInput();
        }
        
        function updateLapTimesInput() {
            if (!lapTimesContainer || !distanceSelect || !poolTypeSelect) return;
            
            const distance = parseInt(distanceSelect.value);
            const poolType = poolTypeSelect.value;
            const inputMethod = document.querySelector('input[name="lap_input_method"]:checked')?.value || 'split';
            
            // コンテナをクリア
            lapTimesContainer.innerHTML = '';
            
            if (!distance || !poolType) return;
            
            // ラップ距離を決定
            const lapDistance = poolType === 'SCM' ? 25 : 50;
            const numberOfLaps = distance / lapDistance;
            
            if (!Number.isInteger(numberOfLaps) || numberOfLaps <= 1) {
                // ラップタイム入力不要な場合
                lapTimesContainer.innerHTML = '<p class="text-gray-500 text-sm">この種目ではラップタイムの入力は不要です。</p>';
                return;
            }
            
            console.log(`ラップタイム入力欄生成: ${numberOfLaps}ラップ, ${lapDistance}m間隔, 方式=${inputMethod}`);
            
            // ヘッダーを追加
            const header = document.createElement('div');
            header.className = 'mb-4';
            header.innerHTML = `
                <h4 class="font-medium mb-2">ラップタイム（任意）</h4>
                <p class="text-sm text-gray-600">
                    ${inputMethod === 'split' ? 
                        `各${lapDistance}mのタイムを入力（例：26.50, 28.30）` : 
                        `その時点での合計タイムを入力（例：26.50, 54.80）`
                    }
                </p>
            `;
            lapTimesContainer.appendChild(header);
            
            // 入力欄のコンテナ
            const inputsContainer = document.createElement('div');
            inputsContainer.className = 'grid grid-cols-2 md:grid-cols-4 gap-3';
            
            // 各ラップの入力欄を生成
            for (let i = 1; i <= numberOfLaps; i++) {
                const inputGroup = document.createElement('div');
                inputGroup.innerHTML = `
                    <label class="block text-xs text-gray-600 mb-1">
                        ${i * lapDistance}m ${inputMethod === 'split' ? 'ラップ' : '通過'}
                    </label>
                    <input 
                        type="text" 
                        name="lap_times[]"
                        placeholder="${inputMethod === 'split' ? '26.50' : (i === 1 ? '26.50' : '54.80')}"
                        pattern="^(\\d{1,2}:)?\\d{1,2}\\.\\d{2}$"
                        title="タイム形式: 秒.1/100秒 または 分:秒.1/100秒"
                        class="lap-time-input w-full text-sm border border-gray-300 rounded px-2 py-1"
                        data-lap="${i}"
                        data-distance="${i * lapDistance}"
                    >
                `;
                inputsContainer.appendChild(inputGroup);
            }
            
            lapTimesContainer.appendChild(inputsContainer);
            
            // 一貫性分析表示エリアを追加
            const consistencyDiv = document.createElement('div');
            consistencyDiv.id = 'lap_consistency';
            lapTimesContainer.appendChild(consistencyDiv);
            
            // ラップタイム入力欄にもイベントリスナーを追加
            setupLapTimeValidation();
        }
        
        function setupLapTimeValidation() {
            const lapInputs = document.querySelectorAll('.lap-time-input');
            
            lapInputs.forEach(input => {
                input.addEventListener('blur', function() {
                    validateTimeFormat.call(this);
                    calculateConsistency();
                });
                
                input.addEventListener('input', function() {
                    updateLapTimePreview();
                });
            });
        }
        
        function validateTimeFormat() {
            const timeValue = this.value.trim();
            if (!timeValue) return; // 空の場合はスキップ
            
            const timePattern = /^(\d{1,2}:)?\d{1,2}\.\d{2}$/;
            
            if (!timePattern.test(timeValue)) {
                this.classList.add('border-red-500');
                showFieldError(this, 'タイム形式が正しくありません（例: 23.45 または 1:23.45）');
            } else {
                this.classList.remove('border-red-500');
                hideFieldError(this);
                
                // 正しい形式の場合、値を正規化
                this.value = normalizeTimeFormat(timeValue);
            }
        }
        
        function normalizeTimeFormat(timeStr) {
            // 1/100秒が1桁の場合は0埋め
            const parts = timeStr.split('.');
            if (parts[1] && parts[1].length === 1) {
                parts[1] = parts[1] + '0';
            }
            return parts.join('.');
        }
        
        function previewFormattedTime() {
            if (!finalTimeInput) return;
            
            const timeValue = finalTimeInput.value.trim();
            let preview = document.getElementById('time-preview');
            
            if (!preview) {
                preview = document.createElement('div');
                preview.id = 'time-preview';
                finalTimeInput.parentNode.appendChild(preview);
            }
            
            if (timeValue && /^(\d{1,2}:)?\d{1,2}\.\d{1,2}$/.test(timeValue)) {
                const normalized = normalizeTimeFormat(timeValue);
                const centiseconds = parseTimeStringToCentiseconds(normalized);
                
                preview.textContent = `= ${formatCentisecondsToTime(centiseconds)}`;
                preview.className = 'text-sm text-green-600 mt-1';
            } else {
                preview.textContent = '';
            }
        }
        
        function calculateConsistency() {
            const lapInputs = document.querySelectorAll('.lap-time-input');
            const consistencyDiv = document.getElementById('lap_consistency');
            
            if (!consistencyDiv || lapInputs.length < 2) return;
            
            const times = [];
            lapInputs.forEach(input => {
                if (input.value.trim()) {
                    try {
                        times.push(parseTimeStringToCentiseconds(input.value.trim()));
                    } catch (e) {
                        // 無効なタイムは無視
                    }
                }
            });
            
            if (times.length < 2) {
                consistencyDiv.innerHTML = '';
                return;
            }
            
            const inputMethod = document.querySelector('input[name="lap_input_method"]:checked')?.value || 'split';
            let lapTimes = [];
            
            if (inputMethod === 'split') {
                lapTimes = times;
            } else {
                // 累積タイムからラップタイムを計算
                for (let i = 0; i < times.length; i++) {
                    lapTimes.push(i === 0 ? times[i] : times[i] - times[i - 1]);
                }
            }
            
            // 統計計算
            const avg = lapTimes.reduce((a, b) => a + b, 0) / lapTimes.length;
            const fastest = Math.min(...lapTimes);
            const slowest = Math.max(...lapTimes);
            const variance = lapTimes.reduce((sum, time) => sum + Math.pow(time - avg, 2), 0) / lapTimes.length;
            const stdDev = Math.sqrt(variance);
            
            consistencyDiv.innerHTML = `
                <div class="mt-3 p-3 bg-blue-50 rounded-lg text-sm">
                    <h5 class="font-medium mb-2">ラップ分析</h5>
                    <div class="grid grid-cols-2 gap-2">
                        <div>平均: ${formatCentisecondsToTime(Math.round(avg))}</div>
                        <div>最速: ${formatCentisecondsToTime(fastest)}</div>
                        <div>最遅: ${formatCentisecondsToTime(slowest)}</div>
                        <div>標準偏差: ${(stdDev / 100).toFixed(2)}秒</div>
                    </div>
                </div>
            `;
        }
        
        function updateRecordTypeOptions() {
            if (!recordTypeSelect || !isOfficialCheckbox) return;
            
            const isOfficial = isOfficialCheckbox.checked;
            
            // オプションをクリア
            recordTypeSelect.innerHTML = '';
            
            if (isOfficial) {
                recordTypeSelect.innerHTML = `
                    <option value="competition">公式大会</option>
                    <option value="time_trial">公式タイム測定会</option>
                `;
            } else {
                recordTypeSelect.innerHTML = `
                    <option value="practice">練習中の計測</option>
                    <option value="relay_split">リレーのスプリット</option>
                    <option value="time_trial">非公式タイム測定</option>
                `;
            }
        }
        
        function updateLapTimePreview() {
            const inputMethod = document.querySelector('input[name="lap_input_method"]:checked')?.value || 'split';
            const lapInputs = document.querySelectorAll('.lap-time-input');
            let previewContainer = document.getElementById('lap-preview');
            
            if (!previewContainer) {
                previewContainer = document.createElement('div');
                previewContainer.id = 'lap-preview';
                lapTimesContainer?.appendChild(previewContainer);
            }
            
            if (!previewContainer || lapInputs.length === 0) return;
            
            const times = [];
            lapInputs.forEach(input => {
                if (input.value.trim()) {
                    try {
                        times.push({
                            value: input.value.trim(),
                            centiseconds: parseTimeStringToCentiseconds(input.value.trim()),
                            distance: parseInt(input.dataset.distance)
                        });
                    } catch (e) {
                        // 無効なタイム形式は無視
                    }
                }
            });
            
            if (times.length === 0) {
                previewContainer.innerHTML = '';
                return;
            }
            
            // 入力方式に応じてプレビューを生成
            let previewHtml = '<div class="mt-3 p-3 bg-gray-50 rounded-lg text-sm">';
            previewHtml += '<h5 class="font-medium mb-2">タイムプレビュー</h5>';
            previewHtml += '<div class="grid grid-cols-2 gap-4">';
            
            if (inputMethod === 'split') {
                // スプリット入力 → 累積タイム表示
                previewHtml += '<div><strong>累積タイム：</strong><br>';
                let cumulative = 0;
                times.forEach((time, index) => {
                    cumulative += time.centiseconds;
                    previewHtml += `${time.distance}m: ${formatCentisecondsToTime(cumulative)}<br>`;
                });
                previewHtml += '</div>';
                
                previewHtml += '<div><strong>ラップタイム：</strong><br>';
                times.forEach(time => {
                    previewHtml += `${time.distance}m: ${formatCentisecondsToTime(time.centiseconds)}<br>`;
                });
                previewHtml += '</div>';
            } else {
                // 累積入力 → ラップタイム表示
                previewHtml += '<div><strong>累積タイム：</strong><br>';
                times.forEach(time => {
                    previewHtml += `${time.distance}m: ${formatCentisecondsToTime(time.centiseconds)}<br>`;
                });
                previewHtml += '</div>';
                
                previewHtml += '<div><strong>ラップタイム：</strong><br>';
                times.forEach((time, index) => {
                    const lapTime = index === 0 ? time.centiseconds : time.centiseconds - times[index - 1].centiseconds;
                    previewHtml += `${time.distance}m: ${formatCentisecondsToTime(lapTime)}<br>`;
                });
                previewHtml += '</div>';
            }
            
            previewHtml += '</div></div>';
            previewContainer.innerHTML = previewHtml;
        }
        
        function validateFormBeforeSubmit(event) {
            console.log('フォーム送信前検証を実行');
            
            let isValid = true;
            const errors = [];
            
            // 必須フィールドの検証
            const requiredFields = [
                { element: finalTimeInput, name: '最終タイム' },
                { element: strokeTypeSelect, name: '泳法' },
                { element: distanceSelect, name: '距離' },
                { element: poolTypeSelect, name: 'プール種別' }
            ];
            
            requiredFields.forEach(field => {
                if (!field.element || !field.element.value.trim()) {
                    errors.push(`${field.name}は必須です。`);
                    if (field.element) {
                        field.element.classList.add('border-red-500');
                    }
                    isValid = false;
                }
            }
            
            // ラップタイムの検証
            const lapInputs = document.querySelectorAll('.lap-time-input');
            lapInputs.forEach((input, index) => {
                if (input.value.trim()) {
                    const timePattern = /^(\d{1,2}:)?\d{1,2}\.\d{2}$/;
                    if (!timePattern.test(input.value.trim())) {
                        errors.push(`ラップタイム ${index + 1}の形式が正しくありません。`);
                        input.classList.add('border-red-500');
                        isValid = false;
                    } else {
                        input.classList.remove('border-red-500');
                    }
                }
            });
            
            if (!isValid) {
                event.preventDefault();
                
                // エラーメッセージを表示
                showFormErrors(errors);
                
                // 最初のエラーフィールドにフォーカス
                const firstErrorField = document.querySelector('.border-red-500');
                if (firstErrorField) {
                    firstErrorField.focus();
                    firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            
            return isValid;
        }
        
        function showFormErrors(errors) {
            // 既存のエラーメッセージを削除
            const existingError = document.querySelector('.form-error-message');
            if (existingError) {
                existingError.remove();
            }
            
            if (errors.length === 0) return;
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'form-error-message bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
            errorDiv.innerHTML = `
                <h4 class="font-medium mb-2">入力エラーがあります：</h4>
                <ul class="list-disc list-inside space-y-1">
                    ${errors.map(error => `<li>${error}</li>`).join('')}
                </ul>
            `;
            
            // フォームの先頭に挿入
            const form = document.getElementById('competition-result-form');
            if (form) {
                form.insertBefore(errorDiv, form.firstChild);
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        
        function showFieldError(field, message) {
            hideFieldError(field); // 既存のエラーを削除
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error text-red-600 text-xs mt-1';
            errorDiv.textContent = message;
            
            field.parentNode.appendChild(errorDiv);
        }
        
        function hideFieldError(field) {
            const existingError = field.parentNode.querySelector('.field-error');
            if (existingError) {
                existingError.remove();
            }
        }
        
        function parseTimeStringToCentiseconds(timeStr) {
            const trimmed = timeStr.trim();
            
            if (trimmed.includes(':')) {
                const [minutes, secondsPart] = trimmed.split(':');
                const [seconds, centiseconds] = secondsPart.split('.');
                return parseInt(minutes) * 6000 + parseInt(seconds) * 100 + parseInt(centiseconds);
            } else {
                const [seconds, centiseconds] = trimmed.split('.');
                return parseInt(seconds) * 100 + parseInt(centiseconds);
            }
        }
        
        function formatCentisecondsToTime(centiseconds) {
            const minutes = Math.floor(centiseconds / 6000);
            const seconds = Math.floor((centiseconds % 6000) / 100);
            const cs = centiseconds % 100;
            
            if (minutes > 0) {
                return `${minutes}:${seconds.toString().padStart(2, '0')}.${cs.toString().padStart(2, '0')}`;
            } else {
                return `${seconds}.${cs.toString().padStart(2, '0')}`;
            }
        }
        
        // 進歩グラフを表示
        window.showProgressChart = function(strokeType, distance, poolType) {
            console.log(`進歩グラフを表示: ${strokeType} ${distance}m ${poolType}`);
            
            // モーダルを作成
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-4xl w-full mx-4 max-h-screen overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">${getStrokeName(strokeType)} ${distance}m ${poolType} の進歩グラフ</h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="progress-chart-container" class="h-96">
                        <div class="flex items-center justify-center h-full">
                            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                            <span class="ml-3">データを読み込み中...</span>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // データを取得してグラフを描画
            fetch(`api/enhanced_competition.php?action=get_progress_chart&stroke_type=${strokeType}&distance_meters=${distance}&pool_type=${poolType}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.chart_data.length > 0) {
                        drawProgressChart(data.chart_data, data.event_info);
                    } else {
                        document.getElementById('progress-chart-container').innerHTML = `
                            <div class="text-center py-8">
                                <p class="text-gray-500">この種目の記録がまだありません。</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('進歩グラフデータ取得エラー:', error);
                    document.getElementById('progress-chart-container').innerHTML = `
                        <div class="text-center py-8">
                            <p class="text-red-500">データの取得中にエラーが発生しました。</p>
                        </div>
                    `;
                });
        };
        
        function drawProgressChart(chartData, eventInfo) {
            const container = document.getElementById('progress-chart-container');
            container.innerHTML = '<canvas id="progress-chart"></canvas>';
            
            const ctx = document.getElementById('progress-chart').getContext('2d');
            
            // データの準備
            const labels = chartData.map(item => {
                const date = new Date(item.date);
                return `${date.getMonth() + 1}/${date.getDate()}`;
            });
            
            const timeData = chartData.map(item => item.time_centiseconds / 100); // 秒単位に変換
            
            // グラフの設定
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'タイム (秒)',
                        data: timeData,
                        borderColor: 'rgba(59, 130, 246, 1)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: chartData.map(item => item.is_personal_best ? '#dc2626' : '#3b82f6'),
                        pointBorderColor: chartData.map(item => item.is_personal_best ? '#dc2626' : '#3b82f6'),
                        pointRadius: chartData.map(item => item.is_personal_best ? 6 : 4),
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false,
                            reverse: true, // 速いタイムが上に来るように
                            title: {
                                display: true,
                                text: 'タイム (秒)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return formatSecondsToTimeString(value);
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: '日付'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const item = chartData[context.dataIndex];
                                    let label = `タイム: ${item.formatted_time}`;
                                    
                                    if (item.is_personal_best) {
                                        label += ' (自己ベスト)';
                                    }
                                    
                                    if (item.rank) {
                                        label += ` | 順位: ${item.rank}位`;
                                    }
                                    
                                    return label;
                                },
                                afterLabel: function(context) {
                                    const item = chartData[context.dataIndex];
                                    return [
                                        `大会: ${item.competition_name}`,
                                        `記録種別: ${getRecordTypeName(item.record_type)}`,
                                        `公式記録: ${item.is_official ? 'はい' : 'いいえ'}`
                                    ];
                                }
                            }
                        },
                        legend: {
                            display: true,
                            labels: {
                                generateLabels: function() {
                                    return [
                                        {
                                            text: '通常記録',
                                            fillStyle: '#3b82f6',
                                            strokeStyle: '#3b82f6',
                                            pointStyle: 'circle'
                                        },
                                        {
                                            text: '自己ベスト',
                                            fillStyle: '#dc2626',
                                            strokeStyle: '#dc2626',
                                            pointStyle: 'circle'
                                        }
                                    ];
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function formatSecondsToTimeString(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = (seconds % 60).toFixed(2);
            
            if (minutes > 0) {
                return `${minutes}:${remainingSeconds.padStart(5, '0')}`;
            } else {
                return remainingSeconds;
            }
        }
        
        function getStrokeName(strokeType) {
            const strokeNames = {
                'butterfly': 'バタフライ',
                'backstroke': '背泳ぎ',
                'breaststroke': '平泳ぎ',
                'freestyle': '自由形',
                'medley': '個人メドレー'
            };
            return strokeNames[strokeType] || strokeType;
        }
        
        function getRecordTypeName(recordType) {
            const recordTypeNames = {
                'competition': '公式大会',
                'time_trial': 'タイム測定会',
                'practice': '練習記録',
                'relay_split': 'リレーのスプリット'
            };
            return recordTypeNames[recordType] || recordType;
        }
    });
    
    // 結果フィルター機能 false;
                } else if (field.element) {
                    field.element.classList.remove('border-red-500');
                }
            });
            
            // タイム形式の検証
            if (finalTimeInput && finalTimeInput.value) {
                const timePattern = /^(\d{1,2}:)?\d{1,2}\.\d{2}$/;
                if (!timePattern.test(finalTimeInput.value.trim())) {
                    errors.push('最終タイムの形式が正しくありません。');
                    finalTimeInput.classList.add('border-red-500');
                    isValid =</script>
    <script>
    // 結果フィルター機能
    function filterResults(type) {
        const rows = document.querySelectorAll('.result-row');
        const buttons = document.querySelectorAll('.filter-btn');
        
        // ボタンのアクティブ状態更新
        buttons.forEach(btn => {
            btn.classList.remove('active', 'bg-blue-500', 'text-white');
            btn.classList.add('border-gray-300', 'text-gray-700');
        });
        
        event.target.classList.add('active', 'bg-blue-500', 'text-white');
        event.target.classList.remove('border-gray-300', 'text-gray-700');
        
        // 行の表示/非表示
        rows.forEach(row => {
            let show = true;
            
            switch(type) {
                case 'official':
                    show = row.dataset.official === 'true';
                    break;
                case 'personal_best':
                    show = row.dataset.personalBest === 'true';
                    break;
                case 'all':
                default:
                    show = true;
                    break;
            }
            
            row.style.display = show ? '' : 'none';
        });
    }
    
    // ラップタイム表示モーダル
    function showLapTimes(resultId) {
        // APIからラップタイムデータを取得
        fetch(`api/enhanced_competition.php?action=get_lap_times&result_id=${resultId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayLapTimesModal(data.lap_times, data.result_info);
                } else {
                    alert('ラップタイムの取得に失敗しました。');
                }
            })
            .catch(error => {
                console.error('ラップタイム取得エラー:', error);
                alert('ラップタイムデータの取得中にエラーが発生しました。');
            });
    }
    
    function displayLapTimesModal(lapTimes, resultInfo) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        
        let lapTableHtml = '<table class="min-w-full text-sm"><thead><tr class="bg-gray-50"><th class="py-2 px-3 text-left">距離</th><th class="py-2 px-3 text-left">ラップタイム</th><th class="py-2 px-3 text-left">スプリット</th></tr></thead><tbody>';
        
        lapTimes.forEach((lap, index) => {
            lapTableHtml += `
                <tr class="border-b">
                    <td class="py-2 px-3">${lap.distance_meters}m</td>
                    <td class="py-2 px-3 font-medium">${formatTimeFromCentiseconds(lap.lap_time_centiseconds)}</td>
                    <td class="py-2 px-3">${formatTimeFromCentiseconds(lap.split_time_centiseconds)}</td>
                </tr>
            `;
        });
        
        lapTableHtml += '</tbody></table>';
        
        modal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4 max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">${resultInfo.event_display} - ラップタイム</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mb-4">
                    <p class="text-sm text-gray-600">最終タイム: <span class="font-medium">${resultInfo.final_time}</span></p>
                </div>
                <div class="overflow-x-auto">
                    ${lapTableHtml}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    function formatTimeFromCentiseconds(centiseconds) {
        const minutes = Math.floor(centiseconds / 6000);
        const seconds = Math.floor((centiseconds % 6000) / 100);
        const cs = centiseconds % 100;
        
        if (minutes > 0) {
            return `${minutes}:${seconds.toString().padStart(2, '0')}.${cs.toString().padStart(2, '0')}`;
        } else {
            return `${seconds}.${cs.toString().padStart(2, '0')}`;
        }
    }
    
    // エクスポート機能
    function exportResults() {
        const competitionId = <?php echo $competitionId; ?>;
        window.open(`api/export_results.php?competition_id=${competitionId}&format=csv`, '_blank');
    }
    </script>
    
    <?php } ?>
<?php else: ?>
    <!-- 大会一覧表示（既存コードを使用） -->
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">大会記録</h1>
        <a href="competition.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> 新しい大会を記録
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <p class="text-center text-gray-500">大会一覧機能は既存のcompetition.phpを使用してください。</p>
    </div>
<?php endif; ?>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>