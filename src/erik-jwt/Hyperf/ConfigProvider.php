<?php

declare(strict_types=1);

namespace ErikJwt\Hyperf;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                \ErikJwt\JWT::class => function (ContainerInterface $container) {
                    $config = $container->get(ConfigInterface::class)->get('jwt', []);
                    $logger = $container->get(\Psr\Log\LoggerInterface::class);

                    $connections = [];
                    if (($config['storage']['type'] ?? '') === 'redis') {
                        $connections['redis'] = fn() => $container->get(\Hyperf\Redis\Redis::class);
                    }
                    if (($config['storage']['type'] ?? '') === 'database') {
                        $connections['pdo'] = $container->get(\Hyperf\DbConnection\Db::class)->connection()->getPdo();
                    }

                    return \ErikJwt\JWTFactory::createFromConfig($config, $logger, $connections);
                },
            ],
            'middlewares' => [
                'http' => [\ErikJwt\Hyperf\Middleware::class],
            ],
            'commands' => [
                InstallCommand::class,
            ],
            'publish' => [
                [
                    'id'          => 'config',
                    'description' => 'JWT config file.',
                    'source'      => __DIR__ . '/config/jwt.php',
                    'destination' => BASE_PATH . '/config/autoload/jwt.php',
                ],
            ],
        ];
    }
}
