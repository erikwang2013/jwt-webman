<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Hyperf;

use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\MiddlewareSupport;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;

#[Aspect]
class JWTAspect extends AbstractAspect
{
    use MiddlewareSupport;

    public array $annotations = [
        JWT::class,
    ];

    public function __construct(
        protected ContainerInterface $container,
        protected RequestInterface $request,
        protected ResponseInterface $response
    ) {
    }

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $jwt    = $this->container->get(\Erikwang2013\Jwt\JWT::class);
        $config = $this->container->get(\Hyperf\Contract\ConfigInterface::class)->get('jwt', []);

        $except = $config['middleware']['except'] ?? [];
        if (self::matchesExcept($except, $this->request->getUri()->getPath())) {
            return $proceedingJoinPoint->process();
        }

        $token = \Erikwang2013\Jwt\JWT::bearerToken($this->request->getHeaderLine('Authorization'));

        if ($token === '') {
            return $this->response->json([
                'code' => 401, 'msg' => 'Token not provided', 'data' => null
            ])->withStatus(401);
        }

        try {
            $payload = $jwt->decode($token);
            Context::set('jwt_payload', $payload);
        } catch (JWTException $e) {
            return $this->response->json([
                'code' => 401, 'msg' => JWTException::userMessage($e), 'data' => null
            ])->withStatus(401);
        }

        return $proceedingJoinPoint->process();
    }
}
