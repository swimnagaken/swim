<?php
// 出力バッファリングを開始（ヘッダー送信エラーを防止）
ob_start();

// デバッグモード（開発中は true に設定、本番環境では false に設定）
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    // デバッグ時のみエラー表示
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// データベース接続情報
define('DB_HOST', 'mysql322.phy.lolipop.lan');
define('DB_NAME', 'LAA1515450-swim');
define('DB_USER', 'LAA1515450'); // 本番環境では変更
define('DB_PASS', 'Aiueo123');    // 本番環境では変更

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// セッション設定
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// セッションの開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// データベース接続関数
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                echo "データベース接続エラー: " . $e->getMessage();
            }
            error_log("データベース接続エラー: " . $e->getMessage());
            exit("技術的な問題が発生しました。しばらくしてからお試しください。");
        }
    }
    
    return $pdo;
}

// CSRFトークン生成
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRFトークン検証
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// ログイン状態のチェック
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// ログイン必須ページでの認証チェック
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['login_required_message'] = 'このページにアクセスするにはログインが必要です。';
        header('Location: login.php');
        exit;
    }
}

// XSS対策 - HTMLエスケープ
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// ログメッセージの追加
function addSuccessMessage($message) {
    if (!isset($_SESSION['success_messages'])) {
        $_SESSION['success_messages'] = [];
    }
    $_SESSION['success_messages'][] = $message;
}

function addErrorMessage($message) {
    if (!isset($_SESSION['error_messages'])) {
        $_SESSION['error_messages'] = [];
    }
    $_SESSION['error_messages'][] = $message;
}

// テスト用関数 - 正常動作確認用
function testDatabaseConnection() {
    try {
        $db = getDbConnection();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>