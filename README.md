# php-CronLock
Сервис для блокировки крон-процессов 

## How used
```
use locker\CronLock;

// Создаем сервис блокировок
$locker = new CronLock('/data/locks');

// Параметры
$processName = 'data_sync'; //Название процесса
$lockTtl = '5 minutes'; //Значение по-дефолту 8 минут

try {
    // Пытаемся получить блокировку
    if ($locker->acquire($processName, $lockTtl)) {
        try {
            // Выполняем задачу
            echo "Starting data sync...\n";
            // ... ваш код ...
            echo "Data sync completed.\n";
            
        } finally {
            // Всегда освобождаем блокировку
            $locker->release($processName);
        }
    } else {
        echo "Process '{$processName}' is already running. Skipping.\n";
    }
    
} catch (\Exception $e) {
    error_log("Cron lock error: " . $e->getMessage());
    exit(1);
}

// Использование с ожиданием освобождения блокировки
if ($locker->acquire($processName, $lockTtl, true, 60)) {
    // Блокировка получена (возможно, после ожидания)
}

// Проверка статуса блокировки
if ($locker->isLocked($processName)) {
    echo "Process is locked\n";
}

// Получение информации о блокировке
$info = $locker->getLockInfo($processName);
if ($info) {
    echo "Locked until: " . date('Y-m-d H:i:s', $info['expires_at']) . "\n";
}
```