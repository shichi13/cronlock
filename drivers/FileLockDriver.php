<?php
namespace locker\drivers;

class FileLockDriver
{
    private $storagePath;

    public function __construct($storagePath)
    {
        $this->storagePath = rtrim($storagePath, '/');

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Получает блокировку
     * @param string $lockId
     * @param int $ttl
     * @return bool
     */
    public function acquire($lockId, $ttl)
    {
        $lockFile = $this->getLockFilePath($lockId);

        // Проверяем существующую блокировку
        if (file_exists($lockFile)) {
            $content = file_get_contents($lockFile);
            $data = json_decode($content, true);

            if ($data && isset($data['expires_at'])) {
                // Если блокировка еще действует
                if ($data['expires_at'] > time()) {
                    return false;
                }
            }
        }

        // Создаем новую блокировку
        $data = [
            'lock_id' => $lockId,
            'created_at' => time(),
            'expires_at' => ($ttl != 0) ? (time() + $ttl) : 0,
            'pid' => getmypid(),
            'hostname' => gethostname(),
        ];

        $result = file_put_contents($lockFile, json_encode($data), LOCK_EX);

        return $result !== false;
    }

    /**
     * Освобождает блокировку
     * @param string $lockId
     * @return bool
     */
    public function release($lockId)
    {
        $lockFile = $this->getLockFilePath($lockId);

        if (!file_exists($lockFile)) {
            return true;
        }

        return unlink($lockFile);
    }

    /**
     * Проверяет активность блокировки
     * @param string $lockId
     * @return bool
     */
    public function isLocked($lockId)
    {
        $lockFile = $this->getLockFilePath($lockId);

        if (!file_exists($lockFile)) {
            return false;
        }

        $content = file_get_contents($lockFile);
        $data = json_decode($content, true);

        if (!$data || !isset($data['expires_at'])) {
            return false;
        }

        if (($data['expires_at'] != 0) && ($data['expires_at'] < time())) {
            return false;
        }

        return true;
    }

    /**
     * Получает информацию о блокировке
     * @param string $lockId
     * @return array
     */
    public function getLockInfo($lockId)
    {
        $lockFile = $this->getLockFilePath($lockId);

        if (!file_exists($lockFile)) {
            return null;
        }

        $content = file_get_contents($lockFile);
        $data = json_decode($content, true);

        if (!$data) {
            return null;
        }

        $data['is_expired'] = ($data['expires_at'] == 0) ? false : ($data['expires_at'] <= time());
        $data['remaining_seconds'] = max(0, $data['expires_at'] - time());

        return $data;
    }

    /**
     * Очищает устаревшие блокировки
     * @return int Кол-во удалённых блокировок
     */
    public function cleanupExpired()
    {
        $count = 0;
        $files = glob($this->storagePath . '/*.lock');
        $process = $this->getActiveProcess();
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if ($data){
                if (in_array($data['pid'], $process)) {
                    continue;
                } else {
                    unlink($file);
                    $count++;
                }
            }
        }

        return $count;
    }

    private function getActiveProcess()
    {
        exec('ps -C php -o pid', $output);
        unset($output[0]);
        return array_map(function($row){
            return (int)trim($row);
        }, $output);
    }

    private function getLockFilePath($lockId)
    {
        return $this->storagePath . '/' . $lockId . '.lock';
    }
}