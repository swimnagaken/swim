<?php
// 設定ファイルの読み込み
require_once '../config/config.php';

// ログイン確認
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => '認証エラー: ログインが必要です']);
    exit;
}

// リクエストに応じた処理
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // パラメータの取得
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $period = isset($_GET['period']) ? $_GET['period'] : '3m';
    
    // 期間に応じて日付範囲を設定
    $startDate = null;
    $endDate = date('Y-m-d');
    
    switch ($period) {
        case '1m':
            $startDate = date('Y-m-d', strtotime('-1 month'));
            break;
        case '3m':
            $startDate = date('Y-m-d', strtotime('-3 months'));
            break;
        case '6m':
            $startDate = date('Y-m-d', strtotime('-6 months'));
            break;
        case '1y':
            $startDate = date('Y-m-d', strtotime('-1 year'));
            break;
        case 'all':
            $startDate = '2000-01-01'; // 十分過去
            break;
        default:
            $startDate = date('Y-m-d', strtotime('-3 months'));
    }
    
    try {
        $db = getDbConnection();
        $userId = $_SESSION['user_id'];
        
        switch ($action) {
            case 'summary':
                // 期間のサマリー情報を取得
                getSummary($db, $userId, $startDate, $endDate);
                break;
                
            case 'trend':
                // トレンドデータを取得
                getTrendData($db, $userId, $startDate, $endDate);
                break;
                
            case 'strokes':
                // 泳法別の距離データを取得
                getStrokeDistribution($db, $userId, $startDate, $endDate);
                break;
                
            case 'goals':
                // 目標達成状況を取得
                getGoalAchievement($db, $userId, $startDate, $endDate);
                break;
                
            case 'heatmap':
                // ヒートマップカレンダーのデータを取得
                getHeatmapData($db, $userId, $startDate, $endDate);
                break;
                
            case 'best_sessions':
                // 最長距離の練習セッションを取得
                getBestSessions($db, $userId, $startDate, $endDate);
                break;
                
            case 'stroke_details':
                // 泳法別の詳細データを取得
                getStrokeDetails($db, $userId, $startDate, $endDate);
                break;
                
            default:
                // すべてのデータを取得
                $data = [
                    'summary' => getSummaryData($db, $userId, $startDate, $endDate),
                    'trend' => getTrendData($db, $userId, $startDate, $endDate, false),
                    'strokes' => getStrokeDistribution($db, $userId, $startDate, $endDate, false),
                    'goals' => getGoalAchievement($db, $userId, $startDate, $endDate, false),
                    'heatmap' => getHeatmapData($db, $userId, $startDate, $endDate, false),
                    'best_sessions' => getBestSessions($db, $userId, $startDate, $endDate, false),
                    'stroke_details' => getStrokeDetails($db, $userId, $startDate, $endDate, false)
                ];
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'data' => $data]);
                break;
        }
    } catch (PDOException $e) {
        error_log('分析データ取得エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'データの取得中にエラーが発生しました。']);
        exit;
    }
} else {
    // サポートされていないリクエストメソッド
    header('Content-Type: application/json');
    echo json_encode(['error' => 'サポートされていないリクエストメソッドです。']);
    exit;
}

// 以下、各機能のデータ取得関数を実装
function getSummary($db, $userId, $startDate, $endDate, $returnOnly = false) {
    // サマリーデータの取得
    $stmt = $db->prepare("
        SELECT 
            SUM(total_distance) as total_distance,
            COUNT(*) as session_count,
            CASE WHEN COUNT(*) > 0 THEN SUM(total_distance) / COUNT(*) ELSE 0 END as avg_distance
        FROM practice_sessions
        WHERE user_id = ? AND practice_date BETWEEN ? AND ?
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $summary = $stmt->fetch();
    
    // 目標達成率の取得
    $stmt = $db->prepare("
        SELECT 
            SUM(mg.distance_goal) as total_goal,
            SUM(LEAST(COALESCE(session_total.total_distance, 0), mg.distance_goal)) as achieved_distance
        FROM monthly_goals mg
        LEFT JOIN (
            SELECT 
                YEAR(practice_date) as year,
                MONTH(practice_date) as month,
                SUM(total_distance) as total_distance
            FROM practice_sessions
            WHERE user_id = ? AND practice_date BETWEEN ? AND ?
            GROUP BY YEAR(practice_date), MONTH(practice_date)
        ) session_total ON mg.year = session_total.year AND mg.month = session_total.month
        WHERE mg.user_id = ? AND CONCAT(mg.year, '-', LPAD(mg.month, 2, '0'), '-01') BETWEEN ? AND ?
    ");
    $stmt->execute([$userId, $startDate, $endDate, $userId, $startDate, $endDate]);
    $goalData = $stmt->fetch();
    
    $goalAchievement = 0;
    if ($goalData && $goalData['total_goal'] > 0) {
        $goalAchievement = round(($goalData['achieved_distance'] / $goalData['total_goal']) * 100);
    }
    
    $data = [
        'total_distance' => (int)($summary['total_distance'] ?? 0),
        'session_count' => (int)($summary['session_count'] ?? 0),
        'avg_distance' => round($summary['avg_distance'] ?? 0),
        'goal_achievement' => $goalAchievement
    ];
    
    if ($returnOnly) {
        return $data;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// ここに他の関数を追加（getTrendData, getStrokeDistribution等）
// 各関数は同様のパターンで実装できます