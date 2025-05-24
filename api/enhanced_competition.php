<?php
// api/enhanced_competition.php - 簡易版大会記録API
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
    $action = $_POST['action'] ?? 'add_result';
    
    switch ($action) {
        case 'add_result':
            addRaceResult();
            break;
        default:
            header('Content-Type: application/json');
            echo json_encode(['error' => '無効なアクションです']);
            exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_progress_chart':
            getProgressChart();
            break;
        default:
            header('Content-Type: application/json');
            echo json_encode(['message' => 'APIは準備中です。']);
            exit;
    }
}

/**
 * 競技結果を追加する
 */
function addRaceResult() {
    // 入力値の取得
    $competition_id = (int)($_POST['competition_id'] ?? 0);
    $event_name = trim($_POST['event_name'] ?? '');
    $stroke_type = $_POST['stroke_type'] ?? '';
    $distance_meters = (int)($_POST['distance_meters'] ?? 0);
    $pool_type = $_POST['pool_type'] ?? 'SCM';
    $is_official = isset($_POST['is_official']) ? (bool)$_POST['is_official'] : true;
    $record_type = $_POST['record_type'] ?? 'competition';
    $rank = !empty($_POST['rank']) ? (int)$_POST['rank'] : null;
    $notes = trim($_POST['notes'] ?? '');
    
    // タイム関連
    $final_time = $_POST['final_time'] ?? '';
    $reaction_time = $_POST['reaction_time'] ?? '';
    $lap_times_data = $_POST['lap_times'] ?? [];
    $lap_input_method = $_POST['lap_input_method'] ?? 'split';
    
    // 入力検証
    if ($competition_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '無効な大会IDです。']);
        exit;
    }
    
    if (empty($event_name)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '種目名は必須です。']);
        exit;
    }
    
    if (empty($stroke_type) || !in_array($stroke_type, ['butterfly', 'backstroke', 'breaststroke', 'freestyle', 'medley'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '有効な泳法を選択してください。']);
        exit;
    }
    
    if ($distance_meters <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '距離は正の値で入力してください。']);
        exit;
    }
    
    if (!in_array($pool_type, ['SCM', 'LCM'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '有効なプール種別を選択してください。']);
        exit;
    }
    
    if (empty($final_time)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '最終タイムは必須です。']);
        exit;
    }
    
    // タイム形式の検証
    if (!preg_match('/^(\d{1,2}:)?\d{1,2}\.\d{2}$/', $final_time)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'タイム形式が正しくありません（例: 23.45 または 1:23.45）']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // 大会の所有権確認
        $stmt = $db->prepare("SELECT user_id FROM competitions WHERE competition_id = ?");
        $stmt->execute([$competition_id]);
        $competition = $stmt->fetch();
        
        if (!$competition || $competition['user_id'] != $_SESSION['user_id']) {
            header('Content-Type: application/json');
            echo json_encode(['error' => '指定された大会が見つからないか、所有権がありません。']);
            exit;
        }
        
        // トランザクション開始
        $db->beginTransaction();
        
        // 最終タイムを1/100秒単位に変換
        $total_time_centiseconds = parseTimeStringToCentiseconds($final_time);
        $reaction_time_centiseconds = !empty($reaction_time) ? parseTimeStringToCentiseconds($reaction_time) : null;
        
        // 自己ベストかどうかチェック
        $is_personal_best = checkPersonalBest($_SESSION['user_id'], $stroke_type, $distance_meters, $pool_type, $total_time_centiseconds, $db);
        
        // 競技結果を挿入
        $stmt = $db->prepare("
            INSERT INTO race_results 
            (competition_id, event_name, stroke_type_new, distance_meters, pool_type, 
             total_time_centiseconds, is_official, record_type, reaction_time_centiseconds,
             is_personal_best, rank, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $competition_id, $event_name, $stroke_type, $distance_meters, $pool_type,
            $total_time_centiseconds, $is_official, $record_type, $reaction_time_centiseconds,
            $is_personal_best, $rank, $notes
        ]);
        
        $result_id = $db->lastInsertId();
        
        // ラップタイムの処理
        if (!empty($lap_times_data) && is_array($lap_times_data)) {
            $processed_laps = processLapTimes($lap_times_data, $lap_input_method, $distance_meters, $pool_type);
            
            foreach ($processed_laps as $lap) {
                $stmt = $db->prepare("
                    INSERT INTO lap_times 
                    (result_id, lap_number, distance_meters, split_time_centiseconds, lap_time_centiseconds, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $result_id, $lap['lap_number'], $lap['distance'], 
                    $lap['split_time'], $lap['lap_time']
                ]);
            }
        }
        
        // 自己ベスト更新の場合、履歴を記録
        if ($is_personal_best) {
            recordPersonalBestHistory($_SESSION['user_id'], $stroke_type, $distance_meters, $pool_type, $result_id, $total_time_centiseconds, $db);
        }
        
        $db->commit();
        
        // 成功レスポンス
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => '競技結果が正常に保存されました。',
            'result_id' => $result_id,
            'is_personal_best' => $is_personal_best,
            'formatted_time' => formatCentisecondsToTime($total_time_centiseconds)
        ]);
        exit;
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('競技結果保存エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => '競技結果の保存中にエラーが発生しました。']);
        exit;
    }
}

/**
 * 進歩グラフ用データを取得
 */
function getProgressChart() {
    $stroke_type = $_GET['stroke_type'] ?? '';
    $distance_meters = (int)($_GET['distance_meters'] ?? 0);
    $pool_type = $_GET['pool_type'] ?? 'SCM';
    
    if (empty($stroke_type) || $distance_meters <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '泳法と距離を指定してください。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT r.total_time_centiseconds, r.is_personal_best, r.rank,
                   c.competition_name, c.competition_date, r.created_at,
                   r.is_official, r.record_type
            FROM race_results r
            JOIN competitions c ON r.competition_id = c.competition_id
            WHERE c.user_id = ? AND r.stroke_type_new = ? AND r.distance_meters = ? AND r.pool_type = ?
            ORDER BY c.competition_date, r.created_at
        ");
        $stmt->execute([$_SESSION['user_id'], $stroke_type, $distance_meters, $pool_type]);
        $results = $stmt->fetchAll();
        
        // グラフ用データに変換
        $chart_data = [];
        foreach ($results as $result) {
            $chart_data[] = [
                'date' => $result['competition_date'],
                'time_centiseconds' => $result['total_time_centiseconds'],
                'formatted_time' => formatCentisecondsToTime($result['total_time_centiseconds']),
                'is_personal_best' => (bool)$result['is_personal_best'],
                'is_official' => (bool)$result['is_official'],
                'competition_name' => $result['competition_name'],
                'rank' => $result['rank'],
                'record_type' => $result['record_type']
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'chart_data' => $chart_data,
            'event_info' => [
                'stroke_type' => $stroke_type,
                'distance_meters' => $distance_meters,
                'pool_type' => $pool_type
            ]
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log('進歩グラフデータ取得エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'データの取得中にエラーが発生しました。']);
        exit;
    }
}

/**
 * 自己ベストかどうかをチェック
 */
function checkPersonalBest($user_id, $stroke_type, $distance_meters, $pool_type, $new_time_centiseconds, $db) {
    $stmt = $db->prepare("
        SELECT MIN(r.total_time_centiseconds) as best_time
        FROM race_results r
        JOIN competitions c ON r.competition_id = c.competition_id
        WHERE c.user_id = ? AND r.stroke_type_new = ? AND r.distance_meters = ? AND r.pool_type = ?
    ");
    $stmt->execute([$user_id, $stroke_type, $distance_meters, $pool_type]);
    $result = $stmt->fetch();
    
    return !$result || !$result['best_time'] || $new_time_centiseconds < $result['best_time'];
}

/**
 * 自己ベスト履歴を記録
 */
function recordPersonalBestHistory($user_id, $stroke_type, $distance_meters, $pool_type, $result_id, $new_time_centiseconds, $db) {
    // 前回の自己ベストを取得
    $stmt = $db->prepare("
        SELECT MIN(r.total_time_centiseconds) as previous_best
        FROM race_results r
        JOIN competitions c ON r.competition_id = c.competition_id
        WHERE c.user_id = ? AND r.stroke_type_new = ? AND r.distance_meters = ? AND r.pool_type = ?
        AND r.result_id != ?
    ");
    $stmt->execute([$user_id, $stroke_type, $distance_meters, $pool_type, $result_id]);
    $result = $stmt->fetch();
    
    $previous_best = $result ? $result['previous_best'] : null;
    $improvement = $previous_best ? ($previous_best - $new_time_centiseconds) : $new_time_centiseconds;
    
    // 履歴を記録
    $stmt = $db->prepare("
        INSERT INTO personal_best_history 
        (user_id, stroke_type, distance_meters, pool_type, result_id, 
         previous_time_centiseconds, new_time_centiseconds, improvement_centiseconds, record_date, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())
    ");
    $stmt->execute([
        $user_id, $stroke_type, $distance_meters, $pool_type, $result_id,
        $previous_best, $new_time_centiseconds, $improvement
    ]);
}

/**
 * ラップタイムを処理する
 */
function processLapTimes($lap_times_data, $input_method, $distance_meters, $pool_type) {
    $lap_distance = ($pool_type === 'SCM') ? 25 : 50; // ラップ距離
    $expected_laps = $distance_meters / $lap_distance;
    
    $processed_laps = [];
    
    if ($input_method === 'split') {
        // スプリットタイム入力（各ラップのタイム）
        $cumulative_time = 0;
        
        for ($i = 0; $i < count($lap_times_data) && $i < $expected_laps; $i++) {
            if (empty($lap_times_data[$i])) continue;
            
            $lap_time_cs = parseTimeStringToCentiseconds($lap_times_data[$i]);
            $cumulative_time += $lap_time_cs;
            
            $processed_laps[] = [
                'lap_number' => $i + 1,
                'distance' => ($i + 1) * $lap_distance,
                'split_time' => $cumulative_time,
                'lap_time' => $lap_time_cs
            ];
        }
    } else {
        // 累積タイム入力（その時点での合計タイム）
        for ($i = 0; $i < count($lap_times_data) && $i < $expected_laps; $i++) {
            if (empty($lap_times_data[$i])) continue;
            
            $split_time_cs = parseTimeStringToCentiseconds($lap_times_data[$i]);
            $lap_time_cs = $i === 0 ? $split_time_cs : ($split_time_cs - parseTimeStringToCentiseconds($lap_times_data[$i - 1]));
            
            $processed_laps[] = [
                'lap_number' => $i + 1,
                'distance' => ($i + 1) * $lap_distance,
                'split_time' => $split_time_cs,
                'lap_time' => $lap_time_cs
            ];
        }
    }
    
    return $processed_laps;
}

/**
 * 時間文字列を1/100秒に変換
 */
function parseTimeStringToCentiseconds($time_str) {
    $time_str = trim($time_str);
    
    // 分:秒.1/100秒 または 秒.1/100秒の形式を解析
    if (strpos($time_str, ':') !== false) {
        // 分:秒.1/100秒の形式
        list($minutes, $seconds_part) = explode(':', $time_str);
        list($seconds, $centiseconds) = explode('.', $seconds_part);
        
        return (int)$minutes * 6000 + (int)$seconds * 100 + (int)$centiseconds;
    } else {
        // 秒.1/100秒の形式
        list($seconds, $centiseconds) = explode('.', $time_str);
        return (int)$seconds * 100 + (int)$centiseconds;
    }
}

/**
 * 1/100秒を時間文字列に変換
 */
function formatCentisecondsToTime($centiseconds) {
    $minutes = floor($centiseconds / 6000);
    $seconds = floor(($centiseconds % 6000) / 100);
    $cs = $centiseconds % 100;
    
    if ($minutes > 0) {
        return sprintf('%d:%02d.%02d', $minutes, $seconds, $cs);
    } else {
        return sprintf('%d.%02d', $seconds, $cs);
    }
}
?>