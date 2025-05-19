<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// ページタイトル
$page_title = "テンプレート作成";

// ログイン必須
requireLogin();

// 複製元テンプレートIDの取得（存在する場合）
$duplicate_id = isset($_GET['duplicate_id']) ? (int)$_GET['duplicate_id'] : 0;

// テンプレート情報の初期化
$template = [
    'template_name' => '',
    'category' => null,
    'description' => '',
    'total_distance' => 0,
    'is_favorite' => 0,
    'is_public' => 0
];
$sets = [];

// 複製元テンプレートが指定されている場合
if ($duplicate_id > 0) {
    try {
        $db = getDbConnection();
        
        // テンプレート基本情報を取得
        $stmt = $db->prepare("
            SELECT * FROM practice_templates
            WHERE template_id = ? AND user_id = ?
        ");
        $stmt->execute([$duplicate_id, $_SESSION['user_id']]);
        $source_template = $stmt->fetch();
        
        if ($source_template) {
            $template = [
                'template_name' => $source_template['template_name'] . ' (コピー)',
                'category' => $source_template['category'],
                'description' => $source_template['description'],
                'total_distance' => $source_template['total_distance'],
                'is_favorite' => 0,  // コピー時はお気に入りにしない
                'is_public' => 0     // コピー時は非公開にする
            ];
            
            // テンプレートセットを取得
            $stmt = $db->prepare("
                SELECT ts.*, wt.type_name
                FROM template_sets ts
                LEFT JOIN workout_types wt ON ts.type_id = wt.type_id
                WHERE ts.template_id = ?
                ORDER BY ts.order_index
            ");
            $stmt->execute([$duplicate_id]);
            $source_sets = $stmt->fetchAll();
            
            // セットごとの器具情報を取得して複製用のデータを作成
            foreach ($source_sets as $setIndex => $set) {
                $sets[$setIndex] = $set;
                
                $stmt = $db->prepare("
                    SELECT tse.equipment_id
                    FROM template_set_equipment tse
                    WHERE tse.set_id = ?
                ");
                $stmt->execute([$set['set_id']]);
                $sets[$setIndex]['equipment_ids'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    } catch (PDOException $e) {
        error_log('テンプレート複製エラー: ' . $e->getMessage());
        $_SESSION['error_messages'][] = 'テンプレートの複製中にエラーが発生しました。';
    }
}

// カテゴリ一覧を取得
$categories = [];
try {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT * FROM template_categories
        WHERE user_id = ? OR is_system = 1
        ORDER BY is_system DESC, category_name ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('カテゴリ一覧取得エラー: ' . $e->getMessage());
}

// 練習種別と器具の一覧を取得
$workout_types = [];
$equipment_list = [];

try {
    $db = getDbConnection();
    
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
    error_log('種別・器具一覧取得エラー: ' . $e->getMessage());
    $_SESSION['error_messages'][] = '種別・器具情報の取得中にエラーが発生しました。';
}

// 泳法の表示名マッピング
$strokeTypes = [
    'freestyle' => '自由形',
    'backstroke' => '背泳ぎ',
    'breaststroke' => '平泳ぎ',
    'butterfly' => 'バタフライ',
    'im' => '個人メドレー',
    'kick' => 'キック',
    'pull' => 'プル',
    'drill' => 'ドリル',
    'other' => 'その他'
];

// ヘッダーの読み込み
include 'includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold">
        <?php echo $duplicate_id > 0 ? 'テンプレートを複製して作成' : '新しいテンプレートを作成'; ?>
    </h1>
    <a href="templates.php" class="text-blue-600 hover:text-blue-800">
        <i class="fas fa-arrow-left mr-1"></i> テンプレート一覧に戻る
    </a>
</div>

<form id="template-form" method="POST" action="api/templates.php">
    <!-- CSRF トークン -->
    <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
    <input type="hidden" name="action" value="create">
    
    <!-- 基本情報 -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-6">基本情報</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- テンプレート名 -->
            <div>
                <label for="template_name" class="block text-gray-700 mb-2">テンプレート名 <span class="text-red-500">*</span></label>
                <input
                    type="text"
                    id="template_name"
                    name="template_name"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                    value="<?php echo h($template['template_name']); ?>"
                    placeholder="例: 持久力トレーニング 2000m"
                >
            </div>
            
            <!-- カテゴリ -->
            <div>
                <label for="category" class="block text-gray-700 mb-2">カテゴリ</label>
                <select
                    id="category"
                    name="category"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">選択してください</option>
                    <?php foreach ($categories as $category): ?>
                    <option 
                        value="<?php echo $category['category_id']; ?>"
                        <?php echo $template['category'] == $category['category_id'] ? 'selected' : ''; ?>
                    >
                        <?php echo h($category['category_name']); ?>
                        <?php echo $category['is_system'] ? ' (システム)' : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- 総距離 -->
            <div>
                <label for="total_distance" class="block text-gray-700 mb-2">総距離 (m) <span class="text-red-500">*</span></label>
                <input
                    type="number"
                    id="total_distance"
                    name="total_distance"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                    min="0"
                    step="100"
                    value="<?php echo $template['total_distance']; ?>"
                    placeholder="例: 2000"
                >
            </div>
            
            <!-- 公開設定 -->
            <div class="flex items-center space-x-6">
                <label class="inline-flex items-center">
                    <input 
                        type="checkbox" 
                        name="is_favorite" 
                        value="1" 
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500"
                        <?php echo $template['is_favorite'] ? 'checked' : ''; ?>
                    >
                    <span class="ml-2 text-gray-700">お気に入りに登録</span>
                </label>
                
                <label class="inline-flex items-center">
                    <input 
                        type="checkbox" 
                        name="is_public" 
                        value="1" 
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500"
                        <?php echo $template['is_public'] ? 'checked' : ''; ?>
                    >
                    <span class="ml-2 text-gray-700">公開する</span>
                </label>
            </div>
        </div>
        
        <!-- 説明 -->
        <div>
            <label for="description" class="block text-gray-700 mb-2">説明</label>
            <textarea
                id="description"
                name="description"
                rows="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="このテンプレートの説明や目的などを入力してください"
            ><?php echo h($template['description']); ?></textarea>
        </div>
    </div>
    
    <!-- セット情報 -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold">セット情報</h2>
            <button type="button" id="add-set-btn" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg">
                <i class="fas fa-plus mr-2"></i> セットを追加
            </button>
        </div>
        
        <div id="sets-container">
            <?php foreach ($sets as $index => $set): ?>
            <div class="set-item border rounded-lg mb-4" data-set-index="<?php echo $index; ?>">
                <div class="bg-blue-50 p-3 border-b flex justify-between items-center">
                    <h3 class="font-semibold">セット <span class="set-number"><?php echo $index + 1; ?></span></h3>
                    <div class="flex space-x-2">
                        <button type="button" class="move-up-btn text-gray-600 hover:text-gray-800 px-2" title="上に移動">
                            <i class="fas fa-arrow-up"></i>
                        </button>
                        <button type="button" class="move-down-btn text-gray-600 hover:text-gray-800 px-2" title="下に移動">
                            <i class="fas fa-arrow-down"></i>
                        </button>
                        <button type="button" class="remove-set-btn text-red-600 hover:text-red-800 px-2" title="削除">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <!-- 種別 -->
                        <div>
                            <label class="block text-gray-700 mb-2">種別</label>
                            <select
                                name="sets[<?php echo $index; ?>][type_id]"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">選択してください</option>
                                <?php foreach ($workout_types as $type): ?>
                                <option 
                                    value="<?php echo $type['type_id']; ?>"
                                    <?php echo isset($set['type_id']) && $set['type_id'] == $type['type_id'] ? 'selected' : ''; ?>
                                >
                                    <?php echo h($type['type_name']); ?>
                                    <?php echo $type['is_system'] ? ' (システム)' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- 泳法 -->
                        <div>
                            <label class="block text-gray-700 mb-2">泳法 <span class="text-red-500">*</span></label>
                            <select
                                name="sets[<?php echo $index; ?>][stroke_type]"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                            >
                                <?php foreach ($strokeTypes as $value => $label): ?>
                                <option 
                                    value="<?php echo $value; ?>"
                                    <?php echo isset($set['stroke_type']) && $set['stroke_type'] === $value ? 'selected' : ''; ?>
                                >
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- 距離/回数 -->
                        <div class="flex space-x-2">
                            <div class="flex-1">
                                <label class="block text-gray-700 mb-2">距離 (m) <span class="text-red-500">*</span></label>
                                <input
                                    type="number"
                                    name="sets[<?php echo $index; ?>][distance]"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                    min="1"
                                    value="<?php echo isset($set['distance']) ? $set['distance'] : 100; ?>"
                                >
                            </div>
                            <div class="flex-1">
                                <label class="block text-gray-700 mb-2">回数</label>
                                <input
                                    type="number"
                                    name="sets[<?php echo $index; ?>][repetitions]"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    min="1"
                                    value="<?php echo isset($set['repetitions']) ? $set['repetitions'] : 1; ?>"
                                >
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <!-- サイクル -->
                        <div>
                            <label class="block text-gray-700 mb-2">サイクル</label>
                            <input
                                type="text"
                                name="sets[<?php echo $index; ?>][cycle]"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="例: 1:30"
                                value="<?php echo isset($set['cycle']) ? h($set['cycle']) : ''; ?>"
                            >
                        </div>
                        
                        <!-- セット距離合計 -->
                        <div>
                            <label class="block text-gray-700 mb-2">セット距離合計 (m)</label>
                            <input
                                type="number"
                                name="sets[<?php echo $index; ?>][total_distance]"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                min="0"
                                value="<?php echo isset($set['total_distance']) ? $set['total_distance'] : 100; ?>"
                            >
                        </div>
                    </div>
                    
                    <!-- 器具 -->
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">使用器具</label>
                        <div class="border border-gray-300 rounded-md p-3 bg-gray-50 max-h-36 overflow-y-auto">
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                <?php foreach ($equipment_list as $eq): ?>
                                <div class="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="set_<?php echo $index; ?>_equipment_<?php echo $eq['equipment_id']; ?>"
                                        name="sets[<?php echo $index; ?>][equipment][]"
                                        value="<?php echo $eq['equipment_id']; ?>"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500"
                                        <?php echo isset($set['equipment_ids']) && in_array($eq['equipment_id'], $set['equipment_ids']) ? 'checked' : ''; ?>
                                    >
                                    <label 
                                        for="set_<?php echo $index; ?>_equipment_<?php echo $eq['equipment_id']; ?>"
                                        class="ml-2 text-sm text-gray-700"
                                    >
                                        <?php echo h($eq['equipment_name']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- メモ -->
                    <div>
                        <label class="block text-gray-700 mb-2">メモ</label>
                        <textarea
                            name="sets[<?php echo $index; ?>][notes]"
                            rows="2"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        ><?php echo isset($set['notes']) ? h($set['notes']) : ''; ?></textarea>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div id="no-sets-message" class="text-center py-4 <?php echo !empty($sets) ? 'hidden' : ''; ?>">
            <p class="text-gray-500">セットが登録されていません。「セットを追加」ボタンをクリックしてセットを追加してください。</p>
        </div>
    </div>
    
    <!-- 保存ボタン -->
    <div class="flex justify-end space-x-3 mb-8">
        <a 
            href="templates.php"
            class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-6 rounded-lg"
        >
            キャンセル
        </a>
        <button 
            type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg"
        >
            テンプレートを作成
        </button>
    </div>
</form>

<!-- セットのテンプレート (JavaScript で複製して使用) -->
<div id="set-template" class="hidden">
    <div class="set-item border rounded-lg mb-4" data-set-index="__INDEX__">
        <div class="bg-blue-50 p-3 border-b flex justify-between items-center">
            <h3 class="font-semibold">セット <span class="set-number">__NUMBER__</span></h3>
            <div class="flex space-x-2">
                <button type="button" class="move-up-btn text-gray-600 hover:text-gray-800 px-2" title="上に移動">
                    <i class="fas fa-arrow-up"></i>
                </button>
                <button type="button" class="move-down-btn text-gray-600 hover:text-gray-800 px-2" title="下に移動">
                    <i class="fas fa-arrow-down"></i>
                </button>
                <button type="button" class="remove-set-btn text-red-600 hover:text-red-800 px-2" title="削除">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- 種別 -->
                <div>
                    <label class="block text-gray-700 mb-2">種別</label>
                    <select
                        name="sets[__INDEX__][type_id]"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">選択してください</option>
                        <?php foreach ($workout_types as $type): ?>
                        <option value="<?php echo $type['type_id']; ?>">
                            <?php echo h($type['type_name']); ?>
                            <?php echo $type['is_system'] ? ' (システム)' : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- 泳法 -->
                <div>
                    <label class="block text-gray-700 mb-2">泳法 <span class="text-red-500">*</span></label>
                    <select
                        name="sets[__INDEX__][stroke_type]"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    >
                        <?php foreach ($strokeTypes as $value => $label): ?>
                        <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- 距離/回数 -->
                <div class="flex space-x-2">
                    <div class="flex-1">
                        <label class="block text-gray-700 mb-2">距離 (m) <span class="text-red-500">*</span></label>
                        <input
                            type="number"
                            name="sets[__INDEX__][distance]"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                            min="1"
                            value="100"
                        >
                    </div>
                    <div class="flex-1">
                        <label class="block text-gray-700 mb-2">回数</label>
                        <input
                            type="number"
                            name="sets[__INDEX__][repetitions]"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            min="1"
                            value="1"
                        >
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <!-- サイクル -->
                <div>
                    <label class="block text-gray-700 mb-2">サイクル</label>
                    <input
                        type="text"
                        name="sets[__INDEX__][cycle]"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="例: 1:30"
                    >
                </div>
                
                <!-- セット距離合計 -->
                <div>
                    <label class="block text-gray-700 mb-2">セット距離合計 (m)</label>
                    <input
                        type="number"
                        name="sets[__INDEX__][total_distance]"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        min="0"
                        value="100"
                    >
                </div>
            </div>
            
            <!-- 器具 -->
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">使用器具</label>
                <div class="border border-gray-300 rounded-md p-3 bg-gray-50 max-h-36 overflow-y-auto">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        <?php foreach ($equipment_list as $eq): ?>
                        <div class="flex items-center">
                            <input
                                type="checkbox"
                                id="set___INDEX___equipment_<?php echo $eq['equipment_id']; ?>"
                                name="sets[__INDEX__][equipment][]"
                                value="<?php echo $eq['equipment_id']; ?>"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500"
                            >
                            <label 
                                for="set___INDEX___equipment_<?php echo $eq['equipment_id']; ?>"
                                class="ml-2 text-sm text-gray-700"
                            >
                                <?php echo h($eq['equipment_name']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- メモ -->
            <div>
                <label class="block text-gray-700 mb-2">メモ</label>
                <textarea
                    name="sets[__INDEX__][notes]"
                    rows="2"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                ></textarea>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // セット管理関連
    const setsContainer = document.getElementById('sets-container');
    const setTemplate = document.getElementById('set-template').innerHTML;
    const addSetBtn = document.getElementById('add-set-btn');
    const noSetsMessage = document.getElementById('no-sets-message');
    
    // 現在のセット数
    let setCount = document.querySelectorAll('.set-item').length;
    
    // セット追加ボタンのイベントリスナー
    addSetBtn.addEventListener('click', function() {
        // 「セットがない」メッセージを非表示
        noSetsMessage.classList.add('hidden');
        
        // 新しいセットのHTML生成
        const newIndex = Date.now(); // 一意のインデックス（現在のタイムスタンプ）
        const newSetNumber = setCount + 1;
        
        // テンプレートの置換
        let newSetHtml = setTemplate
            .replace(/__INDEX__/g, newIndex)
            .replace(/__NUMBER__/g, newSetNumber);
        
        // 新しいセット要素を作成
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = newSetHtml;
        const newSetElement = tempDiv.firstElementChild;
        
        // セットコンテナに追加
        setsContainer.appendChild(newSetElement);
        
        // セット数を更新
        setCount++;
        
        // 新しく追加したセットのid属性を更新（セットを開く時のジャンプ先として）
        newSetElement.id = 'set-' + newIndex;
        
        // イベントリスナーを設定
        setupSetEventListeners(newSetElement);
        
        // 新しいセットにスクロール
        newSetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    
    // 既存のセットにイベントリスナーを設定
    document.querySelectorAll('.set-item').forEach(setItem => {
        setupSetEventListeners(setItem);
    });
    
    // セット項目のイベントリスナー設定
    function setupSetEventListeners(setElement) {
        // 削除ボタン
        const removeBtn = setElement.querySelector('.remove-set-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                if (confirm('このセットを削除してよろしいですか？')) {
                    setElement.remove();
                    updateSetNumbers();
                    
                    // セットがなくなった場合のメッセージを表示
                    if (document.querySelectorAll('.set-item').length === 0) {
                        noSetsMessage.classList.remove('hidden');
                    }
                }
            });
        }
        
        // 上に移動ボタン
        const moveUpBtn = setElement.querySelector('.move-up-btn');
        if (moveUpBtn) {
            moveUpBtn.addEventListener('click', function() {
                const prevSet = setElement.previousElementSibling;
                if (prevSet && prevSet.classList.contains('set-item')) {
                    setsContainer.insertBefore(setElement, prevSet);
                    updateSetNumbers();
                }
            });
        }
        
        // 下に移動ボタン
        const moveDownBtn = setElement.querySelector('.move-down-btn');
        if (moveDownBtn) {
            moveDownBtn.addEventListener('click', function() {
                const nextSet = setElement.nextElementSibling;
                if (nextSet && nextSet.classList.contains('set-item')) {
                    setsContainer.insertBefore(nextSet, setElement);
                    updateSetNumbers();
                }
            });
        }
        
        // 距離と回数の入力に対して、総距離の自動計算
        const distanceInput = setElement.querySelector('input[name$="[distance]"]');
        const repetitionsInput = setElement.querySelector('input[name$="[repetitions]"]');
        const totalDistanceInput = setElement.querySelector('input[name$="[total_distance]"]');
        
        if (distanceInput && repetitionsInput && totalDistanceInput) {
            const updateTotalDistance = function() {
                const distance = parseInt(distanceInput.value) || 0;
                const repetitions = parseInt(repetitionsInput.value) || 1;
                totalDistanceInput.value = distance * repetitions;
            };
            
            distanceInput.addEventListener('input', updateTotalDistance);
            repetitionsInput.addEventListener('input', updateTotalDistance);
        }
    }
    
    // セット番号の更新
    function updateSetNumbers() {
        document.querySelectorAll('.set-item').forEach((setItem, index) => {
            setItem.querySelector('.set-number').textContent = index + 1;
        });
    }
    
    // フォーム送信前の処理
    document.getElementById('template-form').addEventListener('submit', function(e) {
        // 入力検証（必要に応じて追加）
        
        // テンプレート名の検証
        const templateName = document.getElementById('template_name').value.trim();
        if (!templateName) {
            e.preventDefault();
            alert('テンプレート名を入力してください。');
            document.getElementById('template_name').focus();
            return;
        }
        
        // 総距離の検証
        const totalDistance = parseInt(document.getElementById('total_distance').value);
        if (isNaN(totalDistance) || totalDistance <= 0) {
            e.preventDefault();
            alert('有効な総距離を入力してください。');
            document.getElementById('total_distance').focus();
            return;
        }
        
        // セットが少なくとも1つあるか確認
        const setItems = document.querySelectorAll('.set-item');
        if (setItems.length === 0) {
            e.preventDefault();
            alert('少なくとも1つのセットを追加してください。');
            document.getElementById('add-set-btn').focus();
            return;
        }
        
        // セット番号を正しく更新（サーバー側の処理のため）
        setItems.forEach((setItem, index) => {
            // name属性のインデックス部分を更新
            setItem.querySelectorAll('[name^="sets["]').forEach(input => {
                const newName = input.name.replace(/sets\[\d+\]|sets\[__INDEX__\]/, 'sets[' + index + ']');
                input.name = newName;
            });
            
            // id属性も更新（チェックボックスとラベルの関連付けのため）
            setItem.querySelectorAll('[id^="set_"]').forEach(element => {
                if (element.id.includes('_equipment_')) {
                    const newId = element.id.replace(/set_\d+_|set___INDEX___/, 'set_' + index + '_');
                    
                    // 関連するlabel要素も更新
                    const label = setItem.querySelector('label[for="' + element.id + '"]');
                    if (label) {
                        label.setAttribute('for', newId);
                    }
                    
                    element.id = newId;
                }
            });
        });
    });
    
    // 自動で総距離を計算する
    const calculateTotalDistance = function() {
        let total = 0;
        document.querySelectorAll('.set-item').forEach(setItem => {
            const totalDistanceInput = setItem.querySelector('input[name$="[total_distance]"]');
            if (totalDistanceInput) {
                total += parseInt(totalDistanceInput.value) || 0;
            }
        });
        
        const totalDistanceField = document.getElementById('total_distance');
        if (totalDistanceField) {
            totalDistanceField.value = total;
        }
    };
    
    // 各セットの総距離が変更されたときに全体の総距離を再計算
    document.addEventListener('input', function(e) {
        if (e.target.name && e.target.name.match(/\[total_distance\]$/)) {
            calculateTotalDistance();
        }
    });
    
    // 初期状態でセットがなければ、セットを1つ追加するボタンを目立たせる
    if (setCount === 0) {
        addSetBtn.classList.add('animate-pulse');
        setTimeout(() => {
            addSetBtn.classList.remove('animate-pulse');
        }, 3000);
    }
});
</script>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>