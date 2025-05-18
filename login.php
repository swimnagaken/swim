<?php
// 設定ファイルとライブラリの読み込み
require_once 'config/config.php';
require_once 'config/auth.php';

// ページタイトル
$page_title = "ログイン";

// ログイン済みの場合はダッシュボードにリダイレクト
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// フォーム送信の処理
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error_message = '無効なリクエストです。ページを再読み込みしてください。';
    } else {
        // 入力値の検証
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // 基本的な検証
        if (empty($email) || empty($password)) {
            $error_message = 'メールアドレスとパスワードを入力してください。';
        } else {
            // ログイン処理を実行
            $result = loginUser($email, $password);
            
            if ($result['success']) {
                // ログイン成功時はダッシュボードにリダイレクト
                header('Location: dashboard.php');
                exit;
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
    <h1 class="text-2xl font-bold mb-6 text-center">ログイン</h1>
    
    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo h($error_message); ?>
        </div>
    <?php endif; ?>
    
    <?php
    // ログアウトメッセージの表示
    if (isset($_SESSION['logout_message'])) {
        echo '<div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">';
        echo h($_SESSION['logout_message']);
        echo '</div>';
        // メッセージを表示したら削除
        unset($_SESSION['logout_message']);
    }
    
    // ログイン要求メッセージの表示
    if (isset($_SESSION['login_required_message'])) {
        echo '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">';
        echo h($_SESSION['login_required_message']);
        echo '</div>';
        // メッセージを表示したら削除
        unset($_SESSION['login_required_message']);
    }
    ?>
    
    <form method="POST" action="login.php">
        <!-- CSRFトークン -->
        <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
        
        <div class="mb-4">
            <label for="email" class="block text-gray-700 mb-2">メールアドレス</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
                value="<?php echo isset($_POST['email']) ? h($_POST['email']) : ''; ?>"
            >
        </div>
        
        <div class="mb-6">
            <label for="password" class="block text-gray-700 mb-2">パスワード</label>
            <input 
                type="password" 
                id="password" 
                name="password" 
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
            >
        </div>
        
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center">
                <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-blue-600">
                <label for="remember" class="ml-2 text-gray-700">ログイン状態を保存</label>
            </div>
            <a href="#" class="text-blue-600 hover:text-blue-800">パスワードを忘れた？</a>
        </div>
        
        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
            ログイン
        </button>
    </form>
    
    <div class="mt-6 text-center">
        <p class="text-gray-600">
            アカウントをお持ちでない方は 
            <a href="register.php" class="text-blue-600 hover:text-blue-800">新規登録</a>
        </p>
    </div>
</div>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>