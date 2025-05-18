<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// ページタイトル
$page_title = "プロフィール";

// ログイン必須
requireLogin();

// アクションの取得（view, edit, update）
$action = isset($_GET['action']) ? $_GET['action'] : 'view';

// ユーザー情報の取得
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$userInfo = null;

try {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT u.*, 
            (SELECT COUNT(*) FROM practice_sessions WHERE user_id = u.user_id) as practice_count,
            (SELECT SUM(total_distance) FROM practice_sessions WHERE user_id = u.user_id) as total_distance
        FROM users u
        WHERE u.user_id = ?
    ");
    $stmt->execute([$userId]);
    $userInfo = $stmt->fetch();
} catch (PDOException $e) {
    error_log('ユーザー情報取得エラー: ' . $e->getMessage());
}

// 月間目標の取得
$currentYear = date('Y');
$currentMonth = date('n');

$monthlyGoal = null;
try {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT * FROM monthly_goals
        WHERE user_id = ? AND year = ? AND month = ?
    ");
    $stmt->execute([$userId, $currentYear, $currentMonth]);
    $monthlyGoal = $stmt->fetch();
} catch (PDOException $e) {
    error_log('月間目標取得エラー: ' . $e->getMessage());
}

// 練習統計の取得
$stats = [
    'current_month_distance' => 0,
    'current_month_sessions' => 0,
    'total_distance' => $userInfo['total_distance'] ?? 0,
    'total_sessions' => $userInfo['practice_count'] ?? 0
];

try {
    $db = getDbConnection();
    
    // 今月の練習統計
    $startDate = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
    $lastDay = date('t', strtotime($startDate));
    $endDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $lastDay);
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as session_count, SUM(total_distance) as total_distance
        FROM practice_sessions
        WHERE user_id = ? AND practice_date BETWEEN ? AND ?
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $monthStats = $stmt->fetch();
    
    if ($monthStats) {
        $stats['current_month_distance'] = (int)($monthStats['total_distance'] ?? 0);
        $stats['current_month_sessions'] = (int)($monthStats['session_count'] ?? 0);
    }
} catch (PDOException $e) {
    error_log('月間統計取得エラー: ' . $e->getMessage());
}

// パスワード変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_messages'][] = '無効なリクエストです。ページを再読み込みしてください。';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password)) {
            $_SESSION['error_messages'][] = '現在のパスワードを入力してください。';
        } elseif (empty($new_password)) {
            $_SESSION['error_messages'][] = '新しいパスワードを入力してください。';
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error_messages'][] = '新しいパスワードと確認用パスワードが一致しません。';
        } elseif (strlen($new_password) < 8) {
            $_SESSION['error_messages'][] = 'パスワードは8文字以上で入力してください。';
        } else {
            try {
                $db = getDbConnection();
                
                // 現在のパスワードを確認
                $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if (!$user || !password_verify($current_password, $user['password'])) {
                    $_SESSION['error_messages'][] = '現在のパスワードが正しくありません。';
                } else {
                    // パスワードを更新
                    $passwordHash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->execute([$passwordHash, $userId]);
                    
                    $_SESSION['success_messages'][] = 'パスワードが正常に更新されました。';
                    
                    // 元のページにリダイレクト
                    header('Location: profile.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('パスワード更新エラー: ' . $e->getMessage());
                $_SESSION['error_messages'][] = 'パスワードの更新中にエラーが発生しました。';
            }
        }
    }
}

// 月間目標の更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_goal') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_messages'][] = '無効なリクエストです。';
    } else {
        $distance_goal = (int)($_POST['distance_goal'] ?? 0);
        $sessions_goal = (int)($_POST['sessions_goal'] ?? 0);
        
        if ($distance_goal <= 0 && $sessions_goal <= 0) {
            $_SESSION['error_messages'][] = '距離目標または練習回数目標のいずれかを設定してください。';
        } else {
            try {
                $db = getDbConnection();
                
                // 既存の目標があるか確認
                $stmt = $db->prepare("
                    SELECT goal_id FROM monthly_goals 
                    WHERE user_id = ? AND year = ? AND month = ?
                ");
                $stmt->execute([$userId, $currentYear, $currentMonth]);
                $existingGoal = $stmt->fetch();
                
                if ($existingGoal) {
                    // 目標を更新
                    $stmt = $db->prepare("
                        UPDATE monthly_goals 
                        SET distance_goal = ?, sessions_goal = ?, updated_at = NOW()
                        WHERE goal_id = ?
                    ");
                    $stmt->execute([$distance_goal, $sessions_goal, $existingGoal['goal_id']]);
                } else {
                    // 新しい目標を作成
                    $stmt = $db->prepare("
                        INSERT INTO monthly_goals (user_id, year, month, distance_goal, sessions_goal, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$userId, $currentYear, $currentMonth, $distance_goal, $sessions_goal]);
                }
                
                $_SESSION['success_messages'][] = '月間目標が正常に更新されました。';
                
                // 目標が変更されたので再取得
                $stmt = $db->prepare("
                    SELECT * FROM monthly_goals
                    WHERE user_id = ? AND year = ? AND month = ?
                ");
                $stmt->execute([$userId, $currentYear, $currentMonth]);
                $monthlyGoal = $stmt->fetch();
                
                // 元のページにリダイレクト
                header('Location: profile.php');
                exit;
            } catch (PDOException $e) {
                error_log('月間目標更新エラー: ' . $e->getMessage());
                $_SESSION['error_messages'][] = '月間目標の更新中にエラーが発生しました。';
            }
        }
    }
}

// ヘッダーの読み込み
include 'includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold">プロフィール</h1>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- プロフィール情報 -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold">ユーザー情報</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-gray-600 text-sm">ユーザー名</p>
                    <p class="font-medium"><?php echo h($username); ?></p>
                </div>
                
                <div>
                    <p class="text-gray-600 text-sm">メールアドレス</p>
                    <p class="font-medium"><?php echo h($userInfo['email'] ?? '-'); ?></p>
                </div>
                
                <div>
                    <p class="text-gray-600 text-sm">登録日</p>
                    <p class="font-medium"><?php echo $userInfo['created_at'] ? date('Y年n月j日', strtotime($userInfo['created_at'])) : '-'; ?></p>
                </div>
                
                <div>
                    <p class="text-gray-600 text-sm">練習記録数</p>
                    <p class="font-medium"><?php echo number_format($stats['total_sessions']); ?> 回</p>
                </div>
                
                <div>
                    <p class="text-gray-600 text-sm">累計練習距離</p>
                    <p class="font-medium"><?php echo number_format($stats['total_distance']); ?> m</p>
                </div>
            </div>
            
            <div class="mt-8">
                <button type="button" id="toggle-password-form" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-key mr-1"></i> パスワード変更
                </button>
                
                <div id="password-change-form" class="mt-4 hidden">
                    <h3 class="text-lg font-semibold mb-4">パスワード変更</h3>
                    
                    <form method="POST" action="profile.php?action=update">
                        <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                        
                        <div class="mb-4">
                            <label for="current_password" class="block text-gray-700 mb-2">現在のパスワード</label>
                            <input 
                                type="password" 
                                id="current_password" 
                                name="current_password" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label for="new_password" class="block text-gray-700 mb-2">新しいパスワード</label>
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                                minlength="8"
                            >
                            <p class="text-gray-500 text-sm mt-1">8文字以上で入力してください</p>
                        </div>
                        
                        <div class="mb-6">
                            <label for="confirm_password" class="block text-gray-700 mb-2">新しいパスワード（確認）</label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                                minlength="8"
                            >
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="button" id="cancel-password-change" class="mr-3 text-gray-600 hover:text-gray-800">
                                キャンセル
                            </button>
                            
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg">
                                パスワードを変更する
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 月間目標と統計 -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold">今月の目標</h2>
                <button type="button" id="toggle-goal-form" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
            
            <div id="goal-display" class="<?php echo $monthlyGoal ? '' : 'hidden'; ?>">
                <?php if ($monthlyGoal): ?>
                <div class="mb-6">
                    <p class="text-gray-600 text-sm">距離目標</p>
                    <p class="text-3xl font-bold text-blue-600">
                        <?php echo number_format($monthlyGoal['distance_goal']); ?> <span class="text-lg font-normal text-gray-500">m</span>
                    </p>
                    
                    <?php if ($monthlyGoal['distance_goal'] > 0): ?>
                    <div class="mt-2">
                        <?php $distance_percentage = min(100, round(($stats['current_month_distance'] / $monthlyGoal['distance_goal']) * 100)); ?>
                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span>現在: <?php echo number_format($stats['current_month_distance']); ?> m</span>
                            <span><?php echo $distance_percentage; ?>%</span>
                        </div>
                        <div class="bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 rounded-full h-2" style="width: <?php echo $distance_percentage; ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div>
                    <p class="text-gray-600 text-sm">練習回数目標</p>
                    <p class="text-3xl font-bold text-green-600">
                        <?php echo $monthlyGoal['sessions_goal']; ?> <span class="text-lg font-normal text-gray-500">回</span>
                    </p>
                    
                    <?php if ($monthlyGoal['sessions_goal'] > 0): ?>
                    <div class="mt-2">
                        <?php $sessions_percentage = min(100, round(($stats['current_month_sessions'] / $monthlyGoal['sessions_goal']) * 100)); ?>
                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span>現在: <?php echo $stats['current_month_sessions']; ?> 回</span>
                            <span><?php echo $sessions_percentage; ?>%</span>
                        </div>
                        <div class="bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 rounded-full h-2" style="width: <?php echo $sessions_percentage; ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div id="goal-empty" class="text-center py-4 <?php echo $monthlyGoal ? 'hidden' : ''; ?>">
                <p class="text-gray-500 mb-4">
                    まだ目標が設定されていません。<br>今月の目標を設定しましょう。
                </p>
                <button type="button" id="show-goal-form" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-plus mr-1"></i> 目標を設定する
                </button>
            </div>
            
            <div id="goal-form" class="<?php echo ($action === 'update_goal' || !$monthlyGoal) ? '' : 'hidden'; ?>">
                <h3 class="text-lg font-semibold mb-4">目標設定</h3>
                <form method="POST" action="profile.php?action=update_goal">
                    <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                    
                    <div class="mb-4">
                        <label for="distance_goal" class="block text-gray-700 mb-2">月間距離目標 (m)</label>
                        <input 
                            type="number" 
                            id="distance_goal" 
                            name="distance_goal" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $monthlyGoal ? $monthlyGoal['distance_goal'] : '10000'; ?>"
                            min="0"
                            step="1000"
                        >
                    </div>
                    
                    <div class="mb-6">
                        <label for="sessions_goal" class="block text-gray-700 mb-2">月間練習回数目標</label>
                        <input 
                            type="number" 
                            id="sessions_goal" 
                            name="sessions_goal" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $monthlyGoal ? $monthlyGoal['sessions_goal'] : '8'; ?>"
                            min="0"
                        >
                    </div>
                    
                    <div class="flex justify-end">
                        <?php if ($monthlyGoal): ?>
                        <button type="button" id="cancel-goal-edit" class="mr-3 text-gray-600 hover:text-gray-800">
                            キャンセル
                        </button>
                        <?php endif; ?>
                        
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg">
                            目標を保存する
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-6">今月の統計</h2>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-3 bg-blue-50 rounded-lg">
                    <p class="text-gray-600 text-sm">合計距離</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo number_format($stats['current_month_distance']); ?> m</p>
                </div>
                
                <div class="text-center p-3 bg-green-50 rounded-lg">
                    <p class="text-gray-600 text-sm">練習回数</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo $stats['current_month_sessions']; ?> 回</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // パスワード変更フォームの表示/非表示
    const togglePasswordForm = document.getElementById('toggle-password-form');
    const passwordForm = document.getElementById('password-change-form');
    const cancelPasswordChange = document.getElementById('cancel-password-change');
    
    if (togglePasswordForm && passwordForm) {
        togglePasswordForm.addEventListener('click', function() {
            passwordForm.classList.remove('hidden');
            togglePasswordForm.classList.add('hidden');
        });
    }
    
    if (cancelPasswordChange && passwordForm && togglePasswordForm) {
        cancelPasswordChange.addEventListener('click', function() {
            passwordForm.classList.add('hidden');
            togglePasswordForm.classList.remove('hidden');
        });
    }
    
    // 目標編集フォームの表示/非表示
    const toggleGoalForm = document.getElementById('toggle-goal-form');
    const goalDisplay = document.getElementById('goal-display');
    const goalForm = document.getElementById('goal-form');
    const cancelGoalEdit = document.getElementById('cancel-goal-edit');
    const showGoalForm = document.getElementById('show-goal-form');
    const goalEmpty = document.getElementById('goal-empty');
    
    if (toggleGoalForm && goalDisplay && goalForm) {
        toggleGoalForm.addEventListener('click', function() {
            goalDisplay.classList.add('hidden');
            goalForm.classList.remove('hidden');
        });
    }
    
    if (cancelGoalEdit && goalDisplay && goalForm) {
        cancelGoalEdit.addEventListener('click', function() {
            goalForm.classList.add('hidden');
            goalDisplay.classList.remove('hidden');
        });
    }
    
    if (showGoalForm && goalEmpty && goalForm) {
        showGoalForm.addEventListener('click', function() {
            goalEmpty.classList.add('hidden');
            goalForm.classList.remove('hidden');
        });
    }
});
</script>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>