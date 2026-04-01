<?php
namespace locker;

use locker\exceptions\LockException;
use locker\drivers\FileLockDriver;
use locker\helpers\CronLockHelper;

class CronLock {
    private $driver;
    private $lockedProcesses = [];

    /**
     * @param string $storagePath Путь для файловых блокировок
     */
    public function __construct($storagePath)
    {
        $this->driver = new FileLockDriver($storagePath);
    }

    /**
     * Пытается получить блокировку для процесса
     *
     * @param string        $processName Название процесса/задачи
     * @param string|int    $ttl Время жизни блокировки (строка или секунды)
     * @param bool          $wait Ожидать освобождения блокировки
     * @param int           $waitTimeout Таймаут ожидания в секундах
     * @param int           $retryInterval Интервал между попытками в микросекундах
     * @return bool         Результат получения блокировки
     * @throws LockException
     */
    public function acquire($processName = '', $ttl = null, $wait = false, $waitTimeout = 30, $retryInterval = 100000) {
        $lockId = $this->generateLockId($processName);
        $ttlSeconds = CronLockHelper::parseTtl($ttl);
        $startTime = time();

        do {
            try {
                if ($this->driver->acquire($lockId, $ttlSeconds)) {
                    $this->lockedProcesses[$lockId] = [
                        'process' => $processName,
                        'acquired_at' => time(),
                        'ttl' => $ttlSeconds
                    ];
                    return true;
                }

                if (!$wait) {
                    return false;
                }

                usleep($retryInterval);

            } catch (\Exception $e) {
                throw new LockException("Failed to acquire lock for process '{$processName}': " . $e->getMessage(), 0, $e);
            }

        } while ((time() - $startTime) < $waitTimeout);

        return false;
    }

    /**
     * Освобождает блокировку процесса
     * @param string $processName
     * @return bool
     */
    public function release($processName)
    {
        $lockId = $this->generateLockId($processName);

        try {
            if (($result = $this->driver->release($lockId)) && isset($this->lockedProcesses[$lockId])) {
                unset($this->lockedProcesses[$lockId]);
            }
            return $result;
        } catch (\Exception $e) {
            throw new LockException("Failed to release lock for process '{$processName}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Проверяет, активна ли блокировка
     * @param string $processName
     * @return bool
     */
    public function isLocked($processName)
    {
        $lockId = $this->generateLockId($processName);
        return $this->driver->isLocked($lockId);
    }

    /**
     * Получает информацию о блокировке
     * @param string $processName
     * @return array Результат в виде массива
     */
    public function getLockInfo($processName)
    {
        $lockId = $this->generateLockId($processName);
        return $this->driver->getLockInfo($lockId);
    }

    /**
     * Очищает все устаревшие блокировки
     * @return int Кол-во удалённых блокировок
     */
    public function cleanupExpired()
    {
        return $this->driver->cleanupExpired();
    }

    /**
     * Освобождает все блокировки, полученные этим экземпляром
     * @return void
     */
    public function releaseAll()
    {
        foreach ($this->lockedProcesses as $lockId => $info) {
            try {
                $this->driver->release($lockId);
            } catch (\Exception $e) {
                // Логируем ошибку, но продолжаем освобождать другие блокировки
                error_log("Failed to release lock {$lockId}: " . $e->getMessage());
            }
        }

        $this->lockedProcesses = [];
    }

    /**
     * Автоматически освобождает блокировки при уничтожении объекта
     */
    public function __destruct()
    {
        $this->releaseAll();
    }

    /**
     * Генерирует уникальный идентификатор блокировки
     */
    private function generateLockId($processName)
    {
        // Нормализуем имя процесса для использования в качестве ключа
        $normalizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $processName);
        return hash('sha256', $normalizedName . '_' . gethostname());
    }
}