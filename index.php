<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мониторинг сети — Главная</title>
<style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 30px; background: #f4f6f9; color: #333; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: relative; }
        h2 { margin-top: 0; color: #2c3e50; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; }
        h3 { color: #34495e; margin-top: 30px; }
        .btn-link { display: inline-block; background: #3498db; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; margin-bottom: 20px; transition: background 0.2s; }
        .btn-link:hover { background: #2980b9; }
        
        /* Заглушки загрузки (Скелетоны и прогресс-бары) */
        .loading-container { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px; background: #fafafa; border-radius: 6px; border: 1px dashed #ccc; margin: 10px 0; }
        .progress-bar-bg { width: 100%; max-width: 300px; background: #e0e0e0; height: 12px; border-radius: 6px; overflow: hidden; margin-top: 10px; position: relative; }
        .progress-bar-fill { height: 100%; background: #3498db; width: 0%; transition: width 0.2s ease; }
        .pct-text { font-size: 14px; font-weight: bold; color: #555; }

        .status-card { padding: 25px; border-radius: 8px; text-align: center; margin-bottom: 25px; font-size: 22px; font-weight: bold; }
        .status-card.online { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-card.offline { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .status-card.warning { background: #fffde7; color: #f57f17; border: 1px solid #fff59d; }
        .status-card.loading-state { background: #f5f5f5; color: #7f8c8d; border: 1px solid #e0e0e0; }
        
        .widgets-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
        .widget { background: #fff; padding: 18px; border-radius: 8px; text-align: center; border: 1px solid #e0e0e0; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .widget-title { font-size: 13px; color: #7f8c8d; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        .widget-value { font-size: 26px; font-weight: bold; margin-top: 5px; }
        
        .matrix { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 25px; background: #fafafa; padding: 15px; border-radius: 8px; border: 1px solid #eaeaea; min-height: 48px; }
        .matrix-dot { width: 16px; height: 16px; border-radius: 3px; cursor: pointer; transition: transform 0.1s; }
        .matrix-dot:hover { transform: scale(1.3); z-index: 10; }
        .dot-online { background: #2ecc71; }
        .dot-warning { background: #f1c40f; }
        .dot-offline { background: #e74c3c; }

        .btn-refresh-cache { background: #f1f2f6; color: #57606f; border: 1px solid #ced6e0; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-refresh-cache:hover { background: #dfe4ea; color: #2f3542; }
        .btn-refresh-cache:disabled { opacity: 0.6; cursor: not-allowed; }

        .filter-container { margin-bottom: 12px; display: flex; gap: 10px; }
        .filter-btn { padding: 6px 14px; border: 1px solid #ccc; border-radius: 4px; background: #fff; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.2s; }
        .filter-btn.active { background: #3498db; color: #fff; border-color: #3498db; }
        .history-container { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; max-height: 320px; overflow-y: auto; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); }
        .history-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        .history-table th { background: #f8f9fa; position: sticky; top: 0; z-index: 1; padding: 10px 15px; border-bottom: 2px solid #e0e0e0; color: #7f8c8d; font-weight: 600; }
        .history-table td { padding: 8px 15px; border-bottom: 1px solid #eee; }
        .history-table tr:hover { background: #fcfcfc; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .badge-online { background: #e8f5e9; color: #2e7d32; }
        .badge-warning { background: #fffde7; color: #f57f17; }
        .badge-offline { background: #ffebee; color: #c62828; }

        /* ИЗОЛИРОВАННЫЕ СТИЛИ ПОДСКАЗКИ */
        .custom-tooltip {
            position: fixed;
            background: rgba(44, 62, 80, 0.98);
            color: #fff;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
            box-shadow: 0 6px 18px rgba(0,0,0,0.25);
            pointer-events: none;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.15s ease, transform 0.15s ease;
            z-index: 9999;
            min-width: 240px;
            border: 1px solid rgba(255,255,255,0.1);
            box-sizing: border-box;
        }
        .tooltip-header { font-weight: bold; margin-bottom: 6px; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 4px; color: #ecf0f1; }
        .tooltip-row { display: flex; justify-content: space-between; margin-bottom: 3px; }
        .tooltip-label { color: #bdc3c7; }
        .tooltip-value { font-weight: 600; }
        .t-badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .t-online { background: #2ecc71; color: #fff; }
        .t-warning { background: #f1c40f; color: #2c3e50; }
        .t-offline { background: #e74c3c; color: #fff; }
    </style>
</head>
<body>
<div class="container">
    <h2>📊 Сетевой статус зарубежного сервера</h2>
    
    <!-- Кнопка выровнена строго по центру -->
    <div style="text-align: center; margin-bottom: 25px;">
        <a href="stats.php" class="btn-link" style="margin-bottom: 0;">📈 СТРАНИЦА С ГРАФИКАМИ &rarr;</a>
    </div>

    
    <!-- Карточка текущего состояния с анимацией загрузки -->
    <div id="latest-status-card" class="status-card loading-state">
        <span id="latest-status-text">Загрузка текущего статуса...</span>
        <div class="progress-bar-bg" style="margin: 10px auto 0 auto;"><div id="pb-latest" class="progress-bar-fill"></div></div>
    </div>

    <h3>Стабильность работы и метрики</h3>
    <div class="widgets-grid">
        <div class="widget"><div class="widget-title">Сутки (24ч)</div><div id="m-24h" class="widget-value" style="color: #27ae60; font-size:16px;">0%</div></div>
        <div class="widget"><div class="widget-title">Неделя (7д)</div><div id="m-7d" class="widget-value" style="color: #2980b9; font-size:16px;">0%</div></div>
        <div class="widget"><div class="widget-title">Месяц (30д)</div><div id="m-30d" class="widget-value" style="color: #8e44ad; font-size:16px;">0%</div></div>
        <div class="widget"><div class="widget-title">Джиттер (5м)</div><div id="m-jitter" class="widget-value" style="color: #e67e22; font-size:16px;">0%</div></div>
    </div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px;">
    <h3 style="margin: 0;">📊 История стабильности сети за последние 24 часа (интервал 15 минут)</h3>
    <button id="cacheRefreshBtn" class="btn-refresh-cache" onclick="triggerCacheWarm()">🔄 Сбросить и обновить кэш</button>
</div>

    <div id="matrix-loading" class="loading-container">
        <div class="pct-text">Загрузка суточной матрицы: <span id="pct-matrix">0%</span></div>
        <div class="progress-bar-bg"><div id="pb-matrix" class="progress-bar-fill"></div></div>
    </div>
    <div class="matrix" id="matrixContainer" style="display: none;"></div>
 <div style="display: flex; gap: 20px; font-size: 13px; color: #7f8c8d; margin-top: -15px; margin-bottom: 25px; padding-left: 5px;">
        <div><span style="display:inline-block; width:12px; height:12px; background:#2ecc71; border-radius:2px; margin-right:5px; vertical-align:middle;"></span> Стабильно (0-49 сбоев)</div>
        <div><span style="display:inline-block; width:12px; height:12px; background:#f1c40f; border-radius:2px; margin-right:5px; vertical-align:middle;"></span> Микро-лаги (50-100 сбоев)</div>
        <div><span style="display:inline-block; width:12px; height:12px; background:#e74c3c; border-radius:2px; margin-right:5px; vertical-align:middle;"></span> Авария / Нет связи (>100 сбоев) 1 сбой = 1 секунда</div>
    </div>

    <h3>📜 Детальный журнал проверок за последний час</h3>
    <div class="filter-container">
        <button class="filter-btn" id="btn-all" onclick="filterLogs('all')">Все</button>
        <button class="filter-btn" id="btn-online" onclick="filterLogs('online')">Только Online</button>
        <button class="filter-btn" id="btn-offline" onclick="filterLogs('offline')">Только Offline</button>
    </div>
    
    <div id="history-loading" class="loading-container">
        <div class="pct-text">Загрузка лога истории: <span id="pct-history">0%</span></div>
        <div class="progress-bar-bg"><div id="pb-history" class="progress-bar-fill"></div></div>
    </div>
    
    <div class="history-container" id="historyContainer" style="display: none;">
        <table class="history-table" id="logsTable">
            <thead>
                <tr>
                    <th>Метка времени</th>
                    <th>Статус (5 сек)</th>
                    <th>Ср. пинг (5 сек)</th>
                </tr>
            </thead>
            <tbody id="logsTableBody"></tbody>
        </table>
    </div>
</div>

<script>
// Симуляция плавного заполнения прогресс-бара до ответа сервера
function animateProgress(progressBarId, textPercentId, maxFakePct, speed, callbackObj) {
    let currentPct = 0;
    let interval = setInterval(() => {
        if (callbackObj.loaded) {
            clearInterval(interval);
            document.getElementById(progressBarId).style.width = '100%';
            if (textPercentId) document.getElementById(textPercentId).innerText = '100%';
            return;
        }
        if (currentPct < maxFakePct) {
            currentPct += 1;
            document.getElementById(progressBarId).style.width = currentPct + '%';
            if (textPercentId) document.getElementById(textPercentId).innerText = currentPct + '%';
        }
    }, speed);
}

// Загрузка данных по частям через асинхронный AJAX
async function loadDashboardData() {
    // 1. Загрузка карточки текущего статуса
    let statusStatus = { loaded: false };
    animateProgress('pb-latest', null, 90, 10, statusStatus);
    
    fetch('api.php?type=latest')
        .then(res => res.json())
        .then(data => {
            statusStatus.loaded = true;
            setTimeout(() => {
                const card = document.getElementById('latest-status-card');
                card.className = 'status-card ' + data.status;
                if (data.status === 'no_data') {
                    document.getElementById('latest-status-text').innerHTML = 'НЕТ ДАННЫХ<br><span style="font-size:13px; font-weight:normal;">База пуста</span>';
                } else {
                    let pingTime = data.time !== null ? `(${data.time} ms)` : '';
                    document.getElementById('latest-status-text').innerHTML = `Текущее состояние: ${data.status.toUpperCase()} ${pingTime}<br><span style="font-size: 13px; font-weight: normal; opacity:0.8; display:block; margin-top:5px;">Последний замер: ${data.timestamp}</span>`;
                }
            }, 200);
        });

    // 2. Загрузка метрик Uptime SLA
    let metricsStatus = { loaded: false };
    animateProgress('m-24h', null, 85, 20, metricsStatus);
    animateProgress('m-7d', null, 85, 20, metricsStatus);
    animateProgress('m-30d', null, 85, 20, metricsStatus);
    
    fetch('api.php?type=metrics')
        .then(res => res.json())
        .then(data => {
            metricsStatus.loaded = true;
            document.getElementById('m-24h').innerText = data.sla_24h;
            document.getElementById('m-24h').style.fontSize = '26px';
            document.getElementById('m-7d').innerText = data.sla_7d;
            document.getElementById('m-7d').style.fontSize = '26px';
            document.getElementById('m-30d').innerText = data.sla_30d;
            document.getElementById('m-30d').style.fontSize = '26px';
        });

    // 3. Загрузка Jitter
    let jitterStatus = { loaded: false };
    animateProgress('m-jitter', null, 95, 15, jitterStatus);
    fetch('api.php?type=jitter')
        .then(res => res.json())
        .then(data => {
            jitterStatus.loaded = true;
            document.getElementById('m-jitter').innerHTML = `${data.jitter} <span style="font-size:14px; font-weight:normal;">мс</span>`;
            document.getElementById('m-jitter').style.fontSize = '26px';
        });

    // =========================================================================
    // ИСПРАВЛЕННЫЙ БЛОК №4: Загрузка суточной матрицы с умными всплывающими окнами
    // =========================================================================
    let matrixStatus = { loaded: false };
    animateProgress('pb-matrix', 'pct-matrix', 98, 30, matrixStatus);
    fetch('api.php?type=matrix')
        .then(res => res.json())
        .then(data => {
            matrixStatus.loaded = true;
            setTimeout(() => {
                document.getElementById('matrix-loading').style.display = 'none';
                const container = document.getElementById('matrixContainer');
                container.innerHTML = '';
                if (data.length === 0) {
                    container.innerHTML = '<div style="color: #7f8c8d; font-size: 14px; padding: 10px;">Ожидание накопления данных...</div>';
                } else {
                    data.forEach(item => {
                        let dot = document.createElement('div');
                        dot.className = `matrix-dot dot-${item.status}`;
                        
                        // Сохраняем данные во внутренние атрибуты вместо старого "title"
                        let avgPing = item.avg_time !== null ? item.avg_time + ' мс' : 'timeout';
                        dot.setAttribute('data-time', item.block_time);
                        dot.setAttribute('data-ping', avgPing);
                        dot.setAttribute('data-errors', item.failed_count);
                        dot.setAttribute('data-status', item.status);

                        // Появление при наведении мыши
                        dot.addEventListener('mouseenter', (e) => {
                            const tooltip = document.getElementById('graphTooltip');
                            const time = e.target.getAttribute('data-time');
                            const ping = e.target.getAttribute('data-ping');
                            const errors = parseInt(e.target.getAttribute('data-errors') || 0, 10);
                            const status = e.target.getAttribute('data-status');
                            
                            let statusWord = status === 'online' ? 'Стабильно' : (status === 'warning' ? 'Микро-лаги' : 'Авария');
                            
                            // Конвертируем количество ошибок (секунд) в понятное время простоя
                            let downtimeText = "0 сек";
                            if (errors > 0) {
                                let mins = Math.floor(errors / 60);
                                let secs = errors % 60;
                                downtimeText = (mins > 0 ? mins + " мин " : "") + secs + " сек";
                            }

                            tooltip.innerHTML = `
                                <div class="tooltip-header">⏱️ Интервал: ${time}</div>
                                <div class="tooltip-row">
                                    <span class="tooltip-label">Состояние:</span>
                                    <span class="tooltip-value"><span class="t-badge t-${status}">${statusWord}</span></span>
                                </div>
                                <div class="tooltip-row">
                                    <span class="tooltip-label">Средний пинг:</span>
                                    <span class="tooltip-value" style="color: #3498db;">${ping}</span>
                                </div>
                                <div class="tooltip-row">
                                    <span class="tooltip-label">Время простоя:</span>
                                    <span class="tooltip-value" style="${errors > 0 ? 'color:#e74c3c; font-weight:bold;' : 'color:#2ecc71;'}">${downtimeText}</span>
                                </div>
                            `;
                            tooltip.style.opacity = '1';
                            tooltip.style.transform = 'translateY(0)';
                        });


                        // Движение подсказки за курсором
                        dot.addEventListener('mousemove', (e) => {
                            const tooltip = document.getElementById('graphTooltip');
                            tooltip.style.left = (e.clientX + 15) + 'px';
                            tooltip.style.top = (e.clientY + 15) + 'px';
                        });

                        // Исчезновение при уходе курсора
                        dot.addEventListener('mouseleave', () => {
                            const tooltip = document.getElementById('graphTooltip');
                            tooltip.style.opacity = '0';
                            tooltip.style.transform = 'translateY(10px)';
                        });

                        container.appendChild(dot);
                    });
                }
                container.style.display = 'flex';
            }, 300);
        });
    // =========================================================================

    // 5. Загрузка часового лога истории
    let historyStatus = { loaded: false };
    animateProgress('pb-history', 'pct-history', 99, 40, historyStatus);
    fetch('api.php?type=history')
        .then(res => res.json())
        .then(data => {
            historyStatus.loaded = true;
            setTimeout(() => {
                document.getElementById('history-loading').style.display = 'none';
                const tbody = document.getElementById('logsTableBody');
                tbody.innerHTML = '';
                if (data.length === 0) {
                    document.getElementById('historyContainer').innerHTML = '<div style="color: #7f8c8d; font-size: 14px; padding: 15px; text-align: center;">Лог пуст.</div>';
                } else {
                    data.forEach(log => {
                        let tr = document.createElement('tr');
                        tr.setAttribute('data-status', log.status);
                        let pingVal = (log.time !== null && log.status === 'online') ? `${log.time} мс` : '<span style="color: #c62828; font-weight: bold;">timeout</span>';
                        tr.innerHTML = `
                            <td>${log.local_time}</td>
                            <td><span class="badge badge-${log.status}">${log.status}</span></td>
                            <td>${pingVal}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                    const savedStatus = localStorage.getItem('ping_filter_status') || 'all';
                    filterLogs(savedStatus);
                }
                document.getElementById('historyContainer').style.display = 'block';
            }, 400);
        });
}

function filterLogs(status) {
    localStorage.setItem('ping_filter_status', status);
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.getElementById('btn-' + status);
    if (activeBtn) activeBtn.classList.add('active');

    const rows = document.querySelectorAll('#logsTable tbody tr');
    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        if (status === 'all' || rowStatus === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

document.addEventListener("DOMContentLoaded", () => {
    loadDashboardData();
    // Фоновое обновление данных каждые 5 секунд без моргания и перезагрузки каркаса
    setInterval(loadDashboardData, 5000);
});

function triggerCacheWarm() {
    const btn = document.getElementById('cacheRefreshBtn');
    const originalText = btn.innerText;
    
    btn.disabled = true;
    btn.innerText = '⏳ Пересчет базы...';
    
    fetch('warm_matrix.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                btn.innerText = '✅ Готово!';
                btn.style.background = '#e8f5e9';
                btn.style.color = '#2e7d32';
                
                // Сразу же фоново перерисовываем кубики на экране новыми данными
                loadDashboardData(); 
            } else {
                alert('Ошибка: ' + data.message);
                btn.innerText = originalText;
            }
            
            // Через 2 секунды возвращаем кнопке исходное состояние
            setTimeout(() => {
                btn.disabled = false;
                btn.innerText = '🔄 Сбросить и обновить кэш';
                btn.style.background = '';
                btn.style.color = '';
            }, 200);
        })
        .catch(err => {
            alert('Сетевая ошибка при обновлении кэша');
            btn.disabled = false;
            btn.innerText = originalText;
        });
}
</script>

<div id="graphTooltip" class="custom-tooltip"></div>

</body>
</html>
