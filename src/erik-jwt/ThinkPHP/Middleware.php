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
use Erikwang2013\Jwt\JWTException;
use think\Request;
use think\Response;

class Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $jwt    = app('erik.jwt');
        $config = config('jwt');

        $except = $config['middleware']['except'] ?? [];
        $path   = $request->pathinfo();
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
            return json(['code' => 401, 'msg' => 'Token not provided', 'data' => null])->code(401);
        }

        try {
            $payload = $jwt->decode($token);
            $request->jwt_payload = $payload;
        } catch (JWTException $e) {
            $msg = in_array($e->getCode(), [
                JWTException::TOKEN_EXPIRED,
                JWTException::TOKEN_INVALID,
                JWTException::TOKEN_BLACKLISTED,
            ], true) ? $e->getMessage() : 'Token authentication failed';
            return json(['code' => 401, 'msg' => $msg, 'data' => null])->code(401);
        }

        return $next($request);
    }
}
