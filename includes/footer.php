</main>
    
    <!-- フッター -->
    <footer class="bg-gray-800 text-white py-6 mt-auto">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <div class="flex items-center">
                        <img src="assets/images/logo.png" alt="SwimLog" class="h-6 mr-2">
                        <span class="font-bold">SwimLog</span>
                    </div>
                </div>
                <div class="text-sm text-gray-400">
                    &copy; <?php echo date('Y'); ?> SwimLog by Cre.eight12. All rights reserved.
                </div>
                <div class="footer-links">
            <a href="terms.php">利用規約</a> | 
            <a href="privacy.php">プライバシーポリシー</a> |
            <a href="commerce.php">特定商取引法に基づく表記</a> |
            <a href="mailto:cre.eight12@gmail.com?subject=SwimLogに関するお問い合わせ">お問い合わせ</a>
        </div>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script src="assets/js/main.js"></script>
    <script>
    // モバイルメニューの切り替え
    document.addEventListener('DOMContentLoaded', function() {
        const mobileNavToggle = document.getElementById('mobile-nav-toggle');
        const mobileNav = document.getElementById('mobile-nav');
        
        if (mobileNavToggle && mobileNav) {
            mobileNavToggle.addEventListener('click', function() {
                mobileNav.classList.toggle('hidden');
            });
        }
    });
    </script>
</body>
</html>