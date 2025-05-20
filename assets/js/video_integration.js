// assets/js/video_integration.js
document.addEventListener('DOMContentLoaded', function() {
    /**
     * 練習フォームに動画アップロード機能を追加
     */
    function initVideoUploader() {
        const videoDropZone = document.getElementById('video-drop-zone');
        if (!videoDropZone) return;
        
        // ドラッグ&ドロップでの動画アップロード
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            videoDropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            videoDropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            videoDropZone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            videoDropZone.classList.add('border-blue-500', 'bg-blue-50');
        }
        
        function unhighlight() {
            videoDropZone.classList.remove('border-blue-500', 'bg-blue-50');
        }
        
        videoDropZone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        }
        
        function handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                if (file.type.startsWith('video/')) {
                    uploadVideo(file);
                } else {
                    addNotification('動画ファイルを選択してください', 'error');
                }
            }
        }
        
        // ファイル選択ボタン
        const videoInput = document.getElementById('video-input');
        if (videoInput) {
            videoInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    handleFiles(this.files);
                }
            });
        }
        
        // 動画アップロード処理
        function uploadVideo(file) {
            const formData = new FormData();
            formData.append('video', file);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            formData.append('session_id', document.querySelector('input[name="session_id"]')?.value || '');
            
            // プログレスバーの表示
            const progressBar = document.getElementById('video-progress');
            const progressContainer = document.getElementById('video-progress-container');
            if (progressBar && progressContainer) {
                progressContainer.classList.remove('hidden');
                progressBar.style.width = '0%';
            }
            
            // APIリクエスト
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api/video_upload.php', true);
            
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable && progressBar) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressBar.style.width = percentComplete + '%';
                }
            };
            
            xhr.onload = function() {
                if (progressContainer) {
                    progressContainer.classList.add('hidden');
                }
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            addNotification('動画がアップロードされました', 'success');
                            
                            // 動画プレビューを表示
                            const videoPreview = document.getElementById('video-preview');
                            if (videoPreview) {
                                videoPreview.classList.remove('hidden');
                                videoPreview.innerHTML = `
                                    <div class="relative">
                                        <video controls class="w-full rounded-lg shadow-lg">
                                            <source src="${response.video_url}" type="${file.type}">
                                            お使いのブラウザは動画再生に対応していません。
                                        </video>
                                        <input type="hidden" name="video_id" value="${response.video_id}">
                                    </div>
                                `;
                            }
                        } else {
                            addNotification(response.error || 'アップロードに失敗しました', 'error');
                        }
                    } catch (e) {
                        addNotification('サーバーからの応答を処理できませんでした', 'error');
                    }
                } else {
                    addNotification('アップロードに失敗しました', 'error');
                }
            };
            
            xhr.onerror = function() {
                if (progressContainer) {
                    progressContainer.classList.add('hidden');
                }
                addNotification('アップロード中にエラーが発生しました', 'error');
            };
            
            xhr.send(formData);
        }
    }
});