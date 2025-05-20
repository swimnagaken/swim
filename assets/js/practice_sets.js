// assets/js/practice_sets.js
document.addEventListener('DOMContentLoaded', function() {
    /**
     * 練習セット管理の初期化
     */
    function initPracticeSets() {
        const addSetBtn = document.getElementById('add-set-btn');
        const setsContainer = document.getElementById('sets-container');
        const setTemplate = document.getElementById('set-template');
        const noSetsMessage = document.getElementById('no-sets-message');
        const totalDistanceInput = document.getElementById('total_distance');

        if (!addSetBtn || !setsContainer || !setTemplate) return;

        // 現在のセット数
        let setCount = document.querySelectorAll('.set-item').length;

        // 既存のセットのイベントリスナーをセットアップ
        document.querySelectorAll('.set-item').forEach(setItem => {
            setupSetEventListeners(setItem);
        });

        // 合計距離の自動計算
        calculateTotalDistance();

        // セット追加ボタンのイベントリスナー
        addSetBtn.addEventListener('click', function() {
            addNewSet();
        });

        /**
         * 新しいセットを追加
         */
        function addNewSet() {
            // 「セットがない」メッセージを非表示
            if (noSetsMessage) {
                noSetsMessage.classList.add('hidden');
            }

            const newIndex = Date.now(); // 一意のインデックス（現在のタイムスタンプ）
            const newSetNumber = setCount + 1;

            // テンプレートのHTMLを取得
            let newSetHtml = setTemplate.innerHTML
                .replace(/__INDEX__/g, newIndex)
                .replace(/__NUMBER__/g, newSetNumber);

            // 新しいセット要素を作成
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = newSetHtml;
            const newSetElement = tempDiv.firstElementChild;

            // セットコンテナに追加
            setsContainer.appendChild(newSetElement);

            // セット数を更新
            setCount++;

            // イベントリスナーを設定
            setupSetEventListeners(newSetElement);

            // 新しいセットにスクロール
            newSetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        /**
         * セット項目のイベントリスナーを設定
         * @param {HTMLElement} setElement - セット要素
         */
        function setupSetEventListeners(setElement) {
            // 削除ボタン
            const removeBtn = setElement.querySelector('.remove-set-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    if (setCount <= 1) {
                        alert('少なくとも1つのセットが必要です。');
                        return;
                    }

                    if (confirm('このセットを削除してもよろしいですか？')) {
                        setElement.classList.add('fade-out');
                        // アニメーション後に要素を削除
                        setTimeout(() => {
                            setElement.remove();
                            setCount--;
                            updateSetNumbers();
                            calculateTotalDistance();
                            
                            // セットがなくなった場合のメッセージを表示
                            if (setCount === 0 && noSetsMessage) {
                                noSetsMessage.classList.remove('hidden');
                            }
                        }, 300);
                    }
                });
            }

            // 上に移動ボタン
            const moveUpBtn = setElement.querySelector('.move-up-btn');
            if (moveUpBtn) {
                moveUpBtn.addEventListener('click', function() {
                    const prevSet = setElement.previousElementSibling;
                    if (prevSet && prevSet.classList.contains('set-item')) {
                        // アニメーション効果を追加
                        setElement.classList.add('animate-move');
                        prevSet.classList.add('animate-move');
                        
                        setTimeout(() => {
                            setsContainer.insertBefore(setElement, prevSet);
                            setElement.classList.remove('animate-move');
                            prevSet.classList.remove('animate-move');
                            updateSetNumbers();
                        }, 150);
                    }
                });
            }

            // 下に移動ボタン
            const moveDownBtn = setElement.querySelector('.move-down-btn');
            if (moveDownBtn) {
                moveDownBtn.addEventListener('click', function() {
                    const nextSet = setElement.nextElementSibling;
                    if (nextSet && nextSet.classList.contains('set-item')) {
                        // アニメーション効果を追加
                        setElement.classList.add('animate-move');
                        nextSet.classList.add('animate-move');
                        
                        setTimeout(() => {
                            setsContainer.insertBefore(nextSet, setElement);
                            setElement.classList.remove('animate-move');
                            nextSet.classList.remove('animate-move');
                            updateSetNumbers();
                        }, 150);
                    }
                });
            }

            // 距離と回数の入力に対して、総距離の自動計算
            const distanceInput = setElement.querySelector('input[name$="[distance]"]');
            const repsInput = setElement.querySelector('input[name$="[repetitions]"]');
            const totalInput = setElement.querySelector('input[name$="[total_distance]"]');

            if (distanceInput && repsInput && totalInput) {
                const updateSetTotalDistance = function() {
                    const distance = parseInt(distanceInput.value) || 0;
                    const reps = parseInt(repsInput.value) || 1;
                    totalInput.value = distance * reps;

                    // 合計距離も更新
                    calculateTotalDistance();
                };

                distanceInput.addEventListener('input', updateSetTotalDistance);
                repsInput.addEventListener('input', updateSetTotalDistance);
            }

            // 器具選択の複数選択をカスタマイズ
            const equipmentSelect = setElement.querySelector('.equipment-select');
            if (equipmentSelect) {
                // 選択項目のクリック処理
                equipmentSelect.addEventListener('click', function(e) {
                    if (e.target.tagName === 'OPTION') {
                        e.preventDefault();
                        e.target.selected = !e.target.selected;
                        
                        // 選択状態をハイライト表示
                        if (e.target.selected) {
                            e.target.classList.add('bg-blue-100');
                        } else {
                            e.target.classList.remove('bg-blue-100');
                        }
                        
                        // 選択変更イベントを発火
                        const event = new Event('change', { bubbles: true });
                        this.dispatchEvent(event);
                    }
                });
                
                // 初期選択項目のスタイル設定
                Array.from(equipmentSelect.options).forEach(option => {
                    if (option.selected) {
                        option.classList.add('bg-blue-100');
                    }
                });
                
                // スタイルのカスタマイズ
                equipmentSelect.classList.add('custom-multiselect');
            }
        }

        /**
         * セット番号の更新
         */
        function updateSetNumbers() {
            document.querySelectorAll('.set-item').forEach((setItem, index) => {
                const setTitle = setItem.querySelector('.set-number');
                if (setTitle) {
                    setTitle.textContent = index + 1;
                }
                
                // フォーム送信用にインデックスを更新
                setItem.querySelectorAll('[name^="sets["]').forEach(input => {
                    const newName = input.name.replace(/sets\[\d+\]|sets\[__INDEX__\]/, 'sets[' + index + ']');
                    input.name = newName;
                });
                
                // ID属性の更新（チェックボックスとラベルの関連付け）
                setItem.querySelectorAll('[id^="set_"]').forEach(element => {
                    if (element.id.includes('_equipment_')) {
                        const newId = element.id.replace(/set_\d+_|set___INDEX___/, 'set_' + index + '_');
                        
                        // 関連するlabel要素も更新
                        const label = setItem.querySelector(`label[for="${element.id}"]`);
                        if (label) {
                            label.setAttribute('for', newId);
                        }
                        
                        element.id = newId;
                    }
                });
            });
        }

        /**
         * 全体の総距離を計算する
         */
        function calculateTotalDistance() {
            if (!totalDistanceInput) return;
            
            let sum = 0;
            document.querySelectorAll('.set-item input[name$="[total_distance]"]').forEach(input => {
                sum += parseInt(input.value) || 0;
            });
            
            totalDistanceInput.value = sum;
            
            // バリデーション表示
            if (sum > 0) {
                totalDistanceInput.classList.remove('border-red-500');
                totalDistanceInput.classList.add('border-green-500');
            } else {
                totalDistanceInput.classList.remove('border-green-500');
            }
        }

        /**
         * 複製ボタンの処理
         */
        const duplicateButtons = document.querySelectorAll('.duplicate-set-btn');
        duplicateButtons.forEach(button => {
            button.addEventListener('click', function() {
                const sourceSet = this.closest('.set-item');
                if (!sourceSet) return;
                
                // 新しいセットのインデックス
                const newIndex = Date.now();
                
                // 複製元のセットから値を取得
                const sourceValues = {
                    type_id: sourceSet.querySelector('[name$="[type_id]"]')?.value,
                    stroke_type: sourceSet.querySelector('[name$="[stroke_type]"]')?.value,
                    distance: sourceSet.querySelector('[name$="[distance]"]')?.value,
                    repetitions: sourceSet.querySelector('[name$="[repetitions]"]')?.value,
                    cycle: sourceSet.querySelector('[name$="[cycle]"]')?.value,
                    total_distance: sourceSet.querySelector('[name$="[total_distance]"]')?.value,
                    notes: sourceSet.querySelector('[name$="[notes]"]')?.value
                };
                
                // 複製元の器具選択状態を取得
                const selectedEquipment = [];
                sourceSet.querySelectorAll('[name$="[equipment][]"]:checked').forEach(checkbox => {
                    selectedEquipment.push(checkbox.value);
                });
                
                // テンプレートから新しいセットを作成
                let newSetHtml = setTemplate.innerHTML
                    .replace(/__INDEX__/g, newIndex)
                    .replace(/__NUMBER__/g, setCount + 1);
                
                // 新しいセット要素を作成
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = newSetHtml;
                const newSetElement = tempDiv.firstElementChild;
                
                // 値をセット
                newSetElement.querySelector('[name$="[type_id]"]').value = sourceValues.type_id || '';
                newSetElement.querySelector('[name$="[stroke_type]"]').value = sourceValues.stroke_type || 'freestyle';
                newSetElement.querySelector('[name$="[distance]"]').value = sourceValues.distance || '100';
                newSetElement.querySelector('[name$="[repetitions]"]').value = sourceValues.repetitions || '1';
                newSetElement.querySelector('[name$="[cycle]"]').value = sourceValues.cycle || '';
                newSetElement.querySelector('[name$="[total_distance]"]').value = sourceValues.total_distance || '100';
                newSetElement.querySelector('[name$="[notes]"]').value = sourceValues.notes || '';
                
                // 器具選択
                selectedEquipment.forEach(eqId => {
                    const checkbox = newSetElement.querySelector(`[name$="[equipment][]"][value="${eqId}"]`);
                    if (checkbox) checkbox.checked = true;
                });
                
                // 複製元の直後に新しいセットを挿入
                sourceSet.insertAdjacentElement('afterend', newSetElement);
                
                // セット数を更新
                setCount++;
                
                // イベントリスナーを設定
                setupSetEventListeners(newSetElement);
                
                // セット番号を更新
                updateSetNumbers();
                
                // 合計距離を更新
                calculateTotalDistance();
                
                // 新しいセットにスクロール
                newSetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // 追加エフェクト
                newSetElement.classList.add('highlight-new');
                setTimeout(() => {
                    newSetElement.classList.remove('highlight-new');
                }, 1000);
            });
        });

        /**
         * セットの折りたたみ機能
         */
        const toggleButtons = document.querySelectorAll('.toggle-set-btn');
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const setItem = this.closest('.set-item');
                const content = setItem.querySelector('.set-content');
                const icon = this.querySelector('i');
                
                if (content.classList.contains('hidden')) {
                    // 開く
                    content.classList.remove('hidden');
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    // 閉じる
                    content.classList.add('hidden');
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            });
        });

        /**
         * フォームの送信前検証
         */
        const practiceForm = document.getElementById('practice-form');
        if (practiceForm) {
            practiceForm.addEventListener('submit', function(e) {
                // 基本検証
                const practice_date = document.getElementById('practice_date').value;
                const total_distance = parseInt(document.getElementById('total_distance').value) || 0;
                
                if (!practice_date) {
                    e.preventDefault();
                    alert('練習日を入力してください。');
                    document.getElementById('practice_date').focus();
                    return;
                }
                
                if (total_distance <= 0) {
                    e.preventDefault();
                    alert('総距離を入力してください。');
                    document.getElementById('total_distance').focus();
                    return;
                }
                
                // セットの検証
                const sets = document.querySelectorAll('.set-item');
                if (sets.length === 0) {
                    e.preventDefault();
                    alert('少なくとも1つのセットを追加してください。');
                    return;
                }
                
                let hasInvalidSet = false;
                
                sets.forEach((setItem, index) => {
                    const distance = parseInt(setItem.querySelector('input[name$="[distance]"]').value) || 0;
                    const reps = parseInt(setItem.querySelector('input[name$="[repetitions]"]').value) || 0;
                    
                    if (distance <= 0 || reps <= 0) {
                        hasInvalidSet = true;
                        setItem.classList.add('border-red-500');
                    } else {
                        setItem.classList.remove('border-red-500');
                    }
                });
                
                if (hasInvalidSet) {
                    e.preventDefault();
                    alert('すべてのセットに正しい距離と回数を入力してください。');
                    return;
                }
                
                // フォーム送信前のインデックス更新
                updateSetNumbers();
            });
        }
    }

    /**
     * テンプレートを練習記録に適用する
     * @param {number} template_id - テンプレートID
     */
    window.applyTemplate = function(template_id) {
        if (!template_id) return;
        
        // テンプレート情報を取得
        fetch(`api/templates.php?template_id=${template_id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.template) {
                    applyTemplateToForm(data.template);
                } else {
                    console.error('テンプレート取得エラー:', data.error);
                    alert('テンプレートの取得に失敗しました。');
                }
            })
            .catch(error => {
                console.error('API呼び出しエラー:', error);
                alert('テンプレートの取得中にエラーが発生しました。');
            });
    };

    /**
     * テンプレートデータをフォームに適用する
     * @param {Object} template - テンプレートデータ
     */
    function applyTemplateToForm(template) {
        // 総距離を設定
        const totalDistanceInput = document.getElementById('total_distance');
        if (totalDistanceInput) {
            totalDistanceInput.value = template.total_distance;
        }
        
        // 既存のセットをクリア
        const setsContainer = document.getElementById('sets-container');
        if (setsContainer) {
            setsContainer.innerHTML = '';
        }
        
        // セットがなければ何もしない
        if (!template.sets || template.sets.length === 0) return;
        
        // テンプレート
        const setTemplate = document.getElementById('set-template');
        if (!setTemplate) return;
        
        // 「セットがない」メッセージを非表示
        const noSetsMessage = document.getElementById('no-sets-message');
        if (noSetsMessage) {
            noSetsMessage.classList.add('hidden');
        }
        
        // 各セットを追加
        template.sets.forEach((set, index) => {
            // 新しいセットのHTML生成
            const newIndex = Date.now() + index; // 一意のインデックス
            
            // テンプレートの置換
            let newSetHtml = setTemplate.innerHTML
                .replace(/__INDEX__/g, newIndex)
                .replace(/__NUMBER__/g, index + 1);
            
            // 新しいセット要素を作成
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = newSetHtml;
            const newSetElement = tempDiv.firstElementChild;
            
            // 値をセット
            newSetElement.querySelector('[name$="[type_id]"]').value = set.type_id || '';
            newSetElement.querySelector('[name$="[stroke_type]"]').value = set.stroke_type || 'freestyle';
            newSetElement.querySelector('[name$="[distance]"]').value = set.distance || '100';
            newSetElement.querySelector('[name$="[repetitions]"]').value = set.repetitions || '1';
            newSetElement.querySelector('[name$="[cycle]"]').value = set.cycle || '';
            newSetElement.querySelector('[name$="[total_distance]"]').value = set.total_distance || '100';
            newSetElement.querySelector('[name$="[notes]"]').value = set.notes || '';
            
            // 器具選択（template.equipment[set.set_id]から取得）
            const equipment = template.equipment[set.set_id] || [];
            equipment.forEach(eq => {
                const checkbox = newSetElement.querySelector(`[name$="[equipment][]"][value="${eq.equipment_id}"]`);
                if (checkbox) checkbox.checked = true;
            });
            
            // セットコンテナに追加
            setsContainer.appendChild(newSetElement);
        });
        
        // イベントリスナーを再設定
        document.querySelectorAll('.set-item').forEach(setItem => {
            setupSetEventListeners(setItem);
        });
        
        // 完了メッセージ
        addNotification('テンプレートを適用しました', 'success');
    }

    /**
     * 通知メッセージを表示
     * @param {string} message - 表示するメッセージ
     * @param {string} type - 通知タイプ ('success', 'error', 'info')
     */
    function addNotification(message, type = 'info') {
        const container = document.createElement('div');
        container.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg notification ${type === 'success' ? 'bg-green-100 text-green-800' : type === 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'}`;
        container.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(container);
        
        // 自動消去
        setTimeout(() => {
            container.style.opacity = '0';
            setTimeout(() => {
                container.remove();
            }, 500);
        }, 3000);
    }

    // 初期化関数を実行
    initPracticeSets();
});

// スタイルシートを動的に追加
(function() {
    const style = document.createElement('style');
    style.textContent = `
        .set-item {
            transition: all 0.3s ease;
        }
        
        .animate-move {
            transform: scale(0.98);
            opacity: 0.8;
        }
        
        .fade-out {
            opacity: 0;
            transform: translateX(20px);
        }
        
        .highlight-new {
            animation: highlight 1s ease;
        }
        
        @keyframes highlight {
            0%, 100% { background-color: transparent; }
            50% { background-color: rgba(59, 130, 246, 0.1); }
        }
        
        .custom-multiselect option {
            padding: 8px;
            margin: 2px 0;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .custom-multiselect option:checked {
            background-color: rgba(59, 130, 246, 0.2) !important;
            color: #1E40AF;
            font-weight: 500;
        }
    `;
    document.head.appendChild(style);
})();