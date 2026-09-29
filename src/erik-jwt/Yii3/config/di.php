<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\JWTFactory;
use Erikwang2013\Jwt\Yii3\Middleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Config\Config;

return [
    JWT::class => static function (ContainerInterface $container): JWT {
        $params = $container->get(Config::class)->get('params');
        $config = $params['jwt'] ?? [];
        $type   = $config['storage']['type'] ?? 'file';
        $id     = $config['storage']['connection'] ?? null;

        // 只在用到对应驱动时才去取连接：file / redis 存储的应用不应该因为
        // 容器里没有数据库服务而整体挂掉
        $connections = [];
        if ($type === 'redis') {
            if (!is_string($id) || $id === '') {
                throw JWTException::storageError(
                    "storage.connection must be the container service id of a Redis client when storage type is 'redis'"
                );
            }
            $connections['redis'] = static fn() => $container->get($id);
        }
        if ($type === 'database') {
            if (!is_string($id) || $id === '') {
                throw JWTException::storageError(
                    "storage.connection must be the container service id of a DB connection when storage type is 'database'"
                );
            }
            $connections['pdo'] = $container->get($id)->getPDO();
        }
        if ($type === 'memcached' && $container->has(\Memcached::class)) {
            $connections['memcached'] = $container->get(\Memcached::class);
        }

        $logger = $container->has(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null;

        return JWTFactory::createFromConfig($config, $logger, $connections);
    },

    Middleware::class => static function (ContainerInterface $container): Middleware {
        $params = $container->get(Config::class)->get('params');

        return new Middleware(
            $container->get(JWT::class),
            $container->get(ResponseFactoryInterface::class),
            $params['jwt']['middleware']['except'] ?? []
        );
    },
];
