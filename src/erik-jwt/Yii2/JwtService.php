<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Yii2;

use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTFactory;
use Psr\Log\LoggerInterface;
use Yii;
use yii\base\Component;

/**
 * Yii2 应用组件：Yii::$app->jwt
 *
 * 注册（config/web.php）：
 *
 *     'components' => [
 *         'jwt' => [
 *             'class'  => \Erikwang2013\Jwt\Yii2\JwtService::class,
 *             // 不填 config 则回退到 Yii::$app->params['jwt']
 *             'config' => require __DIR__ . '/jwt.php',
 *         ],
 *     ],
 *
 * 之后即可 Yii::$app->jwt->encode(...) / Yii::$app->jwt->decode($token)。
 */
class JwtService extends Component
{
    /**
     * @var array JWT 配置；留空时回退到 Yii::$app->params['jwt']
     */
    public $config = [];

    /**
     * @var LoggerInterface|null Yii2 自带日志不是 PSR-3，需要日志时自行注入
     */
    public $logger;

    /**
     * @var JWT|null
     */
    private $jwtInstance;

    public function getJwt(): JWT
    {
        if ($this->jwtInstance === null) {
            $config = $this->config ?: (Yii::$app->params['jwt'] ?? []);
            $this->jwtInstance = JWTFactory::createFromConfig($config, $this->logger, self::connections($config));
        }

        return $this->jwtInstance;
    }

    /**
     * 按需解析存储连接：只有 storage.type 指向某个驱动时才去取对应组件。
     * file 存储的应用不应该因为没装 yii2-redis 或数据库不可用而整体挂掉。
     */
    private static function connections(array $config): array
    {
        $type        = $config['storage']['type'] ?? 'file';
        $connections = [];

        if ($type === 'redis' && Yii::$app->has('redis')) {
            $connections['redis'] = static fn() => Yii::$app->get('redis');
        }
        if ($type === 'database' && Yii::$app->has('db')) {
            $connections['pdo'] = Yii::$app->get('db')->getPdo();
        }
        if ($type === 'memcached' && Yii::$app->has('memcached')) {
            $connections['memcached'] = Yii::$app->get('memcached');
        }

        return $connections;
    }

    public function encode(array $payload, int $expire = 0, array $headers = []): string
    {
        return $this->getJwt()->encode($payload, $expire, $headers);
    }

    public function decode(string $token, bool $allowRefresh = false): array
    {
        return $this->getJwt()->decode($token, $allowRefresh);
    }

    public function validate(string $token, bool $allowRefresh = false): bool
    {
        return $this->getJwt()->validate($token, $allowRefresh);
    }

    public function refresh(string $token, int $newExpire = 0): string
    {
        return $this->getJwt()->refresh($token, $newExpire);
    }

    public function blacklist(string $token): bool
    {
        return $this->getJwt()->blacklist($token);
    }

    public function isBlacklisted(string $token): bool
    {
        return $this->getJwt()->isBlacklisted($token);
    }

    public function cleanup(): bool
    {
        return $this->getJwt()->cleanup();
    }
}
