// assets/js/competition_form.js - 統合版大会記録フォーム

document.addEventListener('DOMContentLoaded', function() {
    console.log("統合版大会記録フォームを初期化します");
    
    // 距離設定（泳法・プール種別別）
    const distanceOptions = {
        'butterfly': { 'SCM': [50, 100, 200], 'LCM': [50, 100, 200] },
        'backstroke': { 'SCM': [50, 100, 200], 'LCM': [50, 100, 200] },
        'breaststroke': { 'SCM': [50, 100, 200], 'LCM': [50, 100, 200] },
        'freestyle': { 'SCM': [50, 100, 200, 400, 800, 1500], 'LCM': [50, 100, 200, 400, 800, 1500] },
        'medley': { 'SCM': [100, 200, 400], 'LCM': [200, 400] } // 100m個人メドレーは短水路のみ
    };
    
    // 追加競技結果カウンター
    let additionalResultsCount = 0;
    
    // 初期化
    init();
    
    function init() {
        setupMainFormEventListeners();
        setupAdditionalResultsFeature();
        updateDistanceOptions();
        updateLapTimesInput();
    }
    
    /**
     * メインフォームのイベントリスナー設定
     */
    function setupMainFormEventListeners() {
        // プール種別・泳法変更時
        const poolTypeSelect = document.getElementById('pool_type');
        const strokeTypeSelect = document.getElementById('stroke_type');
        const distanceSelect = document.getElementById('distance_meters');
        
        if (poolTypeSelect) {
            poolTypeSelect.addEventListener('change', function() {
                updateDistanceOptions();
                updateLapTimesInput();
            });
        }
        
        if (strokeTypeSelect) {
            strokeTypeSelect.addEventListener('change', function() {
                updateDistanceOptions();
                updateLapTimesInput();
            });
        }
        
        if (distanceSelect) {
            distanceSelect.addEventListener('change', updateLapTimesInput);
        }
        
        // ラップ入力方式変更時
        const lapMethodRadios = document.querySelectorAll('input[name="lap_input_method"]');
        lapMethodRadios.forEach(radio => {
            radio.addEventListener('change', updateLapTimesInput);
        });
        
        // タイム入力フィールドの検証
        const finalTimeInput = document.getElementById('final_time');
        const reactionTimeInput = document.getElementById('reaction_time');
        
        if (finalTimeInput) {
            finalTimeInput.addEventListener('blur', validateTimeFormat);
            finalTimeInput.addEventListener('input', previewFormattedTime);
        }
        
        if (reactionTimeInput) {
            reactionTimeInput.addEventListener('blur', validateTimeFormat);
        }
        
        // フォーム送信時の検証
        const form = document.getElementById('unified-competition-form');
        if (form) {
            form.addEventListener('submit', validateUnifiedFormBeforeSubmit);
        }
    }
    
    /**
     * 追加競技結果機能の設定
     */
    function setupAdditionalResultsFeature() {
        const addMoreButton = document.getElementById('add-more-results');
        const container = document.getElementById('additional-results-container');
        const template = document.getElementById('additional-result-template');
        
        if (!addMoreButton || !container || !template) return;
        
        addMoreButton.addEventListener('click', function() {
            additionalResultsCount++;
            addAdditionalResult();
        });
        
        function addAdditionalResult() {
            const templateContent = template.content.cloneNode(true);
            const resultItem = templateContent.querySelector('.additional-result-item');
            
            // インデックスの置換
            const index = additionalResultsCount;
            resultItem.innerHTML = resultItem.innerHTML.replace(/INDEX/g, index);
            
            // 競技番号の更新
            const resultNumber = resultItem.querySelector('.result-number');
            if (resultNumber) {
                resultNumber.textContent = index + 1;
            }
            
            // 削除ボタンのイベントリスナー
            const removeButton = resultItem.querySelector('.remove-result');
            if (removeButton) {
                removeButton.addEventListener('click', function() {
                    if (confirm('この競技結果を削除してもよろしいですか？')) {
                        resultItem.remove();
                        updateResultNumbers();
                    }
                });
            }
            
            container.appendChild(resultItem);
            
            // 新しい要素のイベントリスナーを設定
            setupAdditionalResultEventListeners(resultItem, index);
            
            // 新しい要素にスクロール
            resultItem.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        function updateResultNumbers() {
            const resultItems = container.querySelectorAll('.additional-result-item');
            resultItems.forEach((item, index) => {
                const numberSpan = item.querySelector('.result-number');
                if (numberSpan) {
                    numberSpan.textContent = index + 2; // メイン結果が1なので+2
                }
            });
        }
    }
    
    /**
     * 追加競技結果のイベントリスナー設定
     */
    function setupAdditionalResultEventListeners(resultItem, index) {
        const poolTypeSelect = resultItem.querySelector('.pool-type-select');
        const strokeTypeSelect = resultItem.querySelector('.stroke-type-select');
        const distanceSelect = resultItem.querySelector('.distance-select');
        
        if (poolTypeSelect && strokeTypeSelect && distanceSelect) {
            poolTypeSelect.addEventListener('change', function() {
                updateAdditionalDistanceOptions(poolTypeSelect, strokeTypeSelect, distanceSelect);
            });
            
            strokeTypeSelect.addEventListener('change', function() {
                updateAdditionalDistanceOptions(poolTypeSelect, strokeTypeSelect, distanceSelect);
            });
        }
        
        // タイム入力の検証
        const timeInputs = resultItem.querySelectorAll('input[pattern]');
        timeInputs.forEach(input => {
            input.addEventListener('blur', validateTimeFormat);
        });
    }
    
    /**
     * 追加競技結果の距離オプション更新
     */
    function updateAdditionalDistanceOptions(poolTypeSelect, strokeTypeSelect, distanceSelect) {
        const poolType = poolTypeSelect.value;
        const strokeType = strokeTypeSelect.value;
        
        // 現在の選択をクリア
        distanceSelect.innerHTML = '<option value="">距離を選択</option>';
        
        if (!poolType || !strokeType) return;
        
        // 距離オプションを追加
        const distances = distanceOptions[strokeType] && distanceOptions[strokeType][poolType] ? 
                         distanceOptions[strokeType][poolType] : [];
        
        distances.forEach(distance => {
            const option = document.createElement('option');
            option.value = distance;
            option.textContent = `${distance}m`;
            distanceSelect.appendChild(option);
        });
    }
    
    /**
     * メイン競技の距離オプション更新
     */
    function updateDistanceOptions() {
        const poolTypeSelect = document.getElementById('pool_type');
        const strokeTypeSelect = document.getElementById('stroke_type');
        const distanceSelect = document.getElementById('distance_meters');
        
        if (!poolTypeSelect || !strokeTypeSelect || !distanceSelect) return;
        
        const poolType = poolTypeSelect.value;
        const strokeType = strokeTypeSelect.value;
        
        // 現在の選択をクリア
        distanceSelect.innerHTML = '<option value="">距離を選択</option>';
        
        if (!poolType || !strokeType) return;
        
        // 距離オプションを追加
        const distances = distanceOptions[strokeType] && distanceOptions[strokeType][poolType] ? 
                         distanceOptions[strokeType][poolType] : [];
        
        distances.forEach(distance => {
            const option = document.createElement('option');
            option.value = distance;
            option.textContent = `${distance}m`;
            distanceSelect.appendChild(option);
        });
    }
    
    /**
     * ラップタイム入力欄を更新
     */
    function updateLapTimesInput() {
        const lapTimesContainer = document.getElementById('lap_times_container');
        const distanceSelect = document.getElementById('distance_meters');
        const poolTypeSelect = document.getElementById('pool_type');
        
        if (!lapTimesContainer || !distanceSelect || !poolTypeSelect) return;
        
        const distance = parseInt(distanceSelect.value);
        const poolType = poolTypeSelect.value;
        const inputMethod = document.querySelector('input[name="lap_input_method"]:checked')?.value || 'split';
        
        // コンテナをクリア
        lapTimesContainer.innerHTML = '';
        
        if (!distance || !poolType) return;
        
        // ラップ距離を決定
        const lapDistance = poolType === 'SCM' ? 25 : 50;
        const numberOfLaps = distance / lapDistance;
        
        if (!Number.isInteger(numberOfLaps) || numberOfLaps <= 1) {
            lapTimesContainer.innerHTML = '<p class="text-gray-500 text-sm">この種目ではラップタイムの入力は不要です。</p>';
            return;
        }
        
        // ヘッダーを追加
        const header = document.createElement('div');
        header.className = 'mb-4';
        header.innerHTML = `
            <h4 class="font-medium mb-2">ラップタイム（任意）</h4>
            <p class="text-sm text-gray-600">
                ${inputMethod === 'split' ? 
                    `各${lapDistance}mのタイムを入力（例：26.50, 28.30）` : 
                    `その時点での合計タイムを入力（例：26.50, 54.80）`
                }
            </p>
        `;
        lapTimesContainer.appendChild(header);
        
        // 入力欄のコンテナ
        const inputsContainer = document.createElement('div');
        inputsContainer.className = 'grid grid-cols-2 md:grid-cols-4 gap-3';
        
        // 各ラップの入力欄を生成
        for (let i = 1; i <= numberOfLaps; i++) {
            const inputGroup = document.createElement('div');
            inputGroup.innerHTML = `
                <label class="block text-xs text-gray-600 mb-1">
                    ${i * lapDistance}m ${inputMethod === 'split' ? 'ラップ' : '通過'}
                </label>
                <input 
                    type="text" 
                    name="lap_times[]"
                    placeholder="${inputMethod === 'split' ? '26.50' : (i === 1 ? '26.50' : '54.80')}"
                    pattern="^(\\d{1,2}:)?\\d{1,2}\\.\\d{2}$"
                    title="タイム形式: 秒.1/100秒 または 分:秒.1/100秒"
                    class="lap-time-input w-full text-sm border border-gray-300 rounded px-2 py-1"
                    data-lap="${i}"
                    data-distance="${i * lapDistance}"
                >
            `;
            inputsContainer.appendChild(inputGroup);
        }
        
        lapTimesContainer.appendChild(inputsContainer);
        
        // 一貫性分析表示エリアを追加
        const consistencyDiv = document.createElement('div');
        consistencyDiv.id = 'lap_consistency';
        lapTimesContainer.appendChild(consistencyDiv);
        
        // プレビューエリアを追加
        const previewDiv = document.createElement('div');
        previewDiv.id = 'lap-preview';
        lapTimesContainer.appendChild(previewDiv);
        
        // ラップタイム入力欄にイベントリスナーを追加
        setupLapTimeValidation();
    }
    
    /**
     * ラップタイム入力の検証を設定
     */
    function setupLapTimeValidation() {
        const lapInputs = document.querySelectorAll('.lap-time-input');
        
        lapInputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateTimeFormat.call(this);
                calculateConsistency();
            });
            
            input.addEventListener('input', function() {
                updateLapTimePreview();
            });
        });
    }
    
    /**
     * タイム形式の検証
     */
    function validateTimeFormat() {
        const timeValue = this.value.trim();
        if (!timeValue) return;
        
        const timePattern = /^(\d{1,2}:)?\d{1,2}\.\d{2}$/;
        
        if (!timePattern.test(timeValue)) {
            this.classList.add('border-red-500');
            showFieldError(this, 'タイム形式が正しくありません（例: 23.45 または 1:23.45）');
        } else {
            this.classList.remove('border-red-500');
            hideFieldError(this);
            this.value = normalizeTimeFormat(timeValue);
        }
    }
    
    /**
     * タイム形式の正規化
     */
    function normalizeTimeFormat(timeStr) {
        const parts = timeStr.split('.');
        if (parts[1] && parts[1].length === 1) {
            parts[1] = parts[1] + '0';
        }
        return parts.join('.');
    }
    
    /**
     * フォーマット済みタイムのプレビュー
     */
    function previewFormattedTime() {
        const finalTimeInput = document.getElementById('final_time');
        if (!finalTimeInput) return;
        
        const timeValue = finalTimeInput.value.trim();
        let preview = document.getElementById('time-preview');
        
        if (!preview) {
            preview = document.createElement('div');
            preview.id = 'time-preview';
            finalTimeInput.parentNode.appendChild(preview);
        }
        
        if (timeValue && /^(\d{1,2}:)?\d{1,2}\.\d{1,2}$/.test(timeValue)) {
            const normalized = normalizeTimeFormat(timeValue);
            const centiseconds = parseTimeStringToCentiseconds(normalized);
            
            preview.textContent = `= ${formatCentisecondsToTime(centiseconds)}`;
            preview.className = 'text-sm text-green-600 mt-1';
        } else {
            preview.textContent = '';
        }
    }
    
    /**
     * ラップタイムの一貫性を計算
     */
    function calculateConsistency() {
        const lapInputs = document.querySelectorAll('.lap-time-input');
        const consistencyDiv = document.getElementById('lap_consistency');
        
        if (!consistencyDiv || lapInputs.length < 2) return;
        
        const times = [];
        lapInputs.forEach(input => {
            if (input.value.trim()) {
                try {
                    times.push(parseTimeStringToCentiseconds(input.value.trim()));
                } catch (e) {
                    // 無効なタイムは無視
                }
            }
        });
        
        if (times.length < 2) {
            consistencyDiv.innerHTML = '';
            return;
        }
        
        const inputMethod = document.querySelector('input[name="lap_input_method"]:checked')?.value || 'split';
        let lapTimes = [];
        
        if (inputMethod === 'split') {
            lapTimes = times;
        } else {
            for (let i = 0; i < times.length; i++) {
                lapTimes.push(i === 0 ? times[i] : times[i] - times[i - 1]);
            }
        }
        
        // 統計計算
        const avg = lapTimes.reduce((a, b) => a + b, 0) / lapTimes.length;
        const fastest = Math.min(...lapTimes);
        const slowest = Math.max(...lapTimes);
        const variance = lapTimes.reduce((sum, time) => sum + Math.pow(time - avg, 2), 0) / lapTimes.length;
        const stdDev = Math.sqrt(variance);
        
        consistencyDiv.innerHTML = `
            <div class="mt-3 p-3 bg-blue-50 rounded-lg text-sm">
                <h5 class="font-medium mb-2">ラップ分析</h5>
                <div class="grid grid-cols-2 gap-2">
                    <div>平均: ${formatCentisecondsToTime(Math.round(avg))}</div>
                    <div>最速: ${formatCentisecondsToTime(fastest)}</div>
                    <div>最遅: ${formatCentisecondsToTime(slowest)}</div>
                    <div>標準偏差: ${(stdDev / 100).toFixed(2)}秒</div>
                </div>
            </div>
        `;
    }
    
    /**
     * ラップタイムプレビューを更新
     */
    function updateLapTimePreview() {
        const inputMethod = document.querySelector('input[name="lap_input_method"]:checked')?.value || 'split';
        const lapInputs = document.querySelectorAll('.lap-time-input');
        let previewContainer = document.getElementById('lap-preview');
        
        if (!previewContainer || lapInputs.length === 0) return;
        
        const times = [];
        lapInputs.forEach(input => {
            if (input.value.trim()) {
                try {
                    times.push({
                        value: input.value.trim(),
                        centiseconds: parseTimeStringToCentiseconds(input.value.trim()),
                        distance: parseInt(input.dataset.distance)
                    });
                } catch (e) {
                    // 無効なタイム形式は無視
                }
            }
        });
        
        if (times.length === 0) {
            previewContainer.innerHTML = '';
            return;
        }
        
        let previewHtml = '<div class="mt-3 p-3 bg-gray-50 rounded-lg text-sm">';
        previewHtml += '<h5 class="font-medium mb-2">タイムプレビュー</h5>';
        previewHtml += '<div class="grid grid-cols-2 gap-4">';
        
        if (inputMethod === 'split') {
            previewHtml += '<div><strong>累積タイム：</strong><br>';
            let cumulative = 0;
            times.forEach((time, index) => {
                cumulative += time.centiseconds;
                previewHtml += `${time.distance}m: ${formatCentisecondsToTime(cumulative)}<br>`;
            });
            previewHtml += '</div>';
            
            previewHtml += '<div><strong>ラップタイム：</strong><br>';
            times.forEach(time => {
                previewHtml += `${time.distance}m: ${formatCentisecondsToTime(time.centiseconds)}<br>`;
            });
            previewHtml += '</div>';
        } else {
            previewHtml += '<div><strong>累積タイム：</strong><br>';
            times.forEach(time => {
                previewHtml += `${time.distance}m: ${formatCentisecondsToTime(time.centiseconds)}<br>`;
            });
            previewHtml += '</div>';
            
            previewHtml += '<div><strong>ラップタイム：</strong><br>';
            times.forEach((time, index) => {
                const lapTime = index === 0 ? time.centiseconds : time.centiseconds - times[index - 1].centiseconds;
                previewHtml += `${time.distance}m: ${formatCentisecondsToTime(lapTime)}<br>`;
            });
            previewHtml += '</div>';
        }
        
        previewHtml += '</div></div>';
        previewContainer.innerHTML = previewHtml;
    }
    
    /**
     * 統合フォーム送信前の検証
     */
    function validateUnifiedFormBeforeSubmit(event) {
        console.log('統合フォーム送信前検証を実行');
        
        let isValid = true;
        const errors = [];
        
        // 大会情報の必須フィールド
        const competitionName = document.getElementById('competition_name');
        const competitionDate = document.getElementById('competition_date');
        
        if (!competitionName || !competitionName.value.trim()) {
            errors.push('大会名は必須です。');
            if (competitionName) competitionName.classList.add('border-red-500');
            isValid = false;
        }
        
        if (!competitionDate || !competitionDate.value) {
            errors.push('開催日は必須です。');
            if (competitionDate) competitionDate.classList.add('border-red-500');
            isValid = false;
        }
        
        // メイン競技結果の必須フィールド
        const requiredFields = [
            { element: document.getElementById('final_time'), name: '最終タイム' },
            { element: document.getElementById('stroke_type'), name: '泳法' },
            { element: document.getElementById('distance_meters'), name: '距離' },
            { element: document.getElementById('pool_type'), name: 'プール種別' },
            { element: document.getElementById('event_name'), name: '種目名' },
            { element: document.getElementById('record_type'), name: '記録種別' }
        ];
        
        requiredFields.forEach(field => {
            if (!field.element || !field.element.value.trim()) {
                errors.push(`${field.name}は必須です。`);
                if (field.element) {
                    field.element.classList.add('border-red-500');
                }
                isValid = false;
            } else if (field.element) {
                field.element.classList.remove('border-red-500');
            }
        });
        
        // タイム形式の検証
        const timeInputs = document.querySelectorAll('input[pattern]');
        timeInputs.forEach((input, index) => {
            if (input.value.trim()) {
                const pattern = input.getAttribute('pattern');
                const regex = new RegExp(pattern);
                if (!regex.test(input.value.trim())) {
                    errors.push(`タイム入力 ${index + 1}の形式が正しくありません。`);
                    input.classList.add('border-red-500');
                    isValid = false;
                } else {
                    input.classList.remove('border-red-500');
                }
            }
        });
        
        // 追加競技結果の検証
        const additionalResults = document.querySelectorAll('.additional-result-item');
        additionalResults.forEach((item, index) => {
            const requiredInputs = item.querySelectorAll('[required]');
            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    errors.push(`追加競技 ${index + 1}の${input.previousElementSibling.textContent}は必須です。`);
                    input.classList.add('border-red-500');
                    isValid = false;
                } else {
                    input.classList.remove('border-red-500');
                }
            });
        });
        
        if (!isValid) {
            event.preventDefault();
            showFormErrors(errors);
            
            // 最初のエラーフィールドにフォーカス
            const firstErrorField = document.querySelector('.border-red-500');
            if (firstErrorField) {
                firstErrorField.focus();
                firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        return isValid;
    }
    
    /**
     * フォームエラーを表示
     */
    function showFormErrors(errors) {
        const existingError = document.querySelector('.form-error-message');
        if (existingError) existingError.remove();
        
        if (errors.length === 0) return;
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'form-error-message bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
        errorDiv.innerHTML = `
            <h4 class="font-medium mb-2">入力エラーがあります：</h4>
            <ul class="list-disc list-inside space-y-1">
                ${errors.map(error => `<li>${error}</li>`).join('')}
            </ul>
        `;
        
        const form = document.getElementById('unified-competition-form');
        if (form) {
            form.insertBefore(errorDiv, form.firstChild);
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
    
    /**
     * フィールドエラーを表示
     */
    function showFieldError(field, message) {
        hideFieldError(field);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error text-red-600 text-xs mt-1';
        errorDiv.textContent = message;
        
        field.parentNode.appendChild(errorDiv);
    }
    
    /**
     * フィールドエラーを非表示
     */
    function hideFieldError(field) {
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    }
    
    /**
     * 時間文字列を1/100秒に変換
     */
    function parseTimeStringToCentiseconds(timeStr) {
        const trimmed = timeStr.trim();
        
        if (trimmed.includes(':')) {
            const [minutes, secondsPart] = trimmed.split(':');
            const [seconds, centiseconds] = secondsPart.split('.');
            return parseInt(minutes) * 6000 + parseInt(seconds) * 100 + parseInt(centiseconds);
        } else {
            const [seconds, centiseconds] = trimmed.split('.');
            return parseInt(seconds) * 100 + parseInt(centiseconds);
        }
    }
    
    /**
     * 1/100秒を時間文字列に変換
     */
    function formatCentisecondsToTime(centiseconds) {
        const minutes = Math.floor(centiseconds / 6000);
        const seconds = Math.floor((centiseconds % 6000) / 100);
        const cs = centiseconds % 100;
        
        if (minutes > 0) {
            return `${minutes}:${seconds.toString().padStart(2, '0')}.${cs.toString().padStart(2, '0')}`;
        } else {
            return `${seconds}.${cs.toString().padStart(2, '0')}`;
        }
    }
    
    // 既存の関数（進歩グラフ、ラップタイム表示など）はそのまま保持
    // showProgressChart, showLapTimes, filterResults なども継続使用

    
    /**
     * 進歩グラフを表示
     */
    window.showProgressChart = function(strokeType, distance, poolType) {
        console.log(`進歩グラフを表示: ${strokeType} ${distance}m ${poolType}`);
        
        // モーダルを作成
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-4xl w-full mx-4 max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">${getStrokeName(strokeType)} ${distance}m ${poolType} の進歩グラフ</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="progress-chart-container" class="h-96">
                    <div class="flex items-center justify-center h-full">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                        <span class="ml-3">データを読み込み中...</span>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // データを取得してグラフを描画
        fetch(`api/competition.php?action=get_progress_chart&stroke_type=${strokeType}&distance_meters=${distance}&pool_type=${poolType}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.chart_data.length > 0) {
                    drawProgressChart(data.chart_data, data.event_info);
                } else {
                    document.getElementById('progress-chart-container').innerHTML = `
                        <div class="text-center py-8">
                            <p class="text-gray-500">この種目の記録がまだありません。</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('進歩グラフデータ取得エラー:', error);
                document.getElementById('progress-chart-container').innerHTML = `
                    <div class="text-center py-8">
                        <p class="text-red-500">データの取得中にエラーが発生しました。</p>
                    </div>
                `;
            });
    };
    
    /**
     * 進歩グラフを描画
     */
    function drawProgressChart(chartData, eventInfo) {
        const container = document.getElementById('progress-chart-container');
        container.innerHTML = '<canvas id="progress-chart"></canvas>';
        
        const ctx = document.getElementById('progress-chart').getContext('2d');
        
        // データの準備
        const labels = chartData.map(item => {
            const date = new Date(item.date);
            return `${date.getMonth() + 1}/${date.getDate()}`;
        });
        
        const timeData = chartData.map(item => item.time_centiseconds / 100); // 秒単位に変換
        
        // グラフの設定
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'タイム (秒)',
                    data: timeData,
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    pointBackgroundColor: chartData.map(item => item.is_personal_best ? '#dc2626' : '#3b82f6'),
                    pointBorderColor: chartData.map(item => item.is_personal_best ? '#dc2626' : '#3b82f6'),
                    pointRadius: chartData.map(item => item.is_personal_best ? 6 : 4),
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        reverse: true, // 速いタイムが上に来るように
                        title: {
                            display: true,
                            text: 'タイム (秒)'
                        },
                        ticks: {
                            callback: function(value) {
                                return formatSecondsToTimeString(value);
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: '日付'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const item = chartData[context.dataIndex];
                                let label = `タイム: ${item.formatted_time}`;
                                
                                if (item.is_personal_best) {
                                    label += ' (自己ベスト)';
                                }
                                
                                if (item.rank) {
                                    label += ` | 順位: ${item.rank}位`;
                                }
                                
                                return label;
                            },
                            afterLabel: function(context) {
                                const item = chartData[context.dataIndex];
                                return [
                                    `大会: ${item.competition_name}`,
                                    `記録種別: ${getRecordTypeName(item.record_type)}`,
                                    `公式記録: ${item.is_official ? 'はい' : 'いいえ'}`
                                ];
                            }
                        }
                    },
                    legend: {
                        display: true,
                        labels: {
                            generateLabels: function() {
                                return [
                                    {
                                        text: '通常記録',
                                        fillStyle: '#3b82f6',
                                        strokeStyle: '#3b82f6',
                                        pointStyle: 'circle'
                                    },
                                    {
                                        text: '自己ベスト',
                                        fillStyle: '#dc2626',
                                        strokeStyle: '#dc2626',
                                        pointStyle: 'circle'
                                    }
                                ];
                            }
                        }
                    }
                }
            }
        });
    }
    
    /**
     * 秒を時間文字列に変換（グラフ用）
     */
    function formatSecondsToTimeString(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = (seconds % 60).toFixed(2);
        
        if (minutes > 0) {
            return `${minutes}:${remainingSeconds.padStart(5, '0')}`;
        } else {
            return remainingSeconds;
        }
    }
    
    /**
     * 泳法名を取得
     */
    function getStrokeName(strokeType) {
        const strokeNames = {
            'butterfly': 'バタフライ',
            'backstroke': '背泳ぎ',
            'breaststroke': '平泳ぎ',
            'freestyle': '自由形',
            'medley': '個人メドレー'
        };
        return strokeNames[strokeType] || strokeType;
    }
    
    /**
     * 記録種別名を取得
     */
    function getRecordTypeName(recordType) {
        const recordTypeNames = {
            'competition': '公式大会',
            'time_trial': 'タイム測定会',
            'practice': '練習記録',
            'relay_split': 'リレーのスプリット'
        };
        return recordTypeNames[recordType] || recordType;
    }
    
    // グローバルスコープに関数を公開
    window.enhancedCompetitionForm = {
        validateTimeFormat,
        parseTimeStringToCentiseconds,
        formatCentisecondsToTime,
        showProgressChart
    };
});

// ラップタイム表示モーダル
function showLapTimes(resultId) {
    // APIからラップタイムデータを取得
    fetch(`api/competition.php?action=get_lap_times&result_id=${resultId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLapTimesModal(data.lap_times, data.result_info);
            } else {
                alert('ラップタイムの取得に失敗しました。');
            }
        })
        .catch(error => {
            console.error('ラップタイム取得エラー:', error);
            alert('ラップタイムデータの取得中にエラーが発生しました。');
        });
}

function displayLapTimesModal(lapTimes, resultInfo) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    
    let lapTableHtml = '<table class="min-w-full text-sm"><thead><tr class="bg-gray-50"><th class="py-2 px-3 text-left">距離</th><th class="py-2 px-3 text-left">ラップタイム</th><th class="py-2 px-3 text-left">スプリット</th></tr></thead><tbody>';
    
    lapTimes.forEach((lap, index) => {
        lapTableHtml += `
            <tr class="border-b">
                <td class="py-2 px-3">${lap.distance_meters}m</td>
                <td class="py-2 px-3 font-medium">${formatTimeFromCentiseconds(lap.lap_time_centiseconds)}</td>
                <td class="py-2 px-3">${formatTimeFromCentiseconds(lap.split_time_centiseconds)}</td>
            </tr>
        `;
    });
    
    lapTableHtml += '</tbody></table>';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">${resultInfo.event_display} - ラップタイム</h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mb-4">
                <p class="text-sm text-gray-600">最終タイム: <span class="font-medium">${resultInfo.final_time}</span></p>
            </div>
            <div class="overflow-x-auto">
                ${lapTableHtml}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function formatTimeFromCentiseconds(centiseconds) {
    const minutes = Math.floor(centiseconds / 6000);
    const seconds = Math.floor((centiseconds % 6000) / 100);
    const cs = centiseconds % 100;
    
    if (minutes > 0) {
        return `${minutes}:${seconds.toString().padStart(2, '0')}.${cs.toString().padStart(2, '0')}`;
    } else {
        return `${seconds}.${cs.toString().padStart(2, '0')}`;
    }
}

// 結果フィルター機能
function filterResults(type) {
    const rows = document.querySelectorAll('.result-row');
    const buttons = document.querySelectorAll('.filter-btn');
    
    // ボタンのアクティブ状態更新
    buttons.forEach(btn => {
        btn.classList.remove('active', 'bg-blue-500', 'text-white');
        btn.classList.add('border-gray-300', 'text-gray-700');
    });
    
    event.target.classList.add('active', 'bg-blue-500', 'text-white');
    event.target.classList.remove('border-gray-300', 'text-gray-700');
    
    // 行の表示/非表示
    rows.forEach(row => {
        let show = true;
        
        switch(type) {
            case 'official':
                show = row.dataset.official === 'true';
                break;
            case 'personal_best':
                show = row.dataset.personalBest === 'true';
                break;
            case 'all':
            default:
                show = true;
                break;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

// エクスポート機能
function exportResults() {
    const competitionId = new URLSearchParams(window.location.search).get('id');
    if (competitionId) {
        window.open(`api/export_results.php?competition_id=${competitionId}&format=csv`, '_blank');
    }
}