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

        self::$jwtInstance = JWTFactory::createFromConfig($config, null, [
            'redis' => fn() => \support\Redis::connection(),
            'pdo'   => \support\Db::connection()->getPdo(),
        ]);

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
