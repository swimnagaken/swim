// assets/js/social_sharing.js
document.addEventListener('DOMContentLoaded', function() {
    /**
     * ソーシャルメディア共有機能
     */
    function initSocialSharing() {
        const shareButtons = document.querySelectorAll('.share-button');
        if (shareButtons.length === 0) return;
        
        shareButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const type = this.dataset.share;
                const url = this.dataset.url || window.location.href;
                const title = this.dataset.title || document.title;
                const description = this.dataset.description || '';
                const imageUrl = this.dataset.image || '';
                
                // 共有する練習データを準備
                const practiceData = {
                    date: this.dataset.date || '',
                    distance: this.dataset.distance || '',
                    duration: this.dataset.duration || '',
                    achievement: this.dataset.achievement || ''
                };
                
                switch (type) {
                    case 'twitter':
                        shareToTwitter(title, url, practiceData);
                        break;
                    case 'facebook':
                        shareToFacebook(url);
                        break;
                    case 'line':
                        shareToLine(title, url);
                        break;
                    case 'image':
                        createShareableImage(practiceData, imageUrl);
                        break;
                    default:
                        console.error('不明な共有タイプ:', type);
                }
            });
        });
    }
    
    /**
     * Twitterで共有
     */
    function shareToTwitter(title, url, practiceData) {
        const text = encodeURIComponent(`${title} - ${practiceData.date}に${practiceData.distance}m泳ぎました！`);
        const shareUrl = `https://twitter.com/intent/tweet?text=${text}&url=${encodeURIComponent(url)}`;
        window.open(shareUrl, '_blank');
    }
    
    /**
     * Facebookで共有
     */
    function shareToFacebook(url) {
        const shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
        window.open(shareUrl, '_blank');
    }
    
    /**
     * LINEで共有
     */
    function shareToLine(title, url) {
        const text = encodeURIComponent(`${title} ${url}`);
        const shareUrl = `https://line.me/R/msg/text/?${text}`;
        window.open(shareUrl, '_blank');
    }
    
    /**
     * 共有用画像を作成
     */
    function createShareableImage(practiceData, backgroundUrl) {
        // 画像生成用のキャンバスを作成
        const canvas = document.createElement('canvas');
        canvas.width = 1200;
        canvas.height = 630;
        const ctx = canvas.getContext('2d');
        
        // 背景画像を読み込む
        const img = new Image();
        img.crossOrigin = "anonymous";
        img.onload = function() {
            // 背景画像を描画
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            
            // 半透明の背景オーバーレイ
            ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // タイトル
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 60px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('My Swimming Workout', canvas.width / 2, 150);
            
            // 日付
            ctx.font = '40px Arial';
            ctx.fillText(practiceData.date, canvas.width / 2, 220);
            
            // 区切り線
            ctx.strokeStyle = '#3b82f6';
            ctx.lineWidth = 4;
            ctx.beginPath();
            ctx.moveTo(canvas.width / 2 - 100, 240);
            ctx.lineTo(canvas.width / 2 + 100, 240);
            ctx.stroke();
            
            // 練習データ
            ctx.font = 'bold 50px Arial';
            ctx.fillText(`${practiceData.distance}m`, canvas.width / 2, 320);
            
            ctx.font = '30px Arial';
            if (practiceData.duration) {
                ctx.fillText(`Duration: ${practiceData.duration}`, canvas.width / 2, 380);
            }
            
            if (practiceData.achievement) {
                ctx.fillText(`Achievement: ${practiceData.achievement}%`, canvas.width / 2, 440);
            }
            
            // フッター
            ctx.font = '24px Arial';
            ctx.fillText('Created with SwimLog', canvas.width / 2, 580);
            
            // 画像を表示
            const imgData = canvas.toDataURL('image/png');
            
            // ダウンロードまたは表示モーダル
            showShareImageModal(imgData);
        };
        
        img.onerror = function() {
            console.error('画像の読み込みに失敗しました');
            
            // 背景画像なしでテキストのみの画像を生成
            ctx.fillStyle = '#3b82f6';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // 以降は同じ
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 60px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('My Swimming Workout', canvas.width / 2, 150);
            
            // その他のテキスト描画
            // ...
            
            const imgData = canvas.toDataURL('image/png');
            showShareImageModal(imgData);
        };
        
        img.src = backgroundUrl || 'assets/images/swimming_background.jpg';
    }
    
    /**
     * 共有画像表示モーダル
     */
    function showShareImageModal(imgData) {
        // モーダル要素を作成
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white p-6 rounded-lg shadow-lg max-w-3xl w-full">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold">共有用画像</h3>
                    <button class="close-modal text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mb-4 overflow-auto max-h-[70vh]">
                    <img src="${imgData}" alt="共有用画像" class="w-full rounded-lg shadow">
                </div>
                <div class="flex justify-between">
                    <div>
                        <button class="download-image bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                            <i class="fas fa-download mr-2"></i> ダウンロード
                        </button>
                    </div>
                    <div class="flex space-x-3">
                        <button class="share-twitter bg-[#1DA1F2] hover:bg-[#0c85d0] text-white font-medium py-2 px-4 rounded-lg">
                            <i class="fab fa-twitter mr-1"></i> Twitter
                        </button>
                        <button class="share-facebook bg-[#4267B2] hover:bg-[#365899] text-white font-medium py-2 px-4 rounded-lg">
                            <i class="fab fa-facebook-f mr-1"></i> Facebook
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // モーダルを閉じる
        modal.querySelector('.close-modal').addEventListener('click', function() {
            document.body.removeChild(modal);
        });
        
        // 画像をダウンロード
        modal.querySelector('.download-image').addEventListener('click', function() {
            const a = document.createElement('a');
            a.href = imgData;
            a.download = `swimlog_share_${new Date().toISOString().split('T')[0]}.png`;
            a.click();
        });
        
        // Twitter共有
        modal.querySelector('.share-twitter').addEventListener('click', function() {
            // 画像をサーバーに一時保存してURLを取得する必要がある
            // ここではクライアントサイドで生成した画像をBase64で送信
            shareImageWithTwitter(imgData);
        });
        
        // Facebook共有
        modal.querySelector('.share-facebook').addEventListener('click', function() {
            // Facebook共有も同様
            shareImageWithFacebook(imgData);
        });
    }
    
    /**
     * Twitter共有用の画像アップロード
     */
    function shareImageWithTwitter(imgData) {
        // 実際の実装では、画像をサーバーにアップロードして
        // そのURLをTwitter共有URLに含める必要があります
        
        // 仮の実装（実際にはサーバーサイドのAPIを呼び出す）
        const text = encodeURIComponent('今日の水泳練習記録をシェアします！ #SwimLog');
        const shareUrl = `https://twitter.com/intent/tweet?text=${text}`;
        window.open(shareUrl, '_blank');
    }
    
    /**
     * Facebook共有用の画像アップロード
     */
    function shareImageWithFacebook(imgData) {
        // Facebookも同様に、サーバーサイドでの処理が必要
        const shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(window.location.href)}`;
        window.open(shareUrl, '_blank');
    }
    
    // 初期化
    initSocialSharing();
});