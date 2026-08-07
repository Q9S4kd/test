<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Строго задаем таймзону для PHP
date_default_timezone_set('Europe/Moscow');

header('Content-Type: application/json');

$db_file = __DIR__ . "/ping_monitoring.db";
if (!file_exists($db_file)) {
    echo json_encode(['error' => 'База данных отсутствует']);
    exit;
}

$db = new SQLite3($db_file);
// Увеличиваем таймаут блокировок до 10 сек, чтобы веб-интерфейс не конфликтовал с демоном
$db->busyTimeout(10000); 
$db->exec("PRAGMA journal_mode=WAL;");

$type = isset($_GET['type']) ? $_GET['type'] : '';

/**
 * ИСПРАВЛЕНО: Функция расчета SLA теперь учитывает, что в архиве время лежит в UTC+3
 */
function getUptimeSLA($db, $interval) {
    // Сдвигаем точку отсчета фильтра на +3 часа, чтобы синхронизировать с archive_time демона
    $query = "SELECT AVG(loss_percent) as avg_loss FROM ping_archive WHERE archive_time >= datetime('now', '+3 hours', '$interval')";
    $avg_loss = $db->querySingle($query);
    if ($avg_loss === null) return '100.00%';
    return number_format(100 - $avg_loss, 2) . '%';
}

switch ($type) {
    case 'latest':
        // ИСПРАВЛЕНО: Выводим timestamp последней проверки строго в UTC+3
        $latest = $db->querySingle("SELECT datetime(timestamp, '+3 hours') as timestamp, status, time FROM ping_log WHERE timestamp >= datetime('now', '-15 seconds') ORDER BY timestamp DESC LIMIT 1", true);
        if (!$latest) {
            $latest = $db->querySingle("SELECT datetime(timestamp, '+3 hours') as timestamp, status, time FROM ping_log ORDER BY id DESC LIMIT 1", true);
        }
        echo json_encode($latest ?: ['status' => 'no_data']);
        break;

    case 'metrics':
        echo json_encode([
            'sla_24h' => getUptimeSLA($db, '-1 day'),
            'sla_7d'  => getUptimeSLA($db, '-7 days'),
            'sla_30d' => getUptimeSLA($db, '-30 days'),
        ]);
        break;

    case 'jitter':
        // ОПТИМИЗИРОВАНО: Благодаря покрывающему индексу демона, этот запрос отрабатывает мгновенно
        $jitter_res = $db->query("SELECT time FROM ping_log WHERE timestamp >= datetime('now', '-5 minutes') AND status = 'online' ORDER BY timestamp DESC LIMIT 60");
        $pings = [];
        if ($jitter_res) {
            while ($row = $jitter_res->fetchArray(SQLITE3_ASSOC)) { $pings[] = $row['time']; }
        }
        $jitter = 0;
        if (count($pings) > 1) {
            $diffs = [];
            for ($i = 0; $i < count($pings) - 1; $i++) { $diffs[] = abs($pings[$i] - $pings[$i+1]); }
            $jitter = round(array_sum($diffs) / count($diffs), 1);
        }
        echo json_encode(['jitter' => $jitter]);
        break;

    case 'matrix':
        // ИСПРАВЛЕНО: Демон уже хранит в ping_matrix_cache идеальное время UTC+3. Отдаем как есть.
        $res = $db->query("SELECT block_time, failed_count, status, avg_time FROM ping_matrix_cache ORDER BY block_time ASC LIMIT 100");
        $matrix = [];
        if ($res) {
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) { 
                $matrix[] = $row; 
            }
        }
        echo json_encode($matrix);
        break;

    case 'history':
        // ИСПРАВЛЕНО: Группировка 5-секундных интервалов переведена на строгое смещение '+3 hours' без использования нестабильного 'localtime'
        $history_query = "
            SELECT strftime('%Y-%m-%d %H:%M:', datetime(timestamp, '+3 hours')) || substr('0' || (strftime('%S', datetime(timestamp, '+3 hours')) / 5 * 5), -2) as local_time, 
                   CASE WHEN MIN(CASE WHEN status = 'offline' THEN 0 ELSE 1 END) = 0 THEN 'offline' ELSE 'online' END as status, 
                   ROUND(AVG(time), 1) as time
            FROM ping_log 
            WHERE timestamp >= datetime('now', '-1 hour') 
            GROUP BY local_time 
            ORDER BY local_time DESC 
            LIMIT 720";
        $res = $db->query($history_query);
        $history = [];
        if ($res) {
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) { $history[] = $row; }
        }
        echo json_encode($history);
        break;

    default:
        echo json_encode(['error' => 'Неверный тип запроса']);
}
$db->close();
