<?php
// 大会記録の検索・フィルタリング用の関数
// includes/competition_search.php

/**
 * 大会記録データを検索・フィルタリングする
 *
 * @param PDO $db データベース接続
 * @param int $userId ユーザーID
 * @param array $filters フィルター条件（連想配列）
 * @param int $page ページ番号（ページネーション用）
 * @param int $limit 1ページあたりの件数
 * @return array 検索結果と総件数
 */
function searchCompetitions($db, $userId, $filters = [], $page = 1, $limit = 10) {
    // 基本的なSQL
    $sql = "
        SELECT c.*, COUNT(r.result_id) as result_count
        FROM competitions c
        LEFT JOIN race_results r ON c.competition_id = r.competition_id
        WHERE c.user_id = :user_id
    ";
    
    // パラメータ配列
    $params = [':user_id' => $userId];
    
    // フィルター条件の追加
    // 日付範囲
    if (!empty($filters['date_from'])) {
        $sql .= " AND c.competition_date >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND c.competition_date <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    // 大会名でのキーワード検索
    if (!empty($filters['name_keyword'])) {
        $nameKeyword = '%' . $filters['name_keyword'] . '%';
        $sql .= " AND c.competition_name LIKE :name_keyword";
        $params[':name_keyword'] = $nameKeyword;
    }
    
    // 場所でのキーワード検索
    if (!empty($filters['location_keyword'])) {
        $locationKeyword = '%' . $filters['location_keyword'] . '%';
        $sql .= " AND c.location LIKE :location_keyword";
        $params[':location_keyword'] = $locationKeyword;
    }
    
    // グループ化
    $sql .= " GROUP BY c.competition_id";
    
    // 結果数によるフィルタリング
    if (isset($filters['has_results']) && $filters['has_results'] === 'yes') {
        $sql .= " HAVING COUNT(r.result_id) > 0";
    } elseif (isset($filters['has_results']) && $filters['has_results'] === 'no') {
        $sql .= " HAVING COUNT(r.result_id) = 0";
    }
    
    // 並び順
    $orderBy = "c.competition_date DESC"; // デフォルト
    if (!empty($filters['sort_by'])) {
        switch ($filters['sort_by']) {
            case 'date_asc':
                $orderBy = "c.competition_date ASC";
                break;
            case 'date_desc':
                $orderBy = "c.competition_date DESC";
                break;
            case 'name_asc':
                $orderBy = "c.competition_name ASC";
                break;
            case 'name_desc':
                $orderBy = "c.competition_name DESC";
                break;
            case 'results_asc':
                $orderBy = "result_count ASC";
                break;
            case 'results_desc':
                $orderBy = "result_count DESC";
                break;
        }
    }
    
    $sql .= " ORDER BY " . $orderBy;
    
    // 件数カウント用のSQL（ページネーション用）
    $countSql = "
        SELECT COUNT(*) as count
        FROM (
            $sql
        ) as subquery
    ";
    
    // 総件数の取得
    $stmt = $db->prepare($countSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $totalCount = $stmt->fetch()['count'];
    
    // ページネーション用の制限
    $offset = ($page - 1) * $limit;
    $sql .= " LIMIT :limit OFFSET :offset";
    
    // メインクエリの実行
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $competitions = $stmt->fetchAll();
    
    return [
        'competitions' => $competitions,
        'total_count' => $totalCount,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($totalCount / $limit)
    ];
}

/**
 * 自己ベスト記録データを検索・フィルタリングする
 *
 * @param PDO $db データベース接続
 * @param int $userId ユーザーID
 * @param array $filters フィルター条件（連想配列）
 * @return array 検索結果
 */
function searchPersonalBests($db, $userId, $filters = []) {
    // 基本的なSQL
    $sql = "
        SELECT r.*, c.competition_name, c.competition_date
        FROM race_results r
        JOIN competitions c ON r.competition_id = c.competition_id
        WHERE c.user_id = :user_id AND r.is_personal_best = 1
    ";
    
    // パラメータ配列
    $params = [':user_id' => $userId];
    
    // フィルター条件の追加
    // 距離範囲
    if (!empty($filters['distance'])) {
        $sql .= " AND r.distance = :distance";
        $params[':distance'] = (int)$filters['distance'];
    }
    
    // 泳法
    if (!empty($filters['stroke_type'])) {
        $sql .= " AND r.stroke_type = :stroke_type";
        $params[':stroke_type'] = $filters['stroke_type'];
    }
    
    // 日付範囲
    if (!empty($filters['date_from'])) {
        $sql .= " AND c.competition_date >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND c.competition_date <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    // イベント名でのキーワード検索
    if (!empty($filters['event_keyword'])) {
        $eventKeyword = '%' . $filters['event_keyword'] . '%';
        $sql .= " AND r.event_name LIKE :event_keyword";
        $params[':event_keyword'] = $eventKeyword;
    }
    
    // 並び順
    $sql .= " ORDER BY r.stroke_type, r.distance, r.time_minutes, r.time_seconds, r.time_milliseconds";
    
    // クエリの実行
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * 大会記録用のフィルターオプションを取得する
 *
 * @param PDO $db データベース接続
 * @param int $userId ユーザーID
 * @return array フィルターオプション
 */
function getCompetitionFilterOptions($db, $userId) {
    $options = [];
    
    // 並び替えオプション
    $options['sort_options'] = [
        'date_desc' => '日付 (新しい順)',
        'date_asc' => '日付 (古い順)',
        'name_asc' => '大会名 (昇順)',
        'name_desc' => '大会名 (降順)',
        'results_desc' => '記録数 (多い順)',
        'results_asc' => '記録数 (少ない順)'
    ];
    
    // 泳法の種類
    $options['stroke_types'] = [
        'freestyle' => '自由形',
        'backstroke' => '背泳ぎ',
        'breaststroke' => '平泳ぎ',
        'butterfly' => 'バタフライ',
        'im' => '個人メドレー',
        'other' => 'その他'
    ];
    
    // 距離オプション
    $options['distances'] = [25, 50, 100, 200, 400, 800, 1500];
    
    // 結果の有無
    $options['has_results'] = [
        'all' => 'すべて',
        'yes' => '記録あり',
        'no' => '記録なし'
    ];
    
    return $options;
}