<?php
// equipment.php - 練習種別と器具の管理ページ
require_once 'config/config.php';

// ページタイトル
$page_title = "種別と器具の管理";

// ログイン必須
requireLogin();

// アクション取得
$action = $_GET['action'] ?? '';
$error_message = '';
$success_message = '';

// 種別追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_type') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error_message = '無効なリクエストです。ページを再読み込みしてください。';
    } else {
        // 入力値の検証
        $type_name = trim($_POST['type_name'] ?? '');
        
        if (empty($type_name)) {
            $error_message = '種別名は必須です。';
        } else {
            try {
                $db = getDbConnection();
                
                // 重複チェック
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM workout_types 
                    WHERE type_name = ? AND (user_id = ? OR is_system = 1)
                ");
                $stmt->execute([$type_name, $_SESSION['user_id']]);
                if ($stmt->fetchColumn() > 0) {
                    $error_message = 'この種別名は既に登録されています。';
                } else {
                    // 種別の登録
                    $stmt = $db->prepare("
                        INSERT INTO workout_types (user_id, type_name, is_system)
                        VALUES (?, ?, 0)
                    ");
                    
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $type_name
                    ]);
                    
                    $success_message = '種別が正常に登録されました。';
                }
            } catch (PDOException $e) {
                error_log('種別登録エラー: ' . $e->getMessage());
                $error_message = '種別の登録中にエラーが発生しました。';
            }
        }
    }
}

// 種別削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_type') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error_message = '無効なリクエストです。ページを再読み込みしてください。';
    } else {
        $type_id = (int)($_POST['type_id'] ?? 0);
        
        if ($type_id <= 0) {
            $error_message = '無効な種別IDです。';
        } else {
            try {
                $db = getDbConnection();
                
                // システム種別は削除不可
                $stmt = $db->prepare("
                    SELECT is_system FROM workout_types 
                    WHERE type_id = ?
                ");
                $stmt->execute([$type_id]);
                $type = $stmt->fetch();
                
                if ($type && $type['is_system']) {
                    $error_message = 'システム定義の種別は削除できません。';
                } else {
                    // 種別の削除（自分の種別のみ）
                    $stmt = $db->prepare("
                        DELETE FROM workout_types
                        WHERE type_id = ? AND user_id = ? AND is_system = 0
                    ");
                    
                    $stmt->execute([$type_id, $_SESSION['user_id']]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success_message = '種別が正常に削除されました。';
                    } else {
                        $error_message = '削除する種別が見つからないか、削除権限がありません。';
                    }
                }
            } catch (PDOException $e) {
                error_log('種別削除エラー: ' . $e->getMessage());
                $error_message = '種別の削除中にエラーが発生しました。';
            }
        }
    }
}

// 器具追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_equipment') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error_message = '無効なリクエストです。ページを再読み込みしてください。';
    } else {
        // 入力値の検証
        $equipment_name = trim($_POST['equipment_name'] ?? '');
        
        if (empty($equipment_name)) {
            $error_message = '器具名は必須です。';
        } else {
            try {
                $db = getDbConnection();
                
                // 重複チェック
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM equipment 
                    WHERE equipment_name = ? AND (user_id = ? OR is_system = 1)
                ");
                $stmt->execute([$equipment_name, $_SESSION['user_id']]);
                if ($stmt->fetchColumn() > 0) {
                    $error_message = 'この器具名は既に登録されています。';
                } else {
                    // 器具の登録
                    $stmt = $db->prepare("
                        INSERT INTO equipment (user_id, equipment_name, is_system)
                        VALUES (?, ?, 0)
                    ");
                    
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $equipment_name
                    ]);
                    
                    $success_message = '器具が正常に登録されました。';
                }
            } catch (PDOException $e) {
                error_log('器具登録エラー: ' . $e->getMessage());
                $error_message = '器具の登録中にエラーが発生しました。';
            }
        }
    }
}

// 器具削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_equipment') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error_message = '無効なリクエストです。ページを再読み込みしてください。';
    } else {
        $equipment_id = (int)($_POST['equipment_id'] ?? 0);
        
        if ($equipment_id <= 0) {
            $error_message = '無効な器具IDです。';
        } else {
            try {
                $db = getDbConnection();
                
                // システム器具は削除不可
                $stmt = $db->prepare("
                    SELECT is_system FROM equipment 
                    WHERE equipment_id = ?
                ");
                $stmt->execute([$equipment_id]);
                $equipment = $stmt->fetch();
                
                if ($equipment && $equipment['is_system']) {
                    $error_message = 'システム定義の器具は削除できません。';
                } else {
                    // 器具の削除（自分の器具のみ）
                    $stmt = $db->prepare("
                        DELETE FROM equipment
                        WHERE equipment_id = ? AND user_id = ? AND is_system = 0
                    ");
                    
                    $stmt->execute([$equipment_id, $_SESSION['user_id']]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success_message = '器具が正常に削除されました。';
                    } else {
                        $error_message = '削除する器具が見つからないか、削除権限がありません。';
                    }
                }
            } catch (PDOException $e) {
                error_log('器具削除エラー: ' . $e->getMessage());
                $error_message = '器具の削除中にエラーが発生しました。';
            }
        }
    }
}

// 種別一覧の取得
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
    error_log('種別一覧取得エラー: ' . $e->getMessage());
    $error_message = '種別一覧の取得中にエラーが発生しました。';
}

// 器具一覧の取得
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
    $error_message = '器具一覧の取得中にエラーが発生しました。';
}

// ヘッダーの読み込み
include 'includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold">種別と器具の管理</h1>
    <a href="practice.php" class="text-blue-600 hover:text-blue-800">
        <i class="fas fa-arrow-left mr-1"></i> 練習記録に戻る
    </a>
</div>

<?php if ($error_message): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?php echo h($error_message); ?>
    </div>
<?php endif; ?>

<?php if ($success_message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo h($success_message); ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- 練習種別管理 -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">練習種別</h2>
        
        <!-- 種別一覧 -->
        <div class="mb-6">
            <h3 class="text-lg font-medium mb-3">登録済み種別</h3>
            
            <?php if (empty($workout_types)): ?>
            <p class="text-gray-500 py-3">登録済みの種別がありません。</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="py-2 px-4 text-left">種別名</th>
                            <th class="py-2 px-4 text-left">タイプ</th>
                            <th class="py-2 px-4 text-left">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workout_types as $type): ?>
                        <tr class="border-b">
                            <td class="py-2 px-4"><?php echo h($type['type_name']); ?></td>
                            <td class="py-2 px-4">
                                <?php if ($type['is_system']): ?>
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-0.5 rounded">システム</span>
                                <?php else: ?>
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-0.5 rounded">ユーザー</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4">
                                <?php if (!$type['is_system']): ?>
                                <form method="POST" action="equipment.php" class="inline-block" onsubmit="return confirm('この種別を削除してもよろしいですか？');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                                    <input type="hidden" name="action" value="delete_type">
                                    <input type="hidden" name="type_id" value="<?php echo $type['type_id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-gray-400"><i class="fas fa-lock"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 種別追加フォーム -->
        <form method="POST" action="equipment.php">
            <h3 class="text-lg font-medium mb-3">新規種別追加</h3>
            
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            <input type="hidden" name="action" value="add_type">
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2" for="type_name">種別名 <span class="text-red-500">*</span></label>
                <input
                    type="text"
                    id="type_name"
                    name="type_name"
                    class="w-full border border-gray-300 rounded-md px-3 py-2"
                    required
                    placeholder="例: インターバル、スプリント、など"
                >
            </div>
            
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg">
                種別を追加
            </button>
        </form>
    </div>
    
    <!-- 練習器具管理 -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">練習器具</h2>
        
        <!-- 器具一覧 -->
        <div class="mb-6">
            <h3 class="text-lg font-medium mb-3">登録済み器具</h3>
            
            <?php if (empty($equipment_list)): ?>
            <p class="text-gray-500 py-3">登録済みの器具がありません。</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="py-2 px-4 text-left">器具名</th>
                            <th class="py-2 px-4 text-left">タイプ</th>
                            <th class="py-2 px-4 text-left">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equipment_list as $eq): ?>
                        <tr class="border-b">
                            <td class="py-2 px-4"><?php echo h($eq['equipment_name']); ?></td>
                            <td class="py-2 px-4">
                                <?php if ($eq['is_system']): ?>
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-0.5 rounded">システム</span>
                                <?php else: ?>
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-0.5 rounded">ユーザー</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4">
                                <?php if (!$eq['is_system']): ?>
                                <form method="POST" action="equipment.php" class="inline-block" onsubmit="return confirm('この器具を削除してもよろしいですか？');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                                    <input type="hidden" name="action" value="delete_equipment">
                                    <input type="hidden" name="equipment_id" value="<?php echo $eq['equipment_id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-gray-400"><i class="fas fa-lock"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 器具追加フォーム -->
        <form method="POST" action="equipment.php">
            <h3 class="text-lg font-medium mb-3">新規器具追加</h3>
            
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            <input type="hidden" name="action" value="add_equipment">
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2" for="equipment_name">器具名 <span class="text-red-500">*</span></label>
                <input
                    type="text"
                    id="equipment_name"
                    name="equipment_name"
                    class="w-full border border-gray-300 rounded-md px-3 py-2"
                    required
                    placeholder="例: メトロノーム、アライメントボード、など"
                >
            </div>
            
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg">
                器具を追加
            </button>
        </form>
    </div>
</div>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>