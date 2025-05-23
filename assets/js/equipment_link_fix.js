// improved_equipment_link_fix.js - 種別・器具管理へのリンク修正

document.addEventListener('DOMContentLoaded', function() {
    console.log("種別・器具管理リンク修正スクリプトを開始します");
    
    // 既存のリンクを確認
    const equipmentLinks = document.querySelectorAll('a[href="equipment.php"]');
    console.log(`${equipmentLinks.length}個の器具管理リンクを発見しました`);
    
    if (equipmentLinks.length > 0) {
        // 既存リンクがある場合は、そのリンクの動作を確認
        equipmentLinks.forEach((link, index) => {
            console.log(`リンク #${index + 1} を処理: ${link.textContent || 'テキストなし'}`);
            
            // リンクのクリックイベントを上書き（フォーム処理中の場合のみ）
            link.addEventListener('click', function(event) {
                console.log("種別・器具管理リンクがクリックされました");
                return true; // 通常の動作を許可
            });
        });
    } else {
        console.log("既存の器具管理リンクが見つかりません。リンクを追加します");
        
        // セット詳細のヘッダーを探す
        const setHeaders = document.querySelectorAll('.mb-6 h3.text-lg.font-semibold, .mb-4 h3.text-lg.font-semibold');
        let setDetailHeader = null;
        
        setHeaders.forEach(header => {
            if (header.textContent.includes('セット詳細')) {
                setDetailHeader = header;
                console.log("セット詳細ヘッダーを発見しました");
            }
        });
        
        if (setDetailHeader) {
            // ヘッダーの親要素を取得
            const headerParent = setDetailHeader.parentElement;
            
            // フレックスコンテナを探す
            let actionDiv = null;
            
            // ヘッダー周辺のdivを探す
            for (let i = 0; i < headerParent.children.length; i++) {
                const child = headerParent.children[i];
                if (child.tagName === 'DIV' || child !== setDetailHeader) {
                    actionDiv = child;
                    console.log("アクションコンテナを発見しました");
                    break;
                }
            }
            
            // アクションdivがない場合は新規作成
            if (!actionDiv) {
                console.log("アクションコンテナが見つからないため新規作成します");
                
                // ヘッダーを含むdivを作成してflex化
                const newFlexContainer = document.createElement('div');
                newFlexContainer.className = 'flex justify-between items-center mb-4';
                
                // 元のヘッダーをコンテナに移動
                const clonedHeader = setDetailHeader.cloneNode(true);
                newFlexContainer.appendChild(clonedHeader);
                
                // アクションボタン用divを作成
                actionDiv = document.createElement('div');
                newFlexContainer.appendChild(actionDiv);
                
                // 元のヘッダーを新しいコンテナに置き換え
                headerParent.replaceChild(newFlexContainer, setDetailHeader);
            }
            
            // 既存のリンクがないかチェック
            const existingLink = actionDiv.querySelector('a[href="equipment.php"]');
            if (!existingLink) {
                // 新しいリンクを作成
                const equipmentLink = document.createElement('a');
                equipmentLink.href = 'equipment.php';
                equipmentLink.className = 'text-blue-600 hover:text-blue-800 ml-4';
                equipmentLink.innerHTML = '<i class="fas fa-cog mr-1"></i> 種別・器具管理';
                
                // リンクをアクションdivに追加
                actionDiv.appendChild(equipmentLink);
                console.log("種別・器具管理リンクを追加しました");
            } else {
                console.log("種別・器具管理リンクは既に存在します");
            }
        } else {
            console.log("セット詳細ヘッダーが見つかりません。別の方法を試します");
            
            // 代替として最初のフォームを探す
            const practiceForm = document.getElementById('practice-form');
            if (practiceForm) {
                console.log("練習フォームを発見しました");
                
                // セット追加ボタンを探す
                const addSetBtn = document.getElementById('add-set') || document.getElementById('add-set-btn');
                
                if (addSetBtn) {
                    console.log("セット追加ボタンを発見しました");
                    
                    // ボタンの親要素を取得
                    const btnParent = addSetBtn.parentElement;
                    
                    // 既存のリンクがないかチェック
                    const existingLink = btnParent.querySelector('a[href="equipment.php"]');
                    
                    if (!existingLink) {
                        // 新しいリンクを作成
                        const equipmentLink = document.createElement('a');
                        equipmentLink.href = 'equipment.php';
                        equipmentLink.className = 'text-blue-600 hover:text-blue-800 ml-4';
                        equipmentLink.innerHTML = '<i class="fas fa-cog mr-1"></i> 種別・器具管理';
                        
                        // リンクをボタン親要素に追加
                        btnParent.appendChild(equipmentLink);
                        console.log("種別・器具管理リンクをセット追加ボタンの横に追加しました");
                    } else {
                        console.log("種別・器具管理リンクは既に存在します");
                    }
                } else {
                    console.log("セット追加ボタンが見つかりません");
                    
                    // フォーム内の最初のh3またはh4要素を探す
                    const headers = practiceForm.querySelectorAll('h3, h4');
                    
                    if (headers.length > 0) {
                        console.log("フォーム内のヘッダーを発見しました");
                        
                        // 最初のヘッダーの後にリンクを挿入
                        const firstHeader = headers[0];
                        const linkContainer = document.createElement('div');
                        linkContainer.className = 'text-right mb-4';
                        linkContainer.innerHTML = '<a href="equipment.php" class="text-blue-600 hover:text-blue-800"><i class="fas fa-cog mr-1"></i> 種別・器具管理</a>';
                        
                        firstHeader.parentNode.insertBefore(linkContainer, firstHeader.nextSibling);
                        console.log("種別・器具管理リンクをヘッダーの後に追加しました");
                    } else {
                        console.log("適切な挿入位置が見つかりません");
                    }
                }
            } else {
                console.log("練習フォームが見つかりません");
            }
        }
    }
    
    // フォーム送信前の確認処理を追加（種別・器具が不足している場合の対応）
    const practiceForm = document.getElementById('practice-form');
    if (practiceForm) {
        practiceForm.addEventListener('submit', function(event) {
            console.log("フォーム送信前の確認を実行します");
            
            // ユーザー定義の種別・器具が十分かチェック
            checkCustomTypesAndEquipment();
        });
    }
    
    // 種別・器具のカスタム項目をチェック
    function checkCustomTypesAndEquipment() {
        // 種別選択要素を取得
        const typeSelects = document.querySelectorAll('select[name*="[type_id]"]');
        const equipmentSelects = document.querySelectorAll('select[name*="[equipment]"]');
        
        let hasTypeOptions = false;
        let hasEquipmentOptions = false;
        
        // 種別オプションをチェック
        if (typeSelects.length > 0) {
            const firstTypeSelect = typeSelects[0];
            hasTypeOptions = firstTypeSelect.options.length > 1; // 「選択してください」以外にオプションがあるか
        }
        
        // 器具オプションをチェック
        if (equipmentSelects.length > 0) {
            const firstEquipmentSelect = equipmentSelects[0];
            hasEquipmentOptions = firstEquipmentSelect.options.length > 0;
        }
        
        // オプションが少ない場合は種別・器具管理ページへの案内を表示
        if (!hasTypeOptions || !hasEquipmentOptions) {
            console.log("種別または器具のオプションが不足しています");
            
            // 既に注意メッセージがあれば表示しない
            const existingWarning = document.querySelector('.equipment-warning');
            if (!existingWarning) {
                const warningMsg = document.createElement('div');
                warningMsg.className = 'bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4 equipment-warning';
                warningMsg.innerHTML = `
                    <p><strong>ヒント:</strong> 種別や器具を追加するには 
                    <a href="equipment.php" class="font-bold underline">種別・器具管理</a> 
                    ページをご利用ください。</p>
                `;
                
                // フォームの先頭に挿入
                practiceForm.insertBefore(warningMsg, practiceForm.firstChild);
                
                // 3秒後に自動的に消去
                setTimeout(() => {
                    warningMsg.style.opacity = '0';
                    warningMsg.style.transition = 'opacity 0.5s';
                    
                    setTimeout(() => {
                        if (warningMsg.parentNode) {
                            warningMsg.parentNode.removeChild(warningMsg);
                        }
                    }, 500);
                }, 3000);
            }
        }
    }
});