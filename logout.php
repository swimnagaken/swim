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

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-QMTKRPLHDD"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-QMTKRPLHDD');
</script>