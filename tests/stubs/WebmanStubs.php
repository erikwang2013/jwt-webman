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
/* Webman */
namespace Webman {

    if (!interface_exists(\Webman\MiddlewareInterface::class)) {
        interface MiddlewareInterface
        {
            public function process(\Webman\Http\Request $request, callable $next): \Webman\Http\Response;
        }
    }
}
namespace Webman\Http {

    if (!class_exists(\Webman\Http\Request::class)) {
        #[\AllowDynamicProperties]
        class Request
        {
            private $pathValue;
            private $headers = [];

            public function __construct(string $path = '', array $headers = [])
            {
                $this->pathValue = $path;
                $this->headers = $headers;
            }

            public function path(): string
            {
                return $this->pathValue;
            }

            public function header(string $name, string $default = '')
            {
                return $this->headers[$name] ?? $default;
            }
        }
    }

    if (!class_exists(\Webman\Http\Response::class)) {
        class Response
        {
            private $status;
            private $headers;
            private $body;

            public function __construct(int $status = 200, array $headers = [], string $body = '')
            {
                $this->status = $status;
                $this->headers = $headers;
                $this->body = $body;
            }

            public function getStatusCode(): int
            {
                return $this->status;
            }

            public function rawBody(): string
            {
                return $this->body;
            }

            public function header(string $name, $default = null)
            {
                return $this->headers[$name] ?? $default;
            }
        }
    }
}
namespace support {

    if (!class_exists(\support\Db::class)) {
        class Db
        {
            public static function connection($name = null)
            {
                return new \JwtTestDbConnection();
            }
        }
    }

    if (!class_exists(\support\Redis::class)) {
        class Redis
        {
            public static function connection($name = null)
            {
                return jwt_fw()['redis'] ?? new \JwtTestFakeRedis();
            }
        }
    }
}
