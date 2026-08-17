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
use Erikwang2013\Jwt\JWTFactory;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class Middleware implements MiddlewareInterface
{
    private static ?JWT $jwtInstance = null;

    private static function getJWT(): JWT
    {
        if (self::$jwtInstance !== null) {
            return self::$jwtInstance;
        }

        $config = config('plugin.erikwang2013.jwt.jwt', []);
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
        $path   = $request->path();
        foreach ($except as $pattern) {
            try {
                if (preg_match('#^' . $pattern . '$#', $path)) {
                    return $next($request);
                }
            } catch (\Throwable $e) {
                error_log('JWT middleware: invalid except pattern "' . $pattern . '": ' . $e->getMessage());
            }
        }

        $token = $request->header('Authorization', '');
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        if (empty($token)) {
            return new Response(401, ['Content-Type' => 'application/json'],
                json_encode(['code' => 401, 'msg' => 'Token not provided', 'data' => null]));
        }

        try {
            $jwt = self::getJWT();
            $payload = $jwt->decode($token);
            $request->jwt_payload = $payload;
        } catch (\Erikwang2013\Jwt\JWTException $e) {
            $msg = in_array($e->getCode(), [
                \Erikwang2013\Jwt\JWTException::TOKEN_EXPIRED,
                \Erikwang2013\Jwt\JWTException::TOKEN_INVALID,
                \Erikwang2013\Jwt\JWTException::TOKEN_BLACKLISTED,
            ], true) ? $e->getMessage() : 'Token authentication failed';
            return new Response(401, ['Content-Type' => 'application/json'],
                json_encode(['code' => 401, 'msg' => $msg, 'data' => null]));
        }

        return $next($request);
    }
}
