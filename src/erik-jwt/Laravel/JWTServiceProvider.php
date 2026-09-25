<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Laravel;

use Erikwang2013\Jwt\JWTFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;

class JWTServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/jwt.php', 'jwt');

        $this->app->singleton('erik.jwt', function ($app) {
            $config = $app['config']->get('jwt', []);

            // 只在用到对应驱动时取连接：数据库不可用不应该影响 file / redis 存储的应用
            $type = $config['storage']['type'] ?? 'file';
            $connections = [];
            if ($type === 'redis') {
                $connections['redis'] = fn() => Redis::connection()->client();
            }
            if ($type === 'database') {
                $connections['pdo'] = DB::connection()->getPdo();
            }
            // memcached 由工厂按 storage.servers 自行构造，容器里没有对应绑定时不要塞一个空实例

            return JWTFactory::createFromConfig($config, $app['log']->channel(), $connections);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/jwt.php' => config_path('jwt.php'),
            ], 'jwt-config');

            $this->commands([InstallCommand::class]);
        }

        $this->app['router']->aliasMiddleware('jwt', Middleware::class);
    }
}
