// equipment_link_fix.js - 種別・器具管理へのリンク修正

document.addEventListener('DOMContentLoaded', function() {
    console.log("種別・器具管理リンク修正スクリプトを開始します");
    
    // 既存のリンクを確認
    const equipmentLinks = document.querySelectorAll('a[href="equipment.php"]');
    console.log(`${equipmentLinks.length}個の器具管理リンクを発見しました`);
    
    // リンクの動作を修正
    equipmentLinks.forEach((link, index) => {
        console.log(`リンク #${index + 1} を処理中: ${link.textContent || 'テキストなし'}`);
        
        // リンクのクリックイベントを上書き
        link.addEventListener('click', function(event) {
            console.log("種別・器具管理リンクがクリックされました");
            
            // フォーム処理中の場合のみ確認
            const processingForms = document.querySelectorAll('form.processing');
            if (processingForms.length > 0) {
                console.log("フォーム処理中のためリンク動作を一時中断");
                
                // 確認ダイアログ
                if (confirm('フォームの処理中です。このまま移動しますか？')) {
                    console.log("ユーザーが移動を確認しました");
                    // 通常の動作を続行
                    return true;
                } else {
                    console.log("ユーザーが移動をキャンセルしました");
                    // リンクの動作をキャンセル
                    event.preventDefault();
                    return false;
                }
            }
            
            // 直接リンクに移動
            window.location.href = 'equipment.php';
            
            // 元のイベントをキャンセルして独自の処理を優先
            event.preventDefault();
        });
    });
    
    // 既存リンクがない場合は新規追加
    if (equipmentLinks.length === 0) {
        console.log("既存の器具管理リンクが見つかりません。リンクを追加します");
        
        // セット詳細のヘッダーを探す
        const setHeaders = document.querySelectorAll('.mb-6 h3.text-lg.font-semibold');
        let setDetailHeader = null;
        
        setHeaders.forEach(header => {
            if (header.textContent.includes('セット詳細')) {
                setDetailHeader = header;
            }
        });
        
        if (setDetailHeader) {
            console.log("セット詳細ヘッダーを発見しました");
            
            // ヘッダーの親要素を取得
            const headerParent = setDetailHeader.parentElement;
            
            // ヘッダーに横並びのdivがあるか確認
            let actionDiv = null;
            for (let i = 0; i < headerParent.children.length; i++) {
                const child = headerParent.children[i];
                if (child.tagName === 'DIV' && child !== setDetailHeader) {
                    actionDiv = child;
                    break;
                }
            }
            
            // アクションdivがない場合は作成
            if (!actionDiv) {
                console.log("アクションdivがないため新規作成します");
                actionDiv = document.createElement('div');
                headerParent.appendChild(actionDiv);
            }
            
            // リンクを作成
            const equipmentLink = document.createElement('a');
            equipmentLink.href = 'equipment.php';
            equipmentLink.className = 'text-blue-600 hover:text-blue-800 ml-4';
            equipmentLink.innerHTML = '<i class="fas fa-cog mr-1"></i> 種別・器具管理';
            
            // 既存のセット追加ボタンを確認
            const addSetBtn = actionDiv.querySelector('button#add-set');
            
            if (addSetBtn) {
                console.log("セット追加ボタンの後ろにリンクを追加します");
                // セット追加ボタンの後ろに追加
                actionDiv.appendChild(equipmentLink);
            } else {
                console.log("アクションdivにリンクを追加します");
                // divの中に追加
                actionDiv.appendChild(equipmentLink);
            }
            
            console.log("種別・器具管理リンクを追加しました");
        } else {
            console.log("セット詳細ヘッダーが見つかりません");
        }
    }
});