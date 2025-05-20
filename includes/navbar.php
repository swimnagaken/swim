<!-- PC用ナビゲーション -->
<nav class="hidden md:flex space-x-6">
    <?php if (isLoggedIn()): ?>
        <a href="dashboard.php" class="hover:text-blue-200 transition">ダッシュボード</a>
        <a href="practice.php" class="hover:text-blue-200 transition">練習記録</a>
        <a href="competition.php" class="hover:text-blue-200 transition">大会記録</a>
        <a href="stats.php" class="hover:text-blue-200 transition">統計</a>
        
        <div class="relative group">
            <button class="hover:text-blue-200 transition flex items-center">
                <span><?php echo h($_SESSION['username']); ?></span>
                <i class="fas fa-chevron-down ml-1 text-xs"></i>
            </button>
            <div id="user-dropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 hidden">
                <a href="profile.php" class="block px-4 py-2 text-gray-800 hover:bg-blue-500 hover:text-white">
                    <i class="fas fa-user mr-2"></i>プロフィール
                </a>
                <div class="border-t border-gray-200 my-1"></div>
                <a href="logout.php" class="block px-4 py-2 text-gray-800 hover:bg-blue-500 hover:text-white">
                    <i class="fas fa-sign-out-alt mr-2"></i>ログアウト
                </a>
            </div>
        </div>
    <?php else: ?>
        <a href="login.php" class="hover:text-blue-200 transition">ログイン</a>
        <a href="register.php" class="bg-white text-blue-600 px-4 py-2 rounded-md hover:bg-blue-100 transition">新規登録</a>
    <?php endif; ?>
</nav>

<!-- モバイル用メニューボタン -->
<button id="mobile-nav-toggle" class="md:hidden text-white">
    <i class="fas fa-bars text-xl"></i>
</button>

<!-- モバイル用ナビゲーション -->
<nav id="mobile-nav" class="hidden mt-4 md:hidden">
    <div class="flex flex-col space-y-3 pb-3">
        <?php if (isLoggedIn()): ?>
            <a href="dashboard.php" class="hover:text-blue-200 transition">ダッシュボード</a>
            <a href="practice.php" class="hover:text-blue-200 transition">練習記録</a>
            <a href="competition.php" class="hover:text-blue-200 transition">大会記録</a>
            <a href="stats.php" class="hover:text-blue-200 transition">統計</a>
            <a href="profile.php" class="hover:text-blue-200 transition">プロフィール</a>
            <a href="logout.php" class="hover:text-blue-200 transition">ログアウト</a>
        <?php else: ?>
            <a href="login.php" class="hover:text-blue-200 transition">ログイン</a>
            <a href="register.php" class="bg-white text-blue-600 px-4 py-2 rounded-md hover:bg-blue-100 transition inline-block w-full text-center">新規登録</a>
        <?php endif; ?>
    </div>
</nav>