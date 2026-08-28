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

use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class JWT
{
    private $secretKey;
    private $algorithm;
    private $tokenStorage;
    private $issuer;
    private $audience;
    private $leeway;
    private $config;
    private $logger;

    public function __construct(
        array $config,
        ?LoggerInterface $logger = null
    ) {
        $this->config       = $config;
        $this->secretKey    = $config['secret_key'] ?? '';
        $this->algorithm    = $config['algorithm'] ?? 'HS256';
        $this->issuer       = $config['issuer'] ?? '';
        $this->audience     = $config['audience'] ?? '';
        $this->leeway       = (int)($config['leeway'] ?? 0);
        $this->tokenStorage = $config['_token_storage'] ?? new FileTokenStorage();
        $this->logger       = $logger ?? new NullLogger();

        // firebase/php-jwt v7 rejects keys shorter than 32 bytes
        if (strlen($this->secretKey) < 32) {
            throw JWTException::configError('Secret key must be at least 32 characters (256 bits)');
        }
    }

    /**
     * 生成JWT令牌
     */
    public function encode(array $payload, int $expire = 0, array $headers = []): string
    {
        unset($headers['alg']);
        $config = $this->config;
        if ($expire === 0) {
            $expire = (isset($payload['token_type']) && $payload['token_type'] === 'refresh')
                ? ($config['refresh_expire'] ?? 7200)
                : ($config['default_expire'] ?? 3600);
        }
        $now = time();
        $defaultPayload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $expire,
            'jti' => $this->generateJti()
        ];

        $finalPayload = array_merge($payload, $defaultPayload);

        return FirebaseJWT::encode($finalPayload, $this->secretKey, $this->algorithm, null, $headers);
    }

    /**
     * 解码并验证JWT令牌
     */
    public function decode(string $token): array
    {
        // FirebaseJWT::$leeway is a global static — restore it or concurrent JWT instances clash
        $previousLeeway = FirebaseJWT::$leeway;
        try {
            FirebaseJWT::$leeway = $this->leeway;
            return $this->decodeWithLeeway($token);
        } finally {
            FirebaseJWT::$leeway = $previousLeeway;
        }
    }

    private function decodeWithLeeway(string $token): array
    {
        try {
            $decoded = FirebaseJWT::decode($token, new Key($this->secretKey, $this->algorithm));
            $payload = (array) $decoded;
            if ($this->issuer !== '' && ($payload['iss'] ?? null) !== $this->issuer) {
                throw JWTException::invalid('Invalid issuer');
            }
            if ($this->audience !== '' && !$this->audMatches($payload['aud'] ?? null)) {
                throw JWTException::invalid('Invalid audience');
            }
            if (isset($payload['jti']) && $this->tokenStorage->isBlacklisted($payload['jti'])) {
                throw JWTException::blacklisted();
            }

            return $payload;
        } catch (JWTException $e) {
            $this->logger->info($e->getMessage());
            throw $e;
        } catch (ExpiredException $e) {
            $this->logger->info($e->getMessage());
            throw JWTException::expired();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw JWTException::invalid($e->getMessage());
        }
    }

    private function audMatches($payloadAud): bool
    {
        if (is_array($payloadAud)) {
            return in_array($this->audience, $payloadAud, true);
        }
        return $payloadAud === $this->audience;
    }

    /**
     * 验证令牌而不抛出异常（decode 内部已检查黑名单）
     */
    public function validate(string $token): bool
    {
        try {
            $this->decode($token);
            return true;
        } catch (Exception $e) {
            $this->logger->warning($e->getMessage());
            return false;
        }
    }

    /**
     * 刷新令牌
     */
    public function refresh(string $token, int $newExpire = 3600): string
    {
        $payload = $this->decode($token);

        if (($payload['token_type'] ?? '') !== 'refresh') {
            throw JWTException::invalid('Only refresh tokens can be refreshed');
        }

        if (isset($payload['jti'])) {
            $this->tokenStorage->blacklist($payload['jti'], $payload['exp']);
        }

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
            if (
                $e->getCode() === JWTException::TOKEN_BLACKLISTED ||
                $e->getCode() === JWTException::TOKEN_EXPIRED
            ) {
                try {
                    $payload = $this->getPayloadWithoutValidation($token);
                    if (isset($payload['jti']) && isset($payload['exp'])) {
                        return $this->tokenStorage->blacklist($payload['jti'], $payload['exp']);
                    }
                } catch (JWTException $e) {
                    if ($e->getCode() === JWTException::STORAGE_ERROR) {
                        throw $e;
                    }
                    $this->logger->warning($e->getMessage());
                }
            }
            $this->logger->error($e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    /**
     * 检查令牌是否在黑名单中
     *
     * 只按 jti 查黑名单，不验证签名（单次存储读取）。安全关键的校验路径请先 decode()。
     */
    public function isBlacklisted(string $token): bool
    {
        try {
            $payload = $this->getPayloadWithoutValidation($token);
            return isset($payload['jti']) && $this->tokenStorage->isBlacklisted($payload['jti']);
        } catch (JWTException $e) {
            $this->logger->error($e->getMessage());
            return false;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
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

        $payload = $parts[1];
        $remainder = strlen($payload) % 4;
        if ($remainder) {
            $payload .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $payload));
        $result = json_decode($decoded, true);
        return is_array($result) ? $result : [];
    }

    /**
     * 设置令牌存储
     */
    public function setTokenStorage(TokenStorageInterface $tokenStorage): void
    {
        $this->tokenStorage = $tokenStorage;
    }

    public static function bearerToken($header): string
    {
        if (is_string($header) && strncasecmp($header, 'Bearer ', 7) === 0) {
            return substr($header, 7);
        }
        return '';
    }

    public static function writeEnvSecret(string $envPath, string $key, string $secret): void
    {
        if (!file_exists($envPath)) {
            return;
        }
        $envContent = file_get_contents($envPath);
        $pattern    = '/^' . preg_quote($key, '/') . '=.*$/m';
        if (preg_match($pattern, $envContent)) {
            // Escape $ in the replacement so secrets containing $0/$1 are not mangled
            $replacement = str_replace('$', '\\$', $key . '=' . $secret);
            $envContent  = preg_replace($pattern, $replacement, $envContent) ?? $envContent;
        } else {
            $envContent .= "\n{$key}={$secret}\n";
        }
        file_put_contents($envPath, $envContent, LOCK_EX);
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
