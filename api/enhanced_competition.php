<?php
// api/enhanced_competition.php - 改良版大会記録API
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
        case 'update_result':
            updateRaceResult();
            break;
        case 'delete_result':
            deleteRaceResult();
            break;
        case 'get_event_config':
            getEventConfiguration();
            break;
        default:
            header('Content-Type: application/json');
            echo json_encode(['error' => '無効なアクションです']);
            exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_events':
            getAvailableEvents();
            break;
        case 'get_personal_bests':
            getPersonalBests();
            break;
        case 'get_progress_chart':
            getProgressChart();
            break;
        default:
            header('Content-Type: application/json');
            echo json_encode(['message' => 'APIは準備中です。']);
            exit;
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'サポートされていないリクエストメソッドです。']);
    exit;
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
    $lap_input_method = $_POST['lap_input_method'] ?? 'split'; // 'split' or 'cumulative'
    
    // 入力検証
    $validation_result = validateRaceResultInput($competition_id, $event_name, $stroke_type, $distance_meters, $pool_type, $final_time);
    if (!$validation_result['valid']) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $validation_result['message']]);
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
                    (result_id, lap_number, distance_meters, split_time_centiseconds, lap_time_centiseconds)
                    VALUES (?, ?, ?, ?, ?)
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
 * 利用可能な種目を取得
 */
function getAvailableEvents() {
    try {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT stroke_type, distance_meters, pool_type, display_name
            FROM event_configurations
            WHERE is_valid = TRUE
            ORDER BY stroke_type, distance_meters, pool_type
        ");
        $stmt->execute();
        $events = $stmt->fetchAll();
        
        // 泳法別にグループ化
        $grouped_events = [];
        foreach ($events as $event) {
            $stroke = $event['stroke_type'];
            if (!isset($grouped_events[$stroke])) {
                $grouped_events[$stroke] = [];
            }
            $grouped_events[$stroke][] = $event;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'events' => $events,
            'grouped_events' => $grouped_events
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log('種目取得エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => '種目の取得中にエラーが発生しました。']);
        exit;
    }
}

/**
 * 自己ベスト記録を取得
 */
function getPersonalBests() {
    $stroke_type = $_GET['stroke_type'] ?? '';
    $distance_meters = (int)($_GET['distance_meters'] ?? 0);
    $pool_type = $_GET['pool_type'] ?? '';
    
    try {
        $db = getDbConnection();
        
        $sql = "
            SELECT r.*, c.competition_name, c.competition_date
            FROM race_results r
            JOIN competitions c ON r.competition_id = c.competition_id
            WHERE c.user_id = ? AND r.is_personal_best = TRUE
        ";
        $params = [$_SESSION['user_id']];
        
        // フィルター条件を追加
        if (!empty($stroke_type)) {
            $sql .= " AND r.stroke_type_new = ?";
            $params[] = $stroke_type;
        }
        
        if ($distance_meters > 0) {
            $sql .= " AND r.distance_meters = ?";
            $params[] = $distance_meters;
        }
        
        if (!empty($pool_type)) {
            $sql .= " AND r.pool_type = ?";
            $params[] = $pool_type;
        }
        
        $sql .= " ORDER BY r.stroke_type_new, r.distance_meters, r.pool_type, r.total_time_centiseconds";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        // タイムをフォーマット
        foreach ($results as &$result) {
            $result['formatted_time'] = formatCentisecondsToTime($result['total_time_centiseconds']);
            $result['formatted_reaction_time'] = $result['reaction_time_centiseconds'] ? 
                formatCentisecondsToTime($result['reaction_time_centiseconds']) : null;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'personal_bests' => $results
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log('自己ベスト取得エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => '自己ベストの取得中にエラーが発生しました。']);
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
 * 入力検証を行う
 */
function validateRaceResultInput($competition_id, $event_name, $stroke_type, $distance_meters, $pool_type, $final_time) {
    if ($competition_id <= 0) {
        return ['valid' => false, 'message' => '無効な大会IDです。'];
    }
    
    if (empty($event_name)) {
        return ['valid' => false, 'message' => '種目名は必須です。'];
    }
    
    if (empty($stroke_type) || !in_array($stroke_type, ['butterfly', 'backstroke', 'breaststroke', 'freestyle', 'medley'])) {
        return ['valid' => false, 'message' => '有効な泳法を選択してください。'];
    }
    
    if ($distance_meters <= 0) {
        return ['valid' => false, 'message' => '距離は正の値で入力してください。'];
    }
    
    if (!in_array($pool_type, ['SCM', 'LCM'])) {
        return ['valid' => false, 'message' => '有効なプール種別を選択してください。'];
    }
    
    if (empty($final_time)) {
        return ['valid' => false, 'message' => '最終タイムは必須です。'];
    }
    
    // タイム形式の検証
    if (!preg_match('/^(\d{1,2}:)?\d{1,2}\.\d{2}$/', $final_time)) {
        return ['valid' => false, 'message' => 'タイム形式が正しくありません。（例: 23.45 または 1:23.45）'];
    }
    
    return ['valid' => true, 'message' => 'OK'];
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
         previous_time_centiseconds, new_time_centiseconds, improvement_centiseconds, record_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
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

/**
 * 競技結果を更新する
 */
function updateRaceResult() {
    // 更新機能の実装（必要に応じて）
    header('Content-Type: application/json');
    echo json_encode(['message' => '更新機能は準備中です。']);
    exit;
}

/**
 * 競技結果を削除する
 */
function deleteRaceResult() {
    $result_id = (int)($_POST['result_id'] ?? 0);
    
    if ($result_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '無効な結果IDです。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // 結果と大会の所有権確認
        $stmt = $db->prepare("
            SELECT r.result_id, c.user_id, c.competition_id 
            FROM race_results r
            JOIN competitions c ON r.competition_id = c.competition_id
            WHERE r.result_id = ?
        ");
        $stmt->execute([$result_id]);
        $result = $stmt->fetch();
        
        if (!$result || $result['user_id'] != $_SESSION['user_id']) {
            header('Content-Type: application/json');
            echo json_encode(['error' => '指定された結果が見つからないか、所有権がありません。']);
            exit;
        }
        
        // トランザクション開始
        $db->beginTransaction();
        
        // ラップタイムを削除
        $stmt = $db->prepare("DELETE FROM lap_times WHERE result_id = ?");
        $stmt->execute([$result_id]);
        
        // 自己ベスト履歴を削除
        $stmt = $db->prepare("DELETE FROM personal_best_history WHERE result_id = ?");
        $stmt->execute([$result_id]);
        
        // 結果を削除
        $stmt = $db->prepare("DELETE FROM race_results WHERE result_id = ?");
        $stmt->execute([$result_id]);
        
        $db->commit();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => '競技結果が正常に削除されました。'
        ]);
        exit;
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('競技結果削除エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => '競技結果の削除中にエラーが発生しました。']);
        exit;
    }
}

/**
 * 種目設定を取得する
 */
function getEventConfiguration() {
    $stroke_type = $_POST['stroke_type'] ?? '';
    $pool_type = $_POST['pool_type'] ?? '';
    
    try {
        $db = getDbConnection();
        
        $sql = "SELECT * FROM event_configurations WHERE is_valid = TRUE";
        $params = [];
        
        if (!empty($stroke_type)) {
            $sql .= " AND stroke_type = ?";
            $params[] = $stroke_type;
        }
        
        if (!empty($pool_type)) {
            $sql .= " AND pool_type = ?";
            $params[] = $pool_type;
        }
        
        $sql .= " ORDER BY distance_meters";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'events' => $events
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log('種目設定取得エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => '種目設定の取得中にエラーが発生しました。']);
        exit;
    }
}