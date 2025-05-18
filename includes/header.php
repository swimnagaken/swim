<?php
// HTMLコンテンツを出力する前にconfig.phpの読み込みがされていることを確認
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config/config.php';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? h($page_title) . ' - ' : ''; ?>スイムトラッカー</title>
    <meta name="description" content="水泳の練習を記録・分析し、成長をサポートするアプリです。">
    <meta name="csrf-token" content="<?php echo h(generateCsrfToken()); ?>">
    
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
    <!-- カスタムスタイル -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- ヘッダー -->
    <header class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-swimming-pool text-2xl mr-2"></i>
                    <a href="index.php" class="text-xl font-bold">スイムトラッカー</a>
                </div>
                
                <?php include __DIR__ . '/navbar.php'; ?>
            </div>
        </div>
    </header>
    
    <main class="container mx-auto px-4 py-8">
        <?php
        // 成功メッセージがあれば表示
        if (isset($_SESSION['success_messages']) && !empty($_SESSION['success_messages'])) {
            foreach ($_SESSION['success_messages'] as $message) {
                echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">';
                echo h($message);
                echo '</div>';
            }
            // メッセージを表示したらクリア
            $_SESSION['success_messages'] = [];
        }
        
        // エラーメッセージがあれば表示
        if (isset($_SESSION['error_messages']) && !empty($_SESSION['error_messages'])) {
            foreach ($_SESSION['error_messages'] as $message) {
                echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">';
                echo h($message);
                echo '</div>';
            }
            // メッセージを表示したらクリア
            $_SESSION['error_messages'] = [];
        }
        ?>