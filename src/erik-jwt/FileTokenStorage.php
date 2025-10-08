<?php
// src/FileTokenStorage.php

namespace ErikJwt;

class FileTokenStorage implements TokenStorageInterface
{
    private $storagePath;
    private $gcProbability = 0.1; // 10% 的概率执行垃圾回收

    public function __construct(string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? sys_get_temp_dir() . '/jwt_blacklist';
        var_dump($this->storagePath);
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    public function blacklist(string $jti, int $expireTime): bool
    {
        $now = time();
        $ttl = $expireTime - $now;
        
        if ($ttl <= 0) {
            return true; // 已经过期的令牌不需要加入黑名单
        }

        $filePath = $this->getFilePath($jti);
        $data = [
            'jti' => $jti,
            'expire_time' => $expireTime,
            'created_at' => $now
        ];

        $result = file_put_contents($filePath, json_encode($data));
        
        // 随机执行垃圾回收
        $this->garbageCollection();
        
        return $result !== false;
    }

    public function isBlacklisted(string $jti): bool
    {
        $filePath = $this->getFilePath($jti);
        
        if (!file_exists($filePath)) {
            return false;
        }

        $data = json_decode(file_get_contents($filePath), true);
        if (!$data) {
            return false;
        }

        // 检查是否过期
        if (time() > $data['expire_time']) {
            unlink($filePath);
            return false;
        }

        return true;
    }

    public function cleanup(): bool
    {
        $files = glob($this->storagePath . '/*.json');
        $now = time();
        $cleaned = 0;

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $now > $data['expire_time']) {
                unlink($file);
                $cleaned++;
            }
        }

        return true;
    }

    private function getFilePath(string $jti): string
    {
        return $this->storagePath . '/' . $jti . '.json';
    }

    private function garbageCollection(): void
    {
        if (mt_rand(1, 100) <= ($this->gcProbability * 100)) {
            $this->cleanup();
        }
    }
}