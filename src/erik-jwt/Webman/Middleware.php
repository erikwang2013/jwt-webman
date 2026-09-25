<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik
 * Author: erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Webman;

use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\JWTFactory;
use Erikwang2013\Jwt\MiddlewareSupport;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class Middleware implements MiddlewareInterface
{
    use MiddlewareSupport;

    private static ?JWT $jwtInstance = null;

    private static function getJWT(array $config): JWT
    {
        if (self::$jwtInstance !== null) {
            return self::$jwtInstance;
        }

        // 只在用到对应驱动时取连接：装了 webman/database 才能取到 PDO，
        // file / redis 存储的应用不应该因为它没装或数据库故障而整个挂掉
        $type = $config['storage']['type'] ?? 'file';
        $connections = [];
        if ($type === 'redis') {
            $connections['redis'] = fn() => \support\Redis::connection();
        }
        if ($type === 'database') {
            $connections['pdo'] = \support\Db::connection()->getPdo();
        }

        self::$jwtInstance = JWTFactory::createFromConfig($config, null, $connections);

        return self::$jwtInstance;
    }

    public function process(Request $request, callable $next): Response
    {
        $config = config('plugin.erikwang2013.jwt.jwt', []);

        $except = $config['middleware']['except'] ?? [];
        if (self::matchesExcept($except, $request->path())) {
            return $next($request);
        }

        $token = JWT::bearerToken($request->header('Authorization', ''));

        if ($token === '') {
            return new Response(401, ['Content-Type' => 'application/json'],
                json_encode(['code' => 401, 'msg' => 'Token not provided', 'data' => null]));
        }

        try {
            $payload = self::getJWT($config)->decode($token);
            $request->jwt_payload = $payload;
        } catch (JWTException $e) {
            return new Response(401, ['Content-Type' => 'application/json'],
                json_encode(['code' => 401, 'msg' => JWTException::userMessage($e), 'data' => null]));
        }

        return $next($request);
    }
}
