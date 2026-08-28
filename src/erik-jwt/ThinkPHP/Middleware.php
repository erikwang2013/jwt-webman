<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\ThinkPHP;

use Closure;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\MiddlewareSupport;
use think\Request;
use think\Response;

class Middleware
{
    use MiddlewareSupport;

    public function handle(Request $request, Closure $next): Response
    {
        $jwt    = app('erik.jwt');
        $config = config('jwt');

        $except = $config['middleware']['except'] ?? [];
        if (self::matchesExcept($except, $request->pathinfo())) {
            return $next($request);
        }

        $token = JWT::bearerToken($request->header('Authorization', ''));

        if ($token === '') {
            return json(['code' => 401, 'msg' => 'Token not provided', 'data' => null])->code(401);
        }

        try {
            $payload = $jwt->decode($token);
            $request->jwt_payload = $payload;
        } catch (JWTException $e) {
            return json(['code' => 401, 'msg' => JWTException::userMessage($e), 'data' => null])->code(401);
        }

        return $next($request);
    }
}
