<?php
// src/JWT.php

namespace ErikJwt;

use Exception;
use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT
{
    private $secretKey;
    private $algorithm;
    private $tokenStorage;
    private $issuer;
    private $audience;
    private $leeway;

    public function __construct(
        string $secretKey,
        string $algorithm = 'HS256',
        TokenStorageInterface $tokenStorage = null,
        string $issuer = '',
        string $audience = '',
        int $leeway = 0
    ) {
        $this->secretKey = $secretKey;
        $this->algorithm = $algorithm;
        $this->tokenStorage = $tokenStorage ?? new FileTokenStorage();
        $this->issuer = $issuer;
        $this->audience = $audience;
        $this->leeway = $leeway;

        // 设置JWT leeway
        FirebaseJWT::$leeway = $leeway;
    }

    /**
     * 生成JWT令牌
     */
    public function encode(array $payload, int $expire = 3600, array $headers = []): string
    {
        $now = time();
        $defaultPayload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $expire,
            'jti' => $this->generateJti()
        ];

        $finalPayload = array_merge($defaultPayload, $payload);

        return FirebaseJWT::encode($finalPayload, $this->secretKey, $this->algorithm, null, $headers);
    }

    /**
     * 解码并验证JWT令牌
     */
    public function decode(string $token): array
    {
        try {
            $decoded = FirebaseJWT::decode($token, new Key($this->secretKey, $this->algorithm));
            $payload = (array) $decoded;

            // 检查黑名单
            if (isset($payload['jti']) && $this->tokenStorage->isBlacklisted($payload['jti'])) {
                throw JWTException::blacklisted();
            }

            return $payload;
        } catch (JWTException $e) {
            // 重新抛出我们自己的异常
            throw $e;
        } catch (Exception $e) {
            // 将其他异常转换为JWTException
            if (strpos($e->getMessage(), 'Expired token') !== false) {
                throw JWTException::expired();
            }

            throw JWTException::invalid($e->getMessage());
        }
    }

    /**
     * 验证令牌而不抛出异常
     */
    public function validate(string $token): bool
    {
        try {
            $payload = $this->decode($token);

            // 额外检查黑名单
            if (isset($payload['jti']) && $this->tokenStorage->isBlacklisted($payload['jti'])) {
                return false;
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 刷新令牌
     */
    public function refresh(string $token, int $newExpire = 3600): string
    {
        $payload = $this->decode($token);

        // 将原令牌加入黑名单
        if (isset($payload['jti'])) {
            $this->tokenStorage->blacklist($payload['jti'], $payload['exp']);
        }

        // 移除时间相关字段
        unset($payload['iat'], $payload['nbf'], $payload['exp'], $payload['jti']);

        return $this->encode($payload, $newExpire);
    }

    /**
     * 生成唯一的JWT ID
     */
    private function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * 将令牌加入黑名单
     */
    public function blacklist(string $token): bool
    {
        try {
        $payload = $this->decode($token);
        if (!isset($payload['jti'])) {
            return false;
        }
        
        return $this->tokenStorage->blacklist($payload['jti'], $payload['exp']);
    } catch (JWTException $e) {
        // 如果是黑名单或过期异常，仍然尝试加入黑名单
        if ($e->getCode() === JWTException::TOKEN_BLACKLISTED || 
            $e->getCode() === JWTException::TOKEN_EXPIRED) {
            try {
                $payload = $this->getPayloadWithoutValidation($token);
                if (isset($payload['jti']) && isset($payload['exp'])) {
                    return $this->tokenStorage->blacklist($payload['jti'], $payload['exp']);
                }
            } catch (Exception $e) {
                // 忽略解析错误
            }
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
    }

    /**
     * 检查令牌是否在黑名单中
     */
    public function isBlacklisted(string $token): bool
    {
        try {
            $payload = $this->decode($token);
            return isset($payload['jti']) && $this->tokenStorage->isBlacklisted($payload['jti']);
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * 获取令牌 payload 而不验证
     */
    public function getPayloadWithoutValidation(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new JWTException('Invalid token structure');
        }

        $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
        return json_decode($payload, true);
    }

    /**
     * 设置令牌存储
     */
    public function setTokenStorage(TokenStorageInterface $tokenStorage): void
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * 获取当前使用的算法
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * 清理过期的黑名单条目
     */
    public function cleanup(): bool
    {
        return $this->tokenStorage->cleanup();
    }
}
