<?php
// 認証関連の関数を定義
require_once __DIR__ . '/config.php';

// ユーザー登録
function registerUser($username, $email, $password) {
    $db = getDbConnection();
    
    try {
        // メールアドレスの重複チェック
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'message' => 'このメールアドレスは既に登録されています'
            ];
        }
        
        // ユーザー名の重複チェック
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'message' => 'このユーザー名は既に使用されています'
            ];
        }
        
        // パスワードのハッシュ化
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // ユーザーの登録
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$username, $email, $passwordHash]);
        
        return [
            'success' => true,
            'message' => '登録が完了しました。ログインしてください。',
            'user_id' => $db->lastInsertId()
        ];
    } catch (PDOException $e) {
        error_log("ユーザー登録エラー: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '登録中にエラーが発生しました。しばらくしてからお試しください。'
        ];
    }
}

// ユーザーログイン
function loginUser($email, $password) {
    $db = getDbConnection();
    
    try {
        // ユーザーの検索
        $stmt = $db->prepare("SELECT user_id, username, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // ユーザーが見つからないか、パスワードが一致しない場合
        if (!$user || !password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'メールアドレスまたはパスワードが正しくありません'
            ];
        }
        
        // セッションにユーザー情報を保存
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        
        return [
            'success' => true,
            'message' => 'ログインしました',
            'user_id' => $user['user_id'],
            'username' => $user['username']
        ];
    } catch (PDOException $e) {
        error_log("ログインエラー: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'ログイン中にエラーが発生しました。しばらくしてからお試しください。'
        ];
    }
}

// ユーザーログアウト
function logoutUser() {
    // セッション変数を全て削除
    $_SESSION = [];
    
    // セッションCookieを削除
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // セッションの破棄
    session_destroy();
    
    return [
        'success' => true,
        'message' => 'ログアウトしました'
    ];
}

// ユーザー情報の取得
function getUserInfo($userId) {
    $db = getDbConnection();
    
    try {
        $stmt = $db->prepare("
            SELECT user_id, username, email, created_at
            FROM users
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'ユーザーが見つかりません'
            ];
        }
        
        return [
            'success' => true,
            'user' => $user
        ];
    } catch (PDOException $e) {
        error_log("ユーザー情報取得エラー: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'ユーザー情報の取得中にエラーが発生しました'
        ];
    }
}
?>