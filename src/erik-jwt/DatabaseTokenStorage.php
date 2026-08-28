<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik
 * Author: erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt;

use PDO;
use PDOException;

class DatabaseTokenStorage implements TokenStorageInterface
{
    private $pdo;
    private $tableName;

    public function __construct(PDO $pdo, string $tableName = 'jwt_blacklist')
    {
        $this->pdo = $pdo;
        $this->tableName = $tableName;

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->tableName)) {
            throw JWTException::configError("Invalid table name: {$this->tableName}");
        }

        try {
            $this->createTableIfNotExists();
        } catch (PDOException $e) {
            throw JWTException::storageError('Failed to create table: ' . $e->getMessage());
        }
    }

    private function createTableIfNotExists(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            jti VARCHAR(64) PRIMARY KEY,
            expire_time INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            throw JWTException::storageError('Failed to create table: ' . $e->getMessage());
        }
    }

    public function blacklist(string $jti, int $expireTime): bool
    {
        try {
            $sql = "INSERT INTO {$this->tableName} (jti, expire_time) VALUES (?, ?)";
            $stmt = $this->pdo->prepare($sql);
            try {
                return $stmt->execute([$jti, $expireTime]);
            } catch (PDOException $e) {
                if ($e->getCode() != '23000' && ($e->errorInfo[0] ?? null) !== '23000') {
                    throw JWTException::storageError('Database operation failed: ' . $e->getMessage());
                }
                // 主键冲突时更新过期时间
                $sql = "UPDATE {$this->tableName} SET expire_time = ? WHERE jti = ?";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([$expireTime, $jti]);
            }
        } catch (PDOException $e) {
            throw JWTException::storageError('Database operation failed: ' . $e->getMessage());
        }
    }

    public function isBlacklisted(string $jti): bool
    {
        try {
            $sql = "SELECT 1 FROM {$this->tableName} WHERE jti = ? AND expire_time > ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$jti, time()]);
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            throw JWTException::storageError('Database operation failed: ' . $e->getMessage());
        }
    }

    public function cleanup(): bool
    {
        try {
            $sql = "DELETE FROM {$this->tableName} WHERE expire_time <= ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([time()]);
        } catch (PDOException $e) {
            throw JWTException::storageError('Database operation failed: ' . $e->getMessage());
        }
    }
}