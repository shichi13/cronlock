<?php
namespace locker\interfaces;

interface LockDriverInterface
{
    /**
     * Получает блокировку
     * @param string $lockId
     * @param int $ttl
     * @return bool
     */
    public function acquire($lockId, $ttl);

    /**
     * Освобождает блокировку
     * @param string $lockId
     * @return bool
     */
    public function release($lockId);

    /**
     * Проверяет активность блокировки
     * @param string $lockId
     * @return bool
     */
    public function isLocked($lockId);

    /**
     * Получает информацию о блокировке
     * @param string $lockId
     * @return array
     */
    public function getLockInfo($lockId);

    /**
     * Очищает устаревшие блокировки
     * @return int
     */
    public function cleanupExpired();
}