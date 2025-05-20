// ヘッダーのユーザーメニュードロップダウンを改善するためのスクリプト
document.addEventListener('DOMContentLoaded', function() {
    // ユーザードロップダウンの改善
    const userDropdownButton = document.querySelector('.group > button');
    const userDropdownMenu = document.getElementById('user-dropdown');
    
    if (userDropdownButton && userDropdownMenu) {
        // クリックイベントでの切り替え
        userDropdownButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // hidden クラスの有無を確認して切り替え
            if (userDropdownMenu.classList.contains('hidden')) {
                userDropdownMenu.classList.remove('hidden');
            } else {
                userDropdownMenu.classList.add('hidden');
            }
        });
        
        // 外部クリックで閉じる
        document.addEventListener('click', function(e) {
            if (!userDropdownButton.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                userDropdownMenu.classList.add('hidden');
            }
        });
    }
    
    // モバイルメニューのドロップダウン改善
    const mobileMenuButton = document.getElementById('mobile-nav-toggle');
    const mobileMenu = document.getElementById('mobile-nav');
    
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }
});