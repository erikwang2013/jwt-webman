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
    private $failOpen;

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
        // 黑名单存储故障时的策略：false（默认）拒绝所有令牌，true 放行并记 error 日志
        $this->failOpen     = (bool) ($config['storage']['fail_open'] ?? false);

        // firebase/php-jwt v7 rejects keys shorter than 32 bytes
        if (strlen($this->secretKey) < 32) {
            throw JWTException::configError('Secret key must be at least 32 characters (256 bits)');
        }

        // 算法拼错时 firebase 会在 encode 抛 DomainException、在 decode 把合法令牌全判为无效，
        // 表现成"到处 401"，不如启动即报配置错误
        if (!isset(FirebaseJWT::$supported_algs[$this->algorithm])) {
            throw JWTException::configError('Unsupported algorithm: ' . $this->algorithm);
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
     *
     * 默认拒绝刷新令牌：刷新令牌有效期更长，且刷新时会轮换，把它当访问令牌用
     * 等于让"登出/轮换"失效。确实需要读取刷新令牌时显式传 $allowRefresh = true。
     */
    public function decode(string $token, bool $allowRefresh = false): array
    {
        // FirebaseJWT::$leeway is a global static — restore it or concurrent JWT instances clash
        $previousLeeway = FirebaseJWT::$leeway;
        try {
            FirebaseJWT::$leeway = $this->leeway;
            return $this->decodeWithLeeway($token, $allowRefresh);
        } finally {
            FirebaseJWT::$leeway = $previousLeeway;
        }
    }

    private function decodeWithLeeway(string $token, bool $allowRefresh = false): array
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
            if (!$allowRefresh && ($payload['token_type'] ?? null) === 'refresh') {
                throw JWTException::invalid('Refresh token cannot be used as an access token');
            }

            if (isset($payload['jti']) && $this->isBlacklistedJti($payload['jti'])) {
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

    /**
     * 查询 jti 是否在黑名单。存储故障时按 storage.fail_open 决定放行还是抛出。
     */
    private function isBlacklistedJti(string $jti): bool
    {
        try {
            return $this->tokenStorage->isBlacklisted($jti);
        } catch (JWTException $e) {
            if ($e->getCode() !== JWTException::STORAGE_ERROR || !$this->failOpen) {
                throw $e;
            }
            $this->logger->error('Blacklist check failed, fail-open enabled: ' . $e->getMessage());
            return false;
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
    public function validate(string $token, bool $allowRefresh = false): bool
    {
        try {
            $this->decode($token, $allowRefresh);
            return true;
        } catch (Exception $e) {
            $this->logger->warning($e->getMessage());
            return false;
        }
    }

    /**
     * 刷新令牌
     *
     * $newExpire 为 0 时使用配置的 refresh_expire（默认 7200 秒），与 encode() 保持一致。
     */
    public function refresh(string $token, int $newExpire = 0): string
    {
        $payload = $this->decode($token, true);

        if (($payload['token_type'] ?? '') !== 'refresh') {
            throw JWTException::invalid('Only refresh tokens can be refreshed');
        }

        // firebase v7 允许不带 exp 的令牌，缺失时不能直接当 int 用
        $oldExp = (int) ($payload['exp'] ?? 0);
        if (isset($payload['jti']) && $oldExp > time()) {
            $this->tokenStorage->blacklist($payload['jti'], $oldExp);
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
            $payload = $this->decode($token, true);
            if (!isset($payload['jti'])) {
                return false;
            }

            // 无 exp 的令牌没有可回收期限，expire_time 传 0 时各存储都按"已过期"跳过
            return $this->tokenStorage->blacklist($payload['jti'], (int) ($payload['exp'] ?? 0));
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
        } catch (\Throwable $e) {
            // 含 TypeError/Error：这里是未验证令牌的入口，任何输入都不能变成 500
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
        if (!is_array($result)) {
            return [];
        }

        // 未验证令牌的 jti 不可信：伪造 {"jti":[...]} 会让 storage 的 string 参数抛 TypeError
        if (isset($result['jti']) && !is_string($result['jti'])) {
            unset($result['jti']);
        }

        return $result;
    }

    /**
     * 设置令牌存储
     */
    public function setTokenStorage(TokenStorageInterface $tokenStorage): void
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * 从当前请求提取 Bearer token，未携带时返回空串。
     *
     * 依次检查 $_SERVER['HTTP_AUTHORIZATION']、REDIRECT_HTTP_AUTHORIZATION
     * （Apache / nginx 重写转发后的落点）与 getallheaders()（大小写不敏感），
     * 适用于原生 PHP 与 PHP-FPM。
     */
    public static function requestToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if ($header === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strcasecmp((string) $name, 'Authorization') === 0) {
                        $header = (string) $value;
                        break;
                    }
                }
            }
        }

        return self::bearerToken($header);
    }

    public static function bearerToken($header): string
    {
        if (is_string($header) && strncasecmp($header, 'Bearer ', 7) === 0) {
            // 容忍 "Bearer  <token>" 这类多余空白
            return trim(substr($header, 7));
        }
        return '';
    }

    /**
     * 把密钥写入 .env，写入成功返回 true。
     *
     * 返回 false 表示 .env 不存在或不可写 —— 调用方（安装命令）必须据此提示用户，
     * 否则会出现"安装成功"但密钥并未落盘的假象。
     */
    public static function writeEnvSecret(string $envPath, string $key, string $secret): bool
    {
        if (!file_exists($envPath)) {
            return false;
        }
        $envContent = file_get_contents($envPath);
        if ($envContent === false) {
            return false;
        }

        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
        if (preg_match($pattern, $envContent)) {
            // 反斜杠与 $ 都要转义，否则替换串里的 \1 / $1 会被 preg_replace 当反向引用吞掉
            $replacement = str_replace(['\\', '$'], ['\\\\', '\\$'], $key . '=' . $secret);
            $envContent  = preg_replace($pattern, $replacement, $envContent) ?? $envContent;
        } else {
            $envContent .= "\n{$key}={$secret}\n";
        }

        return file_put_contents($envPath, $envContent, LOCK_EX) !== false;
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
