// enhanced_competition_form.js - 改良版大会記録フォーム

document.addEventListener('DOMContentLoaded', function() {
    console.log("改良版大会記録フォームを初期化します");
    
    // フォーム要素の取得
    const poolTypeSelect = document.getElementById('pool_type');
    const strokeTypeSelect = document.getElementById('stroke_type');
    const distanceSelect = document.getElementById('distance_meters');
    const finalTimeInput = document.getElementById('final_time');
    const reactionTimeInput = document.getElementById('reaction_time');
    const lapInputMethodSelect = document.getElementById('lap_input_method');
    const lapTimesContainer = document.getElementById('lap_times_container');
    const isOfficialCheckbox = document.getElementById('is_official');
    const recordTypeSelect = document.getElementById('record_type');
    
    // イベント設定データ
    let eventConfigurations = [];
    
    // 初期化
    init();
    
    function init() {
        // 種目設定を読み込み
        loadEventConfigurations();
        
        // イベントリスナーの設定
        setupEventListeners();
        
        // 初期状態の設定
        updateDistanceOptions();
        updateLapTimesInput();
    }
    
    /**
     * 種目設定を読み込む
     */
    function loadEventConfigurations() {
        fetch('api/enhanced_competition.php?action=get_events')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    eventConfigurations = data.events;
                    console.log('種目設定を読み込みました:', eventConfigurations.length, '件');
                    updateDistanceOptions();
                } else {
                    console.error('種目設定の読み込みに失敗:', data.error);
                }
            })
            .catch(error => {
                console.error('API呼び出しエラー:', error);
            });
    }
    
    /**
     * イベントリスナーの設定
     */
    function setupEventListeners() {
        // プール種別変更時
        if (poolTypeSelect) {
            poolTypeSelect.addEventListener('change', updateDistanceOptions);
        }
        
        // 泳法変更時
        if (strokeTypeSelect) {
            strokeTypeSelect.addEventListener('change', updateDistanceOptions);
        }
        
        // 距離変更時
        if (distanceSelect) {
            distanceSelect.addEventListener('change', updateLapTimesInput);
        }
        
        // ラップ入力方式変更時
        if (lapInputMethodSelect) {
            lapInputMethodSelect.addEventListener('change', updateLapTimesInput);
        }
        
        // タイム入力フィールドのフォーマット検証
        if (finalTimeInput) {
            finalTimeInput.addEventListener('blur', validateTimeFormat);
            finalTimeInput.addEventListener('input', previewFormattedTime);
        }
        
        if (reactionTimeInput) {
            reactionTimeInput.addEventListener('blur', validateTimeFormat);
        }
        
        // 公式記録チェックボックス
        if (isOfficialCheckbox) {
            isOfficialCheckbox.addEventListener('change', updateRecordTypeOptions);
        }
        
        // フォーム送信時の検証
        const form = document.getElementById('competition-result-form');
        if (form) {
            form.addEventListener('submit', validateFormBeforeSubmit);
        }
    }
    
    /**
     * 距離オプションを更新
     */
    function updateDistanceOptions() {
        if (!poolTypeSelect || !strokeTypeSelect || !distanceSelect) return;
        
        const poolType = poolTypeSelect.value;
        const strokeType = strokeTypeSelect.value;
        
        console.log(`距離オプション更新: pool=${poolType}, stroke=${strokeType}`);
        
        // 現在の選択をクリア
        distanceSelect.innerHTML = '<option value="">距離を選択</option>';
        
        if (!poolType || !strokeType) return;
        
        // 該当する種目設定を絞り込み
        const validDistances = eventConfigurations
            .filter(config => config.pool_type === poolType && config.stroke_type === strokeType)
            .map(config => ({
                distance: config.distance_meters,
                display: config.display_name
            }))
            .sort((a, b) => a.distance - b.distance);
        
        console.log('有効な距離:', validDistances);
        
        // オプションを追加
        validDistances.forEach(item => {
            const option = document.createElement('option');
            option.value = item.distance;
            option.textContent = `${item.distance}m`;
            distanceSelect.appendChild(option);
        });
        
        // 距離が選択されたらラップタイム入力を更新
        updateLapTimesInput();
    }
    
    /**
     * ラップタイム入力欄を更新
     */
    function updateLapTimesInput() {
        if (!lapTimesContainer || !distanceSelect || !poolTypeSelect) return;
        
        const distance = parseInt(distanceSelect.value);
        const poolType = poolTypeSelect.value;
        const inputMethod = lapInputMethodSelect ? lapInputMethodSelect.value : 'split';
        
        // コンテナをクリア
        lapTimesContainer.innerHTML = '';
        
        if (!distance || !poolType) return;
        
        // ラップ距離を決定
        const lapDistance = poolType === 'SCM' ? 25 : 50;
        const numberOfLaps = distance / lapDistance;
        
        if (!Number.isInteger(numberOfLaps) || numberOfLaps <= 1) {
            // ラップタイム入力不要な場合
            lapTimesContainer.innerHTML = '<p class="text-gray-500 text-sm">この種目ではラップタイムの入力は不要です。</p>';
            return;
        }
        
        console.log(`ラップタイム入力欄生成: ${numberOfLaps}ラップ, ${lapDistance}m間隔, 方式=${inputMethod}`);
        
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
                    pattern="^(\d{1,2}:)?\d{1,2}\.\d{2}$"
                    title="タイム形式: 秒.1/100秒 または 分:秒.1/100秒"
                    class="lap-time-input w-full text-sm border border-gray-300 rounded px-2 py-1"
                    data-lap="${i}"
                    data-distance="${i * lapDistance}"
                >
            `;
            inputsContainer.appendChild(inputGroup);
        }
        
        lapTimesContainer.appendChild(inputsContainer);
        
        // ラップタイム入力欄にもイベントリスナーを追加
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
                // リアルタイムで入力方式の相互変換
                if (this.value && lapInputMethodSelect) {
                    updateLapTimePreview();
                }
            });
        });
    }
    
    /**
     * タイム形式の検証
     */
    function validateTimeFormat() {
        const timeValue = this.value.trim();
        if (!timeValue) return; // 空の場合はスキップ
        
        const timePattern = /^(\d{1,2}:)?\d{1,2}\.\d{2}$/;
        
        if (!timePattern.test(timeValue)) {
            this.classList.add('border-red-500');
            showFieldError(this, 'タイム形式が正しくありません（例: 23.45 または 1:23.45）');
        } else {
            this.classList.remove('border-red-500');
            hideFieldError(this);
            
            // 正しい形式の場合、値を正規化
            this.value = normalizeTimeFormat(timeValue);
        }
    }
    
    /**
     * タイム形式の正規化
     */
    function normalizeTimeFormat(timeStr) {
        // 1/100秒が1桁の場合は0埋め
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
        if (!finalTimeInput) return;
        
        const timeValue = finalTimeInput.value.trim();
        const preview = document.getElementById('time-preview');
        
        if (timeValue && /^(\d{1,2}:)?\d{1,2}\.\d{1,2}$/.test(timeValue)) {
            const normalized = normalizeTimeFormat(timeValue);
            const centiseconds = parseTimeStringToCentiseconds(normalized);
            
            if (preview) {
                preview.textContent = `= ${formatCentisecondsToTime(centiseconds)}`;
                preview.className = 'text-sm text-green-600 mt-1';
            }
        } else if (preview) {
            preview.textContent = '';
        }
    }
    
    /**
     * ラップタイムの一貫性を計算
     */
    function calculateConsistency() {
        const lapInputs = document.querySelectorAll('.lap-time-input');
        const consistencyDiv = document.getElementById('lap-consistency');
        
        if (!consistencyDiv || lapInputs.length < 2) return;
        
        const times = [];
        lapInputs.forEach(input => {
            if (input.value.trim()) {
                times.push(parseTimeStringToCentiseconds(input.value.trim()));
            }
        });
        
        if (times.length < 2) {
            consistencyDiv.innerHTML = '';
            return;
        }
        
        const inputMethod = lapInputMethodSelect ? lapInputMethodSelect.value : 'split';
        let lapTimes = [];
        
        if (inputMethod === 'split') {
            lapTimes = times;
        } else {
            // 累積タイムからラップタイムを計算
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
     * 記録種別オプションを更新
     */
    function updateRecordTypeOptions() {
        if (!recordTypeSelect || !isOfficialCheckbox) return;
        
        const isOfficial = isOfficialCheckbox.checked;
        
        // オプションをクリア
        recordTypeSelect.innerHTML = '';
        
        if (isOfficial) {
            recordTypeSelect.innerHTML = `
                <option value="competition">公式大会</option>
                <option value="time_trial">公式タイム測定会</option>
            `;
        } else {
            recordTypeSelect.innerHTML = `
                <option value="practice">練習中の計測</option>
                <option value="relay_split">リレーのスプリット</option>
                <option value="time_trial">非公式タイム測定</option>
            `;
        }
    }
    
    /**
     * フォーム送信前の検証
     */
    function validateFormBeforeSubmit(event) {
        console.log('フォーム送信前検証を実行');
        
        let isValid = true;
        const errors = [];
        
        // 必須フィールドの検証
        const requiredFields = [
            { element: finalTimeInput, name: '最終タイム' },
            { element: strokeTypeSelect, name: '泳法' },
            { element: distanceSelect, name: '距離' },
            { element: poolTypeSelect, name: 'プール種別' }
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
        if (finalTimeInput && finalTimeInput.value) {
            const timePattern = /^(\d{1,2}:)?\d{1,2}\.\d{2}$/;
            if (!timePattern.test(finalTimeInput.value.trim())) {
                errors.push('最終タイムの形式が正しくありません。');
                finalTimeInput.classList.add('border-red-500');
                isValid = false;
            }
        }
        
        // ラップタイムの検証
        const lapInputs = document.querySelectorAll('.lap-time-input');
        lapInputs.forEach((input, index) => {
            if (input.value.trim()) {
                const timePattern = /^(\d{1,2}:)?\d{1,2}\.\d{2}$/;
                if (!timePattern.test(input.value.trim())) {
                    errors.push(`ラップタイム ${index + 1}の形式が正しくありません。`);
                    input.classList.add('border-red-500');
                    isValid = false;
                } else {
                    input.classList.remove('border-red-500');
                }
            }
        });
        
        if (!isValid) {
            event.preventDefault();
            
            // エラーメッセージを表示
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
        // 既存のエラーメッセージを削除
        const existingError = document.querySelector('.form-error-message');
        if (existingError) {
            existingError.remove();
        }
        
        if (errors.length === 0) return;
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'form-error-message bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
        errorDiv.innerHTML = `
            <h4 class="font-medium mb-2">入力エラーがあります：</h4>
            <ul class="list-disc list-inside space-y-1">
                ${errors.map(error => `<li>${error}</li>`).join('')}
            </ul>
        `;
        
        // フォームの先頭に挿入
        const form = document.getElementById('competition-result-form');
        if (form) {
            form.insertBefore(errorDiv, form.firstChild);
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
    
    /**
     * フィールドエラーを表示
     */
    function showFieldError(field, message) {
        hideFieldError(field); // 既存のエラーを削除
        
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
    
    /**
     * ラップタイムプレビューを更新
     */
    function updateLapTimePreview() {
        if (!lapInputMethodSelect) return;
        
        const inputMethod = lapInputMethodSelect.value;
        const lapInputs = document.querySelectorAll('.lap-time-input');
        const previewContainer = document.getElementById('lap-preview');
        
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
        
        // 入力方式に応じてプレビューを生成
        let previewHtml = '<div class="mt-3 p-3 bg-gray-50 rounded-lg text-sm">';
        previewHtml += '<h5 class="font-medium mb-2">タイムプレビュー</h5>';
        previewHtml += '<div class="grid grid-cols-2 gap-4">';
        
        if (inputMethod === 'split') {
            // スプリット入力 → 累積タイム表示
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
            // 累積入力 → ラップタイム表示
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
        fetch(`api/enhanced_competition.php?action=get_progress_chart&stroke_type=${strokeType}&distance_meters=${distance}&pool_type=${poolType}`)
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
        const personalBestPoints = chartData.filter(item => item.is_personal_best);
        
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
    
    // フォーム送信成功時の処理
    window.handleFormSuccess = function(response) {
        console.log('フォーム送信成功:', response);
        
        // 成功メッセージを表示
        const successDiv = document.createElement('div');
        successDiv.className = 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4';
        successDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <div>
                    <strong>${response.message}</strong>
                    ${response.is_personal_best ? '<br><span class="text-sm">🏆 自己ベスト記録です！</span>' : ''}
                </div>
            </div>
        `;
        
        const form = document.getElementById('competition-result-form');
        if (form) {
            form.insertBefore(successDiv, form.firstChild);
            successDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // 5秒後にリダイレクト
        setTimeout(() => {
            const competitionId = new URLSearchParams(window.location.search).get('id');
            window.location.href = `competition.php?action=view&id=${competitionId}`;
        }, 2000);
    };
    
    // グローバルスコープに関数を公開
    window.enhancedCompetitionForm = {
        validateTimeFormat,
        parseTimeStringToCentiseconds,
        formatCentisecondsToTime,
        showProgressChart
    };
});