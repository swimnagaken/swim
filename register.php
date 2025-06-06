<?php
// 設定ファイルとライブラリの読み込み
require_once 'config/config.php';
require_once 'config/auth.php';

// ページタイトル
$page_title = "新規登録";

// ログイン済みの場合はダッシュボードにリダイレクト
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// フォーム送信の処理
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error_message = '無効なリクエストです。ページを再読み込みしてください。';
    } else {
        // 入力値の検証
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
       // 基本的な検証
       if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
        $error_message = 'すべての項目を入力してください。';
    } elseif (!isset($_POST['agree_terms'])) {
        $error_message = '利用規約およびプライバシーポリシーおよび特定商取引法に基づく表記に同意してください。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = '有効なメールアドレスを入力してください。';
    } elseif (strlen($password) < 8) {
        $error_message = 'パスワードは8文字以上で入力してください。';
    } elseif ($password !== $password_confirm) {
        $error_message = 'パスワードが一致しません。';
    } else {
            // ユーザー登録を実行
            $result = registerUser($username, $email, $password);
            
            if ($result['success']) {
                $success_message = $result['message'];
                // 登録成功時はフォーム入力値をクリア
                unset($username, $email, $password, $password_confirm);
            } else {
                $error_message = $result['message'];
            }
        }
    }
}

// ヘッダーの読み込み
include 'includes/header.php';
?>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
    <h1 class="text-2xl font-bold mb-6 text-center">SwimLogに新規登録</h1>
    
    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo h($error_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo h($success_message); ?>
            <p class="mt-2">
                <a href="login.php" class="text-green-700 font-semibold underline">ログインページへ</a>
            </p>
        </div>
    <?php else: ?>
        <form method="POST" action="register.php">
            <!-- CSRFトークン -->
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            
            <div class="mb-4">
                <label for="username" class="block text-gray-700 mb-2">ユーザー名</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                    value="<?php echo isset($username) ? h($username) : ''; ?>"
                >
            </div>
            
            <div class="mb-4">
                <label for="email" class="block text-gray-700 mb-2">メールアドレス</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                    value="<?php echo isset($email) ? h($email) : ''; ?>"
                >
            </div>
            
            <div class="mb-4">
                <label for="password" class="block text-gray-700 mb-2">パスワード</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                    minlength="8"
                >
                <p class="text-gray-500 text-sm mt-1">8文字以上で入力してください</p>
            </div>
            
            <div class="mb-6">
                <label for="password_confirm" class="block text-gray-700 mb-2">パスワード（確認）</label>
                <input 
                    type="password" 
                    id="password_confirm" 
                    name="password_confirm" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                    minlength="8"
                >
            </div>
            
            <!-- ここに追加 ↓ -->
            
<div class="mb-6">
    <label class="flex items-start">
        <input 
            type="checkbox" 
            name="agree_terms" 
            class="mt-1 mr-2" 
            required
        >
        <span class="text-gray-700 text-sm">
            <a href="terms.php" target="_blank" class="text-blue-600 hover:text-blue-800 underline">利用規約</a>、
            <a href="privacy.php" target="_blank" class="text-blue-600 hover:text-blue-800 underline">プライバシーポリシー</a>および
            <a href="commerce.php" target="_blank" class="text-blue-600 hover:text-blue-800 underline">特定商取引法に基づく表記</a>に同意する
        </span>
    </label>
</div>
            <!-- ここまで追加 ↑ -->
            
            <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                登録する
            </button>
        </form>
    <?php endif; ?>
    
    <div class="mt-6 text-center">
        <p class="text-gray-600">
            すでにアカウントをお持ちの方は 
            <a href="login.php" class="text-blue-600 hover:text-blue-800">ログイン</a>
        </p>
    </div>
</div>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-QMTKRPLHDD"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-QMTKRPLHDD');
</script>