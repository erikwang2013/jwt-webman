<?php
declare(strict_types=1);
/*
 * Framework stubs for integration tests. Minimal stand-ins for the framework
 * base classes (Laravel / ThinkPHP / Webman / Hyperf / PSR) so the JWT
 * integration layer can be exercised with pure PHPUnit, without installing
 * any framework. Every definition is guarded: if the real package is
 * installed, the stub is skipped. All state lives in $GLOBALS['__jwt_fw']
 * and is reset per test via jwt_fw_reset().
 */
/* Laravel */
namespace Illuminate\Support\Facades {

    if (!class_exists(\Illuminate\Support\Facades\Facade::class)) {
        class Facade
        {
            protected static function getFacadeAccessor(): string
            {
                return '';
            }
        }
    }

    if (!class_exists(\Illuminate\Support\Facades\DB::class)) {
        class DB
        {
            public static function connection($name = null)
            {
                return new \JwtTestDbConnection();
            }
        }
    }

    if (!class_exists(\Illuminate\Support\Facades\Redis::class)) {
        class Redis
        {
            public static function connection($name = null)
            {
                return new \JwtTestLaravelRedisConnection();
            }
        }
    }
}
namespace Illuminate\Support {

    if (!class_exists(\Illuminate\Support\ServiceProvider::class)) {
        class ServiceProvider
        {
            protected $app;

            public function __construct($app = null)
            {
                $this->app = $app ?? jwt_fw()['app'];
            }

            public function register(): void
            {
            }

            public function boot(): void
            {
            }

            protected function mergeConfigFrom(string $path, string $key): void
            {
                $existing = $GLOBALS['__jwt_fw']['config'][$key] ?? [];
                $defaults = require $path;
                $GLOBALS['__jwt_fw']['config'][$key] = array_merge(
                    $defaults,
                    is_array($existing) ? $existing : []
                );
            }

            protected function publishes(array $paths, string $group): void
            {
                $GLOBALS['__jwt_fw']['published'][$group] = $paths;
            }

            protected function commands(array $commands): void
            {
                $GLOBALS['__jwt_fw']['commands'] = array_merge($GLOBALS['__jwt_fw']['commands'], $commands);
            }
        }
    }
}
namespace Illuminate\Console {

    if (!class_exists(\Illuminate\Console\Command::class)) {
        class Command
        {
            protected $signature = '';
            protected $description = '';

            public function call(string $command, array $parameters = [])
            {
                $GLOBALS['__jwt_fw']['called'][] = ['command' => $command, 'parameters' => $parameters];
                return 0;
            }

            public function info(string $message): void
            {
                $GLOBALS['__jwt_fw']['outputs'][] = $message;
            }

            public function getSignature(): string
            {
                return $this->signature;
            }

            public function getDescription(): string
            {
                return $this->description;
            }
        }
    }
}
namespace Illuminate\Http {

    if (!class_exists(\Illuminate\Http\Request::class)) {
        #[\AllowDynamicProperties]
        class Request
        {
            public $attributes;
            private $pathValue;
            private $headers = [];

            public function __construct(string $path = '', array $headers = [])
            {
                $this->pathValue = $path;
                $this->headers = $headers;
                $this->attributes = new \JwtTestAttributeBag();
            }

            public function path(): string
            {
                return $this->pathValue;
            }

            public function header(string $key, $default = null)
            {
                return $this->headers[$key] ?? $default;
            }
        }
    }
}
