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

    public function verify(string $token): object
    {
        try {
            return (object) $this->jwt->decode($token);
        } catch (\Exception $e) {
            throw JWTException::invalid('Token validation failed');
        }
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
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($header) && function_exists('getallheaders')) {
            $headers = getallheaders();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (strncasecmp($header, 'Bearer ', 7) === 0) {
            return substr($header, 7);
        }
        throw JWTException::invalid('No Bearer token found in request');
    }
}
