<?php

namespace ErikJwt\Laravel;

use Illuminate\Support\Facades\Facade as LaravelFacade;

/**
 * @method static string encode(array $payload, int $expire = 0, array $headers = [])
 * @method static array  decode(string $token)
 * @method static bool   validate(string $token)
 * @method static string refresh(string $token, int $newExpire = 3600)
 * @method static bool   blacklist(string $token)
 * @method static bool   isBlacklisted(string $token)
 */
class Facade extends LaravelFacade
{
    protected static function getFacadeAccessor(): string
    {
        return 'erik.jwt';
    }
}
