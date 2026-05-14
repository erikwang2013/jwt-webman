<?php

namespace ErikJwt\ThinkPHP;

use think\Facade;

/**
 * @method static string encode(array $payload, int $expire = 0, array $headers = [])
 * @method static array  decode(string $token)
 * @method static bool   validate(string $token)
 * @method static string refresh(string $token, int $newExpire = 3600)
 * @method static bool   blacklist(string $token)
 * @method static bool   isBlacklisted(string $token)
 */
class JWT extends Facade
{
    protected static function getFacadeClass(): string
    {
        return 'erik.jwt';
    }
}
