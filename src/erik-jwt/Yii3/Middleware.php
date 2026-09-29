<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Yii3;

use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\MiddlewareSupport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Yii3 PSR-15 中间件。
 *
 * 由 config/di.php 自动装配；应用侧只需把 Middleware::class 加进路由或全局中间件链：
 *
 *     // config/web/di/application.php 或路由配置
 *     Route::methods([Method::GET], '/api/profile', ProfileAction::class)
 *         ->withMiddleware(Yiisoft\Jwt\...\Middleware::class);
 *
 * 校验通过后 payload 作为 jwt_payload 属性挂到请求上：
 *
 *     $payload = $request->getAttribute('jwt_payload');
 *
 * 401 响应体与 webman / Laravel / ThinkPHP / Hyperf 四个适配器保持一致。
 */
final class Middleware implements MiddlewareInterface
{
    use MiddlewareSupport;

    private JWT $jwt;

    private ResponseFactoryInterface $responseFactory;

    /**
     * @var array 跳过校验的路径，正则片段，如 ['api/login', 'api/health']
     */
    private array $except;

    public function __construct(JWT $jwt, ResponseFactoryInterface $responseFactory, array $except = [])
    {
        $this->jwt             = $jwt;
        $this->responseFactory = $responseFactory;
        $this->except          = $except;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (self::matchesExcept($this->except, $request->getUri()->getPath())) {
            return $handler->handle($request);
        }

        $token = JWT::bearerToken($request->getHeaderLine('Authorization'));

        if ($token === '') {
            return $this->unauthorized('Token not provided');
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (JWTException $e) {
            return $this->unauthorized(JWTException::userMessage($e));
        }

        // PSR-15 要求中间件不改写入参请求，只能派生新实例往下传
        return $handler->handle($request->withAttribute('jwt_payload', $payload));
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(401)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');

        $response->getBody()->write(json_encode(
            ['code' => 401, 'msg' => $message, 'data' => null],
            JSON_UNESCAPED_UNICODE
        ));

        return $response;
    }
}
