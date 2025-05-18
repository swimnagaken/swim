<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// デバッグモードを強制的に有効化
define('DEBUG_MODE', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 初期化ステータス
$status = [
    'db_connection' => false,
    'tables_created' => false,
    'admin_created' => false,
    'errors' => []
];

try {
    // データベース接続テスト
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $status['db_connection'] = true;
    
    // データベースが存在するか確認し、なければ作成
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    if (!$stmt->fetch()) {
        $pdo->exec("CREATE DATABASE `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $status['messages'][] = "データベース '" . DB_NAME . "' を作成しました。";
    } else {
        $status['messages'][] = "データベース '" . DB_NAME . "' は既に存在します。";
    }
    
    // データベース選択
    $pdo->exec("USE `" . DB_NAME . "`");
    
    // テーブル作成
    $tables = [
        // ユーザーテーブル
        "CREATE TABLE IF NOT EXISTS `users` (
            `user_id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        // 練習記録テーブル
        "CREATE TABLE IF NOT EXISTS `practice_sessions` (
            `session_id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `practice_date` DATE NOT NULL,
            `total_distance` INT NOT NULL,
            `duration` INT NULL,
            `feeling` TINYINT NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        // 練習詳細テーブル
        "CREATE TABLE IF NOT EXISTS `practice_sets` (
            `set_id` INT AUTO_INCREMENT PRIMARY KEY,
            `session_id` INT NOT NULL,
            `stroke_type` ENUM('freestyle', 'backstroke', 'breaststroke', 'butterfly', 'im', 'kick', 'pull', 'drill') NOT NULL,
            `distance` INT NOT NULL,
            `repetitions` INT DEFAULT 1,
            `interval` VARCHAR(10) NULL,
            `notes` TEXT NULL,
            FOREIGN KEY (`session_id`) REFERENCES `practice_sessions`(`session_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        // 月間目標テーブル
        "CREATE TABLE IF NOT EXISTS `monthly_goals` (
            `goal_id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `year` INT NOT NULL,
            `month` INT NOT NULL,
            `distance_goal` INT NOT NULL,
            `sessions_goal` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`),
            UNIQUE KEY (`user_id`, `year`, `month`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        // 大会/記録会テーブル
        "CREATE TABLE IF NOT EXISTS `competitions` (
            `competition_id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `competition_name` VARCHAR(100) NOT NULL,
            `competition_date` DATE NOT NULL,
            `location` VARCHAR(100) NULL,
            `notes` TEXT NULL,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        // 競技結果テーブル
        "CREATE TABLE IF NOT EXISTS `race_results` (
            `result_id` INT AUTO_INCREMENT PRIMARY KEY,
            `competition_id` INT NOT NULL,
            `event` VARCHAR(50) NOT NULL,
            `time_result` VARCHAR(10) NOT NULL,
            `place` INT NULL,
            `personal_best` BOOLEAN DEFAULT FALSE,
            `splits` TEXT NULL,
            `notes` TEXT NULL,
            FOREIGN KEY (`competition_id`) REFERENCES `competitions`(`competition_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ];
    
    // テーブルを作成
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    $status['tables_created'] = true;
    $status['messages'][] = "必要なテーブルが作成されました。";
    
    $status['setup_complete'] = true;
    
} catch (PDOException $e) {
    $status['errors'][] = "データベースエラー: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>スイムトラッカー - セットアップ</title>
    
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6 text-center">スイムトラッカー - セットアップ</h1>
        
        <?php if (!empty($status['errors'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <h3 class="font-bold">エラーが発生しました:</h3>
                <ul class="list-disc ml-8">
                    <?php foreach ($status['errors'] as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (isset($status['messages']) && !empty($status['messages'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <h3 class="font-bold">セットアップ状況:</h3>
                <ul class="list-disc ml-8">
                    <?php foreach ($status['messages'] as $message): ?>
                        <li><?php echo htmlspecialchars($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">セットアップ結果</h2>
            
            <div class="grid grid-cols-1 gap-4">
                <div class="flex items-center">
                    <div class="w-6 h-6 flex items-center justify-center rounded-full <?php echo $status['db_connection'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?> mr-3">
                        <span><?php echo $status['db_connection'] ? '✓' : '✗'; ?></span>
                    </div>
                    <span>データベース接続</span>
                </div>
                
                <div class="flex items-center">
                    <div class="w-6 h-6 flex items-center justify-center rounded-full <?php echo $status['tables_created'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?> mr-3">
                        <span><?php echo $status['tables_created'] ? '✓' : '✗'; ?></span>
                    </div>
                    <span>テーブル作成</span>
                </div>
            </div>
            
            <?php if (isset($status['setup_complete']) && $status['setup_complete']): ?>
                <div class="mt-6 text-center">
                    <p class="text-green-600 font-semibold mb-4">セットアップが完了しました！</p>
                    <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg inline-block">
                        アプリケーションを開始する
                    </a>
                </div>
            <?php else: ?>
                <div class="mt-6 text-center">
                    <p class="text-yellow-600 font-semibold mb-4">セットアップが完了していません。エラーを修正してから再試行してください。</p>
                    <a href="setup.php" class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-6 rounded-lg inline-block">
                        再試行する
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center text-gray-600 text-sm mt-8">
            <p>スイムトラッカー © <?php echo date('Y'); ?></p>
        </div>
    </div>
</body>
</html>