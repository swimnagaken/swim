<?php
// pools.php - プール管理ページ
require_once 'config/config.php';

// ページタイトル
$page_title = "プール管理";

// ログイン必須
requireLogin();

// プール追加処理
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_pool') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error_message = '無効なリクエストです。ページを再読み込みしてください。';
    } else {
        // 入力値の検証
        $pool_name = trim($_POST['pool_name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $pool_length = (int)($_POST['pool_length'] ?? 25);
        $notes = trim($_POST['notes'] ?? '');
        $is_favorite = isset($_POST['is_favorite']) ? 1 : 0;
        
        if (empty($pool_name)) {
            $error_message = 'プール名は必須です。';
        } else {
            try {
                $db = getDbConnection();
                
                // プールの登録
                $stmt = $db->prepare("
                    INSERT INTO pools (user_id, pool_name, location, pool_length, notes, is_favorite)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $pool_name,
                    $location,
                    $pool_length,
                    $notes,
                    $is_favorite
                ]);
                
                $success_message = 'プールが正常に登録されました。';
            } catch (PDOException $e) {
                error_log('プール登録エラー: ' . $e->getMessage());
                $error_message = 'プールの登録中にエラーが発生しました。';
            }
        }
    }
}

// プール削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_pool') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error_message = '無効なリクエストです。ページを再読み込みしてください。';
    } else {
        $pool_id = (int)($_POST['pool_id'] ?? 0);
        
        if ($pool_id <= 0) {
            $error_message = '無効なプールIDです。';
        } else {
            try {
                $db = getDbConnection();
                
                // プールの削除（自分のプールのみ）
                $stmt = $db->prepare("
                    DELETE FROM pools
                    WHERE pool_id = ? AND user_id = ?
                ");
                
                $stmt->execute([$pool_id, $_SESSION['user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    $success_message = 'プールが正常に削除されました。';
                } else {
                    $error_message = '削除するプールが見つからないか、削除権限がありません。';
                }
            } catch (PDOException $e) {
                error_log('プール削除エラー: ' . $e->getMessage());
                $error_message = 'プールの削除中にエラーが発生しました。';
            }
        }
    }
}

// 登録済みプールの取得
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
    $error_message = 'プールの一覧取得中にエラーが発生しました。';
}

// ヘッダーの読み込み
include 'includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold">プール管理</h1>
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

<!-- プール一覧 -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">登録済みプール</h2>
    
    <?php if (empty($pools)): ?>
    <p class="text-center text-gray-500 py-4">
        登録済みのプールがありません。新しいプールを追加してください。
    </p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead>
                <tr class="bg-gray-50 border-b">
                    <th class="py-2 px-4 text-left">プール名</th>
                    <th class="py-2 px-4 text-left">場所</th>
                    <th class="py-2 px-4 text-left">長さ</th>
                    <th class="py-2 px-4 text-left">お気に入り</th>
                    <th class="py-2 px-4 text-left">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pools as $pool): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4"><?php echo h($pool['pool_name']); ?></td>
                    <td class="py-3 px-4"><?php echo h($pool['location']); ?></td>
                    <td class="py-3 px-4"><?php echo h($pool['pool_length']); ?>m</td>
                    <td class="py-3 px-4">
                        <?php if ($pool['is_favorite']): ?>
                        <i class="fas fa-star text-yellow-500"></i>
                        <?php else: ?>
                        <i class="far fa-star text-gray-400"></i>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex space-x-2">
                            <a href="pools.php?action=edit&id=<?php echo $pool['pool_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="pools.php" class="inline-block">
                                <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                                <input type="hidden" name="action" value="delete_pool">
                                <input type="hidden" name="pool_id" value="<?php echo $pool['pool_id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800" onclick="return confirm('このプールを削除してもよろしいですか？');">
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

<!-- 新規プール追加フォーム -->
<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-xl font-semibold mb-4">新規プール追加</h2>
    
    <form method="POST" action="pools.php">
        <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
        <input type="hidden" name="action" value="add_pool">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- プール名 -->
            <div>
                <label class="block text-gray-700 mb-2" for="pool_name">プール名 <span class="text-red-500">*</span></label>
                <input
                    type="text"
                    id="pool_name"
                    name="pool_name"
                    class="w-full border border-gray-300 rounded-md px-3 py-2"
                    required
                >
            </div>
            
            <!-- 所在地 -->
            <div>
                <label class="block text-gray-700 mb-2" for="location">場所</label>
                <input
                    type="text"
                    id="location"
                    name="location"
                    class="w-full border border-gray-300 rounded-md px-3 py-2"
                >
            </div>
            
            <!-- プール長 -->
<div>
    <label class="block text-gray-700 mb-2" for="pool_length">プール長さ (m)</label>
    <select
        id="pool_length"
        name="pool_length"
        class="w-full border border-gray-300 rounded-md px-3 py-2"
    >
        <option value="25" selected>短水路 (25m)</option>
        <option value="50">長水路 (50m)</option>
    </select>
</div>
            
            <!-- お気に入り -->
            <div class="flex items-center mt-8">
                <input
                    type="checkbox"
                    id="is_favorite"
                    name="is_favorite"
                    class="h-4 w-4 text-blue-600"
                >
                <label class="ml-2 text-gray-700" for="is_favorite">
                    お気に入りに登録
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
            ></textarea>
        </div>
        
        <div class="flex justify-end">
            <button
                type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg"
            >
                プールを追加
            </button>
        </div>
    </form>
</div>

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