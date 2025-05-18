<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// ページタイトル
$page_title = "練習記録";

// ログイン必須
requireLogin();

// アクションの取得（list, new, view, edit）
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$sessionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ヘッダーの読み込み
include 'includes/header.php';
?>

<?php if ($action === 'new'): ?>
    <!-- 新規練習記録フォーム -->
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">新しい練習を記録</h1>
        <div>
            <a href="pools.php" class="bg-blue-100 text-blue-600 hover:bg-blue-200 px-4 py-2 rounded-md mr-2 inline-flex items-center">
                <i class="fas fa-swimming-pool mr-1"></i> プール管理
            </a>
            <a href="practice.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i> 練習一覧に戻る
            </a>
        </div>
    </div>
    
    <?php
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
    
    // 練習器具一覧を取得
    $equipment = [];
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("
            SELECT * FROM equipment
            WHERE user_id = ? OR is_system = 1
            ORDER BY is_system DESC, equipment_name ASC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $equipment = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('練習器具一覧取得エラー: ' . $e->getMessage());
    }
    ?>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" action="api/practice.php">
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
                        value="<?php echo date('Y-m-d'); ?>"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                        required
                    >
                </div>
                
                <!-- プール選択 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="pool_id">プール</label>
                    <select
                        id="pool_id"
                        name="pool_id"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                        <option value="">選択してください</option>
                        <?php foreach ($pools as $pool): ?>
                        <option value="<?php echo $pool['pool_id']; ?>">
                            <?php echo h($pool['pool_name']); ?> (<?php echo h($pool['pool_length']); ?>m)
                            <?php if ($pool['is_favorite']): ?>⭐<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($pools)): ?>
                    <p class="text-sm text-gray-500 mt-1">
                        <a href="pools.php" class="text-blue-600 hover:underline">プール管理</a>から利用するプールを登録できます。
                    </p>
                    <?php endif; ?>
                </div>
                
                <!-- 練習時間 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="duration">練習時間</label>
                    <div class="flex items-center">
                        <input
                            type="number"
                            id="duration_hours"
                            name="duration_hours"
                            min="0"
                            max="12"
                            placeholder="0"
                            class="w-20 border border-gray-300 rounded-md px-3 py-2"
                        >
                        <span class="mx-2">時間</span>
                        <input
                            type="number"
                            id="duration_minutes"
                            name="duration_minutes"
                            min="0"
                            max="59"
                            placeholder="90"
                            class="w-20 border border-gray-300 rounded-md px-3 py-2"
                        >
                        <span class="mx-2">分</span>
                    </div>
                </div>
                
                <!-- 総距離 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="total_distance">総距離 (m) <span class="text-red-500">*</span></label>
                    <input
                        type="number"
                        id="total_distance"
                        name="total_distance"
                        placeholder="2000"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                        required
                    >
                    <p class="text-sm text-gray-500 mt-1">セット詳細から自動計算されます</p>
                </div>
                
                <!-- 次回練習予定日 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="next_practice_date">次回練習予定日</label>
                    <input
                        type="date"
                        id="next_practice_date"
                        name="next_practice_date"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                </div>
                
                <!-- リマインダー設定 -->
                <div class="flex items-center mt-8">
                    <input
                        type="checkbox"
                        id="next_practice_reminder"
                        name="next_practice_reminder"
                        class="h-4 w-4 text-blue-600"
                    >
                    <label class="ml-2 text-gray-700" for="next_practice_reminder">
                        次回練習日にリマインドメールを送信する
                    </label>
                </div>
                
                <!-- 負荷（調子） -->
                <div>
                    <label class="block text-gray-700 mb-2" for="feeling">調子 (1-5)</label>
                    <div class="flex items-center space-x-3">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <label class="flex items-center">
                            <input type="radio" name="feeling" value="<?php echo $i; ?>" <?php echo ($i === 3) ? 'checked' : ''; ?> class="mr-1">
                            <span><?php echo $i; ?></span>
                        </label>
                        <?php endfor; ?>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                        <span>悪い</span>
                        <span>良い</span>
                    </div>
                </div>
            </div>
            
            <!-- 課題 -->
            <div class="mb-6">
                <label class="block text-gray-700 mb-2" for="challenge">課題</label>
                <textarea
                    id="challenge"
                    name="challenge"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 h-24"
                    placeholder="この練習で取り組む課題を記入..."
                ></textarea>
            </div>
            
            <!-- 所感 -->
            <div class="mb-6">
                <label class="block text-gray-700 mb-2" for="reflection">所感・メモ</label>
                <textarea
                    id="reflection"
                    name="reflection"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 h-24"
                    placeholder="練習の感想や気づいたことを記入..."
                ></textarea>
            </div>
            
            <!-- セット詳細 -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-semibold">練習メニュー詳細</h3>
                    <button type="button" id="add-set-btn" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-plus mr-1"></i> セットを追加
                    </button>
                </div>
                
                <div id="sets-container">
                    <!-- 初期セット -->
                    <div class="set-item bg-gray-50 p-4 rounded-md mb-3">
                        <div class="flex justify-between items-start mb-3">
                            <div class="font-medium">セット #<span class="set-number">1</span></div>
                            <button type="button" class="remove-set-btn text-red-500 hover:text-red-700 text-sm" disabled>
                                <i class="fas fa-trash mr-1"></i> 削除
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
                            <!-- 種別 -->
                            <div>
                                <label class="block text-gray-700 mb-1 text-sm">種別</label>
                                <select name="sets[0][type_id]" class="w-full border border-gray-300 rounded-md px-3 py-2">
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
    <label class="block text-gray-700 mb-1 text-sm">泳法</label>
    <select name="sets[0][stroke_type]" class="w-full border border-gray-300 rounded-md px-3 py-2">
        <option value="freestyle">自由形</option>
        <option value="backstroke">背泳ぎ</option>
        <option value="breaststroke">平泳ぎ</option>
        <option value="butterfly">バタフライ</option>
        <option value="im">個人メドレー</option>
        <option value="other">その他</option>
    </select>
</div>
                            
                            <!-- 距離 -->
                            <div>
                                <label class="block text-gray-700 mb-1 text-sm">距離 (m)</label>
                                <input type="number" name="sets[0][distance]" value="100" class="w-full border border-gray-300 rounded-md px-3 py-2 set-distance">
                            </div>
                            
                            <!-- 本数 -->
                            <div>
                                <label class="block text-gray-700 mb-1 text-sm">本数</label>
                                <input type="number" name="sets[0][repetitions]" value="1" min="1" class="w-full border border-gray-300 rounded-md px-3 py-2 set-reps">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                            <!-- サイクル -->
                            <div>
                                <label class="block text-gray-700 mb-1 text-sm">サイクル</label>
                                <input type="text" name="sets[0][cycle]" placeholder="例: 1:30" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                            
                            <!-- メモ -->
                            <div>
                                <label class="block text-gray-700 mb-1 text-sm">メモ</label>
                                <input type="text" name="sets[0][notes]" placeholder="例: ウォームアップ" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                        </div>
                        
                        <!-- 小計 -->
                        <div class="text-right text-gray-600 text-sm">
                            小計: <span class="set-subtotal">100</span>m
                            <input type="hidden" name="sets[0][total_distance]" value="100" class="set-subtotal-input">
                        </div>
                        
                        <!-- 器具 -->
                        <div class="mt-3">
                            <label class="block text-gray-700 mb-1 text-sm">使用器具</label>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($equipment as $eq): ?>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="sets[0][equipment][]" value="<?php echo $eq['equipment_id']; ?>" class="h-4 w-4 text-blue-600">
                                    <span class="ml-1 text-sm"><?php echo h($eq['equipment_name']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- セット合計 -->
                <div class="mt-2 text-right font-medium">
                    合計: <span id="total-distance-calculated">100</span>m
                </div>
            </div>
            
            <!-- テンプレート -->
            <template id="set-template">
                <div class="set-item bg-gray-50 p-4 rounded-md mb-3">
                    <div class="flex justify-between items-start mb-3">
                        <div class="font-medium">セット #<span class="set-number"></span></div>
                        <button type="button" class="remove-set-btn text-red-500 hover:text-red-700 text-sm">
                            <i class="fas fa-trash mr-1"></i> 削除
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
                        <!-- 種別 -->
                        <div>
                            <label class="block text-gray-700 mb-1 text-sm">種別</label>
                            <select name="sets[INDEX][type_id]" class="w-full border border-gray-300 rounded-md px-3 py-2">
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
    <label class="block text-gray-700 mb-1 text-sm">泳法</label>
    <select name="sets[INDEX][stroke_type]" class="w-full border border-gray-300 rounded-md px-3 py-2">
        <option value="freestyle">自由形</option>
        <option value="backstroke">背泳ぎ</option>
        <option value="breaststroke">平泳ぎ</option>
        <option value="butterfly">バタフライ</option>
        <option value="im">個人メドレー</option>
        <option value="other">その他</option>
    </select>
</div>
                        
                        <!-- 距離 -->
                        <div>
                            <label class="block text-gray-700 mb-1 text-sm">距離 (m)</label>
                            <input type="number" name="sets[INDEX][distance]" value="100" class="w-full border border-gray-300 rounded-md px-3 py-2 set-distance">
                        </div>
                        
                        <!-- 本数 -->
                        <div>
                            <label class="block text-gray-700 mb-1 text-sm">本数</label>
                            <input type="number" name="sets[INDEX][repetitions]" value="1" min="1" class="w-full border border-gray-300 rounded-md px-3 py-2 set-reps">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <!-- サイクル -->
                        <div>
                            <label class="block text-gray-700 mb-1 text-sm">サイクル</label>
                            <input type="text" name="sets[INDEX][cycle]" placeholder="例: 1:30" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        
                        <!-- メモ -->
                        <div>
                            <label class="block text-gray-700 mb-1 text-sm">メモ</label>
                            <input type="text" name="sets[INDEX][notes]" placeholder="例: メインセット" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                    </div>
                    
                    <!-- 小計 -->
                    <div class="text-right text-gray-600 text-sm">
                        小計: <span class="set-subtotal">100</span>m
                        <input type="hidden" name="sets[INDEX][total_distance]" value="100" class="set-subtotal-input">
                    </div>
                    
                    <!-- 器具 -->
                    <div class="mt-3">
                        <label class="block text-gray-700 mb-1 text-sm">使用器具</label>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($equipment as $eq): ?>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="sets[INDEX][equipment][]" value="<?php echo $eq['equipment_id']; ?>" class="h-4 w-4 text-blue-600">
                                <span class="ml-1 text-sm"><?php echo h($eq['equipment_name']); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </template>
            
            <div class="flex items-center justify-between">
                <button type="button" id="add-to-calendar-btn" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-calendar-plus mr-2"></i> Googleカレンダーに追加
                </button>
                
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg">
                    練習を記録する
                </button>
            </div>
        </form>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addSetBtn = document.getElementById('add-set-btn');
        const setsContainer = document.getElementById('sets-container');
        const setTemplate = document.getElementById('set-template');
        const totalDistanceInput = document.getElementById('total_distance');
        const totalDistanceCalculated = document.getElementById('total-distance-calculated');
        let setIndex = 1; // 最初のセットは既に表示されているため1から始める
        
        // セット追加ボタンのイベントリスナー
        addSetBtn.addEventListener('click', function() {
            // テンプレートからHTMLを複製
            const templateHtml = setTemplate.innerHTML.replace(/INDEX/g, setIndex);
            
            // 新しいセット要素を作成
            const newSetElement = document.createElement('div');
            newSetElement.innerHTML = templateHtml;
            
            // コンテナに追加
            setsContainer.appendChild(newSetElement.firstElementChild);
            
            // セット番号を更新
            updateSetNumbers();
            
            // 削除ボタンを更新
            updateRemoveButtons();
            
            // インデックスを増やす
            setIndex++;
            
            // 小計と合計を更新
            updateSubtotals();
        });
        
        // 削除ボタンのイベント委任
        setsContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-set-btn') || e.target.closest('.remove-set-btn')) {
                const setItem = e.target.closest('.set-item');
                if (setItem) {
                    setItem.remove();
                    updateSetNumbers();
                    updateRemoveButtons();
                    updateSubtotals();
                }
            }
        });
        
        // 距離や本数が変更されたときのイベント委任
        setsContainer.addEventListener('input', function(e) {
            if (e.target.classList.contains('set-distance') || e.target.classList.contains('set-reps')) {
                updateSubtotals();
            }
        });
        
        // セット番号の更新
        function updateSetNumbers() {
            document.querySelectorAll('.set-item').forEach((setItem, index) => {
                const setNumber = setItem.querySelector('.set-number');
                if (setNumber) {
                    setNumber.textContent = index + 1;
                }
            });
        }
        
        // 削除ボタンの更新（少なくとも1つのセットは必要）
        function updateRemoveButtons() {
            const setItems = document.querySelectorAll('.set-item');
            const removeButtons = document.querySelectorAll('.remove-set-btn');
            
            if (setItems.length === 1) {
                removeButtons[0].setAttribute('disabled', 'disabled');
                removeButtons[0].classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                removeButtons.forEach(btn => {
                    btn.removeAttribute('disabled');
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                });
            }
        }
        
        // 小計と合計の更新
        function updateSubtotals() {
            let totalDistance = 0;
            
            document.querySelectorAll('.set-item').forEach(setItem => {
                const distanceInput = setItem.querySelector('.set-distance');
                const repsInput = setItem.querySelector('.set-reps');
                const subtotalSpan = setItem.querySelector('.set-subtotal');
                const subtotalInput = setItem.querySelector('.set-subtotal-input');
                
                const distance = parseInt(distanceInput.value) || 0;
                const reps = parseInt(repsInput.value) || 1;
                const subtotal = distance * reps;
                
                subtotalSpan.textContent = subtotal;
                subtotalInput.value = subtotal;
                
                totalDistance += subtotal;
            });
            
            totalDistanceCalculated.textContent = totalDistance;
            totalDistanceInput.value = totalDistance;
        }
        
        // 練習時間の計算（時間と分を合算して分単位に変換）
        const durationHoursInput = document.getElementById('duration_hours');
        const durationMinutesInput = document.getElementById('duration_minutes');
        
        function updateDuration() {
            const hours = parseInt(durationHoursInput.value) || 0;
            const minutes = parseInt(durationMinutesInput.value) || 0;
            const totalMinutes = hours * 60 + minutes;
            
            // 隠しフィールドがあれば更新
            const durationInput = document.querySelector('input[name="duration"]');
            if (durationInput) {
                durationInput.value = totalMinutes;
            }
        }
        
        durationHoursInput.addEventListener('input', updateDuration);
        durationMinutesInput.addEventListener('input', updateDuration);
        
        // Google カレンダー追加ボタン
        document.getElementById('add-to-calendar-btn').addEventListener('click', function(e) {
            e.preventDefault();
            
            const nextPracticeDate = document.getElementById('next_practice_date').value;
            if (!nextPracticeDate) {
                alert('次回練習予定日を入力してください。');
                return;
            }
            
            const challenge = document.getElementById('challenge').value || '練習';
            const poolSelect = document.getElementById('pool_id');
            const poolName = poolSelect.options[poolSelect.selectedIndex]?.text || '';
            
            const title = '水泳練習: ' + challenge;
            const details = '課題: ' + challenge + '\n\n前回の練習メモ: ' + document.getElementById('reflection').value;
            const location = poolName;
            
            // Google Calendar URL API を使用
            const baseUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE';
            const dateParam = '&dates=' + nextPracticeDate.replace(/-/g, '') + 'T180000Z/' + nextPracticeDate.replace(/-/g, '') + 'T200000Z'; // 仮の時間: 18:00-20:00
            const titleParam = '&text=' + encodeURIComponent(title);
            const detailsParam = '&details=' + encodeURIComponent(details);
            const locationParam = '&location=' + encodeURIComponent(location);
            
            const calendarUrl = baseUrl + dateParam + titleParam + detailsParam + locationParam;
            
            // 新しいウィンドウでカレンダーを開く
            window.open(calendarUrl, '_blank');
        });
        
        // 初期化
        updateSubtotals();
        updateRemoveButtons();
    });
    </script>
<?php elseif ($action === 'view' && $sessionId > 0): ?>
    <!-- 練習詳細表示 -->
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">練習詳細</h1>
        <a href="practice.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-1"></i> 練習一覧に戻る
        </a>
    </div>
    
    <?php
    // 練習詳細を取得
    $practice = null;
    $sets = [];
    
    try {
        $db = getDbConnection();
        
        // 練習セッション情報を取得
        $stmt = $db->prepare("
            SELECT p.*, pl.pool_name, pl.pool_length
            FROM practice_sessions p
            LEFT JOIN pools pl ON p.pool_id = pl.pool_id
            WHERE p.session_id = ? AND p.user_id = ?
        ");
        $stmt->execute([$sessionId, $_SESSION['user_id']]);
        $practice = $stmt->fetch();
        
        if ($practice) {
            // セット詳細を取得
            $stmt = $db->prepare("
                SELECT ps.*, wt.type_name
                FROM practice_sets ps
                LEFT JOIN workout_types wt ON ps.type_id = wt.type_id
                WHERE ps.session_id = ?
                ORDER BY ps.set_id ASC
            ");
            $stmt->execute([$sessionId]);
            $sets = $stmt->fetchAll();
            
            // 各セットの使用器具を取得
            $equipmentStmt = $db->prepare("
                SELECT se.set_id, e.equipment_name
                FROM set_equipment se
                JOIN equipment e ON se.equipment_id = e.equipment_id
                WHERE se.set_id IN (SELECT set_id FROM practice_sets WHERE session_id = ?)
            ");
            $equipmentStmt->execute([$sessionId]);
            $equipmentData = $equipmentStmt->fetchAll();
            
            // 器具データをセットごとにグループ化
            $equipmentBySet = [];
            foreach ($equipmentData as $eq) {
                if (!isset($equipmentBySet[$eq['set_id']])) {
                    $equipmentBySet[$eq['set_id']] = [];
                }
                $equipmentBySet[$eq['set_id']][] = $eq['equipment_name'];
            }
        }
    } catch (PDOException $e) {
        error_log('練習詳細取得エラー: ' . $e->getMessage());
    }
    
    if (!$practice) {
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">';
        echo '指定された練習が見つからないか、アクセス権がありません。';
        echo '</div>';
    } else {
    ?>
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
            <!-- 基本情報 -->
            <div>
                <h2 class="text-xl font-semibold mb-4 pb-2 border-b">基本情報</h2>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-gray-600 text-sm">練習日</p>
                        <p class="font-medium"><?php echo date('Y年n月j日', strtotime($practice['practice_date'])); ?> (<?php echo ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($practice['practice_date']))]; ?>)</p>
                    </div>
                    
                    <div>
                        <p class="text-gray-600 text-sm">総距離</p>
                        <p class="font-medium"><?php echo number_format($practice['total_distance']); ?> m</p>
                    </div>
                    
                    <?php if ($practice['duration']): ?>
                    <div>
                        <p class="text-gray-600 text-sm">練習時間</p>
                        <p class="font-medium">
                            <?php 
                            $hours = floor($practice['duration'] / 60);
                            $minutes = $practice['duration'] % 60;
                            if ($hours > 0) {
                                echo $hours . '時間 ';
                            }
                            if ($minutes > 0 || $hours === 0) {
                                echo $minutes . '分';
                            }
                            ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($practice['pool_name']): ?>
                    <div>
                        <p class="text-gray-600 text-sm">プール</p>
                        <p class="font-medium"><?php echo h($practice['pool_name']); ?> (<?php echo h($practice['pool_length']); ?>m)</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($practice['feeling']): ?>
                    <div>
                        <p class="text-gray-600 text-sm">調子</p>
                        <div class="flex items-center">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="<?php echo $i <= $practice['feeling'] ? 'text-yellow-500' : 'text-gray-300'; ?> mr-1">★</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($practice['next_practice_date']): ?>
                    <div>
                        <p class="text-gray-600 text-sm">次回練習予定日</p>
                        <p class="font-medium"><?php echo date('Y年n月j日', strtotime($practice['next_practice_date'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 課題と所感 -->
            <div>
                <h2 class="text-xl font-semibold mb-4 pb-2 border-b">課題と所感</h2>
                
                <?php if ($practice['challenge']): ?>
                <div class="mb-4">
                    <p class="text-gray-600 text-sm">課題</p>
                    <div class="bg-blue-50 p-3 rounded mt-1">
                        <?php echo nl2br(h($practice['challenge'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($practice['reflection']): ?>
                <div>
                    <p class="text-gray-600 text-sm">所感・メモ</p>
                    <div class="bg-gray-50 p-3 rounded mt-1">
                        <?php echo nl2br(h($practice['reflection'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 練習メニュー詳細 -->
        <?php if (!empty($sets)): ?>
        <h2 class="text-xl font-semibold mb-4 pb-2 border-b">練習メニュー詳細</h2>
        
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="py-2 px-4 text-left">種別</th>
                        <th class="py-2 px-4 text-left">泳法</th>
                        <th class="py-2 px-4 text-left">距離</th>
                        <th class="py-2 px-4 text-left">本数</th>
                        <th class="py-2 px-4 text-left">サイクル</th>
                        <th class="py-2 px-4 text-left">器具</th>
                        <th class="py-2 px-4 text-left">メモ</th>
                        <th class="py-2 px-4 text-left">小計</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sets as $set): ?>
                    <tr class="border-b">
                        <td class="py-3 px-4"><?php echo h($set['type_name'] ?? '-'); ?></td>
                        <td class="py-3 px-4">
                            <?php
                           <?php
                           $strokeMap = [
                               'freestyle' => '自由形',
                               'backstroke' => '背泳ぎ',
                               'breaststroke' => '平泳ぎ',
                               'butterfly' => 'バタフライ',
                               'im' => '個人メドレー',
                               'other' => 'その他'
                           ];
                           echo h($strokeMap[$set['stroke_type']] ?? $set['stroke_type']);
                           ?>
                        </td>
                        <td class="py-3 px-4"><?php echo h($set['distance']); ?>m</td>
                        <td class="py-3 px-4"><?php echo h($set['repetitions']); ?>本</td>
                        <td class="py-3 px-4"><?php echo h($set['cycle'] ?? '-'); ?></td>
                        <td class="py-3 px-4">
                            <?php
                            if (isset($equipmentBySet[$set['set_id']])) {
                                echo implode(', ', array_map('htmlspecialchars', $equipmentBySet[$set['set_id']]));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="py-3 px-4"><?php echo h($set['notes'] ?? '-'); ?></td>
                        <td class="py-3 px-4 font-medium"><?php echo h($set['total_distance']); ?>m</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- 操作ボタン -->
        <div class="mt-6 flex justify-end space-x-4">
            <a href="practice.php?action=edit&id=<?php echo $sessionId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-edit mr-2"></i> 編集
            </a>
            <form method="POST" action="api/practice.php" class="inline-block" onsubmit="return confirm('この練習記録を削除してもよろしいですか？');">
                <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="session_id" value="<?php echo $sessionId; ?>">
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-trash mr-2"></i> 削除
                </button>
            </form>
        </div>
    </div>
    <?php } ?>
<?php elseif ($action === 'edit' && $sessionId > 0): ?>
    <!-- 練習編集フォーム -->
    <?php
    // 練習詳細を取得
    $practice = null;
    $sets = [];
    
    try {
        $db = getDbConnection();
        
        // 練習セッション情報を取得
        $stmt = $db->prepare("
            SELECT p.*, pl.pool_name, pl.pool_length
            FROM practice_sessions p
            LEFT JOIN pools pl ON p.pool_id = pl.pool_id
            WHERE p.session_id = ? AND p.user_id = ?
        ");
        $stmt->execute([$sessionId, $_SESSION['user_id']]);
        $practice = $stmt->fetch();
        
        if ($practice) {
            // セット詳細を取得
            $stmt = $db->prepare("
                SELECT ps.*, wt.type_name
                FROM practice_sets ps
                LEFT JOIN workout_types wt ON ps.type_id = wt.type_id
                WHERE ps.session_id = ?
                ORDER BY ps.set_id ASC
            ");
            $stmt->execute([$sessionId]);
            $sets = $stmt->fetchAll();
            
            // 各セットの使用器具を取得
            $equipmentStmt = $db->prepare("
                SELECT se.set_id, e.equipment_id, e.equipment_name
                FROM set_equipment se
                JOIN equipment e ON se.equipment_id = e.equipment_id
                WHERE se.set_id IN (SELECT set_id FROM practice_sets WHERE session_id = ?)
            ");
            $equipmentStmt->execute([$sessionId]);
            $equipmentData = $equipmentStmt->fetchAll();
            
            // 器具データをセットごとにグループ化
            $equipmentBySet = [];
            foreach ($equipmentData as $eq) {
                if (!isset($equipmentBySet[$eq['set_id']])) {
                    $equipmentBySet[$eq['set_id']] = [];
                }
                $equipmentBySet[$eq['set_id']][] = $eq['equipment_id'];
            }
        }
    } catch (PDOException $e) {
        error_log('練習詳細取得エラー: ' . $e->getMessage());
    }
    
    if (!$practice) {
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">';
        echo '指定された練習が見つからないか、アクセス権がありません。';
        echo '</div>';
    } else {
        // 練習があれば、フォームに情報を表示
        
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
        
        // 練習器具一覧を取得
        $equipment = [];
        try {
            $db = getDbConnection();
            $stmt = $db->prepare("
                SELECT * FROM equipment
                WHERE user_id = ? OR is_system = 1
                ORDER BY is_system DESC, equipment_name ASC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $equipment = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('練習器具一覧取得エラー: ' . $e->getMessage());
        }
    ?>
    
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">練習を編集</h1>
        <a href="practice.php?action=view&id=<?php echo $sessionId; ?>" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-1"></i> 詳細に戻る
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <!-- 編集用フォーム -->
        <form method="POST" action="api/practice.php">
            <!-- CSRFトークン -->
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            <input type="hidden" name="session_id" value="<?php echo $sessionId; ?>">
            <input type="hidden" name="action" value="update">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- 練習日 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="practice_date">練習日 <span class="text-red-500">*</span></label>
                    <input
                        type="date"
                        id="practice_date"
                        name="practice_date"
                        value="<?php echo h($practice['practice_date']); ?>"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                        required
                    >
                </div>
                
                <!-- プール選択 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="pool_id">プール</label>
                    <select
                        id="pool_id"
                        name="pool_id"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                        <option value="">選択してください</option>
                        <?php foreach ($pools as $pool): ?>
                        <option value="<?php echo $pool['pool_id']; ?>" <?php echo $pool['pool_id'] == $practice['pool_id'] ? 'selected' : ''; ?>>
                            <?php echo h($pool['pool_name']); ?> (<?php echo h($pool['pool_length']); ?>m)
                            <?php if ($pool['is_favorite']): ?>⭐<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- 練習時間 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="duration">練習時間</label>
                    <div class="flex items-center">
                        <input
                            type="number"
                            id="duration_hours"
                            name="duration_hours"
                            min="0"
                            max="12"
                            value="<?php echo floor(($practice['duration'] ?? 0) / 60); ?>"
                            class="w-20 border border-gray-300 rounded-md px-3 py-2"
                        >
                        <span class="mx-2">時間</span>
                        <input
                            type="number"
                            id="duration_minutes"
                            name="duration_minutes"
                            min="0"
                            max="59"
                            value="<?php echo ($practice['duration'] ?? 0) % 60; ?>"
                            class="w-20 border border-gray-300 rounded-md px-3 py-2"
                        >
                        <span class="mx-2">分</span>
                    </div>
                </div>
                
                <!-- 総距離 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="total_distance">総距離 (m) <span class="text-red-500">*</span></label>
                    <input
                        type="number"
                        id="total_distance"
                        name="total_distance"
                        value="<?php echo h($practice['total_distance']); ?>"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                        required
                    >
                    <p class="text-sm text-gray-500 mt-1">セット詳細から自動計算されます</p>
                </div>
                
                <!-- 次回練習予定日 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="next_practice_date">次回練習予定日</label>
                    <input
                        type="date"
                        id="next_practice_date"
                        name="next_practice_date"
                        value="<?php echo h($practice['next_practice_date'] ?? ''); ?>"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                </div>
                
                <!-- リマインダー設定 -->
                <div class="flex items-center mt-8">
                    <input
                        type="checkbox"
                        id="next_practice_reminder"
                        name="next_practice_reminder"
                        <?php echo ($practice['next_practice_reminder'] ?? 0) ? 'checked' : ''; ?>
                        class="h-4 w-4 text-blue-600"
                    >
                    <label class="ml-2 text-gray-700" for="next_practice_reminder">
                        次回練習日にリマインドメールを送信する
                    </label>
                </div>
                
                <!-- 負荷（調子） -->
                <div>
                    <label class="block text-gray-700 mb-2" for="feeling">調子 (1-5)</label>
                    <div class="flex items-center space-x-3">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <label class="flex items-center">
                            <input type="radio" name="feeling" value="<?php echo $i; ?>" <?php echo ($practice['feeling'] == $i) ? 'checked' : ''; ?> class="mr-1">
                            <span><?php echo $i; ?></span>
                        </label>
                        <?php endfor; ?>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                        <span>悪い</span>
                        <span>良い</span>
                    </div>
                </div>
            </div>
            
            <!-- 課題 -->
            <div class="mb-6">
                <label class="block text-gray-700 mb-2" for="challenge">課題</label>
                <textarea
                    id="challenge"
                    name="challenge"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 h-24"
                ><?php echo h($practice['challenge'] ?? ''); ?></textarea>
            </div>
            
            <!-- 所感 -->
            <div class="mb-6">
                <label class="block text-gray-700 mb-2" for="reflection">所感・メモ</label>
                <textarea
                    id="reflection"
                    name="reflection"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 h-24"
                ><?php echo h($practice['reflection'] ?? ''); ?></textarea>
            </div>
            
            <!-- セット詳細 -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-semibold">練習メニュー詳細</h3>
                    <button type="button" id="add-set-btn" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-plus mr-1"></i> セットを追加
                    </button>
                </div>
                
                <div id="sets-container">
                    <?php foreach ($sets as $index => $set): ?>
                    <div class="set-item bg-gray-50 p-4 rounded-md mb-3">
                        <input type="hidden" name="sets[<?php echo $index; ?>][set_id]" value="<?php echo $set['set_id']; ?>">
                        
                        <div class="flex justify-between items-start mb-3">
                            <div class="font-medium">セット #<span class="set-number"><?php echo $index + 1; ?></span></div>
                            <button type="button" class="remove-set-btn text-red-500 hover:text-red-700 text-sm" <?php echo (count($sets) <= 1) ? 'disabled' : ''; ?>>
                                <i class="fas fa-trash mr-1"></i> 削除
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
                            <!-- 種別 -->
                            <div>
                                <label class="block text-gray-700 mb-1 text-sm">種別</label>
                                <select name="sets[<?php echo $index; ?>][type_id]" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    <option value="">選択してください</option>
                                    <?php foreach ($workout_types as $type): ?>
                                    <option value="<?php echo $type['type_id']; ?>" <?php echo ($set['type_id'] == $type['type_id']) ? 'selected' : ''; ?>>
                                        <?php echo h($type['type_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- 泳法 -->
                            <div>
                                <label class="block text-gray-700 mb-1 text-sm">泳法</label>
                                <select name="sets[<?php echo $index; ?>][stroke_type]" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    <?php 
                                    $strokeOptions = [
                                        'freestyle' => '自由形',
                                        'backstroke' => '背泳ぎ',
                                        'breaststroke' => '平泳ぎ',
                                        'butterfly' => 'バタフライ',
                                        'im' => '個人メドレー',
                                        'other' => 'その他'
                                    ];
                                    
                                    foreach ($strokeOptions as $value => $label): 
                                    ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($set['stroke_type'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- 距離 -->
                            <div>
                                <label class="block text-gray-700 mb-1 text-sm">距離 (m)</label>
                                <input type="number" name="sets[<?php echo $index; ?>][distance]" value="<?php echo h($set['distance']); ?>" class="w-full border border-gray-300 rounded-md px-3 py-2 set-distance">
                            </div>
                            
                            <!-- 本数 -->
                            <div>
                                <label class="block text-gray-700 mb-1 text-sm">本数</label>
                                <input type="number" name="sets[<?php echo $index; ?>][repetitions]" value="<?php echo h($set['repetitions']); ?>" min="1" class="w-full border border-gray-300 rounded-md px-3 py-2 set-reps">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                            <!-- サイクル -->
                            <div>
                                <label class="block text-gray-700 mb-1 text-sm">サイクル</label>
                                <input type="text" name="sets[<?php echo $index; ?>][cycle]" value="<?php echo h($set['cycle'] ?? ''); ?>" placeholder="例: 1:30" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                            
                            <!-- メモ -->
                            <div>
                                <label class="block text-gray-700 mb-1 text-sm">メモ</label>
                                <input type="text" name="sets[<?php echo $index; ?>][notes]" value="<?php echo h($set['notes'] ?? ''); ?>" placeholder="例: ウォームアップ" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                        </div>
                        
                        <!-- 小計 -->
                        <div class="text-right text-gray-600 text-sm">
                            小計: <span class="set-subtotal"><?php echo h($set['total_distance']); ?></span>m
                            <input type="hidden" name="sets[<?php echo $index; ?>][total_distance]" value="<?php echo h($set['total_distance']); ?>" class="set-subtotal-input">
                        </div>
                        
                        <!-- 器具 -->
                        <div class="mt-3">
                            <label class="block text-gray-700 mb-1 text-sm">使用器具</label>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($equipment as $eq): ?>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="sets[<?php echo $index; ?>][equipment][]" value="<?php echo $eq['equipment_id']; ?>" class="h-4 w-4 text-blue-600" <?php echo in_array($eq['equipment_id'], $equipmentBySet[$set['set_id']] ?? []) ? 'checked' : ''; ?>>
                                    <span class="ml-1 text-sm"><?php echo h($eq['equipment_name']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- セット合計 -->
                <div class="mt-2 text-right font-medium">
                    合計: <span id="total-distance-calculated"><?php echo h($practice['total_distance']); ?></span>m
                </div>
            </div>
            
            <!-- テンプレート -->
            <template id="set-template">
                <div class="set-item bg-gray-50 p-4 rounded-md mb-3">
                    <div class="flex justify-between items-start mb-3">
                        <div class="font-medium">セット #<span class="set-number"></span></div>
                        <button type="button" class="remove-set-btn text-red-500 hover:text-red-700 text-sm">
                            <i class="fas fa-trash mr-1"></i> 削除
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
                        <!-- 種別 -->
                        <div>
                            <label class="block text-gray-700 mb-1 text-sm">種別</label>
                            <select name="sets[INDEX][type_id]" class="w-full border border-gray-300 rounded-md px-3 py-2">
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
                            <label class="block text-gray-700 mb-1 text-sm">泳法</label>
                            <select name="sets[INDEX][stroke_type]" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="freestyle">自由形</option>                                
                                <option value="backstroke">背泳ぎ</option>
                                <option value="breaststroke">平泳ぎ</option>
                                <option value="butterfly">バタフライ</option>
                                <option value="im">個人メドレー</option>
                                <option value="kick">キック</option>
                                <option value="pull">プル</option>
                                <option value="drill">ドリル</option>
                            </select>
                        </div>
                        
                        <!-- 距離 -->
                        <div>
                            <label class="block text-gray-700 mb-1 text-sm">距離 (m)</label>
                            <input type="number" name="sets[INDEX][distance]" value="100" class="w-full border border-gray-300 rounded-md px-3 py-2 set-distance">
                        </div>
                        
                        <!-- 本数 -->
                        <div>
                            <label class="block text-gray-700 mb-1 text-sm">本数</label>
                            <input type="number" name="sets[INDEX][repetitions]" value="1" min="1" class="w-full border border-gray-300 rounded-md px-3 py-2 set-reps">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <!-- サイクル -->
                        <div>
                            <label class="block text-gray-700 mb-1 text-sm">サイクル</label>
                            <input type="text" name="sets[INDEX][cycle]" placeholder="例: 1:30" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        
                        <!-- メモ -->
                        <div>
                            <label class="block text-gray-700 mb-1 text-sm">メモ</label>
                            <input type="text" name="sets[INDEX][notes]" placeholder="例: メインセット" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                    </div>
                    
                    <!-- 小計 -->
                    <div class="text-right text-gray-600 text-sm">
                        小計: <span class="set-subtotal">100</span>m
                        <input type="hidden" name="sets[INDEX][total_distance]" value="100" class="set-subtotal-input">
                    </div>
                    
                    <!-- 器具 -->
                    <div class="mt-3">
                        <label class="block text-gray-700 mb-1 text-sm">使用器具</label>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($equipment as $eq): ?>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="sets[INDEX][equipment][]" value="<?php echo $eq['equipment_id']; ?>" class="h-4 w-4 text-blue-600">
                                <span class="ml-1 text-sm"><?php echo h($eq['equipment_name']); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </template>
            
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg">
                    変更を保存する
                </button>
            </div>
        </form>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addSetBtn = document.getElementById('add-set-btn');
        const setsContainer = document.getElementById('sets-container');
        const setTemplate = document.getElementById('set-template');
        const totalDistanceInput = document.getElementById('total_distance');
        const totalDistanceCalculated = document.getElementById('total-distance-calculated');
        let setIndex = <?php echo count($sets); ?>; // 既存のセット数
        
        // セット追加ボタンのイベントリスナー
        addSetBtn.addEventListener('click', function() {
            // テンプレートからHTMLを複製
            const templateHtml = setTemplate.innerHTML.replace(/INDEX/g, setIndex);
            
            // 新しいセット要素を作成
            const newSetElement = document.createElement('div');
            newSetElement.innerHTML = templateHtml;
            
            // コンテナに追加
            setsContainer.appendChild(newSetElement.firstElementChild);
            
            // セット番号を更新
            updateSetNumbers();
            
            // 削除ボタンを更新
            updateRemoveButtons();
            
            // インデックスを増やす
            setIndex++;
            
            // 小計と合計を更新
            updateSubtotals();
        });
        
        // 削除ボタンのイベント委任
        setsContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-set-btn') || e.target.closest('.remove-set-btn')) {
                const button = e.target.closest('.remove-set-btn');
                if (button && !button.hasAttribute('disabled')) {
                    const setItem = button.closest('.set-item');
                    if (setItem) {
                        setItem.remove();
                        updateSetNumbers();
                        updateRemoveButtons();
                        updateSubtotals();
                    }
                }
            }
        });
        
        // 距離や本数が変更されたときのイベント委任
        setsContainer.addEventListener('input', function(e) {
            if (e.target.classList.contains('set-distance') || e.target.classList.contains('set-reps')) {
                updateSubtotals();
            }
        });
        
        // セット番号の更新
        function updateSetNumbers() {
            document.querySelectorAll('.set-item').forEach((setItem, index) => {
                const setNumber = setItem.querySelector('.set-number');
                if (setNumber) {
                    setNumber.textContent = index + 1;
                }
                
                // セットのインデックスを更新（name属性内の[index]部分を更新）
                const inputs = setItem.querySelectorAll('input, select');
                inputs.forEach(input => {
                    if (input.name) {
                        input.name = input.name.replace(/sets\[\d+\]/, `sets[${index}]`);
                    }
                });
            });
        }
        
        // 削除ボタンの更新（少なくとも1つのセットは必要）
        function updateRemoveButtons() {
            const setItems = document.querySelectorAll('.set-item');
            const removeButtons = document.querySelectorAll('.remove-set-btn');
            
            if (setItems.length === 1) {
                removeButtons[0].setAttribute('disabled', 'disabled');
                removeButtons[0].classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                removeButtons.forEach(btn => {
                    btn.removeAttribute('disabled');
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                });
            }
        }
        
        // 小計と合計の更新
        function updateSubtotals() {
            let totalDistance = 0;
            
            document.querySelectorAll('.set-item').forEach(setItem => {
                const distanceInput = setItem.querySelector('.set-distance');
                const repsInput = setItem.querySelector('.set-reps');
                const subtotalSpan = setItem.querySelector('.set-subtotal');
                const subtotalInput = setItem.querySelector('.set-subtotal-input');
                
                const distance = parseInt(distanceInput.value) || 0;
                const reps = parseInt(repsInput.value) || 1;
                const subtotal = distance * reps;
                
                subtotalSpan.textContent = subtotal;
                subtotalInput.value = subtotal;
                
                totalDistance += subtotal;
            });
            
            totalDistanceCalculated.textContent = totalDistance;
            totalDistanceInput.value = totalDistance;
        }
        
        // 初期化
        updateRemoveButtons();
    });
    </script>
    <?php } ?>
<?php else: ?>
    <!-- 練習一覧 -->
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">練習記録</h1>
        <a href="practice.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> 新しい練習を記録
        </a>
    </div>
    
    <!-- フィルター -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form id="filter-form" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="date-from" class="block text-sm text-gray-600 mb-1">開始日</label>
                <input type="date" id="date-from" name="date_from" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label for="date-to" class="block text-sm text-gray-600 mb-1">終了日</label>
                <input type="date" id="date-to" name="date_to" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label for="stroke-type" class="block text-sm text-gray-600 mb-1">泳法</label>
                <select id="stroke-type" name="stroke_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200">
                    <option value="">すべて</option>
                    <option value="freestyle">自由形</option>
                    <option value="backstroke">背泳ぎ</option>
                    <option value="breaststroke">平泳ぎ</option>
                    <option value="butterfly">バタフライ</option>
                    <option value="im">個人メドレー</option>
                </select>
            </div>
            <div class="flex items-end w-full sm:w-auto">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md w-full sm:w-auto">
                    <i class="fas fa-filter mr-1"></i> フィルター
                </button>
            </div>
        </form>
    </div>
    
    <?php
    // 練習一覧を取得
    $practices = [];
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("
            SELECT p.*, pl.pool_name
            FROM practice_sessions p
            LEFT JOIN pools pl ON p.pool_id = pl.pool_id
            WHERE p.user_id = ?
            ORDER BY p.practice_date DESC
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $practices = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('練習一覧取得エラー: ' . $e->getMessage());
    }
    ?>
    
    <!-- 練習一覧 -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <?php if (empty($practices)): ?>
        <div class="text-center py-8">
            <p class="text-gray-500 mb-6">
                まだ練習記録がありません。<br>新しい練習を記録しましょう。
            </p>
            <a href="practice.php?action=new" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                最初の練習を記録する
            </a>
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
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>