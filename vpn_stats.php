<?php
// Полный путь к файлу вашей базы данных SQLite
$db_file = '/var/www/nc1.unblock.name/check/ping_monitoring.db';

try {
    // Подключаемся к SQLite через PDO
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    die("Ошибка подключения к SQLite: " . $e->getMessage());
}

// Получаем последние 50 записей лога. 
$stmt = $pdo->query("SELECT datetime(event_time, 'localtime') as display_time, status, attempt_count, message FROM vpn_monitoring_logs ORDER BY id DESC LIMIT 50");
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="2">
    <title>Мониторинг VPN сессий (SQLite)</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; padding: 20px; }
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #343a40; color: white; }
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .online { background: #d4edda; color: #155724; }
        .offline { background: #fed7d7; color: #742a2a; }
        .timeout { background: #fff3cd; color: #856404; }
        .ONLINE { background: #d4edda; color: #155724; }
        .OFFLINE { background: #fed7d7; color: #742a2a; }
    </style>
</head>
<body>

    <h2>Лог мониторинга VPN хоста (База данных: SQLite)</h2>

    <table>
        <thead>
            <tr>
                <th>Время события</th>
                <th>Статус</th>
                <th>Попытка</th>
                <th>Описание</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="4" style="text-align:center;">Логов в файле БД пока нет...</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $row): ?>
                    <?php 
                        $status_class = strtolower(trim($row['status'])); 
                        
                        if ((int)$row['attempt_count'] == 20 || strpos($row['message'], 'Перезапуск') !== false) {
                            $status_class = 'offline';
                        } elseif ($status_class == 'offline') {
                            $status_class = 'timeout';
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['display_time']) ?></td>
                        <td>
                            <span class="badge <?= $status_class ?>">
                                <?= htmlspecialchars($row['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                                $count = (int)$row['attempt_count'];
                                if ($count == 20) {
                                    echo "<strong>20 / 20</strong>";
                                } elseif ($count > 0) {
                                    echo $count . ' / 20';
                                } else {
                                    echo '-';
                                }
                            ?>
                        </td>
                        <td><?= htmlspecialchars($row['message']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
