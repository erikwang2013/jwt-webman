<?php

declare(strict_types=1);

namespace Erikwang2013\Jwt;

class JwtWrapper
{
    private $jwt;

    public function __construct(JWT $jwt)
    {
        $this->jwt = $jwt;
    }

    public function create(array $payload, int $expire = 0): string
    {
        return $this->jwt->encode($payload, $expire);
    }

    public function refresh(?string $token = null): string
    {
        if ($token === null) {
            $token = $this->currentToken();
        }
        return $this->jwt->refresh($token);
    }

    /**
     * 校验令牌并返回 payload 对象
     *
     * $token 为 null 时自动从当前请求的 Authorization 头获取，原生 PHP 入口脚本里可直接调用。
     */
    public function verify(?string $token = null): object
    {
        return (object) $this->jwt->decode($token ?? $this->currentToken());
    }

    public function decode(string $token): array
    {
        return $this->jwt->decode($token);
    }

    public function validate(string $token): bool
    {
        return $this->jwt->validate($token);
    }

    public function blacklist(string $token): bool
    {
        return $this->jwt->blacklist($token);
    }

    public function isBlacklisted(string $token): bool
    {
        return $this->jwt->isBlacklisted($token);
    }

    private function currentToken(): string
    {
        $token = JWT::requestToken();
        if ($token === '') {
            throw JWTException::invalid('No Bearer token found in request');
        }

        return $token;
    }
}
