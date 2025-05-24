switch ($action) {
        case 'add_competition':
            addCompetition(); // 従来の大会のみ追加（後方互換性）
            break;
        case 'add_unified_competition':
            addUnifiedCompetition(); // 新しい統合版
            break;
        case 'add_result':
            addRaceResult();
            break;
        case 'delete_result':
            deleteRaceResult();
            break;
        default:
            header('Content-Type: application/json');
            echo json_encode(['error' => '無効なアクションです']);
            exit;
    }<?php
// api/competition.php - 完全版大会記録API（上書き用）
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
        case 'add_competition':
            addCompetition();
            break;
        case 'add_result':
            addRaceResult();
            break;
        case 'delete_result':
            deleteRaceResult();
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
        case 'get_lap_times':
            getLapTimes();
            break;
        case 'get_events':
            getEventConfigurations();
            break;
        default:
            header('Content-Type: application/json');
            echo json_encode(['message' => 'APIは準備中です。']);
            exit;
    }
}

/**
 * 新しい大会を追加する（従来版・後方互換性のため残す）
 */
function addCompetition() {
    $competition_name = trim($_POST['competition_name'] ?? '');
    $competition_date = $_POST['competition_date'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // 必須フィールドの検証
    if (!$competition_name || !$competition_date) {
        $_SESSION['error_messages'][] = '大会名と開催日は必須です。';
        header('Location: ../competition.php?action=new');
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // 大会記録の挿入
        $stmt = $db->prepare("
            INSERT INTO competitions (user_id, competition_name, competition_date, location, notes, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $competition_name, $competition_date, $location, $notes]);
        
        $competition_id = $db->lastInsertId();
        
        // 成功メッセージの設定
        $_SESSION['success_messages'][] = '大会記録が正常に追加されました。';
        
        // 詳細ページにリダイレクト
        header('Location: ../competition.php?action=view&id=' . $competition_id);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_messages'][] = '大会記録の追加中にエラーが発生しました。';
        error_log('大会記録エラー: ' . $e->getMessage());
        header('Location: ../competition.php?action=new');
        exit;
    }
}

/**
 * 統合版大会記録を追加する（大会＋競技結果を同時処理）
 */
function addUnifiedCompetition() {
    // 大会情報の取得
    $competition_name = trim($_POST['competition_name'] ?? '');
    $competition_date = $_POST['competition_date'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $competition_notes = trim($_POST['competition_notes'] ?? '');
    
    // メイン競技結果の取得
    $event_name = trim($_POST['event_name'] ?? '');
    $stroke_type = $_POST['stroke_type'] ?? '';
    $distance_meters = (int)($_POST['distance_meters'] ?? 0);
    $pool_type = $_POST['pool_type'] ?? 'SCM';
    $record_type = $_POST['record_type'] ?? 'official';
    $rank = !empty($_POST['rank']) ? (int)$_POST['rank'] : null;
    $final_time = $_POST['final_time'] ?? '';
    $reaction_time = $_POST['reaction_time'] ?? '';
    $lap_times_data = $_POST['lap_times'] ?? [];
    $lap_input_method = $_POST['lap_input_method'] ?? 'split';
    $race_notes = trim($_POST['race_notes'] ?? '');
    
    // 追加競技結果の取得
    $additional_results = $_POST['additional_results'] ?? [];
    
    // 必須フィールドの検証
    if (!$competition_name || !$competition_date) {
        $_SESSION['error_messages'][] = '大会名と開催日は必須です。';
        header('Location: ../competition.php?action=new');
        exit;
    }
    
    if (empty($event_name) || empty($stroke_type) || $distance_meters <= 0 || empty($pool_type) || empty($final_time)) {
        $_SESSION['error_messages'][] = '競技結果の必須項目が不足しています。';
        header('Location: ../competition.php?action=new');
        exit;
    }
    
    // 距離の妥当性チェック
    if (!validateDistanceForStrokeAndPool($stroke_type, $distance_meters, $pool_type)) {
        $_SESSION['error_messages'][] = '選択した泳法・プール種別に対して無効な距離です。';
        header('Location: ../competition.php?action=new');
        exit;
    }
    
    // タイム形式の検証
    if (!preg_match('/^(\d{1,2}:)?\d{1,2}\.\d{2}$/', $final_time)) {
        $_SESSION['error_messages'][] = 'タイム形式が正しくありません。';
        header('Location: ../competition.php?action=new');
        exit;
    }
    
    try {
        $db = getDbConnection();
        $db->beginTransaction();
        
        // 1. 大会記録の挿入
        $stmt = $db->prepare("
            INSERT INTO competitions (user_id, competition_name, competition_date, location, notes, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $competition_name, $competition_date, $location, $competition_notes]);
        $competition_id = $db->lastInsertId();
        
        // 2. メイン競技結果の処理
        $main_result_id = addSingleRaceResult($db, $competition_id, [
            'event_name' => $event_name,
            'stroke_type' => $stroke_type,
            'distance_meters' => $distance_meters,
            'pool_type' => $pool_type,
            'record_type' => $record_type,
            'rank' => $rank,
            'final_time' => $final_time,
            'reaction_time' => $reaction_time,
            'lap_times_data' => $lap_times_data,
            'lap_input_method' => $lap_input_method,
            'race_notes' => $race_notes
        ]);
        
        // 3. 追加競技結果の処理
        $additional_result_ids = [];
        if (!empty($additional_results) && is_array($additional_results)) {
            foreach ($additional_results as $additional_result) {
                if (!empty($additional_result['event_name']) && 
                    !empty($additional_result['stroke_type']) && 
                    !empty($additional_result['final_time'])) {
                    
                    $additional_result_id = addSingleRaceResult($db, $competition_id, $additional_result);
                    $additional_result_ids[] = $additional_result_id;
                }
            }
        }
        
        $db->commit();
        
        // 成功メッセージの設定
        $total_results = 1 + count($additional_result_ids);
        $_SESSION['success_messages'][] = "大会記録と{$total_results}件の競技結果が正常に保存されました。";
        
        // 詳細ページにリダイレクト
        header('Location: ../competition.php?action=view&id=' . $competition_id);
        exit;
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('統合大会記録保存エラー: ' . $e->getMessage());
        $_SESSION['error_messages'][] = '大会記録の保存中にエラーが発生しました。';
        header('Location: ../competition.php?action=new');
        exit;
    }
}

/**
 * 単一の競技結果を追加する（統合版で使用）
 */
function addSingleRaceResult($db, $competition_id, $result_data) {
    $event_name = $result_data['event_name'];
    $stroke_type = $result_data['stroke_type'];
    $distance_meters = (int)($result_data['distance_meters'] ?? 0);
    $pool_type = $result_data['pool_type'] ?? 'SCM';
    $record_type = $result_data['record_type'] ?? 'official';
    $rank = !empty($result_data['rank']) ? (int)$result_data['rank'] : null;
    $final_time = $result_data['final_time'];
    $reaction_time = $result_data['reaction_time'] ?? '';
    $lap_times_data = $result_data['lap_times_data'] ?? [];
    $lap_input_method = $result_data['lap_input_method'] ?? 'split';
    $race_notes = $result_data['race_notes'] ?? '';
    
    // 記録種別に基づく公式記録判定
    $is_official = in_array($record_type, ['official', 'relay_split']);
    
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
        $is_personal_best, $rank, $race_notes
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
    
    return $result_id;
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
    
    // 距離の妥当性チェック
    if (!validateDistanceForStrokeAndPool($stroke_type, $distance_meters, $pool_type)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '選択した泳法・プール種別に対して無効な距離です。']);
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
        
        // 個人ベスト履歴を削除
        $stmt = $db->prepare("DELETE FROM personal_best_history WHERE result_id = ?");
        $stmt->execute([$result_id]);
        
        // 結果を削除
        $stmt = $db->prepare("DELETE FROM race_results WHERE result_id = ?");
        $stmt->execute([$result_id]);
        
        $db->commit();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => '競技結果が正常に削除されました。',
            'competition_id' => $result['competition_id']
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
?>タイムデータを取得
 */
function getLapTimes() {
    $result_id = (int)($_GET['result_id'] ?? 0);
    
    if ($result_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '無効な結果IDです。']);
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // 結果の所有権確認とラップタイム取得
        $stmt = $db->prepare("
            SELECT r.*, c.user_id,
                   CONCAT(r.distance_meters, 'm', 
                          CASE r.stroke_type_new 
                               WHEN 'butterfly' THEN 'バタフライ'
                               WHEN 'backstroke' THEN '背泳ぎ'
                               WHEN 'breaststroke' THEN '平泳ぎ'
                               WHEN 'freestyle' THEN '自由形'
                               WHEN 'medley' THEN '個人メドレー'
                               ELSE r.stroke_type_new
                          END,
                          '(', r.pool_type, ')') as event_display
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
        
        // ラップタイムを取得
        $stmt = $db->prepare("
            SELECT * FROM lap_times
            WHERE result_id = ?
            ORDER BY lap_number
        ");
        $stmt->execute([$result_id]);
        $lap_times = $stmt->fetchAll();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'lap_times' => $lap_times,
            'result_info' => [
                'event_display' => $result['event_display'],
                'final_time' => formatCentisecondsToTime($result['total_time_centiseconds'])
            ]
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log('ラップタイム取得エラー: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'ラップタイムの取得中にエラーが発生しました。']);
        exit;
    }
}

/**
 * 種目設定を取得
 */
function getEventConfigurations() {
    $configurations = [
        // バタフライ
        ['stroke_type' => 'butterfly', 'pool_type' => 'SCM', 'distance_meters' => 50, 'display_name' => '50mバタフライ(短水路)'],
        ['stroke_type' => 'butterfly', 'pool_type' => 'SCM', 'distance_meters' => 100, 'display_name' => '100mバタフライ(短水路)'],
        ['stroke_type' => 'butterfly', 'pool_type' => 'SCM', 'distance_meters' => 200, 'display_name' => '200mバタフライ(短水路)'],
        ['stroke_type' => 'butterfly', 'pool_type' => 'LCM', 'distance_meters' => 50, 'display_name' => '50mバタフライ(長水路)'],
        ['stroke_type' => 'butterfly', 'pool_type' => 'LCM', 'distance_meters' => 100, 'display_name' => '100mバタフライ(長水路)'],
        ['stroke_type' => 'butterfly', 'pool_type' => 'LCM', 'distance_meters' => 200, 'display_name' => '200mバタフライ(長水路)'],
        
        // 背泳ぎ
        ['stroke_type' => 'backstroke', 'pool_type' => 'SCM', 'distance_meters' => 50, 'display_name' => '50m背泳ぎ(短水路)'],
        ['stroke_type' => 'backstroke', 'pool_type' => 'SCM', 'distance_meters' => 100, 'display_name' => '100m背泳ぎ(短水路)'],
        ['stroke_type' => 'backstroke', 'pool_type' => 'SCM', 'distance_meters' => 200, 'display_name' => '200m背泳ぎ(短水路)'],
        ['stroke_type' => 'backstroke', 'pool_type' => 'LCM', 'distance_meters' => 50, 'display_name' => '50m背泳ぎ(長水路)'],
        ['stroke_type' => 'backstroke', 'pool_type' => 'LCM', 'distance_meters' => 100, 'display_name' => '100m背泳ぎ(長水路)'],
        ['stroke_type' => 'backstroke', 'pool_type' => 'LCM', 'distance_meters' => 200, 'display_name' => '200m背泳ぎ(長水路)'],
        
        // 平泳ぎ
        ['stroke_type' => 'breaststroke', 'pool_type' => 'SCM', 'distance_meters' => 50, 'display_name' => '50m平泳ぎ(短水路)'],
        ['stroke_type' => 'breaststroke', 'pool_type' => 'SCM', 'distance_meters' => 100, 'display_name' => '100m平泳ぎ(短水路)'],
        ['stroke_type' => 'breaststroke', 'pool_type' => 'SCM', 'distance_meters' => 200, 'display_name' => '200m平泳ぎ(短水路)'],
        ['stroke_type' => 'breaststroke', 'pool_type' => 'LCM', 'distance_meters' => 50, 'display_name' => '50m平泳ぎ(長水路)'],
        ['stroke_type' => 'breaststroke', 'pool_type' => 'LCM', 'distance_meters' => 100, 'display_name' => '100m平泳ぎ(長水路)'],
        ['stroke_type' => 'breaststroke', 'pool_type' => 'LCM', 'distance_meters' => 200, 'display_name' => '200m平泳ぎ(長水路)'],
        
        // 自由形
        ['stroke_type' => 'freestyle', 'pool_type' => 'SCM', 'distance_meters' => 50, 'display_name' => '50m自由形(短水路)'],
        ['stroke_type' => 'freestyle', 'pool_type' => 'SCM', 'distance_meters' => 100, 'display_name' => '100m自由形(短水路)'],
        ['stroke_type' => 'freestyle', 'pool_type' => 'SCM', 'distance_meters' => 200, 'display_name' => '200m自由形(短水路)'],
        ['stroke_type' => 'freestyle', 'pool_type' => 'SCM', 'distance_meters' => 400, 'display_name' => '400m自由形(短水路)'],
        ['stroke_type' => 'freestyle', 'pool_type' => 'SCM', 'distance_meters' => 800, 'display_name' => '800m自由形(短水路)'],
        ['stroke_type' => 'freestyle', 'pool_type' => 'SCM', 'distance_meters' => 1500, 'display_name' => '1500m自由形(短水路)'],
        ['stroke_type' => 'freestyle', 'pool_type' => 'LCM', 'distance_meters' => 50, 'display_name' => '50m自由形(長水路)'],
        ['stroke_type' => 'freestyle', 'pool_type' => 'LCM', 'distance_meters' => 100, 'display_name' => '100m自由形(長水路)'],
        ['stroke_type' => 'freestyle', 'pool_type' => 'LCM', 'distance_meters' => 200, 'display_name' => '200m自由形(長水路)'],
        ['stroke_type' => 'freestyle', 'pool_type' => 'LCM', 'distance_meters' => 400, 'display_name' => '400m自由形(長水路)'],
        ['stroke_type' => 'freestyle', 'pool_type' => 'LCM', 'distance_meters' => 800, 'display_name' => '800m自由形(長水路)'],
        ['stroke_type' => 'freestyle', 'pool_type' => 'LCM', 'distance_meters' => 1500, 'display_name' => '1500m自由形(長水路)'],
        
        // 個人メドレー
        ['stroke_type' => 'medley', 'pool_type' => 'SCM', 'distance_meters' => 100, 'display_name' => '100m個人メドレー(短水路)'],
        ['stroke_type' => 'medley', 'pool_type' => 'SCM', 'distance_meters' => 200, 'display_name' => '200m個人メドレー(短水路)'],
        ['stroke_type' => 'medley', 'pool_type' => 'SCM', 'distance_meters' => 400, 'display_name' => '400m個人メドレー(短水路)'],
        ['stroke_type' => 'medley', 'pool_type' => 'LCM', 'distance_meters' => 200, 'display_name' => '200m個人メドレー(長水路)'],
        ['stroke_type' => 'medley', 'pool_type' => 'LCM', 'distance_meters' => 400, 'display_name' => '400m個人メドレー(長水路)']
    ];
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'events' => $configurations
    ]);
    exit;
}

/**
 * 距離の妥当性をチェック
 */
function validateDistanceForStrokeAndPool($stroke_type, $distance, $pool_type) {
    $valid_distances = [
        'butterfly' => ['SCM' => [50, 100, 200], 'LCM' => [50, 100, 200]],
        'backstroke' => ['SCM' => [50, 100, 200], 'LCM' => [50, 100, 200]],
        'breaststroke' => ['SCM' => [50, 100, 200], 'LCM' => [50, 100, 200]],
        'freestyle' => ['SCM' => [50, 100, 200, 400, 800, 1500], 'LCM' => [50, 100, 200, 400, 800, 1500]],
        'medley' => ['SCM' => [100, 200, 400], 'LCM' => [200, 400]] // 100m個人メドレーは短水路のみ
    ];
    
    return isset($valid_distances[$stroke_type][$pool_type]) && 
           in_array($distance, $valid_distances[$stroke_type][$pool_type]);
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
 * ラップ