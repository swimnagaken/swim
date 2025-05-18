<?php
// 設定ファイルとライブラリの読み込み
require_once 'config/config.php';
require_once 'config/auth.php';

// ログアウト処理
$result = logoutUser();

// ログアウトメッセージをセッションに保存
$_SESSION['logout_message'] = $result['message'];

// ログインページにリダイレクト
header('Location: login.php');
exit;
?>