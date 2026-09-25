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
use PDOStatement;

class DatabaseTokenStorage implements TokenStorageInterface
{
    /** 主键/唯一键冲突：MySQL 与 SQLite 为 23000，PostgreSQL 为 23505 */
    private const DUPLICATE_KEY_SQLSTATES = ['23000', '23505'];

    private $pdo;
    private $tableName;

    public function __construct(PDO $pdo, string $tableName = 'jwt_blacklist', bool $autoCreate = true)
    {
        $this->pdo = $pdo;
        $this->tableName = $tableName;

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->tableName)) {
            throw JWTException::configError("Invalid table name: {$this->tableName}");
        }

        if ($autoCreate) {
            $this->createTableIfNotExists();
        }
    }

    private function createTableIfNotExists(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            jti VARCHAR(64) PRIMARY KEY,
            expire_time BIGINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        try {
            if ($this->pdo->exec($sql) === false) {
                throw JWTException::storageError('Failed to create table: ' . $this->pdoError());
            }
        } catch (PDOException $e) {
            throw JWTException::storageError('Failed to create table: ' . $e->getMessage());
        }
    }

    public function blacklist(string $jti, int $expireTime): bool
    {
        try {
            $stmt = $this->prepare("INSERT INTO {$this->tableName} (jti, expire_time) VALUES (?, ?)");

            try {
                $inserted = $stmt->execute([$jti, $expireTime]);
                $sqlState = $stmt->errorInfo()[0] ?? null;
            } catch (PDOException $e) {
                // ERRMODE_EXCEPTION 下重复主键在这里抛出
                $inserted = false;
                $sqlState = $e->errorInfo[0] ?? $e->getCode();
            }

            if ($inserted) {
                return true;
            }

            // ERRMODE_SILENT（PDO 默认）下 execute() 只返回 false，必须显式判断 SQLSTATE
            if (!in_array((string) $sqlState, self::DUPLICATE_KEY_SQLSTATES, true)) {
                throw JWTException::storageError('Database operation failed: ' . $this->pdoError());
            }

            // 主键冲突：令牌已在黑名单中，只更新过期时间，保证幂等
            $update = $this->prepare("UPDATE {$this->tableName} SET expire_time = ? WHERE jti = ?");
            if (!$update->execute([$expireTime, $jti])) {
                throw JWTException::storageError('Database operation failed: ' . $this->pdoError());
            }

            return true;
        } catch (PDOException $e) {
            throw JWTException::storageError('Database operation failed: ' . $e->getMessage());
        }
    }

    public function isBlacklisted(string $jti): bool
    {
        try {
            $stmt = $this->prepare("SELECT 1 FROM {$this->tableName} WHERE jti = ? AND expire_time > ?");
            // 查询失败必须抛出，静默返回 false 会放行本应拦截的令牌
            if (!$stmt->execute([$jti, time()])) {
                throw JWTException::storageError('Database operation failed: ' . $this->pdoError());
            }

            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            throw JWTException::storageError('Database operation failed: ' . $e->getMessage());
        }
    }

    public function cleanup(): bool
    {
        try {
            $stmt = $this->prepare("DELETE FROM {$this->tableName} WHERE expire_time <= ?");
            if (!$stmt->execute([time()])) {
                throw JWTException::storageError('Database operation failed: ' . $this->pdoError());
            }

            return true;
        } catch (PDOException $e) {
            throw JWTException::storageError('Database operation failed: ' . $e->getMessage());
        }
    }

    /**
     * PDO::prepare 在 ERRMODE_SILENT 下失败会返回 false，直接调用会触发致命错误。
     */
    private function prepare(string $sql): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt instanceof PDOStatement) {
            throw JWTException::storageError('Database prepare failed: ' . $this->pdoError());
        }

        return $stmt;
    }

    private function pdoError(): string
    {
        $info = $this->pdo->errorInfo();

        return (string) ($info[2] ?? $info[0] ?? 'unknown error');
    }
}
