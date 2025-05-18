<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// ページタイトル
$page_title = "練習テンプレート";

// ログイン必須
requireLogin();

// テンプレート一覧を取得
$templates = [];
try {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT * FROM practice_templates
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $templates = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('テンプレート一覧取得エラー: ' . $e->getMessage());
}

// ヘッダーの読み込み
include 'includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold">練習テンプレート</h1>
    <div>
        <a href="template_create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> 新しいテンプレートを作成
        </a>
    </div>
</div>

<!-- テンプレート一覧 -->
<div class="bg-white rounded-lg shadow-md p-6">
    <?php if (empty($templates)): ?>
    <div class="text-center py-8">
        <p class="text-gray-500 mb-6">
            まだテンプレートがありません。<br>よく使う練習メニューをテンプレートとして保存しましょう。
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
            <div class="bg-blue-50 p-4 border-b">
                <h3 class="font-semibold text-lg truncate"><?php echo h($template['template_name']); ?></h3>
                <p class="text-gray-600 text-sm">
                    総距離: <?php echo number_format($template['total_distance']); ?>m / 
                    作成日: <?php echo date('Y/m/d', strtotime($template['created_at'])); ?>
                </p>
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
                    <a href="template_detail.php?id=<?php echo $template['template_id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-eye mr-1"></i> 詳細
                    </a>
                    <div class="flex space-x-2">
                        <a href="practice.php?action=new&template_id=<?php echo $template['template_id']; ?>" class="text-green-600 hover:text-green-800 text-sm">
                            <i class="fas fa-plus-circle mr-1"></i> 利用
                        </a>
                        <a href="template_edit.php?id=<?php echo $template['template_id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-edit mr-1"></i> 編集
                        </a>
                        <button 
                            type="button" 
                            class="text-red-600 hover:text-red-800 text-sm delete-template" 
                            data-id="<?php echo $template['template_id']; ?>"
                            data-name="<?php echo h($template['template_name']); ?>"
                        >
                            <i class="fas fa-trash mr-1"></i> 削除
                        </button>
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
            <form id="delete-form" method="POST" action="api/template.php">
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
    cancelDeleteButton.addEventListener('click', function() {
        deleteModal.classList.add('hidden');
    });
    
    // モーダル外クリックで閉じる
    deleteModal.addEventListener('click', function(e) {
        if (e.target === this) {
            deleteModal.classList.add('hidden');
        }
    });
});
</script>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>