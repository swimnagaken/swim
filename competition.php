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

// 大会記録の保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_result') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_messages'][] = '無効なリクエストです。';
    } else {
        $competition_id = (int)($_POST['competition_id'] ?? 0);
        $event_name = trim($_POST['event_name'] ?? '');
        $distance = (int)($_POST['distance'] ?? 0);
        $stroke_type = $_POST['stroke_type'] ?? '';
        $time_minutes = (int)($_POST['time_minutes'] ?? 0);
        $time_seconds = (int)($_POST['time_seconds'] ?? 0);
        $time_milliseconds = (int)($_POST['time_milliseconds'] ?? 0);
        $is_personal_best = isset($_POST['is_personal_best']) ? 1 : 0;
        $rank = !empty($_POST['rank']) ? (int)$_POST['rank'] : null;
        $notes = trim($_POST['notes'] ?? '');
        
        // 入力検証
        if ($competition_id <= 0) {
            $_SESSION['error_messages'][] = '無効な大会IDです。';
        } elseif (empty($event_name)) {
            $_SESSION['error_messages'][] = '種目名は必須です。';
        } elseif ($distance <= 0) {
            $_SESSION['error_messages'][] = '距離は正の値で入力してください。';
        } elseif (empty($stroke_type)) {
            $_SESSION['error_messages'][] = '泳法を選択してください。';
        } elseif ($time_minutes < 0 || $time_seconds < 0 || $time_seconds >= 60 || $time_milliseconds < 0 || $time_milliseconds >= 1000) {
            $_SESSION['error_messages'][] = 'タイムを正しく入力してください。';
        } else {
            try {
                $db = getDbConnection();
                
                // 大会の所有権確認
                $stmt = $db->prepare("SELECT user_id FROM competitions WHERE competition_id = ?");
                $stmt->execute([$competition_id]);
                $competition = $stmt->fetch();
                
                if (!$competition || $competition['user_id'] != $_SESSION['user_id']) {
                    $_SESSION['error_messages'][] = '指定された大会が見つからないか、所有権がありません。';
                } else {
                    // 競技結果を保存
                    $stmt = $db->prepare("
                        INSERT INTO race_results 
                        (competition_id, event_name, distance, stroke_type, time_minutes, time_seconds, time_milliseconds, is_personal_best, rank, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $competition_id,
                        $event_name,
                        $distance,
                        $stroke_type,
                        $time_minutes,
                        $time_seconds,
                        $time_milliseconds,
                        $is_personal_best,
                        $rank,
                        $notes
                    ]);
                    
                    $_SESSION['success_messages'][] = '競技結果が正常に保存されました。';
                    
                    // 詳細ページにリダイレクト
                    header('Location: competition.php?action=view&id=' . $competition_id);
                    exit;
                }
            } catch (PDOException $e) {
                error_log('競技結果保存エラー: ' . $e->getMessage());
                $_SESSION['error_messages'][] = '競技結果の保存中にエラーが発生しました。';
            }
        }
    }
}

// 大会結果の削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_result') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_messages'][] = '無効なリクエストです。';
    } else {
        $result_id = (int)($_POST['result_id'] ?? 0);
        
        if ($result_id <= 0) {
            $_SESSION['error_messages'][] = '無効な結果IDです。';
        } else {
            try {
                $db = getDbConnection();
                
                // 結果と大会の所有権確認
                $stmt = $db->prepare("
                    SELECT r.result_id, c.user_id, c.competition_id 
                    FROM race_results r
                    JOIN competitions c ON r.competition_id = c.competition_id
                    WHERE r.result_id = ?
                ");
                $stmt->execute([$result_id]);
                $result = $stmt->fetch();
                
                if (!$result || $result['user_id'] != $_SESSION['user_id']) {
                    $_SESSION['error_messages'][] = '指定された結果が見つからないか、所有権がありません。';
                } else {
                    // 結果を削除
                    $stmt = $db->prepare("DELETE FROM race_results WHERE result_id = ?");
                    $stmt->execute([$result_id]);
                    
                    $_SESSION['success_messages'][] = '競技結果が正常に削除されました。';
                    
                    // 大会詳細ページにリダイレクト
                    $competition_id = $result['competition_id'];
                    header('Location: competition.php?action=view&id=' . $competition_id);
                    exit;
                }
            } catch (PDOException $e) {
                error_log('競技結果削除エラー: ' . $e->getMessage());
                $_SESSION['error_messages'][] = '競技結果の削除中にエラーが発生しました。';
            }
        }
    }
}

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
            // 競技結果を取得
            $stmt = $db->prepare("
                SELECT * FROM race_results
                WHERE competition_id = ?
                ORDER BY is_personal_best DESC, event_name ASC
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
        <a href="competition.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-1"></i> 大会一覧に戻る
        </a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- 大会情報 -->
        <div class="md:col-span-2 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4 pb-2 border-b">大会情報</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-600 text-sm">大会名</p>
                    <p class="font-medium"><?php echo h($competition['competition_name']); ?></p>
                </div>
                
                <div>
                    <p class="text-gray-600 text-sm">開催日</p>
                    <p class="font-medium">
                        <?php echo date('Y年n月j日', strtotime($competition['competition_date'])); ?>
                        (<?php echo ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($competition['competition_date']))]; ?>)
                    </p>
                </div>
                
                <?php if (!empty($competition['location'])): ?>
                <div>
                    <p class="text-gray-600 text-sm">開催場所</p>
                    <p class="font-medium"><?php echo h($competition['location']); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($competition['notes'])): ?>
            <div class="mt-4">
                <p class="text-gray-600 text-sm">メモ</p>
                <div class="bg-gray-50 p-3 rounded mt-1">
                    <?php echo nl2br(h($competition['notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 新しい記録追加 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">新しい記録を追加</h2>
            
            <a href="#add-result-form" class="block w-full bg-green-600 hover:bg-green-700 text-white text-center font-medium py-2 px-4 rounded-lg">
                <i class="fas fa-plus mr-1"></i> 新規記録を追加
            </a>
        </div>
    </div>
    
    <!-- 競技結果一覧 -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">競技結果</h2>
        
        <?php if (empty($results)): ?>
        <div class="text-center py-6">
            <p class="text-gray-500 mb-4">
                まだ競技結果が記録されていません。<br>以下のフォームから記録を追加しましょう。
            </p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="py-2 px-4 text-left">種目</th>
                        <th class="py-2 px-4 text-left">距離</th>
                        <th class="py-2 px-4 text-left">タイム</th>
                        <th class="py-2 px-4 text-left">順位</th>
                        <th class="py-2 px-4 text-left">自己ベスト</th>
                        <th class="py-2 px-4 text-left">操作</th>
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
                    
                    foreach ($results as $result): 
                        // タイム表示の整形
                        $minutes = $result['time_minutes'];
                        $seconds = $result['time_seconds'];
                        $milliseconds = $result['time_milliseconds'];
                        
                        $timeDisplay = '';
                        if ($minutes > 0) {
                            $timeDisplay .= $minutes . ':';
                            $timeDisplay .= str_pad($seconds, 2, '0', STR_PAD_LEFT);
                        } else {
                            $timeDisplay .= $seconds;
                        }
                        $timeDisplay .= '.' . str_pad($milliseconds, 3, '0', STR_PAD_LEFT);
                    ?>
                    <tr class="border-b">
                        <td class="py-3 px-4"><?php echo h($result['event_name']); ?></td>
                        <td class="py-3 px-4">
                            <?php echo h($result['distance']); ?>m 
                            <?php echo h($strokeNames[$result['stroke_type']] ?? $result['stroke_type']); ?>
                        </td>
                        <td class="py-3 px-4 font-medium"><?php echo h($timeDisplay); ?></td>
                        <td class="py-3 px-4">
                            <?php echo $result['rank'] ? h($result['rank']) . '位' : '-'; ?>
                        </td>
                        <td class="py-3 px-4">
                            <?php if ($result['is_personal_best']): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    <i class="fas fa-trophy mr-1"></i> PB
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <form method="POST" action="competition.php" class="inline-block" onsubmit="return confirm('この記録を削除してもよろしいですか？');">
                                <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                                <input type="hidden" name="action" value="delete_result">
                                <input type="hidden" name="result_id" value="<?php echo $result['result_id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800" title="削除">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
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
        <h2 class="text-xl font-semibold mb-4">記録の追加</h2>
        
        <form method="POST" action="competition.php">
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            <input type="hidden" name="action" value="add_result">
            <input type="hidden" name="competition_id" value="<?php echo $competitionId; ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- 種目名 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="event_name">種目名 <span class="text-red-500">*</span></label>
                    <input
                        type="text"
                        id="event_name"
                        name="event_name"
                        placeholder="例：男子, 女子, 混合など"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                        required
                    >
                </div>
                
                <!-- 距離 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="distance">距離 (m) <span class="text-red-500">*</span></label>
                    <select
                        id="distance"
                        name="distance"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                        required
                    >
                        <option value="25">25m</option>
                        <option value="50" selected>50m</option>
                        <option value="100">100m</option>
                        <option value="200">200m</option>
                        <option value="400">400m</option>
                        <option value="800">800m</option>
                        <option value="1500">1500m</option>
                    </select>
                </div>
                
                <!-- 泳法 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="stroke_type">泳法 <span class="text-red-500">*</span></label>
                    <select
                        id="stroke_type"
                        name="stroke_type"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                        required
                    >
                        <option value="freestyle">自由形</option>
                        <option value="backstroke">背泳ぎ</option>
                        <option value="breaststroke">平泳ぎ</option>
                        <option value="butterfly">バタフライ</option>
                        <option value="im">個人メドレー</option>
                        <option value="other">その他</option>
                    </select>
                </div>
                
                <!-- タイム -->
                <div>
                    <label class="block text-gray-700 mb-2" for="time">タイム <span class="text-red-500">*</span></label>
                    <div class="flex items-center">
                        <input
                            type="number"
                            id="time_minutes"
                            name="time_minutes"
                            placeholder="0"
                            min="0"
                            max="59"
                            class="w-16 border border-gray-300 rounded-md px-3 py-2"
                        >
                        <span class="mx-1">:</span>
                        <input
                            type="number"
                            id="time_seconds"
                            name="time_seconds"
                            placeholder="45"
                            min="0"
                            max="59"
                            class="w-16 border border-gray-300 rounded-md px-3 py-2"
                            required
                        >
                        <span class="mx-1">.</span>
                        <input
                            type="number"
                            id="time_milliseconds"
                            name="time_milliseconds"
                            placeholder="00"
                            min="0"
                            max="999"
                            class="w-20 border border-gray-300 rounded-md px-3 py-2"
                            required
                        >
                    </div>
                </div>
                
                <!-- 順位 -->
                <div>
                    <label class="block text-gray-700 mb-2" for="rank">順位</label>
                    <input
                        type="number"
                        id="rank"
                        name="rank"
                        placeholder="順位（任意）"
                        min="1"
                        class="w-full border border-gray-300 rounded-md px-3 py-2"
                    >
                </div>
                
                <!-- 自己ベスト -->
                <div class="flex items-center mt-8">
                    <input
                        type="checkbox"
                        id="is_personal_best"
                        name="is_personal_best"
                        class="h-4 w-4 text-blue-600"
                    >
                    <label class="ml-2 text-gray-700" for="is_personal_best">
                        自己ベスト記録
                    </label>
                </div>
            </div>
            
            <!-- メモ -->
            <div class="mb-6">
                <label class="block text-gray-700 mb-2" for="notes">メモ</label>
                <textarea
                    id="notes"
                    name="notes"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 h-24"
                    placeholder="メモや気づいたことなど..."
                ></textarea>
            </div>
            
            <div class="flex justify-end">
                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg"
                >
                    記録を追加する
                </button>
            </div>
        </form>
    </div>
    <?php } ?>
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
        // 自己ベスト記録を取得
        $personalBests = [];
        try {
            $db = getDbConnection();
            $stmt = $db->prepare("
                SELECT r.*, c.competition_name, c.competition_date
                FROM race_results r
                JOIN competitions c ON r.competition_id = c.competition_id
                WHERE c.user_id = ? AND r.is_personal_best = 1
                ORDER BY r.stroke_type, r.distance
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
            'other' => 'その他'
        ];
        
        foreach ($personalBests as $record) {
            $stroke = $record['stroke_type'];
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
                            // タイム表示の整形
                            $minutes = $record['time_minutes'];
                            $seconds = $record['time_seconds'];
                            $milliseconds = $record['time_milliseconds'];
                            
                            $timeDisplay = '';
                            if ($minutes > 0) {
                                $timeDisplay .= $minutes . ':';
                                $timeDisplay .= str_pad($seconds, 2, '0', STR_PAD_LEFT);
                            } else {
                                $timeDisplay .= $seconds;
                            }
                            $timeDisplay .= '.' . str_pad($milliseconds, 3, '0', STR_PAD_LEFT);
                        ?>
                        <tr class="border-b">
                            <td class="py-3 px-4"><?php echo h($record['distance']); ?>m</td>
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

<?php
// フッターの読み込み
include 'includes/footer.php';
?>