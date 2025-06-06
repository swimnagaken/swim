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
    <!-- 新規練習記録フォーム -->
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">新しい練習を記録</h1>
        <a href="practice.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-1"></i> 練習一覧に戻る
        </a>
    </div>
    
    <?php
    // テンプレートからのロード
    $template = null;
    if (isset($_GET['template_id']) && is_numeric($_GET['template_id'])) {
        $template_id = (int)$_GET['template_id'];
        try {
            $db = getDbConnection();
            
            // テンプレート基本情報を取得
            $stmt = $db->prepare("
                SELECT * FROM practice_templates
                WHERE template_id = ? AND user_id = ?
            ");
            $stmt->execute([$template_id, $_SESSION['user_id']]);
            $template = $stmt->fetch();
            
            if ($template) {
                // テンプレートセットを取得
                $stmt = $db->prepare("
                    SELECT ts.*, wt.type_name
                    FROM template_sets ts
                    LEFT JOIN workout_types wt ON ts.type_id = wt.type_id
                    WHERE ts.template_id = ?
                    ORDER BY ts.order_index
                ");
                $stmt->execute([$template_id]);
                $template['sets'] = $stmt->fetchAll();
                
                // セットごとの器具情報を取得
                $equipment = [];
                foreach ($template['sets'] as $set) {
                    $stmt = $db->prepare("
                        SELECT tse.*, e.equipment_name
                        FROM template_set_equipment tse
                        JOIN equipment e ON tse.equipment_id = e.equipment_id
                        WHERE tse.set_id = ?
                    ");
                    $stmt->execute([$set['set_id']]);
                    $equipment[$set['set_id']] = $stmt->fetchAll();
                }
                $template['equipment'] = $equipment;
            }
        } catch (PDOException $e) {
            error_log('テンプレート読み込みエラー: ' . $e->getMessage());
        }
    }
    
    // プール一覧を取得
    $pools = [];
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("
            SELECT * FROM pools
            WHERE user_id = ?
            ORDER BY is_favorite DESC, pool_name ASC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $pools = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('プール一覧取得エラー: ' . $e->getMessage());
    }
    
    // 練習種別一覧を取得
    $workout_types = [];
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("
            SELECT * FROM workout_types
            WHERE user_id = ? OR is_system = 1
            ORDER BY is_system DESC, type_name ASC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $workout_types = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('練習種別一覧取得エラー: ' . $e->getMessage());
    }
    
    // 器具一覧を取得
    $equipment_list = [];
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("
            SELECT * FROM equipment
            WHERE user_id = ? OR is_system = 1
            ORDER BY is_system DESC, equipment_name ASC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $equipment_list = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('器具一覧取得エラー: ' . $e->getMessage());
    }
    ?>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="POST" action="api/practice.php" id="practice-form">
            <!-- CSRFトークン -->
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- 練習日 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="practice_date">練習日 <span class="text-red-500">*</span></label>
                    <input
                        type="date"
                        id="practice_date"
                        name="practice_date"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                        value="<?php echo date('Y-m-d'); ?>"
                        required
                    >
                </div>
                
                <!-- 総距離 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="total_distance">総距離 (m) <span class="text-red-500">*</span></label>
                    <input
                        type="number"
                        id="total_distance"
                        name="total_distance"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                        min="0"
                        step="50"
                        value="<?php echo $template ? $template['total_distance'] : ''; ?>"
                        required
                    >
                </div>
                
                <!-- プール選択 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="pool_id">プール</label>
                    <div class="flex space-x-2">
                        <select
                            id="pool_id"
                            name="pool_id"
                            class="w-full border border-gray-300 rounded-md px-3 py-2"
                        >
                            <option value="">選択してください</option>
                            <?php foreach ($pools as $pool): ?>
                            <option value="<?php echo $pool['pool_id']; ?>">
                                <?php echo h($pool['pool_name']); ?>
                                <?php echo $pool['is_favorite'] ? ' ⭐' : ''; ?>
                                (<?php echo h($pool['pool_length']); ?>m)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="pools.php" class="bg-blue-100 text-blue-600 hover:bg-blue-200 px-3 py-2 rounded-md flex items-center" title="プール管理">
                            <i class="fas fa-plus"></i>
                        </a>
                    </div>
                </div>
                
                <!-- 練習時間 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="duration_hours">練習時間</label>
                    <div class="flex space-x-2">
                        <div class="w-1/2">
                            <select
                                id="duration_hours"
                                name="duration_hours"
                                class="w-full border border-gray-300 rounded-md px-3 py-2"
                            >
                                <option value="0">0時間</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?>時間</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="w-1/2">
                            <select
                                id="duration_minutes"
                                name="duration_minutes"
                                class="w-full border border-gray-300 rounded-md px-3 py-2"
                            >
                                <?php for ($i = 0; $i <= 55; $i += 5): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?>分</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- その他の入力項目 -->
            <div class="mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- 調子 -->
                    <div>
    <label class="block text-gray-700 mb-2" for="feeling">調子（1:悪い ～ 5:良い）</label>
    <div class="flex items-center space-x-1">
        <?php for ($i = 1; $i <= 5; $i++): ?>
        <label class="flex items-center cursor-pointer">
            <input type="radio" name="feeling" value="<?php echo $i; ?>" class="hidden peer" <?php echo $i === 3 ? 'checked' : ''; ?>>
            <span class="<?php echo $i === 3 ? 'text-blue-500 font-medium' : ''; ?>">
             <?php echo $i; ?>
            </span>
        </label>
        <?php endfor; ?>
    </div>
</div>
                    
                    <!-- 次回練習予定 -->
                    <div>
                        <label class="block text-gray-700 mb-2" for="next_practice_date">次回練習予定</label>
                        <div class="flex items-center space-x-2">
                            <input
                                type="date"
                                id="next_practice_date"
                                name="next_practice_date"
                                class="w-full border border-gray-300 rounded-md px-3 py-2"
                                min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                            >
                            <div class="flex items-center ml-2">
                                <input
                                    type="checkbox"
                                    id="next_practice_reminder"
                                    name="next_practice_reminder"
                                    class="h-4 w-4 text-blue-600"
                                >
                                <label for="next_practice_reminder" class="ml-2 text-sm text-gray-700">
                                    リマインダー
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 課題・振り返り -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- 課題 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="challenge">今日の課題</label>
                    <textarea
                        id="challenge"
                        name="challenge"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 h-24"
                        placeholder="例: キックの強化、呼吸の安定など"
                    ></textarea>
                </div>
                
                <!-- 振り返り -->
                <div>
                    <label class="block text-gray-700 mb-2" for="reflection">振り返り</label>
                    <textarea
                        id="reflection"
                        name="reflection"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 h-24"
                        placeholder="例: キックが安定してきた、ターンがスムーズになってきたなど"
                    ></textarea>
                </div>
            </div>
            
            <!-- セット詳細 -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">セット詳細</h3>
                    <div>
                        <button type="button" id="add-set" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus mr-1"></i> セット追加
                        </button>
                        <a href="equipment.php" class="text-blue-600 hover:text-blue-800 ml-4">
                            <i class="fas fa-cog mr-1"></i> 種別・器具管理
                        </a>
                    </div>
                </div>
                
                <div id="sets-container">
                    <?php if ($template && !empty($template['sets'])): ?>
                        <?php foreach ($template['sets'] as $index => $set): ?>
                            <div class="set-item border border-gray-200 rounded-md p-4 mb-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h4 class="font-medium">セット <?php echo $index + 1; ?></h4>
                                    <button type="button" class="text-red-600 hover:text-red-800 remove-set">
                                        <i class="fas fa-times"></i> 削除
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <!-- 種別 -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">種別</label>
                                        <select 
                                            name="sets[<?php echo $index; ?>][type_id]" 
                                            class="w-full border border-gray-300 rounded-md px-3 py-2"
                                        >
                                            <option value="">選択してください</option>
                                            <?php foreach ($workout_types as $type): ?>
                                            <option 
                                                value="<?php echo $type['type_id']; ?>"
                                                <?php echo $set['type_id'] == $type['type_id'] ? 'selected' : ''; ?>
                                            >
                                                <?php echo h($type['type_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- 泳法 -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">泳法</label>
                                        <select 
                                            name="sets[<?php echo $index; ?>][stroke_type]" 
                                            class="w-full border border-gray-300 rounded-md px-3 py-2"
                                        >
                                            <option value="freestyle" <?php echo $set['stroke_type'] === 'freestyle' ? 'selected' : ''; ?>>自由形</option>
                                            <option value="backstroke" <?php echo $set['stroke_type'] === 'backstroke' ? 'selected' : ''; ?>>背泳ぎ</option>
                                            <option value="breaststroke" <?php echo $set['stroke_type'] === 'breaststroke' ? 'selected' : ''; ?>>平泳ぎ</option>
                                            <option value="butterfly" <?php echo $set['stroke_type'] === 'butterfly' ? 'selected' : ''; ?>>バタフライ</option>
                                            <option value="im" <?php echo $set['stroke_type'] === 'im' ? 'selected' : ''; ?>>個人メドレー</option>
                                            <option value="other" <?php echo $set['stroke_type'] === 'other' ? 'selected' : ''; ?>>その他</option>
                                        </select>
                                    </div>
                                    
                                    <!-- 器具 -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">器具</label>
                                        <select 
                                            name="sets[<?php echo $index; ?>][equipment][]" 
                                            class="equipment-select w-full border border-gray-300 rounded-md px-3 py-2"
                                            multiple
                                        >
                                            <?php 
                                            // セットに紐づく器具のIDを取得
                                            $selectedEquipment = [];
                                            if (isset($template['equipment'][$set['set_id']])) {
                                                foreach ($template['equipment'][$set['set_id']] as $eq) {
                                                    $selectedEquipment[] = $eq['equipment_id'];
                                                }
                                            }
                                            
                                            foreach ($equipment_list as $eq): 
                                            ?>
                                            <option 
                                                value="<?php echo $eq['equipment_id']; ?>"
                                                <?php echo in_array($eq['equipment_id'], $selectedEquipment) ? 'selected' : ''; ?>
                                            >
                                                <?php echo h($eq['equipment_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <!-- 距離 -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">距離 (m)</label>
                                        <input 
                                            type="number" 
                                            name="sets[<?php echo $index; ?>][distance]" 
                                            value="<?php echo $set['distance']; ?>"
                                            min="25" 
                                            step="25" 
                                            class="set-distance w-full border border-gray-300 rounded-md px-3 py-2"
                                        >
                                    </div>
                                    
                                    <!-- 回数 -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">回数</label>
                                        <input 
                                            type="number" 
                                            name="sets[<?php echo $index; ?>][repetitions]" 
                                            value="<?php echo $set['repetitions']; ?>"
                                            min="1" 
                                            class="set-repetitions w-full border border-gray-300 rounded-md px-3 py-2"
                                        >
                                    </div>
                                    
                                    <!-- インターバル -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">サイクル</label>
                                        <input 
                                            type="text" 
                                            name="sets[<?php echo $index; ?>][cycle]" 
                                            value="<?php echo $set['cycle']; ?>"
                                            placeholder="例: 1:30、R30など" 
                                            class="w-full border border-gray-300 rounded-md px-3 py-2"
                                        >
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- 合計距離 -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">セット合計距離 (m)</label>
                                        <input 
                                            type="number" 
                                            name="sets[<?php echo $index; ?>][total_distance]" 
                                            value="<?php echo $set['total_distance']; ?>"
                                            min="0" 
                                            class="set-total w-full border border-gray-300 rounded-md px-3 py-2"
                                            readonly
                                        >
                                    </div>
                                    
                                    <!-- メモ -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">メモ</label>
                                        <input 
                                            type="text" 
                                            name="sets[<?php echo $index; ?>][notes]" 
                                            value="<?php echo h($set['notes'] ?? ''); ?>"
                                            class="w-full border border-gray-300 rounded-md px-3 py-2"
                                        >
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- テンプレートがない場合、デフォルトのセット -->
                        <div class="set-item border border-gray-200 rounded-md p-4 mb-4">
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="font-medium">セット 1</h4>
                                <button type="button" class="text-red-600 hover:text-red-800 remove-set">
                                    <i class="fas fa-times"></i> 削除
                                </button>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <!-- 種別 -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">種別</label>
                                    <select 
                                        name="sets[0][type_id]" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                                    >
                                        <option value="">選択してください</option>
                                        <?php foreach ($workout_types as $type): ?>
                                        <option value="<?php echo $type['type_id']; ?>">
                                            <?php echo h($type['type_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- 泳法 -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">泳法</label>
                                    <select 
                                        name="sets[0][stroke_type]" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                                    >
                                        <option value="freestyle">自由形</option>
                                        <option value="backstroke">背泳ぎ</option>
                                        <option value="breaststroke">平泳ぎ</option>
                                        <option value="butterfly">バタフライ</option>
                                        <option value="im">個人メドレー</option>
                                        <option value="other">その他</option>
                                    </select>
                                </div>
                                
                                <!-- 器具 -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">器具</label>
                                    <select 
                                        name="sets[0][equipment][]" 
                                        class="equipment-select w-full border border-gray-300 rounded-md px-3 py-2"
                                        multiple
                                    >
                                        <?php foreach ($equipment_list as $equipment): ?>
                                        <option value="<?php echo $equipment['equipment_id']; ?>">
                                            <?php echo h($equipment['equipment_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <!-- 距離 -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">距離 (m)</label>
                                    <input 
                                        type="number" 
                                        name="sets[0][distance]" 
                                        value="100"
                                        min="25" 
                                        step="25" 
                                        class="set-distance w-full border border-gray-300 rounded-md px-3 py-2"
                                    >
                                </div>
                                
                                <!-- 回数 -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">回数</label>
                                    <input 
                                        type="number" 
                                        name="sets[0][repetitions]" 
                                        value="1"
                                        min="1" 
                                        class="set-repetitions w-full border border-gray-300 rounded-md px-3 py-2"
                                    >
                                </div>
                                
                                <!-- インターバル -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">サイクル</label>
                                    <input 
                                        type="text" 
                                        name="sets[0][cycle]" 
                                        placeholder="例: 1:30、R30など" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                                    >
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- 合計距離 -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">セット合計距離 (m)</label>
                                    <input 
                                        type="number" 
                                        name="sets[0][total_distance]" 
                                        value="100"
                                        min="0" 
                                        class="set-total w-full border border-gray-300 rounded-md px-3 py-2"
                                        readonly
                                    >
                                </div>
                                
                                <!-- メモ -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">メモ</label>
                                    <input 
                                        type="text" 
                                        name="sets[0][notes]" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                                    >
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="flex justify-end">
                <a href="practice.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-6 rounded-lg mr-2">
                    キャンセル
                </a>
                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg"
                >
                    練習を記録する
                    </button>
            </div>
        </form>
    </div>
    
    <!-- テンプレートコンテナ（新規セット用） -->
    <template id="set-template">
        <div class="set-item border border-gray-200 rounded-md p-4 mb-4">
            <div class="flex justify-between items-center mb-3">
                <h4 class="font-medium">セット {index}</h4>
                <button type="button" class="text-red-600 hover:text-red-800 remove-set">
                    <i class="fas fa-times"></i> 削除
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- 種別 -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">種別</label>
                    <select 
                        name="sets[{index}][type_id]" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                        <option value="">選択してください</option>
                        <?php foreach ($workout_types as $type): ?>
                        <option value="<?php echo $type['type_id']; ?>">
                            <?php echo h($type['type_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- 泳法 -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">泳法</label>
                    <select 
                        name="sets[{index}][stroke_type]" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                        <option value="freestyle">自由形</option>
                        <option value="backstroke">背泳ぎ</option>
                        <option value="breaststroke">平泳ぎ</option>
                        <option value="butterfly">バタフライ</option>
                        <option value="im">個人メドレー</option>
                        <option value="other">その他</option>
                    </select>
                </div>
                
                <!-- 器具 -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">器具</label>
                    <select 
                        name="sets[{index}][equipment][]" 
                        class="equipment-select w-full border border-gray-300 rounded-md px-3 py-2"
                        multiple
                    >
                        <?php foreach ($equipment_list as $equipment): ?>
                        <option value="<?php echo $equipment['equipment_id']; ?>">
                            <?php echo h($equipment['equipment_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- 距離 -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">距離 (m)</label>
                    <input 
                        type="number" 
                        name="sets[{index}][distance]" 
                        value="100"
                        min="25" 
                        step="25" 
                        class="set-distance w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                </div>
                
                <!-- 回数 -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">回数</label>
                    <input 
                        type="number" 
                        name="sets[{index}][repetitions]" 
                        value="1"
                        min="1" 
                        class="set-repetitions w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                </div>
                
                <!-- インターバル -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">サイクル</label>
                    <input 
                        type="text" 
                        name="sets[{index}][cycle]" 
                        placeholder="例: 1:30、R30など" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- 合計距離 -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">セット合計距離 (m)</label>
                    <input 
                        type="number" 
                        name="sets[{index}][total_distance]" 
                        value="100"
                        min="0" 
                        class="set-total w-full border border-gray-300 rounded-md px-3 py-2"
                        readonly
                    >
                </div>
                
                <!-- メモ -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">メモ</label>
                    <input 
                        type="text" 
                        name="sets[{index}][notes]" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                </div>
            </div>
        </div>
    </template>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 器具選択の初期化
        initializeEquipmentSelects();
        
        // セット合計距離の計算
        initializeSetCalculations();
        
        // 総距離の自動計算
        calculateTotalDistance();
        
        // セット追加ボタン
        const addSetButton = document.getElementById('add-set');
        if (addSetButton) {
            addSetButton.addEventListener('click', function() {
                addNewSet();
            });
        }
        
        // 初期セットでの削除ボタンイベント付与
        bindRemoveSetEvents();
    });
    
    // 器具選択の初期化
    function initializeEquipmentSelects() {
        document.querySelectorAll('.equipment-select').forEach(select => {
            // ここでは簡易的な実装。実際にはSelect2などのライブラリを使うことを推奨
            select.addEventListener('click', function(e) {
                if (e.target.tagName === 'OPTION') {
                    e.preventDefault();
                    e.target.selected = !e.target.selected;
                }
            });
        });
    }
    
    // セット合計距離の計算初期化
    function initializeSetCalculations() {
        document.querySelectorAll('.set-item').forEach(setItem => {
            const distanceInput = setItem.querySelector('.set-distance');
            const repsInput = setItem.querySelector('.set-repetitions');
            const totalInput = setItem.querySelector('.set-total');
            
            if (distanceInput && repsInput && totalInput) {
                const calculateTotal = () => {
                    const distance = parseInt(distanceInput.value) || 0;
                    const reps = parseInt(repsInput.value) || 1;
                    totalInput.value = distance * reps;
                    
                    // 全体の総距離も更新
                    calculateTotalDistance();
                };
                
                distanceInput.addEventListener('input', calculateTotal);
                repsInput.addEventListener('input', calculateTotal);
            }
        });
    }
    
    // 総距離の自動計算
    function calculateTotalDistance() {
        const totalDistanceInput = document.getElementById('total_distance');
        const setTotals = document.querySelectorAll('.set-total');
        
        if (totalDistanceInput && setTotals.length > 0) {
            let sum = 0;
            setTotals.forEach(input => {
                sum += parseInt(input.value) || 0;
            });
            
            totalDistanceInput.value = sum;
        }
    }
    
    // 新しいセットを追加
    function addNewSet() {
        const container = document.getElementById('sets-container');
        const template = document.getElementById('set-template');
        const setItems = container.querySelectorAll('.set-item');
        const newIndex = setItems.length;
        
        // テンプレートのクローンを作成
        const clone = template.content.cloneNode(true);
        const setItem = clone.querySelector('.set-item');
        
        // インデックスの置換
        const setTitle = setItem.querySelector('h4');
        setTitle.textContent = setTitle.textContent.replace('{index}', newIndex + 1);
        
        // 名前属性の置換
        setItem.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/\{index\}/g, newIndex);
        });
        
        // コンテナに追加
        container.appendChild(setItem);
        
        // イベントリスナーを設定
        bindRemoveSetEvents();
        initializeEquipmentSelects();
        initializeSetCalculations();
    }
    
    // 削除ボタンイベントのバインド
    function bindRemoveSetEvents() {
        document.querySelectorAll('.remove-set').forEach(btn => {
            // 既存のイベントリスナーを削除（重複防止）
            btn.removeEventListener('click', handleRemoveSet);
            
            // 新しいイベントリスナーを追加
            btn.addEventListener('click', handleRemoveSet);
        });
    }
    
    // セット削除処理
    function handleRemoveSet() {
        const setItem = this.closest('.set-item');
        const container = document.getElementById('sets-container');
        const setItems = container.querySelectorAll('.set-item');
        
        // 最後の1つは削除しない
        if (setItems.length <= 1) {
            alert('最低1つのセットが必要です。');
            return;
        }
        
        // セットを削除
        setItem.remove();
        
        // 残りのセットのインデックスを更新
        updateSetIndexes();
        
        // 総距離を再計算
        calculateTotalDistance();
    }
    
    // セットインデックスの更新
    function updateSetIndexes() {
        const container = document.getElementById('sets-container');
        const setItems = container.querySelectorAll('.set-item');
        
        setItems.forEach((item, index) => {
            // タイトル更新
            const title = item.querySelector('h4');
            if (title) {
                title.textContent = 'セット ' + (index + 1);
            }
            
            // name属性の更新
            item.querySelectorAll('[name]').forEach(el => {
                el.name = el.name.replace(/sets\[\d+\]/, 'sets[' + index + ']');
            });
        });
    }
    </script>

<?php elseif ($action === 'view' && $sessionId > 0): ?>
    <!-- 練習詳細表示 -->
    <?php
    // 練習セッションの取得
    $practice = null;
    $sets = [];
    
    try {
        $db = getDbConnection();
        
        // 練習情報を取得
        $stmt = $db->prepare("
            SELECT p.*, pl.pool_name, pl.pool_length
            FROM practice_sessions p
            LEFT JOIN pools pl ON p.pool_id = pl.pool_id
            WHERE p.session_id = ? AND p.user_id = ?
        ");
        $stmt->execute([$sessionId, $_SESSION['user_id']]);
        $practice = $stmt->fetch();
        
        if ($practice) {
            // セット情報を取得
            $stmt = $db->prepare("
                SELECT ps.*, wt.type_name
                FROM practice_sets ps
                LEFT JOIN workout_types wt ON ps.type_id = wt.type_id
                WHERE ps.session_id = ?
                ORDER BY ps.set_id
            ");
            $stmt->execute([$sessionId]);
            $sets = $stmt->fetchAll();
            
            // セットごとの器具情報を取得
            $equipment = [];
            foreach ($sets as $set) {
                $stmt = $db->prepare("
                    SELECT se.*, e.equipment_name
                    FROM set_equipment se
                    JOIN equipment e ON se.equipment_id = e.equipment_id
                    WHERE se.set_id = ?
                ");
                $stmt->execute([$set['set_id']]);
                $equipment[$set['set_id']] = $stmt->fetchAll();
            }
            $practice['equipment'] = $equipment;
        }
    } catch (PDOException $e) {
        error_log('練習詳細取得エラー: ' . $e->getMessage());
    }
    
    if (!$practice) {
        $_SESSION['error_messages'][] = '指定された練習が見つからないか、アクセス権がありません。';
        header('Location: practice.php');
        exit;
    }
    ?>
    
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">練習詳細</h1>
        <div class="flex space-x-3">
            <a href="practice.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i> 一覧に戻る
            </a>
            <a href="practice.php?action=edit&id=<?php echo $sessionId; ?>" class="text-green-600 hover:text-green-800">
                <i class="fas fa-edit mr-1"></i> 編集
            </a>
        </div>
    </div>
    
    <!-- 練習概要 -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between mb-4">
            <h2 class="text-xl font-semibold">練習概要</h2>
            <div>
                <a href="api/templates.php?action=create_from_practice&session_id=<?php echo $sessionId; ?>" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-save mr-1"></i> テンプレートとして保存
                </a>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-4">
            <div>
                <p class="text-gray-600 text-sm">練習日</p>
                <p class="font-medium">
                    <?php echo date('Y年n月j日 (', strtotime($practice['practice_date'])); ?>
                    <?php echo ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($practice['practice_date']))]; ?>
                    <?php echo ')'; ?>
                </p>
            </div>
            
            <div>
                <p class="text-gray-600 text-sm">総距離</p>
                <p class="font-medium"><?php echo number_format($practice['total_distance']); ?> m</p>
            </div>
            
            <div>
                <p class="text-gray-600 text-sm">プール</p>
                <p class="font-medium">
                    <?php 
                    if ($practice['pool_id']) {
                        echo h($practice['pool_name']) . ' (' . h($practice['pool_length']) . 'm)';
                    } else {
                        echo '-';
                    }
                    ?>
                </p>
            </div>
            
            <div>
                <p class="text-gray-600 text-sm">練習時間</p>
                <p class="font-medium">
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
                </p>
            </div>
            
            <div>
                <p class="text-gray-600 text-sm">調子</p>
                <p class="font-medium">
                    <?php 
                    if ($practice['feeling']) {
                        echo $practice['feeling'] . ' / 5';
                    } else {
                        echo '-';
                    }
                    ?>
                </p>
            </div>
            
            <div>
                <p class="text-gray-600 text-sm">次回練習予定</p>
                <p class="font-medium">
                    <?php
                    if ($practice['next_practice_date']) {
                        echo date('Y年n月j日', strtotime($practice['next_practice_date']));
                        if ($practice['next_practice_reminder']) {
                            echo ' <span class="text-blue-600"><i class="fas fa-bell" title="リマインダー設定済み"></i></span>';
                        }
                    } else {
                        echo '-';
                    }
                    ?>
                </p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php if ($practice['challenge']): ?>
            <div>
                <p class="text-gray-600 text-sm">今日の課題</p>
                <div class="bg-gray-50 p-3 rounded mt-1">
                    <?php echo nl2br(h($practice['challenge'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($practice['reflection']): ?>
            <div>
                <p class="text-gray-600 text-sm">振り返り</p>
                <div class="bg-gray-50 p-3 rounded mt-1">
                    <?php echo nl2br(h($practice['reflection'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- セット詳細 -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">セット詳細</h2>
        
        <?php if (empty($sets)): ?>
        <p class="text-gray-500 text-center py-4">セット情報はありません</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="py-2 px-4 text-left">種別</th>
                        <th class="py-2 px-4 text-left">泳法</th>
                        <th class="py-2 px-4 text-left">距離</th>
                        <th class="py-2 px-4 text-left">器具</th>
                        <th class="py-2 px-4 text-left">メモ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $strokeNames = [
                        'freestyle' => '自由形',
                        'backstroke' => '背泳ぎ',
                        'breaststroke' => '平泳ぎ',
                        'butterfly' => 'バタフライ',
                        'im' => '個人メドレー',
                        'other' => 'その他'
                    ];
                    
                    foreach ($sets as $set): 
                    ?>
                    <tr class="border-b">
                        <td class="py-3 px-4"><?php echo h($set['type_name'] ?? '-'); ?></td>
                        <td class="py-3 px-4"><?php echo h($strokeNames[$set['stroke_type']] ?? $set['stroke_type']); ?></td>
                        <td class="py-3 px-4">
                            <?php
                            if ($set['repetitions'] > 1) {
                                echo $set['distance'] . 'm × ' . $set['repetitions'] . ' = ' . $set['total_distance'] . 'm';
                                if ($set['cycle']) {
                                    echo ' @ ' . h($set['cycle']);
                                }
                            } else {
                                echo $set['distance'] . 'm';
                            }
                            ?>
                        </td>
                        <td class="py-3 px-4">
                            <?php
                            if (isset($practice['equipment'][$set['set_id']]) && count($practice['equipment'][$set['set_id']]) > 0) {
                                $equipmentNames = [];
                                foreach ($practice['equipment'][$set['set_id']] as $eq) {
                                    $equipmentNames[] = h($eq['equipment_name']);
                                }
                                echo implode(', ', $equipmentNames);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="py-3 px-4"><?php echo h($set['notes'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- アクションボタン -->
    <div class="flex justify-between mb-8">
        <form method="POST" action="api/practice.php" onsubmit="return confirm('この練習記録を削除してもよろしいですか？');">
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="session_id" value="<?php echo $sessionId; ?>">
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg">
                <i class="fas fa-trash mr-1"></i> 削除
            </button>
        </form>
        
        <a href="practice.php?action=edit&id=<?php echo $sessionId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
            <i class="fas fa-edit mr-1"></i> 編集
        </a>
    </div>

<?php elseif ($action === 'edit' && $sessionId > 0): ?>
    <!-- 練習編集フォーム -->
    <?php
    // 練習セッションの取得
    $practice = null;
    $sets = [];
    
    try {
        $db = getDbConnection();
        
        // 練習情報を取得
        $stmt = $db->prepare("
            SELECT p.*
            FROM practice_sessions p
            WHERE p.session_id = ? AND p.user_id = ?
        ");
        $stmt->execute([$sessionId, $_SESSION['user_id']]);
        $practice = $stmt->fetch();
        
        if ($practice) {
            // セット情報を取得
            $stmt = $db->prepare("
                SELECT ps.*, wt.type_name
                FROM practice_sets ps
                LEFT JOIN workout_types wt ON ps.type_id = wt.type_id
                WHERE ps.session_id = ?
                ORDER BY ps.set_id
            ");
            $stmt->execute([$sessionId]);
            $sets = $stmt->fetchAll();
            
            // セットごとの器具情報を取得
            $equipment = [];
            foreach ($sets as $set) {
                $stmt = $db->prepare("
                    SELECT se.equipment_id
                    FROM set_equipment se
                    WHERE se.set_id = ?
                ");
                $stmt->execute([$set['set_id']]);
                $equipment[$set['set_id']] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            $practice['equipment'] = $equipment;
        }
        
        // プール一覧を取得
        $stmt = $db->prepare("
            SELECT * FROM pools
            WHERE user_id = ?
            ORDER BY is_favorite DESC, pool_name ASC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $pools = $stmt->fetchAll();
        
        // 練習種別一覧を取得
        $stmt = $db->prepare("
            SELECT * FROM workout_types
            WHERE user_id = ? OR is_system = 1
            ORDER BY is_system DESC, type_name ASC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $workout_types = $stmt->fetchAll();
        
        // 器具一覧を取得
        $stmt = $db->prepare("
            SELECT * FROM equipment
            WHERE user_id = ? OR is_system = 1
            ORDER BY is_system DESC, equipment_name ASC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $equipment_list = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log('練習詳細取得エラー: ' . $e->getMessage());
    }
    
    if (!$practice) {
        $_SESSION['error_messages'][] = '指定された練習が見つからないか、アクセス権がありません。';
        header('Location: practice.php');
        exit;
    }
    
    // 練習時間を時と分に分解
    $duration_hours = 0;
    $duration_minutes = 0;
    if ($practice['duration']) {
        $duration_hours = floor($practice['duration'] / 60);
        $duration_minutes = $practice['duration'] % 60;
    }
    ?>
    
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">練習記録の編集</h1>
        <a href="practice.php?action=view&id=<?php echo $sessionId; ?>" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-1"></i> 詳細に戻る
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="POST" action="api/practice.php" id="practice-form">
            <!-- CSRFトークン -->
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="session_id" value="<?php echo $sessionId; ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- 練習日 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="practice_date">練習日 <span class="text-red-500">*</span></label>
                    <input
                        type="date"
                        id="practice_date"
                        name="practice_date"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                        value="<?php echo $practice['practice_date']; ?>"
                        required
                    >
                </div>
                
                <!-- 総距離 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="total_distance">総距離 (m) <span class="text-red-500">*</span></label>
                    <input
                        type="number"
                        id="total_distance"
                        name="total_distance"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                        min="0"
                        step="50"
                        value="<?php echo $practice['total_distance']; ?>"
                        required
                    >
                </div>
                
                <!-- プール選択 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="pool_id">プール</label>
                    <div class="flex space-x-2">
                        <select
                            id="pool_id"
                            name="pool_id"
                            class="w-full border border-gray-300 rounded-md px-3 py-2"
                        >
                            <option value="">選択してください</option>
                            <?php foreach ($pools as $pool): ?>
                            <option 
                                value="<?php echo $pool['pool_id']; ?>"
                                <?php echo $pool['pool_id'] == $practice['pool_id'] ? 'selected' : ''; ?>
                            >
                                <?php echo h($pool['pool_name']); ?>
                                <?php echo $pool['is_favorite'] ? ' ⭐' : ''; ?>
                                (<?php echo h($pool['pool_length']); ?>m)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="pools.php" class="bg-blue-100 text-blue-600 hover:bg-blue-200 px-3 py-2 rounded-md flex items-center" title="プール管理">
                            <i class="fas fa-plus"></i>
                        </a>
                    </div>
                </div>
                
                <!-- 練習時間 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="duration_hours">練習時間</label>
                    <div class="flex space-x-2">
                        <div class="w-1/2">
                            <select
                                id="duration_hours"
                                name="duration_hours"
                                class="w-full border border-gray-300 rounded-md px-3 py-2"
                            >
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $duration_hours === $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>時間
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="w-1/2">
                            <select
                                id="duration_minutes"
                                name="duration_minutes"
                                class="w-full border border-gray-300 rounded-md px-3 py-2"
                            >
                                <?php for ($i = 0; $i <= 55; $i += 5): ?>
                                <option value="<?php echo $i; ?>" <?php echo $duration_minutes === $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>分
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- その他の入力項目 -->
            <div class="mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- 調子 -->
                    <div>
    <label class="block text-gray-700 mb-2" for="feeling">調子（1:悪い ～ 5:良い）</label>
    <div class="flex items-center space-x-1">
        <?php for ($i = 1; $i <= 5; $i++): ?>
        <label class="flex items-center cursor-pointer">
            <input type="radio" name="feeling" value="<?php echo $i; ?>" class="hidden peer" <?php echo $i === 3 ? 'checked' : ''; ?>>
            <span class="<?php echo $i === 3 ? 'text-blue-500 font-medium' : ''; ?>">
             <?php echo $i; ?>
            </span>
        </label>
        <?php endfor; ?>
    </div>
</div>
                    
                    <!-- 次回練習予定 -->
                    <div>
                        <label class="block text-gray-700 mb-2" for="next_practice_date">次回練習予定</label>
                        <div class="flex items-center space-x-2">
                            <input
                                type="date"
                                id="next_practice_date"
                                name="next_practice_date"
                                class="w-full border border-gray-300 rounded-md px-3 py-2"
                                value="<?php echo $practice['next_practice_date'] ?? ''; ?>"
                            >
                            <div class="flex items-center ml-2">
                                <input
                                    type="checkbox"
                                    id="next_practice_reminder"
                                    name="next_practice_reminder"
                                    class="h-4 w-4 text-blue-600"
                                    <?php echo $practice['next_practice_reminder'] ? 'checked' : ''; ?>
                                >
                                <label for="next_practice_reminder" class="ml-2 text-sm text-gray-700">
                                    リマインダー
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 課題・振り返り -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- 課題 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="challenge">今日の課題</label>
                    <textarea
                        id="challenge"
                        name="challenge"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 h-24"
                        placeholder="例: キックの強化、呼吸の安定など"
                    ><?php echo h($practice['challenge'] ?? ''); ?></textarea>
                </div>
                
                <!-- 振り返り -->
                <div>
                    <label class="block text-gray-700 mb-2" for="reflection">振り返り</label>
                    <textarea
                        id="reflection"
                        name="reflection"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 h-24"
                        placeholder="例: キックが安定してきた、ターンがスムーズになってきたなど"
                    ><?php echo h($practice['reflection'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <!-- セット詳細 -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">セット詳細</h3>
                    <div>
                        <button type="button" id="add-set" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus mr-1"></i> セット追加
                        </button>
                        <a href="equipment.php" class="text-blue-600 hover:text-blue-800 ml-4">
                            <i class="fas fa-cog mr-1"></i> 種別・器具管理
                        </a>
                    </div>
                </div>
                
                <div id="sets-container">
                    <?php if (!empty($sets)): ?>
                        <?php foreach ($sets as $index => $set): ?>
                            <div class="set-item border border-gray-200 rounded-md p-4 mb-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h4 class="font-medium">セット <?php echo $index + 1; ?></h4>
                                    <button type="button" class="text-red-600 hover:text-red-800 remove-set">
                                        <i class="fas fa-times"></i> 削除
                                    </button>
                                </div>
                                
                                <input type="hidden" name="sets[<?php echo $index; ?>][set_id]" value="<?php echo $set['set_id']; ?>">
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <!-- 種別 -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">種別</label>
                                        <select 
                                            name="sets[<?php echo $index; ?>][type_id]" 
                                            class="w-full border border-gray-300 rounded-md px-3 py-2"
                                        >
                                            <option value="">選択してください</option>
                                            <?php foreach ($workout_types as $type): ?>
                                            <option 
                                                value="<?php echo $type['type_id']; ?>"
                                                <?php echo $set['type_id'] == $type['type_id'] ? 'selected' : ''; ?>
                                            >
                                                <?php echo h($type['type_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- 泳法 -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">泳法</label>
                                        <select 
                                            name="sets[<?php echo $index; ?>][stroke_type]" 
                                            class="w-full border border-gray-300 rounded-md px-3 py-2"
                                        >
                                            <option value="freestyle" <?php echo $set['stroke_type'] === 'freestyle' ? 'selected' : ''; ?>>自由形</option>
                                            <option value="backstroke" <?php echo $set['stroke_type'] === 'backstroke' ? 'selected' : ''; ?>>背泳ぎ</option>
                                            <option value="breaststroke" <?php echo $set['stroke_type'] === 'breaststroke' ? 'selected' : ''; ?>>平泳ぎ</option>
                                            <option value="butterfly" <?php echo $set['stroke_type'] === 'butterfly' ? 'selected' : ''; ?>>バタフライ</option>
                                            <option value="im" <?php echo $set['stroke_type'] === 'im' ? 'selected' : ''; ?>>個人メドレー</option>
                                            <option value="other" <?php echo $set['stroke_type'] === 'other' ? 'selected' : ''; ?>>その他</option>
                                        </select>
                                    </div>
                                    
                                    <!-- 器具 -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">器具</label>
                                        <select 
                                            name="sets[<?php echo $index; ?>][equipment][]" 
                                            class="equipment-select w-full border border-gray-300 rounded-md px-3 py-2"
                                            multiple
                                        >
                                            <?php 
                                            // セットに紐づく器具のIDを取得
                                            $selectedEquipment = $practice['equipment'][$set['set_id']] ?? [];
                                            
                                            foreach ($equipment_list as $eq): 
                                            ?>
                                            <option 
                                                value="<?php echo $eq['equipment_id']; ?>"
                                                <?php echo in_array($eq['equipment_id'], $selectedEquipment) ? 'selected' : ''; ?>
                                            >
                                                <?php echo h($eq['equipment_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <!-- 距離 -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">距離 (m)</label>
                                        <input 
                                            type="number" 
                                            name="sets[<?php echo $index; ?>][distance]" 
                                            value="<?php echo $set['distance']; ?>"
                                            min="25" 
                                            step="25" 
                                            class="set-distance w-full border border-gray-300 rounded-md px-3 py-2"
                                        >
                                    </div>
                                    
                                    <!-- 回数 -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">回数</label>
                                        <input 
                                            type="number" 
                                            name="sets[<?php echo $index; ?>][repetitions]" 
                                            value="<?php echo $set['repetitions']; ?>"
                                            min="1" 
                                            class="set-repetitions w-full border border-gray-300 rounded-md px-3 py-2"
                                        >
                                    </div>
                                    
                                    <!-- インターバル -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">サイクル</label>
                                        <input 
                                            type="text" 
                                            name="sets[<?php echo $index; ?>][cycle]" 
                                            value="<?php echo h($set['cycle'] ?? ''); ?>"
                                            placeholder="例: 1:30、R30など" 
                                            class="w-full border border-gray-300 rounded-md px-3 py-2"
                                        >
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- 合計距離 -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">セット合計距離 (m)</label>
                                        <input 
                                            type="number" 
                                            name="sets[<?php echo $index; ?>][total_distance]" 
                                            value="<?php echo $set['total_distance']; ?>"
                                            min="0" 
                                            class="set-total w-full border border-gray-300 rounded-md px-3 py-2"
                                            readonly
                                        >
                                    </div>
                                    
                                    <!-- メモ -->
                                    <div>
                                        <label class="block text-gray-700 mb-2 text-sm">メモ</label>
                                        <input 
                                            type="text" 
                                            name="sets[<?php echo $index; ?>][notes]" 
                                            value="<?php echo h($set['notes'] ?? ''); ?>"
                                            class="w-full border border-gray-300 rounded-md px-3 py-2"
                                        >
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- セットがない場合のデフォルト -->
                        <div class="set-item border border-gray-200 rounded-md p-4 mb-4">
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="font-medium">セット 1</h4>
                                <button type="button" class="text-red-600 hover:text-red-800 remove-set">
                                    <i class="fas fa-times"></i> 削除
                                </button>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <!-- 種別 -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">種別</label>
                                    <select 
                                        name="sets[0][type_id]" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                                    >
                                        <option value="">選択してください</option>
                                        <?php foreach ($workout_types as $type): ?>
                                        <option value="<?php echo $type['type_id']; ?>">
                                            <?php echo h($type['type_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- 泳法 -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">泳法</label>
                                    <select 
                                        name="sets[0][stroke_type]" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                                    >
                                        <option value="freestyle">自由形</option>
                                        <option value="backstroke">背泳ぎ</option>
                                        <option value="breaststroke">平泳ぎ</option>
                                        <option value="butterfly">バタフライ</option>
                                        <option value="im">個人メドレー</option>
                                        <option value="other">その他</option>
                                    </select>
                                </div>
                                
                                <!-- 器具 -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">器具</label>
                                    <select 
                                        name="sets[0][equipment][]" 
                                        class="equipment-select w-full border border-gray-300 rounded-md px-3 py-2"
                                        multiple
                                    >
                                        <?php foreach ($equipment_list as $equipment): ?>
                                        <option value="<?php echo $equipment['equipment_id']; ?>">
                                            <?php echo h($equipment['equipment_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <!-- 距離 -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">距離 (m)</label>
                                    <input 
                                        type="number" 
                                        name="sets[0][distance]" 
                                        value="100"
                                        min="25" 
                                        step="25" 
                                        class="set-distance w-full border border-gray-300 rounded-md px-3 py-2"
                                    >
                                </div>
                                
                                <!-- 回数 -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">回数</label>
                                    <input 
                                        type="number" 
                                        name="sets[0][repetitions]" 
                                        value="1"
                                        min="1" 
                                        class="set-repetitions w-full border border-gray-300 rounded-md px-3 py-2"
                                    >
                                </div>
                                
                                <!-- インターバル -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">サイクル</label>
                                    <input 
                                        type="text" 
                                        name="sets[0][cycle]" 
                                        placeholder="例: 1:30、R30など" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                                    >
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- 合計距離 -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">セット合計距離 (m)</label>
                                    <input 
                                        type="number" 
                                        name="sets[0][total_distance]" 
                                        value="100"
                                        min="0" 
                                        class="set-total w-full border border-gray-300 rounded-md px-3 py-2"
                                        readonly
                                    >
                                </div>
                                
                                <!-- メモ -->
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm">メモ</label>
                                    <input 
                                        type="text" 
                                        name="sets[0][notes]" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                                    >
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="flex justify-end">
                <a href="practice.php?action=view&id=<?php echo $sessionId; ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-6 rounded-lg mr-2">
                    キャンセル
                </a>
                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg"
                >
                    更新する
                </button>
            </div>
        </form>
    </div>
    
    <!-- テンプレートコンテナ（新規セット用） -->
    <template id="set-template">
        <div class="set-item border border-gray-200 rounded-md p-4 mb-4">
            <div class="flex justify-between items-center mb-3">
                <h4 class="font-medium">セット {index}</h4>
                <button type="button" class="text-red-600 hover:text-red-800 remove-set">
                    <i class="fas fa-times"></i> 削除
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- 種別 -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">種別</label>
                    <select 
                        name="sets[{index}][type_id]" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                        <option value="">選択してください</option>
                        <?php foreach ($workout_types as $type): ?>
                        <option value="<?php echo $type['type_id']; ?>">
                            <?php echo h($type['type_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- 泳法 -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">泳法</label>
                    <select 
                        name="sets[{index}][stroke_type]" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                        <option value="freestyle">自由形</option>
                        <option value="backstroke">背泳ぎ</option>
                        <option value="breaststroke">平泳ぎ</option>
                        <option value="butterfly">バタフライ</option>
                        <option value="im">個人メドレー</option>
                        <option value="other">その他</option>
                    </select>
                </div>
                
                <!-- 器具 -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">器具</label>
                    <select 
                        name="sets[{index}][equipment][]" 
                        class="equipment-select w-full border border-gray-300 rounded-md px-3 py-2"
                        multiple
                    >
                        <?php foreach ($equipment_list as $equipment): ?>
                        <option value="<?php echo $equipment['equipment_id']; ?>">
                            <?php echo h($equipment['equipment_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- 距離 -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">距離 (m)</label>
                    <input 
                        type="number" 
                        name="sets[{index}][distance]" 
                        value="100"
                        min="25" 
                        step="25" 
                        class="set-distance w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                </div>
                
                <!-- 回数 -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">回数</label>
                    <input 
                        type="number" 
                        name="sets[{index}][repetitions]" 
                        value="1"
                        min="1" 
                        class="set-repetitions w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                </div>
                
                <!-- インターバル -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">サイクル</label>
                    <input 
                        type="text" 
                        name="sets[{index}][cycle]" 
                        placeholder="例: 1:30、R30など" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- 合計距離 -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">セット合計距離 (m)</label>
                    <input 
                        type="number" 
                        name="sets[{index}][total_distance]" 
                        value="100"
                        min="0" 
                        class="set-total w-full border border-gray-300 rounded-md px-3 py-2"
                        readonly
                    >
                </div>
                
                <!-- メモ -->
                <div>
                    <label class="block text-gray-700 mb-2 text-sm">メモ</label>
                    <input 
                        type="text" 
                        name="sets[{index}][notes]" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                </div>
            </div>
        </div>
    </template>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 器具選択の初期化
        initializeEquipmentSelects();
        
        // セット合計距離の計算
        initializeSetCalculations();
        
        // 総距離の自動計算
        calculateTotalDistance();
        
        // セット追加ボタン
        const addSetButton = document.getElementById('add-set');
        if (addSetButton) {
            addSetButton.addEventListener('click', function() {
                addNewSet();
            });
        }
        
        // 初期セットでの削除ボタンイベント付与
        bindRemoveSetEvents();
    });
    
    // 器具選択の初期化
    function initializeEquipmentSelects() {
        document.querySelectorAll('.equipment-select').forEach(select => {
            // ここでは簡易的な実装。実際にはSelect2などのライブラリを使うことを推奨
            select.addEventListener('click', function(e) {
                if (e.target.tagName === 'OPTION') {
                    e.preventDefault();
                    e.target.selected = !e.target.selected;
                }
            });
        });
    }
    
    // セット合計距離の計算初期化
    function initializeSetCalculations() {
        document.querySelectorAll('.set-item').forEach(setItem => {
            const distanceInput = setItem.querySelector('.set-distance');
            const repsInput = setItem.querySelector('.set-repetitions');
            const totalInput = setItem.querySelector('.set-total');
            
            if (distanceInput && repsInput && totalInput) {
                const calculateTotal = () => {
                    const distance = parseInt(distanceInput.value) || 0;
                    const reps = parseInt(repsInput.value) || 1;
                    totalInput.value = distance * reps;
                    
                    // 全体の総距離も更新
                    calculateTotalDistance();
                };
                
                distanceInput.addEventListener('input', calculateTotal);
                repsInput.addEventListener('input', calculateTotal);
            }
        });
    }
    
    // 総距離の自動計算
    function calculateTotalDistance() {
        const totalDistanceInput = document.getElementById('total_distance');
        const setTotals = document.querySelectorAll('.set-total');
        
        if (totalDistanceInput && setTotals.length > 0) {
            let sum = 0;
            setTotals.forEach(input => {
                sum += parseInt(input.value) || 0;
            });
            
            totalDistanceInput.value = sum;
        }
    }
    
    // 新しいセットを追加
    function addNewSet() {
        const container = document.getElementById('sets-container');
        const template = document.getElementById('set-template');
        const setItems = container.querySelectorAll('.set-item');
        const newIndex = setItems.length;
        
        // テンプレートのクローンを作成
        const clone = template.content.cloneNode(true);
        const setItem = clone.querySelector('.set-item');
        
        // インデックスの置換
        const setTitle = setItem.querySelector('h4');
        setTitle.textContent = setTitle.textContent.replace('{index}', newIndex + 1);
        
        // 名前属性の置換
        setItem.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/\{index\}/g, newIndex);
        });
        
        // コンテナに追加
        container.appendChild(setItem);
        
        // イベントリスナーを設定
        bindRemoveSetEvents();
        initializeEquipmentSelects();
        initializeSetCalculations();
    }
    
    // 削除ボタンイベントのバインド
    function bindRemoveSetEvents() {
        document.querySelectorAll('.remove-set').forEach(btn => {
            // 既存のイベントリスナーを削除（重複防止）
            btn.removeEventListener('click', handleRemoveSet);
            
            // 新しいイベントリスナーを追加
            btn.addEventListener('click', handleRemoveSet);
        });
    }
    
    // セット削除処理
    function handleRemoveSet() {
        const setItem = this.closest('.set-item');
        const container = document.getElementById('sets-container');
        const setItems = container.querySelectorAll('.set-item');
        
        // 最後の1つは削除しない
        if (setItems.length <= 1) {
            alert('最低1つのセットが必要です。');
            return;
        }
        
        // セットを削除
        setItem.remove();
        
        // 残りのセットのインデックスを更新
        updateSetIndexes();
        
        // 総距離を再計算
        calculateTotalDistance();
    }
    
    // セットインデックスの更新
function updateSetIndexes() {
    const container = document.getElementById('sets-container');
    const setItems = container.querySelectorAll('.set-item');
    
    setItems.forEach((item, index) => {
        // タイトル更新
        const title = item.querySelector('h4');
        if (title) {
            title.textContent = 'セット ' + (index + 1);
        }
        
        // name属性の更新
        item.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/sets\[\d+\]/, 'sets[' + index + ']');
        });
    });
}
</script>

<?php elseif ($action === 'list' || $action === 'search'): ?>
    <!-- 練習履歴一覧 -->
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">練習記録</h1>
        <a href="practice.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> 新しい練習を記録
        </a>
    </div>
    
    <!-- 検索フィルター -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">検索・フィルター</h2>
        
        <form action="practice.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <input type="hidden" name="action" value="search">
            
            <!-- 日付範囲 -->
            <div>
                <label for="date_from" class="block text-gray-700 mb-2 text-sm">練習日（開始）</label>
                <input
                    type="date"
                    id="date_from"
                    name="date_from"
                    class="w-full border border-gray-300 rounded-md px-3 py-2"
                    value="<?php echo isset($filters['date_from']) ? $filters['date_from'] : ''; ?>"
                >
            </div>
            
            <div>
                <label for="date_to" class="block text-gray-700 mb-2 text-sm">練習日（終了）</label>
                <input
                    type="date"
                    id="date_to"
                    name="date_to"
                    class="w-full border border-gray-300 rounded-md px-3 py-2"
                    value="<?php echo isset($filters['date_to']) ? $filters['date_to'] : ''; ?>"
                >
            </div>
            
            <!-- 距離範囲 -->
            <div>
                <label for="distance_min" class="block text-gray-700 mb-2 text-sm">距離（最小）</label>
                <input
                    type="number"
                    id="distance_min"
                    name="distance_min"
                    min="0"
                    step="100"
                    class="w-full border border-gray-300 rounded-md px-3 py-2"
                    placeholder="例: 1000"
                    value="<?php echo isset($filters['distance_min']) ? $filters['distance_min'] : ''; ?>"
                >
            </div>
            
            <div>
                <label for="distance_max" class="block text-gray-700 mb-2 text-sm">距離（最大）</label>
                <input
                    type="number"
                    id="distance_max"
                    name="distance_max"
                    min="0"
                    step="100"
                    class="w-full border border-gray-300 rounded-md px-3 py-2"
                    placeholder="例: 5000"
                    value="<?php echo isset($filters['distance_max']) ? $filters['distance_max'] : ''; ?>"
                >
            </div>
            
            <!-- プール -->
            <div>
                <label for="pool_id" class="block text-gray-700 mb-2 text-sm">プール</label>
                <select
                    id="pool_id"
                    name="pool_id"
                    class="w-full border border-gray-300 rounded-md px-3 py-2"
                >
                    <option value="">すべて</option>
                    <?php foreach ($filterOptions['pools'] as $pool): ?>
                    <option 
                        value="<?php echo $pool['pool_id']; ?>"
                        <?php echo isset($filters['pool_id']) && $filters['pool_id'] == $pool['pool_id'] ? 'selected' : ''; ?>
                    >
                        <?php echo h($pool['pool_name']); ?>
                        <?php echo $pool['is_favorite'] ? ' ⭐' : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- 泳法 -->
            <div>
                <label for="stroke_type" class="block text-gray-700 mb-2 text-sm">泳法</label>
                <select
                    id="stroke_type"
                    name="stroke_type"
                    class="w-full border border-gray-300 rounded-md px-3 py-2"
                >
                    <option value="">すべて</option>
                    <?php foreach ($filterOptions['stroke_types'] as $value => $label): ?>
                    <option 
                        value="<?php echo $value; ?>"
                        <?php echo isset($filters['stroke_type']) && $filters['stroke_type'] === $value ? 'selected' : ''; ?>
                    >
                        <?php echo $label; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- キーワード -->
            <div>
                <label for="keyword" class="block text-gray-700 mb-2 text-sm">キーワード</label>
                <input
                    type="text"
                    id="keyword"
                    name="keyword"
                    class="w-full border border-gray-300 rounded-md px-3 py-2"
                    placeholder="課題、メモなどから検索"
                    value="<?php echo isset($filters['keyword']) ? h($filters['keyword']) : ''; ?>"
                >
            </div>
            
            <!-- 並び順 -->
            <div>
                <label for="sort_by" class="block text-gray-700 mb-2 text-sm">並び順</label>
                <select
                    id="sort_by"
                    name="sort_by"
                    class="w-full border border-gray-300 rounded-md px-3 py-2"
                >
                    <?php foreach ($filterOptions['sort_options'] as $value => $label): ?>
                    <option 
                        value="<?php echo $value; ?>"
                        <?php echo isset($filters['sort_by']) && $filters['sort_by'] === $value ? 'selected' : ''; ?>
                    >
                        <?php echo $label; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- 検索ボタン -->
            <div class="md:col-span-2 lg:col-span-3 flex justify-end space-x-2 mt-2">
                <a href="practice.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-lg">
                    リセット
                </a>
                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg"
                >
                    <i class="fas fa-search mr-1"></i> 検索
                </button>
            </div>
        </form>
    </div>
    
    <!-- 練習記録一覧 -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="mb-4 flex justify-between items-center">
            <h2 class="text-lg font-semibold">
                <?php if ($isFiltered): ?>
                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded">フィルター適用中</span>
                <?php endif; ?>
                練習記録一覧
            </h2>
            
            <div class="text-sm">
                <a href="templates.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-copy mr-1"></i> テンプレート管理
                </a>
            </div>
        </div>
        
        <?php
        // 練習記録データの取得
        $practices = [];
        $pagination = [
            'total_count' => 0,
            'total_pages' => 1,
            'page' => 1
        ];
        
        try {
            $result = searchPractices($db, $_SESSION['user_id'], $filters, $page, $limit);
            $practices = $result['practices'];
            $pagination = [
                'total_count' => $result['total_count'],
                'total_pages' => $result['total_pages'],
                'page' => $result['page']
            ];
        } catch (PDOException $e) {
            error_log('練習履歴取得エラー: ' . $e->getMessage());
            echo '<div class="bg-red-100 text-red-700 p-4 rounded mb-4">データの取得中にエラーが発生しました。</div>';
        }
        ?>
        
        <?php if (empty($practices)): ?>
        <div class="text-center py-8">
            <p class="text-gray-500 mb-6">
                <?php if ($isFiltered): ?>
                検索条件に一致する練習記録がありません。<br>条件を変更して再度検索してください。
                <?php else: ?>
                まだ練習記録がありません。<br>新しい練習を記録しましょう。
                <?php endif; ?>
            </p>
            <a href="practice.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                練習を記録する
            </a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="py-2 px-4 text-left">日付</th>
                        <th class="py-2 px-4 text-left">距離</th>
                        <th class="py-2 px-4 text-left">プール</th>
                        <th class="py-2 px-4 text-left">時間</th>
                        <th class="py-2 px-4 text-left">調子</th>
                        <th class="py-2 px-4 text-left">課題</th>
                        <th class="py-2 px-4 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($practices as $practice): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3 px-4 whitespace-nowrap">
                            <?php echo date('Y/m/d (', strtotime($practice['practice_date'])); ?>
                            <?php echo ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($practice['practice_date']))]; ?>
                            <?php echo ')'; ?>
                        </td>
                        <td class="py-3 px-4 whitespace-nowrap font-medium">
                            <?php echo number_format($practice['total_distance']); ?> m
                        </td>
                        <td class="py-3 px-4 whitespace-nowrap">
                            <?php echo h($practice['pool_name'] ?? '-'); ?>
                        </td>
                        <td class="py-3 px-4 whitespace-nowrap">
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
                        <td class="py-3 px-4 whitespace-nowrap">
                            <?php 
                            if ($practice['feeling']) {
                                echo $practice['feeling'] . ' / 5';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="py-3 px-4">
                            <div class="max-w-xs truncate">
                                <?php echo !empty($practice['challenge']) ? h($practice['challenge']) : '-'; ?>
                            </div>
                        </td>
                        <td class="py-3 px-4 whitespace-nowrap">
                            <a href="practice.php?action=view&id=<?php echo $practice['session_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                詳細
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ページネーション -->
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="flex justify-center mt-6">
            <nav>
                <ul class="flex space-x-2">
                    <!-- 前のページ -->
                    <?php if ($pagination['page'] > 1): ?>
                    <li>
                        <a 
                            href="practice.php?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['page'] - 1])); ?>" 
                            class="border border-gray-300 px-3 py-1 rounded hover:bg-gray-100"
                        >
                            前へ
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- ページ番号 -->
                    <?php
                    $start = max(1, $pagination['page'] - 2);
                    $end = min($pagination['total_pages'], $pagination['page'] + 2);
                    
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                    <li>
                        <a 
                            href="practice.php?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                            class="<?php echo $i === $pagination['page'] ? 'bg-blue-600 text-white' : 'border border-gray-300 hover:bg-gray-100'; ?> px-3 py-1 rounded"
                        >
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <!-- 次のページ -->
                    <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                    <li>
                        <a 
                            href="practice.php?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['page'] + 1])); ?>" 
                            class="border border-gray-300 px-3 py-1 rounded hover:bg-gray-100"
                        >
                            次へ
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script src="assets/js/practice_sets.js"></script>
<script src="assets/js/practice_improvements.js"></script>
<!-- 種別・器具管理リンク修正スクリプト -->
<script src="assets/js/equipment_link_fix.js"></script>
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