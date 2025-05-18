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
// アクションの取得
$action = isset($_POST['action']) ? $_POST['action'] : 'create';

// 編集処理
if ($action === 'update') {
    // セッションIDの確認
    $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
    if ($session_id <= 0) {
        $_SESSION['error_messages'][] = '無効なセッションIDです。';
        header('Location: ../practice.php');
        exit;
    }

    // 入力データの取得
    $practice_date = $_POST['practice_date'] ?? null;
    $total_distance = (int)($_POST['total_distance'] ?? 0);

    // 拡張データ
    $pool_id = !empty($_POST['pool_id']) ? (int)$_POST['pool_id'] : null;
    $duration_hours = isset($_POST['duration_hours']) ? (int)$_POST['duration_hours'] : 0;
    $duration_minutes = isset($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : 0;
    $duration = $duration_hours * 60 + $duration_minutes; // 分単位で計算
    $feeling = isset($_POST['feeling']) && !empty($_POST['feeling']) ? (int)$_POST['feeling'] : null;
    $challenge = $_POST['challenge'] ?? null;
    $reflection = $_POST['reflection'] ?? null;
    $next_practice_date = !empty($_POST['next_practice_date']) ? $_POST['next_practice_date'] : null;
    $next_practice_reminder = isset($_POST['next_practice_reminder']) ? 1 : 0;

    // セット情報
    $sets = $_POST['sets'] ?? [];

    // 必須フィールドの検証
    if (!$practice_date || !$total_distance) {
        $_SESSION['error_messages'][] = '練習日と総距離は必須です。';
        header('Location: ../practice.php?action=edit&id=' . $session_id);
        exit;
    }

    try {
        $db = getDbConnection();
        
        // ユーザー権限チェック
        $stmt = $db->prepare("SELECT user_id FROM practice_sessions WHERE session_id = ?");
        $stmt->execute([$session_id]);
        $practice = $stmt->fetch();
        
        if (!$practice || $practice['user_id'] != $_SESSION['user_id']) {
            $_SESSION['error_messages'][] = '編集権限がないか、指定された練習が見つかりません。';
            header('Location: ../practice.php');
            exit;
        }
        
        // トランザクション開始
        $db->beginTransaction();
        
        // 練習セッションの更新
        $stmt = $db->prepare("
            UPDATE practice_sessions 
            SET practice_date = ?, total_distance = ?, duration = ?, pool_id = ?, 
                feeling = ?, challenge = ?, reflection = ?, next_practice_date = ?, 
                next_practice_reminder = ?
            WHERE session_id = ? AND user_id = ?
        ");
        $stmt->execute([
            $practice_date,
            $total_distance,
            $duration > 0 ? $duration : null,
            $pool_id,
            $feeling,
            $challenge,
            $reflection,
            $next_practice_date,
            $next_practice_reminder,
            $session_id,
            $_SESSION['user_id']
        ]);
        
        // 既存のセットIDを取得
        $stmt = $db->prepare("SELECT set_id FROM practice_sets WHERE session_id = ?");
        $stmt->execute([$session_id]);
        $existingSets = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 更新されたセットIDを追跡
        $updatedSetIds = [];
        
        // セット詳細の更新または挿入
        if (!empty($sets)) {
            // 更新用のステートメント
            $updateSetStmt = $db->prepare("
                UPDATE practice_sets 
                SET type_id = ?, stroke_type = ?, distance = ?, repetitions = ?, 
                    cycle = ?, total_distance = ?, notes = ?
                WHERE set_id = ? AND session_id = ?
            ");
            
            // 挿入用のステートメント
            $insertSetStmt = $db->prepare("
                INSERT INTO practice_sets 
                (session_id, type_id, stroke_type, distance, repetitions, cycle, total_distance, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // 各セットを処理
            foreach ($sets as $set) {
                $type_id = !empty($set['type_id']) ? (int)$set['type_id'] : null;
                $set_distance = (int)$set['distance'];
                $repetitions = (int)($set['repetitions'] ?? 1);
                $total_set_distance = (int)($set['total_distance'] ?? ($set_distance * $repetitions));
                
                // 既存のセットIDがある場合は更新
                if (!empty($set['set_id']) && in_array($set['set_id'], $existingSets)) {
                    $updateSetStmt->execute([
                        $type_id,
                        $set['stroke_type'],
                        $set_distance,
                        $repetitions,
                        $set['cycle'] ?? null,
                        $total_set_distance,
                        $set['notes'] ?? null,
                        $set['set_id'],
                        $session_id
                    ]);
                    
                    $updatedSetIds[] = $set['set_id'];
                    $current_set_id = $set['set_id'];
                } else {
                    // 新しいセットを挿入
                    $insertSetStmt->execute([
                        $session_id,
                        $type_id,
                        $set['stroke_type'],
                        $set_distance,
                        $repetitions,
                        $set['cycle'] ?? null,
                        $total_set_distance,
                        $set['notes'] ?? null
                    ]);
                    
                    $current_set_id = $db->lastInsertId();
                    $updatedSetIds[] = $current_set_id;
                }
                
                // 既存の器具を削除
                $db->prepare("DELETE FROM set_equipment WHERE set_id = ?")->execute([$current_set_id]);
                
                // 器具情報があれば保存
                if (!empty($set['equipment']) && is_array($set['equipment'])) {
                    $equipmentStmt = $db->prepare("
                        INSERT INTO set_equipment (set_id, equipment_id)
                        VALUES (?, ?)
                    ");
                    
                    foreach ($set['equipment'] as $equipment_id) {
                        $equipmentStmt->execute([$current_set_id, (int)$equipment_id]);
                    }
                }
            }
        }
        
        // 削除されたセットを処理
        $setsToDelete = array_diff($existingSets, $updatedSetIds);
        if (!empty($setsToDelete)) {
            $placeholders = implode(',', array_fill(0, count($setsToDelete), '?'));
            
            // 器具関連を削除
            $stmt = $db->prepare("DELETE FROM set_equipment WHERE set_id IN ($placeholders)");
            $stmt->execute($setsToDelete);
            
            // セットを削除
            $stmt = $db->prepare("DELETE FROM practice_sets WHERE set_id IN ($placeholders)");
            $stmt->execute($setsToDelete);
        }
        
        // トランザクションをコミット
        $db->commit();
        
        // 成功メッセージの設定
        $_SESSION['success_messages'][] = '練習記録が正常に更新されました。';
        
        // リダイレクト
        header('Location: ../practice.php?action=view&id=' . $session_id);
        exit;
        
    } catch (PDOException $e) {
        // エラー発生時はロールバック
        $db->rollBack();
        
        error_log('練習更新エラー: ' . $e->getMessage());
        $_SESSION['error_messages'][] = '練習記録の更新中にエラーが発生しました。';
        
        // リダイレクト
        header('Location: ../practice.php?action=edit&id=' . $session_id);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // アクションの取得
    $action = isset($_POST['action']) ? $_POST['action'] : 'create';
    
    if ($action === 'delete') {
        // 練習記録の削除処理
        $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
        
        if ($session_id <= 0) {
            $_SESSION['error_messages'][] = '無効なセッションIDです。';
            header('Location: ../practice.php');
            exit;
        }
        
        try {
            $db = getDbConnection();
            
            // 権限チェック
            $stmt = $db->prepare("SELECT user_id FROM practice_sessions WHERE session_id = ?");
            $stmt->execute([$session_id]);
            $practice = $stmt->fetch();
            
            if (!$practice || $practice['user_id'] != $_SESSION['user_id']) {
                $_SESSION['error_messages'][] = '削除権限がないか、指定された練習が見つかりません。';
                header('Location: ../practice.php');
                exit;
            }
            
            // トランザクション開始
            $db->beginTransaction();
            
            // 練習セット詳細の器具関連を削除
            $stmt = $db->prepare("
                DELETE se FROM set_equipment se
                INNER JOIN practice_sets ps ON se.set_id = ps.set_id
                WHERE ps.session_id = ?
            ");
            $stmt->execute([$session_id]);
            
            // 練習セット詳細を削除
            $stmt = $db->prepare("DELETE FROM practice_sets WHERE session_id = ?");
            $stmt->execute([$session_id]);
            
            // 練習セッションを削除
            $stmt = $db->prepare("DELETE FROM practice_sessions WHERE session_id = ?");
            $stmt->execute([$session_id]);
            
            $db->commit();
            
            $_SESSION['success_messages'][] = '練習記録が正常に削除されました。';
            header('Location: ../practice.php');
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('練習削除エラー: ' . $e->getMessage());
            $_SESSION['error_messages'][] = '練習記録の削除中にエラーが発生しました。';
            header('Location: ../practice.php');
            exit;
        }
    } else {
        // 練習記録の新規作成
        $practice_date = $_POST['practice_date'] ?? null;
        $total_distance = (int)($_POST['total_distance'] ?? 0);
        
        // 拡張データ
        $pool_id = !empty($_POST['pool_id']) ? (int)$_POST['pool_id'] : null;
        $duration_hours = isset($_POST['duration_hours']) ? (int)$_POST['duration_hours'] : 0;
        $duration_minutes = isset($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : 0;
        $duration = $duration_hours * 60 + $duration_minutes; // 分単位で計算
        $feeling = isset($_POST['feeling']) && !empty($_POST['feeling']) ? (int)$_POST['feeling'] : null;
        $challenge = $_POST['challenge'] ?? null;
        $reflection = $_POST['reflection'] ?? null;
        $next_practice_date = !empty($_POST['next_practice_date']) ? $_POST['next_practice_date'] : null;
        $next_practice_reminder = isset($_POST['next_practice_reminder']) ? 1 : 0;
        
        // セット情報
        $sets = $_POST['sets'] ?? [];
        
        // 必須フィールドの検証
        if (!$practice_date || !$total_distance) {
            $_SESSION['error_messages'][] = '練習日と総距離は必須です。';
            header('Location: ../practice.php?action=new');
            exit;
        }
        
        try {
            $db = getDbConnection();
            $db->beginTransaction();
            
            // 練習セッションの挿入
            $stmt = $db->prepare("
                INSERT INTO practice_sessions 
                (user_id, practice_date, total_distance, duration, pool_id, feeling, challenge, reflection, next_practice_date, next_practice_reminder, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $practice_date,
                $total_distance,
                $duration > 0 ? $duration : null,
                $pool_id,
                $feeling,
                $challenge,
                $reflection,
                $next_practice_date,
                $next_practice_reminder
            ]);
            
            $session_id = $db->lastInsertId();
            
            // セット詳細の挿入
            if (!empty($sets)) {
                $setStmt = $db->prepare("
                    INSERT INTO practice_sets 
                    (session_id, type_id, stroke_type, distance, repetitions, cycle, total_distance, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($sets as $set) {
                    $type_id = !empty($set['type_id']) ? (int)$set['type_id'] : null;
                    $setStmt->execute([
                        $session_id,
                        $type_id,
                        $set['stroke_type'],
                        (int)$set['distance'],
                        (int)($set['repetitions'] ?? 1),
                        $set['cycle'] ?? null,
                        (int)($set['total_distance'] ?? 0),
                        $set['notes'] ?? null
                    ]);
                    
                    // セットに紐づく器具の登録
                    $set_id = $db->lastInsertId();
                    
                    // 器具情報があれば保存
                    if (!empty($set['equipment']) && is_array($set['equipment'])) {
                        $equipmentStmt = $db->prepare("
                            INSERT INTO set_equipment (set_id, equipment_id)
                            VALUES (?, ?)
                        ");
                        
                        foreach ($set['equipment'] as $equipment_id) {
                            $equipmentStmt->execute([$set_id, (int)$equipment_id]);
                        }
                    }
                }
            }
            
            $db->commit();
            
            // 成功メッセージの設定
            $_SESSION['success_messages'][] = '練習が正常に記録されました。';
            
            // リダイレクト
            header('Location: ../practice.php');
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $_SESSION['error_messages'][] = '練習の記録中にエラーが発生しました。';
            error_log('練習記録エラー: ' . $e->getMessage());
            header('Location: ../practice.php?action=new');
            exit;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 練習データの取得処理（将来的に実装）
    header('Content-Type: application/json');
    echo json_encode(['message' => 'APIは準備中です。']);
    exit;
} else {
    // サポートされていないリクエストメソッド
    header('Content-Type: application/json');
    echo json_encode(['error' => 'サポートされていないリクエストメソッドです。']);
    exit;
}
?>>