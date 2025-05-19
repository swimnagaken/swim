<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// ページタイトル
$page_title = "練習テンプレート";

// ログイン必須
requireLogin();

// テンプレート一覧を取得（カテゴリとお気に入り対応）
$templates = [];
$categories = [];
try {
    $db = getDbConnection();
    
    // カテゴリ一覧を取得
    $stmt = $db->prepare("
        SELECT * FROM template_categories
        WHERE user_id = ? OR is_system = 1
        ORDER BY is_system DESC, category_name ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $categories = $stmt->fetchAll();
    
    // 選択されたカテゴリID
    $selectedCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    
    // お気に入りフィルター
    $favoritesOnly = isset($_GET['favorites']) && $_GET['favorites'] == 1;
    
    // テンプレート一覧のSQL
    $sql = "
        SELECT t.*, tc.category_name 
        FROM practice_templates t
        LEFT JOIN template_categories tc ON t.category = tc.category_id
        WHERE t.user_id = ?
    ";
    
    $params = [$_SESSION['user_id']];
    
    // カテゴリフィルター
    if ($selectedCategory > 0) {
        $sql .= " AND t.category = ?";
        $params[] = $selectedCategory;
    }
    
    // お気に入りフィルター
    if ($favoritesOnly) {
        $sql .= " AND t.is_favorite = 1";
    }
    
    $sql .= " ORDER BY t.is_favorite DESC, t.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $templates = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('テンプレート一覧取得エラー: ' . $e->getMessage());
}

// ヘッダーの読み込み
include 'includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold">練習テンプレート</h1>
    <div class="flex space-x-2">
        <a href="template_categories.php" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-tags mr-2"></i> カテゴリ管理
        </a>
        <a href="templates_popular.php" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-fire mr-2"></i> 人気テンプレート
        </a>
        <a href="template_create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> 新規作成
        </a>
    </div>
</div>

<!-- フィルターUI -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <form action="templates.php" method="GET" class="flex flex-col sm:flex-row items-center gap-4">
        <div class="w-full sm:w-auto">
            <label for="category" class="block text-sm text-gray-700 mb-1">カテゴリ</label>
            <select id="category" name="category" class="w-full border border-gray-300 rounded-md px-3 py-2">
                <option value="0">すべて</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['category_id']; ?>" <?php echo $selectedCategory == $cat['category_id'] ? 'selected' : ''; ?>>
                    <?php echo h($cat['category_name']); ?>
                    <?php echo $cat['is_system'] ? ' (システム)' : ''; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="w-full sm:w-auto flex items-end">
            <label class="inline-flex items-center">
                <input type="checkbox" name="favorites" value="1" class="h-4 w-4 text-blue-600" <?php echo $favoritesOnly ? 'checked' : ''; ?>>
                <span class="ml-2 text-gray-700">お気に入りのみ</span>
            </label>
        </div>
        
        <div class="w-full sm:w-auto flex items-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                <i class="fas fa-filter mr-1"></i> フィルター
            </button>
        </div>
    </form>
</div>

<!-- テンプレート一覧 -->
<div class="bg-white rounded-lg shadow-md p-6">
    <?php if (empty($templates)): ?>
    <div class="text-center py-8">
        <p class="text-gray-500 mb-6">
            <?php if ($selectedCategory > 0 || $favoritesOnly): ?>
            検索条件に一致するテンプレートがありません。<br>条件を変更して再度検索してください。
            <?php else: ?>
            まだテンプレートがありません。<br>よく使う練習メニューをテンプレートとして保存しましょう。
            <?php endif; ?>
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="template_create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                新しいテンプレートを作成
            </a>
            <a href="practice.php" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-lg inline-flex items-center">
                <i class="fas fa-swimming-pool mr-2"></i>
                練習記録から作成
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($templates as $template): ?>
        <div class="border rounded-lg overflow-hidden hover:shadow-md transition-shadow">
            <div class="bg-blue-50 p-4 border-b flex justify-between items-start">
                <div>
                    <h3 class="font-semibold text-lg truncate"><?php echo h($template['template_name']); ?></h3>
                    <?php if (!empty($template['category_name'])): ?>
                    <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                        <?php echo h($template['category_name']); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center space-x-1">
                    <!-- お気に入りトグルボタン -->
                    <form method="POST" action="api/templates.php" class="inline-block toggle-favorite-form">
                        <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                        <input type="hidden" name="action" value="toggle_favorite">
                        <input type="hidden" name="template_id" value="<?php echo $template['template_id']; ?>">
                        <button type="submit" class="text-yellow-500 hover:text-yellow-600" title="<?php echo $template['is_favorite'] ? 'お気に入りから削除' : 'お気に入りに追加'; ?>">
                            <i class="<?php echo $template['is_favorite'] ? 'fas' : 'far'; ?> fa-star"></i>
                        </button>
                    </form>
                    
                    <?php if ($template['usage_count'] > 0): ?>
                    <span class="text-xs text-gray-600" title="利用回数">
                        <i class="fas fa-chart-bar mr-1"></i><?php echo $template['usage_count']; ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($template['is_public']): ?>
                    <span class="text-xs text-green-600" title="公開中">
                        <i class="fas fa-globe"></i>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="p-4">
                <?php if (!empty($template['description'])): ?>
                <p class="text-gray-700 text-sm mb-4 line-clamp-2" title="<?php echo h($template['description']); ?>">
                    <?php echo h($template['description']); ?>
                </p>
                <?php else: ?>
                <p class="text-gray-500 text-sm mb-4 italic">説明はありません</p>
                <?php endif; ?>
                
                <div class="flex justify-between items-center">
                    <p class="text-sm text-gray-600">
                        <span title="総距離"><?php echo number_format($template['total_distance']); ?>m</span>
                    </p>
                    <div class="flex space-x-2">
                        <a href="practice.php?action=new&template_id=<?php echo $template['template_id']; ?>" class="text-green-600 hover:text-green-800 text-sm">
                            <i class="fas fa-plus-circle mr-1"></i> 利用
                        </a>
                        <a href="template_detail.php?id=<?php echo $template['template_id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-eye mr-1"></i> 詳細
                        </a>
                        <a href="template_edit.php?id=<?php echo $template['template_id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-edit mr-1"></i> 編集
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 削除確認モーダル -->
<div id="delete-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <h3 class="text-xl font-bold mb-4 text-red-600">テンプレートの削除</h3>
        
        <p class="mb-4">
            <span id="template-name" class="font-semibold"></span> を削除してもよろしいですか？
            <br>この操作は取り消せません。
        </p>
        
        <div class="flex justify-end space-x-3">
            <button
                type="button"
                id="cancel-delete"
                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-lg"
            >
                キャンセル
            </button>
            <form id="delete-form" method="POST" action="api/templates.php">
                <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="template_id" id="delete-template-id" value="">
                <button
                    type="submit"
                    class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg"
                >
                    削除する
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 削除モーダル関連
    const deleteModal = document.getElementById('delete-modal');
    const deleteButtons = document.querySelectorAll('.delete-template');
    const cancelDeleteButton = document.getElementById('cancel-delete');
    const templateNameElement = document.getElementById('template-name');
    const deleteTemplateIdInput = document.getElementById('delete-template-id');
    
    // 削除ボタンクリック時
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const templateId = this.getAttribute('data-id');
            const templateName = this.getAttribute('data-name');
            
            templateNameElement.textContent = templateName;
            deleteTemplateIdInput.value = templateId;
            deleteModal.classList.remove('hidden');
        });
    });
    
    // キャンセルボタンクリック時
    if (cancelDeleteButton) {
        cancelDeleteButton.addEventListener('click', function() {
            deleteModal.classList.add('hidden');
        });
    }
    
    // モーダル外クリックで閉じる
    if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
            if (e.target === this) {
                deleteModal.classList.add('hidden');
            }
        });
    }
    
    // お気に入りトグルのAjax処理
    document.querySelectorAll('.toggle-favorite-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const starIcon = this.querySelector('i');
            
            fetch('api/templates.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // アイコンのクラスを切り替え
                    if (data.is_favorite) {
                        starIcon.classList.replace('far', 'fas');
                    } else {
                        starIcon.classList.replace('fas', 'far');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });
});
</script>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>