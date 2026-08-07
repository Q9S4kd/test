<?php
set_time_limit(0);

class PingDaemon 
{
    private string $ip;
    private int $port = 9931;
    private float $timeout = 0.5;
    private string $dbFile;
    private SQLite3 $db;
    
    // Подготовленные выражения (Prepared Statements) для ускорения SQL
    private ?SQLite3Stmt $insertLogStmt = null;
    private ?SQLite3Stmt $updateCacheStmt = null;

    // Переменные состояния демона
    private ?string $lastArchivedHour = null;
    private ?string $lastCachedMinute = null;

    public function __construct(string $ip, string $dbFile) 
    {
        $this->ip = $ip;
        $this->dbFile = $dbFile;
        
        $this->initDatabase();
        $this->prepareStatements();
    }

    /**
     * Инициализация базы данных и создание оптимальной структуры
     */
    private function initDatabase(): void 
    {
        try {
            $this->db = new SQLite3($this->dbFile);
            
            // Настройки высокой производительности для SQLite в режиме демона
            $this->db->busyTimeout(10000);
            $this->db->exec("PRAGMA journal_mode=WAL;");
            $this->db->exec("PRAGMA synchronous = NORMAL;");
            
            // Таблица сырых логов с покрывающим индексом
            $this->db->exec("CREATE TABLE IF NOT EXISTS ping_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                status TEXT,
                time REAL
            )");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_log_ts_covering ON ping_log(timestamp, status, time);");

            // Таблица ежечасных архивов
            $this->db->exec("CREATE TABLE IF NOT EXISTS ping_archive (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                archive_time DATETIME UNIQUE,
                min_time REAL,
                avg_time REAL,
                max_time REAL,
                loss_percent REAL
            )");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_archive_time ON ping_archive(archive_time);");

            // Кэш суточной матрицы стабильности
            $this->db->exec("CREATE TABLE IF NOT EXISTS ping_matrix_cache (
                block_time TEXT PRIMARY KEY,
                failed_count INTEGER,
                status TEXT,
                avg_time REAL
            )");
            
        } catch (Exception $e) {
            die("Ошибка инициализации БД: " . $e->getMessage() . "\n");
        }
    }

    /**
     * Компиляция запросов один раз при старте для максимального быстродействия
     */
    private function prepareStatements(): void 
    {
        // Запись текущего пинга
        $this->insertLogStmt = $this->db->prepare(
            "INSERT INTO ping_log (timestamp, status, time) VALUES (datetime('now'), :status, :time)"
        );

        // ИСПРАВЛЕНО: Правильный порядок UPSERT (INSERT INTO ... SELECT ... ON CONFLICT)
        $this->updateCacheStmt = $this->db->prepare("
            INSERT INTO ping_matrix_cache (block_time, failed_count, status, avg_time)
            SELECT 
                strftime('%Y-%m-%d %H:', datetime(timestamp, '+3 hours')) || 
                CASE 
                    WHEN cast(strftime('%M', datetime(timestamp, '+3 hours')) as integer) < 15 THEN '00'
                    WHEN cast(strftime('%M', datetime(timestamp, '+3 hours')) as integer) < 30 THEN '15'
                    WHEN cast(strftime('%M', datetime(timestamp, '+3 hours')) as integer) < 45 THEN '30'
                    ELSE '45'
                END as b_time,
                SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as f_count,
                CASE 
                    WHEN SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) > 100 THEN 'offline'
                    WHEN SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) > 50 THEN 'warning'
                    ELSE 'online' 
                END as stat,
                ROUND(AVG(time), 1) as a_time
            FROM ping_log 
            WHERE timestamp >= :block_start AND timestamp <= :block_end
            GROUP BY b_time
            ON CONFLICT(block_time) DO UPDATE SET
                failed_count = failed_count + excluded.failed_count,
                avg_time = ROUND((avg_time + excluded.avg_time) / 2, 1),
                status = CASE 
                    WHEN (failed_count + excluded.failed_count) > 100 THEN 'offline'
                    WHEN (failed_count + excluded.failed_count) > 50 THEN 'warning'
                    ELSE 'online' 
                END
        ");
	}
    /**
     * Замер TCP-доступности с корректной обработкой Connection Refused
     */
    private function pingAddress(): array 
    {
        $startTime = microtime(true);
        $socket = @fsockopen($this->ip, $this->port, $errno, $errstr, $this->timeout);
        $endTime = microtime(true);
        
        $time = round(($endTime - $startTime) * 1000, 1);

        if ($socket) {
            fclose($socket);
            return ['status' => 'online', 'time' => $time];
        }
        
        // Специфические ошибки операционной системы (сервер жив, но порт закрыт)
        // 111 - Linux (Connection Refused), 10061 - Windows
        if ($errno === 111 || $errno === 10061) {
            return ['status' => 'online', 'time' => $time];
        }
        
        // Все остальные ошибки (например, 110 Connection Timed Out) — хост недоступен
        return ['status' => 'offline', 'time' => null];
    }

     /**
     * Запуск основного бесконечного цикла демона с жестким шагом в 1.0 секунду
     */
    public function run(): void 
    {
        echo "Идеальный демон с микросекундной компенсацией запущен...\n";
        $nextTick = microtime(true);

        while (true) {
            $nextTick += 1.0;
            $currentPing = $this->pingAddress();
            
            // БЕЗОПАСНАЯ вставка лога с гарантированным глушением варнингов
            try {
                $this->insertLogStmt->bindValue(':status', $currentPing['status'], SQLITE3_TEXT);
                if ($currentPing['time'] !== null) {
                    $this->insertLogStmt->bindValue(':time', $currentPing['time'], SQLITE3_FLOAT);
                } else {
                    $this->insertLogStmt->bindValue(':time', null, SQLITE3_NULL);
                }

                // Временно отключаем обработчик ошибок сервера
                set_error_handler(function($errno, $errstr) { return true; });
                
                $this->insertLogStmt->execute();
                
            } catch (Throwable $logEx) {
                // ИСПРАВЛЕНО: Сбрасываем стейтмент, чтобы он не завис в состоянии ошибки
                if (isset($this->insertLogStmt)) {
                    $this->insertLogStmt->reset();
                    $this->insertLogStmt->clear();
                }
            } finally {
                // Блок finally выполняется ВСЕГДА: и при успехе, и при падении.
                // Это гарантирует, что стек обработчиков PHP никогда не сломается.
                restore_error_handler();
            }
            
            $currentHour = date('H');
            $currentMinute = date('i');

            if ($this->lastArchivedHour !== $currentHour && $currentMinute === '00') {
                $this->runHourlyArchive();
            }

            if ($currentMinute % 15 === 0 && $this->lastCachedMinute !== $currentMinute) {
                $this->refreshMatrixCache();
            }

            $currentTime = microtime(true);
            $sleepTime = (int)(($nextTick - $currentTime) * 1000000);
            
            if ($sleepTime > 0) {
                usleep($sleepTime);
            } else {
                $nextTick = microtime(true);
            }
        }
    }


    /**
     * Процедура ежечасной архивации и очистки старых данных (Архив в UTC+3)
     */
    private function runHourlyArchive(): void 
    {
        $needVacuum = false;

        try {
            $this->db->exec("BEGIN IMMEDIATE TRANSACTION;");
            
            // Архивация данных за последний час со смещением в UTC+3
            $this->db->exec("INSERT OR IGNORE INTO ping_archive (archive_time, min_time, avg_time, max_time, loss_percent)
                SELECT strftime('%Y-%m-%d %H:00:00', datetime(timestamp, '+3 hours')) as hr, MIN(time), AVG(time), MAX(time),
                SUM(CASE WHEN status='offline' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)
                FROM ping_log WHERE timestamp >= datetime('now', '-1 hour') GROUP BY hr");

            // Очистка логов старше 60 дней
            $this->db->exec("DELETE FROM ping_log WHERE timestamp < datetime('now', '-60 days')");
            
            $this->db->exec("COMMIT;");
            $this->lastArchivedHour = date('H');

            // ИСПРАВЛЕНО: Флаг для VACUUM выставляем внутри, но выполним его ПОСЛЕ коммита транзакции
            if (date('w') == 0) {
                $needVacuum = true;
            }
        } catch (Throwable $e) { // ИСПРАВЛЕНО: Изменено на Throwable
            @$this->db->exec("ROLLBACK;");
        }

        // ИСПРАВЛЕНО: Выносим VACUUM за пределы транзакции, чтобы SQLite не ругался
        if ($needVacuum) {
            try {
                $this->db->exec("VACUUM;");
            } catch (Throwable $vacuumEx) {
                // Если база заблокирована, дефрагментацию просто молча пропускаем
            }
        }
    }

    /**
     * Оптимизированное обновление кэша матрицы (Синхронизация по UTC+3)
     */
    private function refreshMatrixCache(): void 
    {
        try {
            $this->db->exec("BEGIN IMMEDIATE TRANSACTION;");
            
            // ИСПРАВЛЕНО: Так как в SQL-запросе updateCacheStmt используется фильтрация по оригинальному timestamp логов,
            // передаем чистый UTC-диапазон (без смещений +3 часа), чтобы SQLite корректно отфильтровал записи.
            $timeStart = gmdate('Y-m-d H:i:s', time() - 900); 
            $timeEnd = gmdate('Y-m-d H:i:s');
            
            $this->updateCacheStmt->bindValue(':block_start', $timeStart, SQLITE3_TEXT);
            $this->updateCacheStmt->bindValue(':block_end', $timeEnd, SQLITE3_TEXT);

            // Временно отключаем обработчик ошибок сервера, чтобы заглушить "database is locked"
            set_error_handler(function($errno, $errstr) { return true; });
            
            // Выполняем в режиме абсолютной тишины
            $this->updateCacheStmt->execute();
            
            // ИСПРАВЛЕНО: Обязательно освобождаем стейтмент после успешного выполнения
            $this->updateCacheStmt->reset();
            
            // Возвращаем стандартный системный логгер обратно
            restore_error_handler();
            
            $this->db->exec("DELETE FROM ping_matrix_cache WHERE block_time < strftime('%Y-%m-%d %H:%M', datetime('now', '+3 hours', '-1 day'))");
            $this->db->exec("COMMIT;");
            $this->lastCachedMinute = date('i');
        } catch (Throwable $e) { // ИСПРАВЛЕНО: Изменено на Throwable для перехвата всех типов ошибок
            restore_error_handler();
            
            // ИСПРАВЛЕНО: Сбрасываем стейтмент при ошибке, чтобы предотвратить его зависание
            if (isset($this->updateCacheStmt)) {
                $this->updateCacheStmt->reset();
                $this->updateCacheStmt->clear();
            }
            
            @$this->db->exec("ROLLBACK;");
        }
    }




    /**
     * Корректное закрытие базы данных при завершении работы скрипта
     */
    public function __destruct() 
    {
        if (isset($this->db)) {
            $this->db->close();
        }
    }
}

// Автоматический запуск демона при вызове файла
$daemon = new PingDaemon("10.120.5.2", __DIR__ . "/ping_monitoring.db");
$daemon->run();
