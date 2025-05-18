<?php
// 設定ファイルの読み込み
require_once '../config/config.php';

// ログイン確認
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => '認証エラー: ログインが必要です']);
    exit;
}

// CSRFトークン検証（POSTリクエストの場合）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '無効なリクエストです。']);
        exit;
    }
}

// リクエストに応じた処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 新しい大会記録を追加する処理
    $competition_name = $_POST['competition_name'] ?? null;
    $competition_date = $_POST['competition_date'] ?? null;
    $location = $_POST['location'] ?? null;
    $notes = $_POST['notes'] ?? null;
    
    // 必須フィールドの検証
    if (!$competition_name || !$competition_date) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '大会名と開催日は必須です。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // 大会記録の挿入
        $stmt = $db->prepare("
            INSERT INTO competitions (user_id, competition_name, competition_date, location, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $competition_name, $competition_date, $location, $notes]);
        
        $competition_id = $db->lastInsertId();
        
        // 成功メッセージの設定
        $_SESSION['success_messages'][] = '大会記録が正常に追加されました。';
        
        // リダイレクト
        header('Location: ../competition.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_messages'][] = '大会記録の追加中にエラーが発生しました。';
        error_log('大会記録エラー: ' . $e->getMessage());
        header('Location: ../competition.php?action=new');
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 大会データの取得処理（将来的に実装）
    header('Content-Type: application/json');
    echo json_encode(['message' => 'APIは準備中です。']);
    exit;
} else {
    // サポートされていないリクエストメソッド
    header('Content-Type: application/json');
    echo json_encode(['error' => 'サポートされていないリクエストメソッドです。']);
    exit;
}
?>