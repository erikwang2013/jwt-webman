<?php
// src/DatabaseTokenStorage.php

namespace ErikJwt;

use support\Db;
use PDOException;

class DatabaseTokenStorage implements TokenStorageInterface
{
    private $pdo;
    private $tableName;

    public function __construct(string $tableName = 'jwt_blacklist')
    {
        $this->tableName = $tableName;
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            jti VARCHAR(64) PRIMARY KEY,
            expire_time INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_expire_time (expire_time)
        )";

        Db::exec($sql);
    }

    public function blacklist(string $jti, int $expireTime): bool
    {
        try {
            $sql = "REPLACE INTO {$this->tableName} (jti, expire_time) VALUES (?, ?)";
            $stmt = Db::prepare($sql);
            return $stmt->execute([$jti, $expireTime]);
        } catch (PDOException $e) {
            throw new JWTException('Database operation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function isBlacklisted(string $jti): bool
    {
        try {
            $sql = "SELECT jti FROM {$this->tableName} WHERE jti = ? AND expire_time > ?";
            $stmt = Db::prepare($sql);
            $stmt->execute([$jti, time()]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new JWTException('Database operation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function cleanup(): bool
    {
        try {
            $sql = "DELETE FROM {$this->tableName} WHERE expire_time <= ?";
            $stmt = Db::prepare($sql);
            return $stmt->execute([time()]);
        } catch (PDOException $e) {
            throw new JWTException('Database operation failed: ' . $e->getMessage(), 0, $e);
        }
    }
}