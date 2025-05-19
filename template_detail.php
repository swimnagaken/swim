<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// ページタイトル
$page_title = "テンプレート詳細";

// ログイン必須
requireLogin();

// テンプレートIDの取得
$template_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($template_id <= 0) {
    $_SESSION['error_messages'][] = '無効なテンプレートIDです。';
    header('Location: templates.php');
    exit;
}

// テンプレート情報の取得
$template = null;
$sets = [];
$equipment = [];

try {
    $db = getDbConnection();
    
    // テンプレート基本情報を取得
    $stmt = $db->prepare("
        SELECT * FROM practice_templates
        WHERE template_id = ? AND user_id = ?
    ");
    $stmt->execute([$template_id, $_SESSION['user_id']]);
    $template = $stmt->fetch();
    
    if (!$template) {
        $_SESSION['error_messages'][] = '指定されたテンプレートが見つからないか、アクセス権がありません。';
        header('Location: templates.php');
        exit;
    }
    
    // テンプレートセットを取得
    $stmt = $db->prepare("
        SELECT ts.*, wt.type_name
        FROM template_sets ts
        LEFT JOIN workout_types wt ON ts.type_id = wt.type_id
        WHERE ts.template_id = ?
        ORDER BY ts.order_index
    ");
    $stmt->execute([$template_id]);
    $sets = $stmt->fetchAll();
    
    // セットごとの器具情報を取得
    foreach ($sets as $set) {
        $stmt = $db->prepare("
            SELECT tse.*, e.equipment_name
            FROM template_set_equipment tse
            JOIN equipment e ON tse.equipment_id = e.equipment_id
            WHERE tse.set_id = ?
        ");
        $stmt->execute([$set['set_id']]);
        $equipment[$set['set_id']] = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log('テンプレート詳細取得エラー: ' . $e->getMessage());
    $_SESSION['error_messages'][] = 'テンプレート情報の取得中にエラーが発生しました。';
    header('Location: templates.php');
    exit;
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
    <h1 class="text-2xl font-bold">テンプレート詳細</h1>
    <a href="templates.php" class="text-blue-600 hover:text-blue-800">
        <i class="fas fa-arrow-left mr-1"></i> テンプレート一覧に戻る
    </a>
</div>

<!-- テンプレート情報 -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <!-- 基本情報 -->
    <div class="md:col-span-2 bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-start mb-4">
            <h2 class="text-xl font-semibold"><?php echo h($template['template_name']); ?></h2>
            
            <div class="flex space-x-2">
                <a href="template_edit.php?id=<?php echo $template_id; ?>" class="bg-blue-600 hover:bg-blue-700 text-white text-sm py-1 px-3 rounded-lg">
                    <i class="fas fa-edit mr-1"></i> 編集
                </a>
                <button 
                    type="button" 
                    id="delete-template-btn" 
                    class="bg-red-600 hover:bg-red-700 text-white text-sm py-1 px-3 rounded-lg"
                    data-template-id="<?php echo $template_id; ?>"
                    data-template-name="<?php echo h($template['template_name']); ?>"
                >
                    <i class="fas fa-trash-alt mr-1"></i> 削除
                </button>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <p class="text-gray-600 text-sm">総距離</p>
                <p class="font-medium"><?php echo number_format($template['total_distance']); ?> m</p>
            </div>
            
            <div>
                <p class="text-gray-600 text-sm">作成日</p>
                <p class="font-medium"><?php echo date('Y年n月j日', strtotime($template['created_at'])); ?></p>
            </div>
            
            <?php if ($template['updated_at'] && $template['updated_at'] != $template['created_at']): ?>
            <div>
                <p class="text-gray-600 text-sm">最終更新日</p>
                <p class="font-medium"><?php echo date('Y年n月j日', strtotime($template['updated_at'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($template['description'])): ?>
        <div class="mt-4">
            <p class="text-gray-600 text-sm">説明</p>
            <div class="bg-gray-50 p-3 rounded mt-1">
                <?php echo nl2br(h($template['description'])); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- アクションボタン -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold mb-4">アクション</h2>
        
        <div class="space-y-3">
            <a href="practice.php?action=new&template_id=<?php echo $template_id; ?>" class="block w-full bg-green-600 hover:bg-green-700 text-white text-center font-medium py-2 px-4 rounded-lg">
                <i class="fas fa-plus mr-2"></i> このテンプレートで練習を記録
            </a>
            
            <a href="template_edit.php?id=<?php echo $template_id; ?>" class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center font-medium py-2 px-4 rounded-lg">
                <i class="fas fa-edit mr-2"></i> テンプレートを編集
            </a>
            
            <a href="template_create.php?duplicate_id=<?php echo $template_id; ?>" class="block w-full bg-purple-600 hover:bg-purple-700 text-white text-center font-medium py-2 px-4 rounded-lg">
                <i class="fas fa-copy mr-2"></i> 複製して新規作成
            </a>
        </div>
    </div>
</div>

<!-- セット詳細 -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold mb-6">セット詳細</h2>
    
    <?php if (empty($sets)): ?>
    <p class="text-center text-gray-500 py-4">セット情報がありません。</p>
    <?php else: ?>
    <div class="space-y-6">
        <?php foreach ($sets as $index => $set): ?>
        <div class="border rounded-lg overflow-hidden">
            <div class="bg-blue-50 p-3 border-b flex justify-between items-center">
                <h3 class="font-semibold">
                    セット <?php echo $index + 1; ?>:
                    <?php if (!empty($set['type_name'])): ?>
                    <span class="ml-2"><?php echo h($set['type_name']); ?></span>
                    <?php endif; ?>
                </h3>
                <div class="text-gray-600 text-sm font-medium">
                    <?php echo number_format($set['total_distance']); ?> m
                </div>
            </div>
            
            <div class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                    <div>
                        <p class="text-gray-600 text-sm">内容</p>
                        <p class="font-medium">
                            <?php 
                            echo $set['distance'] . ' m';
                            if ($set['repetitions'] > 1) {
                                echo ' × ' . $set['repetitions'];
                            }
                            ?>
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-gray-600 text-sm">泳法</p>
                        <p class="font-medium">
                            <?php echo h($strokeTypes[$set['stroke_type']] ?? $set['stroke_type']); ?>
                        </p>
                    </div>
                    
                    <?php if (!empty($set['cycle'])): ?>
                    <div>
                        <p class="text-gray-600 text-sm">サイクル</p>
                        <p class="font-medium"><?php echo h($set['cycle']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($equipment[$set['set_id']])): ?>
                <div class="mb-3">
                    <p class="text-gray-600 text-sm">使用器具</p>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <?php foreach ($equipment[$set['set_id']] as $eq): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            <?php echo h($eq['equipment_name']); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($set['notes'])): ?>
                <div>
                    <p class="text-gray-600 text-sm">メモ</p>
                    <p class="text-sm mt-1"><?php echo nl2br(h($set['notes'])); ?></p>
                </div>
                <?php endif; ?>
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
    const deleteButton = document.getElementById('delete-template-btn');
    const cancelDeleteButton = document.getElementById('cancel-delete');
    const templateNameElement = document.getElementById('template-name');
    const deleteTemplateIdInput = document.getElementById('delete-template-id');
    
    // 削除ボタンクリック時
    if (deleteButton) {
        deleteButton.addEventListener('click', function() {
            const templateId = this.getAttribute('data-template-id');
            const templateName = this.getAttribute('data-template-name');
            
            templateNameElement.textContent = templateName;
            deleteTemplateIdInput.value = templateId;
            deleteModal.classList.remove('hidden');
        });
    }
    
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
});
</script>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>