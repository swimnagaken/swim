<?php
// テンプレート管理用APIエンドポイント
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
        
    } catch (PDOException $e) {
        error_log('テンプレート一覧取得エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'テンプレート一覧の取得中にエラーが発生しました。']);
        exit;
    }
}

/**
 * テンプレート詳細情報を取得する
 */
function getTemplateDetail($template_id) {
    $template_id = (int)$template_id;
    
    if ($template_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '無効なテンプレートIDです。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // テンプレート基本情報を取得
        $stmt = $db->prepare("
            SELECT t.*, tc.category_name
            FROM practice_templates t
            LEFT JOIN template_categories tc ON t.category = tc.category_id
            WHERE t.template_id = ? AND (t.user_id = ? OR t.is_public = 1)
        ");
        $stmt->execute([$template_id, $_SESSION['user_id']]);
        $template = $stmt->fetch();
        
        if (!$template) {
            header('Content-Type: application/json');
            echo json_encode(['error' => '指定されたテンプレートが見つからないか、アクセス権がありません。']);
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
        $equipment = [];
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
        
        // レスポンスデータの構築
        $template['sets'] = $sets;
        $template['equipment'] = $equipment;
        
        // 成功レスポンス
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'template' => $template
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log('テンプレート詳細取得エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'テンプレート詳細の取得中にエラーが発生しました。']);
        exit;
    }
}
?>
    }
}

/**
 * テンプレート詳細情報を取得する
 */
function getTemplateDetail($template_id) {
    $template_id = (int)$template_id;
    
    if ($template_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '無効なテンプレートIDです。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // テンプレート基本情報を取得
        $stmt = $db->prepare("
            SELECT t.*, tc.category_name
            FROM practice_templates t
            LEFT JOIN template_categories tc ON t.category = tc.category_id
            WHERE t.template_id = ? AND (t.user_id = ? OR t.is_public = 1)
        ");
        $stmt->execute([$template_id, $_SESSION['user_id']]);
        $template = $stmt->fetch();
        
        if (!$template) {
            header('Content-Type: application/json');
            echo json_encode(['error' => '指定されたテンプレートが見つからないか、アクセス権がありません。']);
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
        $equipment = [];
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
        
        // レスポンスデータの構築
        $template['sets'] = $sets;
        $template['equipment'] = $equipment;
        
        // 成功レスポンス
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'template' => $template
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log('テンプレート詳細取得エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'テンプレート詳細の取得中にエラーが発生しました。']);
        exit;
    }
};
    }
}

// リクエストに応じた処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // アクションの取得
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    switch ($action) {
        case 'create':
            // 新しいテンプレートを作成
            createTemplate();
            break;
        
        case 'update':
            // 既存のテンプレートを更新
            updateTemplate();
            break;
        
        case 'delete':
            // テンプレートを削除
            deleteTemplate();
            break;
        
        case 'toggle_favorite':
            // お気に入り状態を切り替え
            toggleFavorite();
            break;
            
        case 'toggle_public':
            // 公開状態を切り替え
            togglePublic();
            break;
        
        case 'create_from_practice':
            // 既存の練習からテンプレートを作成
            createTemplateFromPractice();
            break;
        
        default:
            // 不明なアクション
            header('Content-Type: application/json');
            echo json_encode(['error' => '不明なアクションです。']);
            exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // テンプレート情報の取得
    if (isset($_GET['template_id'])) {
        // 特定のテンプレート詳細を取得
        getTemplateDetail($_GET['template_id']);
    } else {
        // テンプレート一覧を取得
        getTemplateList();
    }
} else {
    // サポートされていないリクエストメソッド
    header('Content-Type: application/json');
    echo json_encode(['error' => 'サポートされていないリクエストメソッドです。']);
    exit;
}

/**
 * 新しいテンプレートを作成する
 */
function createTemplate() {
    // 入力値の検証
    $template_name = trim($_POST['template_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $total_distance = (int)($_POST['total_distance'] ?? 0);
    $category = !empty($_POST['category']) ? (int)$_POST['category'] : null;
    $is_favorite = isset($_POST['is_favorite']) ? 1 : 0;
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $sets = $_POST['sets'] ?? [];
    $redirect_to_practice = isset($_POST['redirect_to_practice']) ? (bool)$_POST['redirect_to_practice'] : false;
    
    // 必須項目の検証
    if (empty($template_name) || $total_distance <= 0 || empty($sets)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'テンプレート名、総距離、セット情報は必須です。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        $db->beginTransaction();
        
        // テンプレート基本情報の保存
        $stmt = $db->prepare("
            INSERT INTO practice_templates (user_id, template_name, description, category, total_distance, is_favorite, is_public, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $template_name,
            $description,
            $category,
            $total_distance,
            $is_favorite,
            $is_public
        ]);
        
        $template_id = $db->lastInsertId();
        
        // テンプレートセットの保存
        $setStmt = $db->prepare("
            INSERT INTO template_sets (
                template_id, type_id, stroke_type, distance, repetitions, 
                cycle, total_distance, notes, order_index
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // 器具登録用のステートメント
        $equipmentStmt = $db->prepare("
            INSERT INTO template_set_equipment (set_id, equipment_id)
            VALUES (?, ?)
        ");
        
        // 各セットを処理
        foreach ($sets as $index => $set) {
            $type_id = !empty($set['type_id']) ? (int)$set['type_id'] : null;
            
            $setStmt->execute([
                $template_id,
                $type_id,
                $set['stroke_type'],
                (int)$set['distance'],
                (int)($set['repetitions'] ?? 1),
                $set['cycle'] ?? null,
                (int)($set['total_distance'] ?? 0),
                $set['notes'] ?? null,
                $index
            ]);
            
            // セットに紐づく器具の登録
            $set_id = $db->lastInsertId();
            
            // 器具情報があれば保存
            if (!empty($set['equipment']) && is_array($set['equipment'])) {
                foreach ($set['equipment'] as $equipment_id) {
                    $equipmentStmt->execute([$set_id, (int)$equipment_id]);
                }
            }
        }
        
        $db->commit();
        
        // 成功メッセージの設定
        $_SESSION['success_messages'][] = 'テンプレートが正常に作成されました。';
        
        // 練習記録画面へのリダイレクトが要求された場合
        if ($redirect_to_practice) {
            header('Location: ../practice.php?action=new&template_id=' . $template_id);
            exit;
        }
        
        // 通常のリダイレクト
        header('Location: ../template_detail.php?id=' . $template_id);
        exit;
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('テンプレート作成エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'テンプレートの作成中にエラーが発生しました。']);
        exit;
    }
}

/**
 * 既存のテンプレートを更新する
 */
function updateTemplate() {
    // 入力値の検証
    $template_id = (int)($_POST['template_id'] ?? 0);
    $template_name = trim($_POST['template_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $total_distance = (int)($_POST['total_distance'] ?? 0);
    $sets = $_POST['sets'] ?? [];
    $redirect_to_practice = isset($_POST['redirect_to_practice']) ? (bool)$_POST['redirect_to_practice'] : false;
    
    // 必須項目の検証
    if ($template_id <= 0 || empty($template_name) || $total_distance <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'テンプレートID、名前、総距離は必須です。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // テンプレートの所有権確認
        $stmt = $db->prepare("SELECT user_id FROM practice_templates WHERE template_id = ?");
        $stmt->execute([$template_id]);
        $template = $stmt->fetch();
        
        if (!$template || $template['user_id'] != $_SESSION['user_id']) {
            header('Content-Type: application/json');
            echo json_encode(['error' => '指定されたテンプレートが見つからないか、編集権限がありません。']);
            exit;
        }
        
        $db->beginTransaction();
        
        // テンプレート基本情報の更新
        $stmt = $db->prepare("
            UPDATE practice_templates
            SET template_name = ?, description = ?, total_distance = ?, updated_at = NOW()
            WHERE template_id = ?
        ");
        $stmt->execute([$template_name, $description, $total_distance, $template_id]);
        
        // 既存のセットとそれに関連する器具を取得し、削除するセットと既存のセットを管理
        $stmt = $db->prepare("
            SELECT set_id FROM template_sets WHERE template_id = ?
        ");
        $stmt->execute([$template_id]);
        $existingSetIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 更新または追加されるセットIDを記録
        $processedSetIds = [];
        
        // セットの更新または挿入
        if (!empty($sets)) {
            // テンプレートセットの保存または更新
            $updateSetStmt = $db->prepare("
                UPDATE template_sets
                SET type_id = ?, stroke_type = ?, distance = ?, repetitions = ?, 
                    cycle = ?, total_distance = ?, notes = ?, order_index = ?
                WHERE set_id = ? AND template_id = ?
            ");
            
            $insertSetStmt = $db->prepare("
                INSERT INTO template_sets (
                    template_id, type_id, stroke_type, distance, repetitions, 
                    cycle, total_distance, notes, order_index
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // 器具登録用のステートメント
            $insertEquipmentStmt = $db->prepare("
                INSERT INTO template_set_equipment (set_id, equipment_id)
                VALUES (?, ?)
            ");
            
            // 器具削除用のステートメント
            $deleteEquipmentStmt = $db->prepare("
                DELETE FROM template_set_equipment WHERE set_id = ?
            ");
            
            // 各セットを処理
            foreach ($sets as $index => $set) {
                $type_id = !empty($set['type_id']) ? (int)$set['type_id'] : null;
                
                // 既存のセットIDが指定されている場合
                if (!empty($set['set_id']) && in_array($set['set_id'], $existingSetIds)) {
                    $set_id = (int)$set['set_id'];
                    
                    // セット情報を更新
                    $updateSetStmt->execute([
                        $type_id,
                        $set['stroke_type'],
                        (int)$set['distance'],
                        (int)($set['repetitions'] ?? 1),
                        $set['cycle'] ?? null,
                        (int)($set['total_distance'] ?? 0),
                        $set['notes'] ?? null,
                        $index,
                        $set_id,
                        $template_id
                    ]);
                    
                    // 既存の器具を削除して新しく登録
                    $deleteEquipmentStmt->execute([$set_id]);
                    
                    // 器具情報があれば保存
                    if (!empty($set['equipment']) && is_array($set['equipment'])) {
                        foreach ($set['equipment'] as $equipment_id) {
                            $insertEquipmentStmt->execute([$set_id, (int)$equipment_id]);
                        }
                    }
                    
                    // 処理済みセットIDを記録
                    $processedSetIds[] = $set_id;
                } else {
                    // 新しいセットを挿入
                    $insertSetStmt->execute([
                        $template_id,
                        $type_id,
                        $set['stroke_type'],
                        (int)$set['distance'],
                        (int)($set['repetitions'] ?? 1),
                        $set['cycle'] ?? null,
                        (int)($set['total_distance'] ?? 0),
                        $set['notes'] ?? null,
                        $index
                    ]);
                    
                    // 新しいセットID
                    $set_id = $db->lastInsertId();
                    
                    // 器具情報があれば保存
                    if (!empty($set['equipment']) && is_array($set['equipment'])) {
                        foreach ($set['equipment'] as $equipment_id) {
                            $insertEquipmentStmt->execute([$set_id, (int)$equipment_id]);
                        }
                    }
                    
                    // 処理済みセットIDを記録
                    $processedSetIds[] = $set_id;
                }
            }
        }
        
        // 削除対象のセットIDを特定
        $setsToDelete = array_diff($existingSetIds, $processedSetIds);
        
        // 削除対象のセットがある場合は削除（関連する器具は外部キー制約でCASCADEされる）
        if (!empty($setsToDelete)) {
            $placeholders = implode(',', array_fill(0, count($setsToDelete), '?'));
            $stmt = $db->prepare("DELETE FROM template_sets WHERE set_id IN ($placeholders)");
            $stmt->execute($setsToDelete);
        }
        
        $db->commit();
        
        // 成功メッセージの設定
        $_SESSION['success_messages'][] = 'テンプレートが正常に更新されました。';
        
        // 練習記録画面へのリダイレクトが要求された場合
        if ($redirect_to_practice) {
            header('Location: ../practice.php?action=new&template_id=' . $template_id);
            exit;
        }
        
        // 通常のリダイレクト
        header('Location: ../template_detail.php?id=' . $template_id);
        exit;
        
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('テンプレート更新エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'テンプレートの更新中にエラーが発生しました。']);
        exit;
    }
}

/**
 * テンプレートを削除する
 */
function deleteTemplate() {
    $template_id = (int)($_POST['template_id'] ?? 0);
    
    if ($template_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '無効なテンプレートIDです。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // テンプレートの所有権確認
        $stmt = $db->prepare("SELECT user_id FROM practice_templates WHERE template_id = ?");
        $stmt->execute([$template_id]);
        $template = $stmt->fetch();
        
        if (!$template || $template['user_id'] != $_SESSION['user_id']) {
            header('Content-Type: application/json');
            echo json_encode(['error' => '指定されたテンプレートが見つからないか、削除権限がありません。']);
            exit;
        }
        
        // トランザクション開始
        $db->beginTransaction();
        
        // テンプレートセットの器具関連を削除
        $stmt = $db->prepare("
            DELETE tse FROM template_set_equipment tse
            INNER JOIN template_sets ts ON tse.set_id = ts.set_id
            WHERE ts.template_id = ?
        ");
        $stmt->execute([$template_id]);
        
        // テンプレートセットを削除
        $stmt = $db->prepare("DELETE FROM template_sets WHERE template_id = ?");
        $stmt->execute([$template_id]);
        
        // テンプレートを削除
        $stmt = $db->prepare("DELETE FROM practice_templates WHERE template_id = ?");
        $stmt->execute([$template_id]);
        
        $db->commit();
        
        // 成功レスポンス
        $_SESSION['success_messages'][] = 'テンプレートが正常に削除されました。';
        
        // Ajaxリクエストかどうかで応答を分ける
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'テンプレートが正常に削除されました。'
            ]);
        } else {
            // 通常のリダイレクト
            header('Location: ../templates.php');
        }
        exit;
        
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('テンプレート削除エラー: ' . $e->getMessage());
        
        // Ajaxリクエストかどうかで応答を分ける
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'テンプレートの削除中にエラーが発生しました。']);
        } else {
            $_SESSION['error_messages'][] = 'テンプレートの削除中にエラーが発生しました。';
            header('Location: ../templates.php');
        }
        exit;
    }
}

/**
 * お気に入り状態を切り替える
 */
function toggleFavorite() {
    $template_id = (int)($_POST['template_id'] ?? 0);
    
    if ($template_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '無効なテンプレートIDです。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // テンプレートの所有権確認と現在のお気に入り状態を取得
        $stmt = $db->prepare("
            SELECT user_id, is_favorite 
            FROM practice_templates 
            WHERE template_id = ?
        ");
        $stmt->execute([$template_id]);
        $template = $stmt->fetch();
        
        if (!$template || $template['user_id'] != $_SESSION['user_id']) {
            header('Content-Type: application/json');
            echo json_encode(['error' => '指定されたテンプレートが見つからないか、操作権限がありません。']);
            exit;
        }
        
        // お気に入り状態を反転
        $newState = $template['is_favorite'] ? 0 : 1;
        
        // 状態を更新
        $stmt = $db->prepare("
            UPDATE practice_templates 
            SET is_favorite = ?, updated_at = NOW()
            WHERE template_id = ?
        ");
        $stmt->execute([$newState, $template_id]);
        
        // 成功レスポンス
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'is_favorite' => (bool)$newState,
            'message' => $newState ? 'お気に入りに追加しました。' : 'お気に入りから削除しました。'
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log('お気に入り切替エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'お気に入り状態の更新中にエラーが発生しました。']);
        exit;
    }
}

/**
 * 公開状態を切り替える
 */
function togglePublic() {
    $template_id = (int)($_POST['template_id'] ?? 0);
    
    if ($template_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '無効なテンプレートIDです。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // テンプレートの所有権確認と現在の公開状態を取得
        $stmt = $db->prepare("
            SELECT user_id, is_public 
            FROM practice_templates 
            WHERE template_id = ?
        ");
        $stmt->execute([$template_id]);
        $template = $stmt->fetch();
        
        if (!$template || $template['user_id'] != $_SESSION['user_id']) {
            header('Content-Type: application/json');
            echo json_encode(['error' => '指定されたテンプレートが見つからないか、操作権限がありません。']);
            exit;
        }
        
        // 公開状態を反転
        $newState = $template['is_public'] ? 0 : 1;
        
        // 状態を更新
        $stmt = $db->prepare("
            UPDATE practice_templates 
            SET is_public = ?, updated_at = NOW()
            WHERE template_id = ?
        ");
        $stmt->execute([$newState, $template_id]);
        
        // 成功レスポンス
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'is_public' => (bool)$newState,
            'message' => $newState ? 'テンプレートを公開しました。' : 'テンプレートを非公開にしました。'
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log('公開状態切替エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => '公開状態の更新中にエラーが発生しました。']);
        exit;
    }
}

/**
 * 既存の練習からテンプレートを作成
 */
function createTemplateFromPractice() {
    $session_id = (int)($_POST['session_id'] ?? 0);
    $template_name = trim($_POST['template_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if ($session_id <= 0 || empty($template_name)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '練習IDとテンプレート名は必須です。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // 練習セッションの所有権と情報を取得
        $stmt = $db->prepare("
            SELECT * FROM practice_sessions
            WHERE session_id = ? AND user_id = ?
        ");
        $stmt->execute([$session_id, $_SESSION['user_id']]);
        $practice = $stmt->fetch();
        
        if (!$practice) {
            header('Content-Type: application/json');
            echo json_encode(['error' => '指定された練習が見つからないか、アクセス権がありません。']);
            exit;
        }
        
        $db->beginTransaction();
        
        // テンプレート基本情報の保存
        $stmt = $db->prepare("
            INSERT INTO practice_templates (user_id, template_name, description, total_distance, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $template_name,
            $description,
            $practice['total_distance']
        ]);
        
        $template_id = $db->lastInsertId();
        
        // 練習セットを取得
        $stmt = $db->prepare("
            SELECT * FROM practice_sets
            WHERE session_id = ?
            ORDER BY set_id
        ");
        $stmt->execute([$session_id]);
        $sets = $stmt->fetchAll();
        
        // テンプレートセットの保存
        $setStmt = $db->prepare("
            INSERT INTO template_sets (
                template_id, type_id, stroke_type, distance, repetitions, 
                cycle, total_distance, notes, order_index
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // 各セットの器具情報を取得する関数
        function getSetEquipment($db, $set_id) {
            $stmt = $db->prepare("
                SELECT equipment_id FROM set_equipment
                WHERE set_id = ?
            ");
            $stmt->execute([$set_id]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // 器具登録用のステートメント
        $equipmentStmt = $db->prepare("
            INSERT INTO template_set_equipment (set_id, equipment_id)
            VALUES (?, ?)
        ");
        
        // 各セットを処理
        foreach ($sets as $index => $set) {
            $setStmt->execute([
                $template_id,
                $set['type_id'],
                $set['stroke_type'],
                $set['distance'],
                $set['repetitions'],
                $set['cycle'],
                $set['total_distance'],
                $set['notes'],
                $index
            ]);
            
            // セットに紐づく器具の登録
            $new_set_id = $db->lastInsertId();
            
            // 元のセットの器具情報を取得
            $equipment_ids = getSetEquipment($db, $set['set_id']);
            
            // 器具情報があれば保存
            foreach ($equipment_ids as $equipment_id) {
                $equipmentStmt->execute([$new_set_id, $equipment_id]);
            }
        }
        
        $db->commit();
        
        // 成功レスポンス
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => '練習からテンプレートが正常に作成されました。',
                'template_id' => $template_id
            ]);
        } else {
            $_SESSION['success_messages'][] = '練習からテンプレートが正常に作成されました。';
            header('Location: ../template_detail.php?id=' . $template_id);
        }
        exit;
        
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('練習からテンプレート作成エラー: ' . $e->getMessage());
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'テンプレートの作成中にエラーが発生しました。']);
        } else {
            $_SESSION['error_messages'][] = 'テンプレートの作成中にエラーが発生しました。';
            header('Location: ../practice.php?action=view&id=' . $session_id);
        }
        exit;
    }
}

/**
 * テンプレート一覧を取得する
 */
function getTemplateList() {
    try {
        $db = getDbConnection();
        
        // URLパラメータからフィルター条件を取得
        $category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
        $favoritesOnly = isset($_GET['favorites']) && $_GET['favorites'] == 1;
        
        // テンプレート一覧を取得するSQLの基本部分
        $sql = "
            SELECT t.*, tc.category_name 
            FROM practice_templates t
            LEFT JOIN template_categories tc ON t.category = tc.category_id
            WHERE t.user_id = ?
        ";
        
        $params = [$_SESSION['user_id']];
        
        // カテゴリフィルター
        if ($category > 0) {
            $sql .= " AND t.category = ?";
            $params[] = $category;
        }
        
        // お気に入りフィルター
        if ($favoritesOnly) {
            $sql .= " AND t.is_favorite = 1";
        }
        
        $sql .= " ORDER BY t.is_favorite DESC, t.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $templates = $stmt->fetchAll();
        
        // 成功レスポンス
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'templates' => $templates
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log('テンプレート一覧取得エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'テンプレート一覧の取得中にエラーが発生しました。']);
        exit;
    }
}