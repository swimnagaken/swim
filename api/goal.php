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
    // アクションの取得
    $action = isset($_POST['action']) ? $_POST['action'] : 'create';
    
    switch ($action) {
        case 'create':
        case 'update':
            // 目標の作成または更新
            createOrUpdateGoal();
            break;
        
        case 'delete':
            // 目標の削除
            deleteGoal();
            break;
        
        default:
            // 不明なアクション
            header('Content-Type: application/json');
            echo json_encode(['error' => '不明なアクションです。']);
            exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 目標情報の取得
    if (isset($_GET['year']) && isset($_GET['month'])) {
        // 特定の年月の目標を取得
        getGoal($_GET['year'], $_GET['month']);
    } else {
        // 目標一覧を取得
        getGoalList();
    }
} else {
    // サポートされていないリクエストメソッド
    header('Content-Type: application/json');
    echo json_encode(['error' => 'サポートされていないリクエストメソッドです。']);
    exit;
}

/**
 * 目標を作成または更新する
 */
function createOrUpdateGoal() {
    // 入力値の検証
    $year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');
    $month = isset($_POST['month']) ? (int)$_POST['month'] : date('n');
    $distance_goal = (int)($_POST['distance_goal'] ?? 0);
    $sessions_goal = (int)($_POST['sessions_goal'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    // 基本的な検証
    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '無効な年月です。']);
        exit;
    }
    
    if ($distance_goal <= 0 && $sessions_goal <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '距離目標または練習回数目標のいずれかを設定してください。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // 既存の目標があるか確認
        $stmt = $db->prepare("
            SELECT goal_id FROM monthly_goals 
            WHERE user_id = ? AND year = ? AND month = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $year, $month]);
        $existingGoal = $stmt->fetch();
        
        if ($existingGoal) {
            // 目標を更新
            $stmt = $db->prepare("
                UPDATE monthly_goals 
                SET distance_goal = ?, sessions_goal = ?, notes = ?, updated_at = NOW()
                WHERE goal_id = ?
            ");
            $stmt->execute([$distance_goal, $sessions_goal, $notes, $existingGoal['goal_id']]);
            
            $_SESSION['success_messages'][] = '目標が正常に更新されました。';
            
            // 更新後の目標情報を取得して返す
            $stmt = $db->prepare("
                SELECT * FROM monthly_goals
                WHERE goal_id = ?
            ");
            $stmt->execute([$existingGoal['goal_id']]);
            $goal = $stmt->fetch();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => '目標が正常に更新されました。',
                'goal' => $goal
            ]);
            exit;
        } else {
            // 新しい目標を作成
            $stmt = $db->prepare("
                INSERT INTO monthly_goals (user_id, year, month, distance_goal, sessions_goal, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $year, $month, $distance_goal, $sessions_goal, $notes]);
            
            $goal_id = $db->lastInsertId();
            
            $_SESSION['success_messages'][] = '目標が正常に設定されました。';
            
            // 作成した目標情報を取得して返す
            $stmt = $db->prepare("
                SELECT * FROM monthly_goals
                WHERE goal_id = ?
            ");
            $stmt->execute([$goal_id]);
            $goal = $stmt->fetch();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => '目標が正常に設定されました。',
                'goal' => $goal
            ]);
            exit;
        }
    } catch (PDOException $e) {
        error_log('目標設定エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => '目標の設定中にエラーが発生しました。']);
        exit;
    }
}

/**
 * 目標を削除する
 */
function deleteGoal() {
    // 入力値の検証
    $goal_id = (int)($_POST['goal_id'] ?? 0);
    
    if ($goal_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '無効な目標IDです。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // 目標の所有権確認
        $stmt = $db->prepare("SELECT user_id FROM monthly_goals WHERE goal_id = ?");
        $stmt->execute([$goal_id]);
        $goal = $stmt->fetch();
        
        if (!$goal || $goal['user_id'] != $_SESSION['user_id']) {
            header('Content-Type: application/json');
            echo json_encode(['error' => '指定された目標が見つからないか、削除権限がありません。']);
            exit;
        }
        
        // 目標を削除
        $stmt = $db->prepare("DELETE FROM monthly_goals WHERE goal_id = ?");
        $stmt->execute([$goal_id]);
        
        $_SESSION['success_messages'][] = '目標が正常に削除されました。';
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => '目標が正常に削除されました。'
        ]);
        exit;
    } catch (PDOException $e) {
        error_log('目標削除エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => '目標の削除中にエラーが発生しました。']);
        exit;
    }
}

/**
 * 特定の年月の目標を取得する
 */
function getGoal($year, $month) {
    $year = (int)$year;
    $month = (int)$month;
    
    // 基本的な検証
    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '無効な年月です。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // 目標情報を取得
        $stmt = $db->prepare("
            SELECT * FROM monthly_goals
            WHERE user_id = ? AND year = ? AND month = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $year, $month]);
        $goal = $stmt->fetch();
        
        // 統計情報を取得
        $stats = [
            'current_distance' => 0,
            'current_sessions' => 0,
            'distance_percentage' => 0,
            'sessions_percentage' => 0
        ];
        
        if ($goal) {
            // 月の初日と最終日
            $startDate = sprintf('%04d-%02d-01', $year, $month);
            $lastDay = date('t', strtotime($startDate));
            $endDate = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
            
            // 月間の統計を取得
            $stmt = $db->prepare("
                SELECT SUM(total_distance) as total_distance, COUNT(*) as session_count
                FROM practice_sessions
                WHERE user_id = ? AND practice_date BETWEEN ? AND ?
            ");
            $stmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
            $monthStats = $stmt->fetch();
            
            if ($monthStats) {
                $stats['current_distance'] = (int)($monthStats['total_distance'] ?? 0);
                $stats['current_sessions'] = (int)($monthStats['session_count'] ?? 0);
                
                if ($goal['distance_goal'] > 0) {
                    $stats['distance_percentage'] = min(100, round(($stats['current_distance'] / $goal['distance_goal']) * 100));
                }
                
                if ($goal['sessions_goal'] > 0) {
                    $stats['sessions_percentage'] = min(100, round(($stats['current_sessions'] / $goal['sessions_goal']) * 100));
                }
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'goal' => $goal ?: null,
            'stats' => $stats
        ]);
        exit;
    } catch (PDOException $e) {
        error_log('目標取得エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => '目標の取得中にエラーが発生しました。']);
        exit;
    }
}

/**
 * 目標一覧を取得する
 */
function getGoalList() {
    try {
        $db = getDbConnection();
        
        // 月間目標一覧を取得
        $stmt = $db->prepare("
            SELECT * FROM monthly_goals
            WHERE user_id = ?
            ORDER BY year DESC, month DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $goals = $stmt->fetchAll();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'goals' => $goals
        ]);
        exit;
    } catch (PDOException $e) {
        error_log('目標一覧取得エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => '目標一覧の取得中にエラーが発生しました。']);
        exit;
    }
}}