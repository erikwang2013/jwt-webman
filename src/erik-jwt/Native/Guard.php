<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Native;

use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\JWTFactory;

/**
 * 原生 PHP（无框架）请求守卫。
 *
 * 给不带任何框架的入口脚本用，401 响应体与四个框架中间件完全一致：
 *
 *     $guard   = new Guard(JWTFactory::createFromFile(__DIR__ . '/config/jwt.php'));
 *     $payload = $guard->requireAuth();   // 校验失败直接 401 并结束
 *     $userId  = $payload['user_id'];
 */
class Guard
{
    private $jwt;

    public function __construct(JWT $jwt)
    {
        $this->jwt = $jwt;
    }

    /**
     * 从当前请求的 Authorization 头创建守卫。
     */
    public static function fromFile(
        string $configFile,
        ?\Psr\Log\LoggerInterface $logger = null,
        array $connections = []
    ): self {
        return new self(JWTFactory::createFromFile($configFile, $logger, $connections));
    }

    /**
     * 校验令牌并返回 payload；$token 为 null 时自动读取 Authorization 头。
     *
     * @throws JWTException 令牌缺失、无效、过期或在黑名单中
     */
    public function authenticate(?string $token = null): array
    {
        $token = $token ?? JWT::requestToken();
        if ($token === '') {
            throw JWTException::invalid('Token not provided');
        }

        return $this->jwt->decode($token);
    }

    /**
     * 令牌是否有效，不抛异常。
     */
    public function check(?string $token = null): bool
    {
        try {
            $this->authenticate($token);
            return true;
        } catch (JWTException $e) {
            return false;
        }
    }

    /**
     * 入口脚本守卫：校验失败时输出 401 JSON 并结束请求。
     *
     * @return array 校验通过时的 payload
     */
    public function requireAuth(?string $token = null): array
    {
        try {
            return $this->authenticate($token);
        } catch (JWTException $e) {
            self::respond($e);
            exit(1);
        }
    }

    /**
     * 输出与四个框架中间件一致的 401 响应。已在输出缓冲中则只返回响应体。
     */
    public static function respond(JWTException $e): void
    {
        if (!headers_sent()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode(
            ['code' => 401, 'msg' => JWTException::userMessage($e), 'data' => null],
            JSON_UNESCAPED_UNICODE
        );
    }

    public function getJWT(): JWT
    {
        return $this->jwt;
    }
}
