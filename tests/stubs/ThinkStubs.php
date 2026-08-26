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
/* Think */
namespace think {

    if (!class_exists(\think\Facade::class)) {
        class Facade
        {
            protected static function getFacadeClass(): string
            {
                return '';
            }
        }
    }

    if (!class_exists(\think\Service::class)) {
        class Service
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
        }
    }

    if (!class_exists(\think\Request::class)) {
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

            public function pathinfo(): string
            {
                return $this->pathValue;
            }

            public function header(string $key, $default = null)
            {
                return $this->headers[$key] ?? $default;
            }
        }
    }

    if (!class_exists(\think\Response::class)) {
        class Response
        {
            private $data = [];
            private $status = 200;

            public function data($data)
            {
                $this->data = $data;
                return $this;
            }

            public function code(int $code)
            {
                $this->status = $code;
                return $this;
            }

            public function getData()
            {
                return $this->data;
            }

            public function getCode(): int
            {
                return $this->status;
            }

            public function getContent(): string
            {
                return json_encode($this->data);
            }
        }
    }
}
namespace think\console {

    if (!class_exists(\think\console\Command::class)) {
        class Command
        {
            protected $name = '';
            protected $description = '';

            public function setName(string $name)
            {
                $this->name = $name;
                return $this;
            }

            public function setDescription(string $description)
            {
                $this->description = $description;
                return $this;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getDescription(): string
            {
                return $this->description;
            }

            protected function execute(\think\console\Input $input, \think\console\Output $output): int
            {
                return 0;
            }
        }
    }

    if (!class_exists(\think\console\Input::class)) {
        class Input
        {
        }
    }

    if (!class_exists(\think\console\Output::class)) {
        class Output
        {
            public function info(string $message): void
            {
                $GLOBALS['__jwt_fw']['outputs'][] = $message;
            }

            public function warning(string $message): void
            {
                $GLOBALS['__jwt_fw']['outputs'][] = $message;
            }
        }
    }
}
namespace think\facade {

    if (!class_exists(\think\facade\Cache::class)) {
        class Cache
        {
            public static function store($store = null)
            {
                return new \JwtTestThinkCacheStore();
            }
        }
    }

    if (!class_exists(\think\facade\Db::class)) {
        class Db
        {
            public static function connect($name = null)
            {
                return new \JwtTestDbConnection();
            }
        }
    }
}
