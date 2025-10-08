<?php
// src/TokenStorageInterface.php

namespace ErikJwt;

interface TokenStorageInterface
{
    /**
     * 将令牌加入黑名单
     */
    public function blacklist(string $jti, int $expireTime): bool;

    /**
     * 检查令牌是否在黑名单中
     */
    public function isBlacklisted(string $jti): bool;

    /**
     * 清理过期的黑名单条目
     */
    public function cleanup(): bool;
}