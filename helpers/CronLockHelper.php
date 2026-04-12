<?php
namespace locker\helpers;

use locker\exceptions\LockException;

class CronLockHelper {
    public static $defaultTtl = '0 seconds';

    public static $units = [
        'seconds' => 1,
        'second' => 1,
        'sec' => 1,
        's' => 1,
        'minutes' => 60,
        'minute' => 60,
        'min' => 60,
        'm' => 60,
        'hours' => 3600,
        'hour' => 3600,
        'hr' => 3600,
        'h' => 3600,
        'days' => 86400,
        'day' => 86400,
        'd' => 86400,
        'weeks' => 604800,
        'week' => 604800,
        'wk' => 604800,
        'w' => 604800,
    ];

    /**
     * Парсит TTL из строки или числа
     * @param string|int $ttl
     * @return int
     * @throws LockException
     */
    public static function parseTtl($ttl = null)
    {
        $ttl = $ttl ?: self::$defaultTtl;

        if (is_numeric($ttl) || is_string($ttl)) {
            if (is_numeric($ttl)) {
                return (int) $ttl;
            }

            $ttl = strtolower(trim($ttl));

            // Парсим строку типа "8 minutes"
            foreach (self::$units as $unit => $multiplier) {
                if (preg_match('/^(\d+)\s*' . preg_quote($unit, '/') . '$/', $ttl, $matches)) {
                    return (int) $matches[1] * $multiplier;
                }
            }
        }

        throw new LockException("Invalid TTL format. Expected string or integer.");
    }
}