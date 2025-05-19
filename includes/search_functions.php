<?php
// 検索・フィルタリング用の関数ファイル
// include/search_functions.php

/**
 * 練習記録データを検索・フィルタリングする
 *
 * @param PDO $db データベース接続
 * @param int $userId ユーザーID
 * @param array $filters フィルター条件（連想配列）
 * @param int $page ページ番号（ページネーション用）
 * @param int $limit 1ページあたりの件数
 * @return array 検索結果と総件数
 */
function searchPractices($db, $userId, $filters = [], $page = 1, $limit = 10) {
    // 基本的なSQL
    $sql = "
        SELECT p.*, pl.pool_name
        FROM practice_sessions p
        LEFT JOIN pools pl ON p.pool_id = pl.pool_id
        WHERE p.user_id = :user_id
    ";
    
    // パラメータ配列
    $params = [':user_id' => $userId];
    
    // フィルター条件の追加
    // 日付範囲
    if (!empty($filters['date_from'])) {
        $sql .= " AND p.practice_date >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND p.practice_date <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    // 距離範囲
    if (!empty($filters['distance_min'])) {
        $sql .= " AND p.total_distance >= :distance_min";
        $params[':distance_min'] = $filters['distance_min'];
    }
    
    if (!empty($filters['distance_max'])) {
        $sql .= " AND p.total_distance <= :distance_max";
        $params[':distance_max'] = $filters['distance_max'];
    }
    
    // プール
    if (!empty($filters['pool_id'])) {
        $sql .= " AND p.pool_id = :pool_id";
        $params[':pool_id'] = $filters['pool_id'];
    }
    
    // 泳法で検索（セット詳細を結合して検索）
    if (!empty($filters['stroke_type'])) {
        $sql .= " AND EXISTS (
            SELECT 1 FROM practice_sets ps 
            WHERE ps.session_id = p.session_id 
            AND ps.stroke_type = :stroke_type
        )";
        $params[':stroke_type'] = $filters['stroke_type'];
    }
    
    // キーワード検索（課題、反省メモなど）
    if (!empty($filters['keyword'])) {
        $keyword = '%' . $filters['keyword'] . '%';
        $sql .= " AND (
            p.challenge LIKE :keyword 
            OR p.reflection LIKE :keyword 
            OR EXISTS (
                SELECT 1 FROM practice_sets ps 
                WHERE ps.session_id = p.session_id 
                AND ps.notes LIKE :keyword
            )
        )";
        $params[':keyword'] = $keyword;
    }
    
    // 並び順
    $orderBy = "p.practice_date DESC"; // デフォルト
    if (!empty($filters['sort_by'])) {
        switch ($filters['sort_by']) {
            case 'date_asc':
                $orderBy = "p.practice_date ASC";
                break;
            case 'date_desc':
                $orderBy = "p.practice_date DESC";
                break;
            case 'distance_asc':
                $orderBy = "p.total_distance ASC";
                break;
            case 'distance_desc':
                $orderBy = "p.total_distance DESC";
                break;
        }
    }
    
    $sql .= " ORDER BY " . $orderBy;
    
    // 件数カウント用のSQL（ページネーション用）
    $countSql = str_replace("SELECT p.*, pl.pool_name", "SELECT COUNT(*) as count", $sql);
    $countSql = preg_replace('/ORDER BY.*$/i', '', $countSql);
    
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
    $practices = $stmt->fetchAll();
    
    return [
        'practices' => $practices,
        'total_count' => $totalCount,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($totalCount / $limit)
    ];
}

/**
 * 練習セットの詳細データを取得する
 *
 * @param PDO $db データベース接続
 * @param int $sessionId 練習セッションID
 * @return array セット詳細データの配列
 */
function getPracticeSets($db, $sessionId) {
    $stmt = $db->prepare("
        SELECT ps.*, wt.type_name
        FROM practice_sets ps
        LEFT JOIN workout_types wt ON ps.type_id = wt.type_id
        WHERE ps.session_id = ?
        ORDER BY ps.set_id ASC
    ");
    $stmt->execute([$sessionId]);
    return $stmt->fetchAll();
}

/**
 * 検索結果を絞り込むためのフィルターオプションを取得する
 *
 * @param PDO $db データベース接続
 * @param int $userId ユーザーID
 * @return array フィルターオプション
 */
function getFilterOptions($db, $userId) {
    $options = [];
    
    // プール一覧
    $stmt = $db->prepare("
        SELECT pool_id, pool_name, is_favorite
        FROM pools
        WHERE user_id = ?
        ORDER BY is_favorite DESC, pool_name ASC
    ");
    $stmt->execute([$userId]);
    $options['pools'] = $stmt->fetchAll();
    
    // 泳法の種類
    $options['stroke_types'] = [
        'freestyle' => '自由形',
        'backstroke' => '背泳ぎ',
        'breaststroke' => '平泳ぎ',
        'butterfly' => 'バタフライ',
        'im' => '個人メドレー',
        'kick' => 'キック',
        'pull' => 'プル',
        'drill' => 'ドリル',
        'other' => 'その他'
    ];
    
    // 並び替えオプション
    $options['sort_options'] = [
        'date_desc' => '日付 (新しい順)',
        'date_asc' => '日付 (古い順)',
        'distance_desc' => '距離 (多い順)',
        'distance_asc' => '距離 (少ない順)'
    ];
    
    // 最小/最大距離の参考値を取得
    $stmt = $db->prepare("
        SELECT MIN(total_distance) as min_distance, MAX(total_distance) as max_distance
        FROM practice_sessions
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $distanceRange = $stmt->fetch();
    
    $options['distance_range'] = [
        'min' => $distanceRange['min_distance'] ?? 0,
        'max' => $distanceRange['max_distance'] ?? 10000
    ];
    
    return $options;
}