// practice_improvements.js - 練習記録フォームの改善

document.addEventListener('DOMContentLoaded', function() {
    console.log("練習フォーム改善スクリプトを開始します");
    
    // 調子選択の改善 - 1:悪い～5:良いがわかりやすいようにラベル表示
    initializeFeelingSelector();
    
    // 器具選択の改善 - 複数選択がしやすいようにカスタマイズ
    initializeEquipmentSelectors();
    
    // セット距離計算の初期化
    initializeSetCalculations();
    
    // セット追加ボタンのイベントリスナーを追加
    const addSetBtn = document.getElementById('add-set-btn');
    if (addSetBtn) {
        console.log("add-set-btnを検出しました");
        addSetBtn.addEventListener('click', function() {
            console.log("add-set-btnがクリックされました");
            // 新しいセットが追加された後、少し待ってから器具選択を初期化
            setTimeout(function() {
                initializeEquipmentSelectors();
            }, 300);
        });
    }
    
    // 既存の「セット追加」ボタンにも対応
    const addSetButton = document.getElementById('add-set');
    if (addSetButton) {
        console.log("add-setボタンを検出しました");
        const originalClickHandler = addSetButton.onclick;
        
        addSetButton.onclick = function(e) {
            console.log("add-setボタンがクリックされました");
            if (typeof originalClickHandler === 'function') {
                originalClickHandler.call(this, e);
            }
            
            // 新しいセットが追加された後、少し待ってから器具選択を初期化
            setTimeout(function() {
                initializeEquipmentSelectors();
            }, 300);
        };
    }
    
    // MutationObserverを設定
    setupMutationObserver();
});

/**
 * 調子選択の改善
 * 1:悪い～5:良いを視覚的にわかりやすく表示
 */
function initializeFeelingSelector() {
    console.log("調子選択の初期化を開始します");
    const feelingRadios = document.querySelectorAll('input[name="feeling"]');
    if (feelingRadios.length === 0) {
        console.log("調子選択ラジオボタンが見つかりません");
        return;
    }
    
    console.log(`${feelingRadios.length}個の調子選択ラジオボタンを見つけました`);
    
    // 既存の選択肢のラベルを非表示
    feelingRadios.forEach(radio => {
        const label = radio.nextElementSibling;
        if (label) {
            label.style.display = 'none';
        }
    });
    
    // ラベルと色の定義
    const feelingLabels = {
        1: { text: '1 (悪い)', color: '#f87171' /* red-400 */ },
        2: { text: '2 (やや悪い)', color: '#fcd34d' /* yellow-300 */ },
        3: { text: '3 (普通)', color: '#a3e635' /* lime-400 */ },
        4: { text: '4 (やや良い)', color: '#34d399' /* emerald-400 */ },
        5: { text: '5 (良い)', color: '#60a5fa' /* blue-400 */ }
    };
    
    // 新しいUI要素を作成
    const container = feelingRadios[0].closest('div');
    
    // 既に初期化されているか確認
    if (container.querySelector('.feeling-container')) {
        console.log("調子選択は既に初期化されています");
        return;
    }
    
    const newContainer = document.createElement('div');
    newContainer.className = 'feeling-container flex justify-between items-center w-full mt-2 p-2 bg-gray-50 rounded-lg';
    
    for (let i = 1; i <= 5; i++) {
        const label = feelingLabels[i];
        const item = document.createElement('div');
        item.className = 'feeling-item text-center cursor-pointer flex flex-col items-center';
        
        const circle = document.createElement('div');
        circle.className = 'w-8 h-8 rounded-full flex items-center justify-center mb-1 transition-all';
        circle.style.backgroundColor = label.color;
        circle.style.opacity = '0.4';
        circle.textContent = i;
        
        const text = document.createElement('span');
        text.className = 'text-xs text-gray-600';
        text.textContent = label.text.split(' ')[1].replace(/[()]/g, '');
        
        item.appendChild(circle);
        item.appendChild(text);
        
        // クリックイベント
        item.addEventListener('click', function() {
            const radio = feelingRadios[i-1];
            radio.checked = true;
            
            // すべての円をリセット
            document.querySelectorAll('.feeling-item div').forEach(el => {
                el.style.opacity = '0.4';
                el.style.transform = 'scale(1)';
            });
            
            // 選択された円をハイライト
            circle.style.opacity = '1';
            circle.style.transform = 'scale(1.1)';
            
            console.log(`調子を${i}に設定しました`);
        });
        
        newContainer.appendChild(item);
        
        // 初期状態で選択されている値をハイライト
        if (feelingRadios[i-1].checked) {
            circle.style.opacity = '1';
            circle.style.transform = 'scale(1.1)';
        }
    }
    
    container.appendChild(newContainer);
    console.log("調子選択の初期化が完了しました");
}

/**
 * 器具選択の改善
 * 複数選択できるチェックボックス形式に変更
 */
function initializeEquipmentSelectors() {
    console.log("器具選択の初期化を実行します");
    const equipmentSelects = document.querySelectorAll('.equipment-select');
    if (equipmentSelects.length === 0) {
        console.log("器具選択要素が見つかりません");
        return;
    }
    
    console.log(`${equipmentSelects.length}個の器具選択要素を見つけました`);
    
    equipmentSelects.forEach((select, index) => {
        // 既に初期化済みかチェック
        if (select.nextElementSibling && select.nextElementSibling.classList.contains('equipment-checkbox-container')) {
            console.log(`器具選択 #${index} は既に初期化済みです`);
            return;
        }
        
        // 元のセレクトを非表示に
        select.style.display = 'none';
        
        // 器具の選択肢を取得
        const options = Array.from(select.options);
        if (options.length === 0) {
            console.log(`器具選択 #${index} に選択肢がありません`);
            return;
        }
        
        console.log(`器具選択 #${index} の選択肢: ${options.length}個`);
        
        // チェックボックスコンテナを作成
        const container = document.createElement('div');
        container.className = 'equipment-checkbox-container grid grid-cols-2 gap-2 bg-gray-50 p-2 rounded-lg';
        
        // 各選択肢についてチェックボックスを作成
        options.forEach(option => {
            const id = `equipment_${index}_${option.value}`;
            const label = document.createElement('label');
            label.className = 'flex items-center space-x-2 cursor-pointer p-1 hover:bg-gray-100 rounded';
            if (option.selected) {
                label.classList.add('selected');
            }
            label.setAttribute('for', id);
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = id;
            checkbox.className = 'form-checkbox h-4 w-4 text-blue-600';
            checkbox.value = option.value;
            checkbox.checked = option.selected;
            checkbox.name = `equipment_checkbox_${index}`;
            
            // チェックボックスの変更イベント
            checkbox.addEventListener('change', function() {
                option.selected = this.checked;
                
                // ラベルのスタイルを更新
                if (this.checked) {
                    label.classList.add('selected');
                } else {
                    label.classList.remove('selected');
                }
                
                // selectのchangeイベントを発火
                const event = new Event('change', { bubbles: true });
                select.dispatchEvent(event);
                
                console.log(`器具選択 #${index} の "${option.textContent}" を ${this.checked ? '選択' : '解除'}`);
            });
            
            const span = document.createElement('span');
            span.className = 'text-sm';
            span.textContent = option.textContent;
            
            label.appendChild(checkbox);
            label.appendChild(span);
            container.appendChild(label);
        });
        
        // 元のセレクトの後にチェックボックスコンテナを挿入
        select.parentNode.insertBefore(container, select.nextSibling);
        console.log(`器具選択 #${index} を初期化完了`);
    });
}

/**
 * セット距離計算の初期化（既存コードの改善）
 */
function initializeSetCalculations() {
    console.log("セット距離計算の初期化を開始します");
    const setItems = document.querySelectorAll('.set-item');
    if (setItems.length === 0) {
        console.log("セット項目が見つかりません");
        return;
    }
    
    console.log(`${setItems.length}個のセット項目を見つけました`);
    
    setItems.forEach((setItem, index) => {
        const distanceInput = setItem.querySelector('input[name$="[distance]"]');
        const repsInput = setItem.querySelector('input[name$="[repetitions]"]');
        const totalInput = setItem.querySelector('input[name$="[total_distance]"]');
        
        if (distanceInput && repsInput && totalInput) {
            // 既にイベントリスナーが設定されているか確認（データ属性を使用）
            if (distanceInput.dataset.calculationInitialized === 'true') {
                console.log(`セット #${index} の距離計算は既に初期化済みです`);
                return;
            }
            
            distanceInput.dataset.calculationInitialized = 'true';
            repsInput.dataset.calculationInitialized = 'true';
            
            const calculateTotal = () => {
                const distance = parseInt(distanceInput.value) || 0;
                const reps = parseInt(repsInput.value) || 1;
                totalInput.value = distance * reps;
                
                // 全体の総距離も更新
                calculateTotalDistance();
                
                console.log(`セット #${index} の距離計算: ${distance} × ${reps} = ${distance * reps}`);
            };
            
            distanceInput.addEventListener('input', calculateTotal);
            repsInput.addEventListener('input', calculateTotal);
            
            // 初期計算を実行
            calculateTotal();
            
            console.log(`セット #${index} の距離計算を初期化しました`);
        } else {
            console.log(`セット #${index} に必要な入力フィールドが見つかりません`);
        }
    });
}

/**
 * 総距離の自動計算（既存コードの再利用）
 */
function calculateTotalDistance() {
    const totalDistanceInput = document.getElementById('total_distance');
    const setTotals = document.querySelectorAll('.set-total');
    
    if (totalDistanceInput && setTotals.length > 0) {
        let sum = 0;
        setTotals.forEach(input => {
            sum += parseInt(input.value) || 0;
        });
        
        totalDistanceInput.value = sum;
        console.log(`総距離を更新: ${sum}m`);
    } else {
        console.log("総距離の計算に必要な要素が見つかりません");
    }
}

// セット追加時に器具選択を初期化する関数
function handleNewSetAdded() {
    console.log("新しいセットが追加されました - 器具選択の再初期化を実行します");
    setTimeout(function() {
        initializeEquipmentSelectors();
        initializeSetCalculations();
    }, 300);
}

// MutationObserverを使ってDOMの変更を監視
function setupMutationObserver() {
    const setsContainer = document.getElementById('sets-container');
    if (!setsContainer) {
        console.log("sets-containerが見つかりません");
        return;
    }
    
    console.log("MutationObserverを設定します");
    const observer = new MutationObserver(function(mutations) {
        let setAdded = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                for (let i = 0; i < mutation.addedNodes.length; i++) {
                    const node = mutation.addedNodes[i];
                    if (node.nodeType === 1 && node.classList.contains('set-item')) {
                        console.log("新しいセット要素が追加されました");
                        setAdded = true;
                    }
                }
            }
        });
        
        if (setAdded) {
            handleNewSetAdded();
        }
    });
    
    // 設定を適用して監視を開始
    observer.observe(setsContainer, { childList: true, subtree: true });
    console.log("MutationObserverの監視を開始しました");
}

// スタイルシートを動的に追加
(function() {
    const style = document.createElement('style');
    style.textContent = `
        .equipment-checkbox-container {
            max-height: 150px;
            overflow-y: auto;
        }
        
        .feeling-item:hover div {
            opacity: 0.9 !important;
            transform: scale(1.05);
        }
        
        /* チェックボックスのスタイル */
        .form-checkbox {
            appearance: none;
            -webkit-appearance: none;
            border: 1px solid #cbd5e0;
            border-radius: 0.25rem;
            width: 1rem;
            height: 1rem;
            background-color: white;
            display: inline-block;
            position: relative;
            vertical-align: middle;
            cursor: pointer;
        }
        
        .form-checkbox:checked {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }
        
        .form-checkbox:checked:after {
            content: '';
            display: block;
            position: absolute;
            left: 5px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        /* 追加のスタイル */
        .equipment-checkbox-container label.selected {
            background-color: #e6f0ff;
            border: 1px solid #3b82f6;
        }
        
        /* レスポンシブデザインの調整 */
        @media (max-width: 640px) {
            .equipment-checkbox-container {
                grid-template-columns: 1fr;
            }
            
            .feeling-container {
                flex-wrap: wrap;
            }
            
            .feeling-item {
                margin: 0 5px;
            }
        }
    `;
    document.head.appendChild(style);
})();