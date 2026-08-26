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
/* Global helper state + shared JwtTest* doubles. */
namespace {

    /* ------------------------------------------------------------------ *
     * Global helper state and functions.
     * ------------------------------------------------------------------ */

    if (!function_exists('jwt_fw_reset')) {
        function jwt_fw_reset(array $overrides = []): array
        {
            $defaults = [
                'config'             => [],
                'bindings'           => [],
                'app'                => null,
                'base_path'          => null,
                'log'                => null,
                'router'             => null,
                'pdo'                => null,
                'redis'              => null,
                'published'          => [],
                'commands'           => [],
                'called'             => [],
                'outputs'            => [],
                'middleware_aliases' => [],
                'context'            => [],
                'console_running'    => false,
                'config_path'        => null,
                'root_path'          => null,
            ];
            $GLOBALS['__jwt_fw'] = array_merge($defaults, $overrides);
            return $GLOBALS['__jwt_fw'];
        }
    }

    if (!function_exists('jwt_fw')) {
        function jwt_fw(): array
        {
            if (!isset($GLOBALS['__jwt_fw'])) {
                jwt_fw_reset();
            }
            return $GLOBALS['__jwt_fw'];
        }
    }

    if (!function_exists('app')) {
        function app(?string $abstract = null)
        {
            $fw = jwt_fw();
            if ($abstract === null) {
                return $fw['app'];
            }
            if (!array_key_exists($abstract, $fw['bindings'])) {
                throw new RuntimeException("Stub container: binding not found: {$abstract}");
            }
            $binding = $fw['bindings'][$abstract];
            return $binding instanceof Closure ? $binding($fw['app']) : $binding;
        }
    }

    if (!function_exists('config')) {
        function config(?string $key = null, $default = null)
        {
            $config = jwt_fw()['config'];
            if ($key === null) {
                return $config;
            }
            $value = $config;
            foreach (explode('.', $key) as $k) {
                if (!is_array($value) || !array_key_exists($k, $value)) {
                    return $default;
                }
                $value = $value[$k];
            }
            return $value;
        }
    }

    if (!function_exists('response')) {
        function response()
        {
            return new \JwtTestLaravelResponseFactory();
        }
    }

    if (!function_exists('json')) {
        function json($data)
        {
            return (new \think\Response())->data($data);
        }
    }

    if (!function_exists('env')) {
        function env(string $key, $default = null)
        {
            $value = getenv($key);
            return $value === false ? $default : $value;
        }
    }

    if (!function_exists('base_path')) {
        function base_path(?string $path = null): string
        {
            $base = jwt_fw()['base_path'] ?? (sys_get_temp_dir() . '/jwt-webman-base');
            return $path === null ? $base : $base . '/' . $path;
        }
    }

    if (!function_exists('config_path')) {
        function config_path(?string $path = null): string
        {
            $base = jwt_fw()['base_path'] ?? (sys_get_temp_dir() . '/jwt-laravel-config');
            return $path === null ? $base : $base . '/' . $path;
        }
    }

    if (!function_exists('copy_dir')) {
        function copy_dir(string $source, string $dest): bool
        {
            if (!is_dir($source)) {
                throw new RuntimeException("copy_dir: source dir not found: {$source}");
            }
            if (!is_dir($dest)) {
                mkdir($dest, 0777, true);
            }
            foreach (scandir($source) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $src = $source . '/' . $file;
                $dst = $dest . '/' . $file;
                if (is_dir($src)) {
                    copy_dir($src, $dst);
                } else {
                    copy($src, $dst);
                }
            }
            return true;
        }
    }

    if (!function_exists('remove_dir')) {
        function remove_dir(string $dir): bool
        {
            if (!is_dir($dir) || is_link($dir)) {
                if (is_file($dir) || is_link($dir)) {
                    unlink($dir);
                }
                return true;
            }
            foreach (scandir($dir) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $path = $dir . '/' . $file;
                if (is_dir($path) && !is_link($path)) {
                    remove_dir($path);
                } else {
                    unlink($path);
                }
            }
            rmdir($dir);
            return true;
        }
    }

    if (!defined('BASE_PATH')) {
        define('BASE_PATH', sys_get_temp_dir() . '/jwt-webman-hyperf-base');
    }

    /* ------------------------------------------------------------------ *
     * Shared test doubles (JwtTest*).
     * ------------------------------------------------------------------ */

    class JwtTestConfig
    {
        public function get(string $key, $default = null)
        {
            $value = jwt_fw()['config'];
            foreach (explode('.', $key) as $k) {
                if (!is_array($value) || !array_key_exists($k, $value)) {
                    return $default;
                }
                $value = $value[$k];
            }
            return $value;
        }
    }

    class JwtTestLog
    {
        public function channel($channel = null)
        {
            return null;
        }
    }

    class JwtTestRouter
    {
        public function aliasMiddleware(string $name, string $class): void
        {
            $GLOBALS['__jwt_fw']['middleware_aliases'][$name] = $class;
        }
    }

    class JwtTestMiddlewareAlias
    {
        public function alias(string $name, string $class): void
        {
            $GLOBALS['__jwt_fw']['middleware_aliases'][$name] = $class;
        }
    }

    class JwtTestApp implements \ArrayAccess
    {
        public $config;
        public $middleware;
        public $log;
        public $router;
        private $bindings = [];

        public function __construct()
        {
            $this->config     = new \JwtTestConfig();
            $this->middleware = new \JwtTestMiddlewareAlias();
            $this->log        = new \JwtTestLog();
            $this->router     = new \JwtTestRouter();
        }

        public function singleton(string $abstract, $concrete): void
        {
            $this->bindings[$abstract] = $concrete;
            $GLOBALS['__jwt_fw']['bindings'][$abstract] = $concrete;
        }

        public function bind(string $abstract, $concrete): void
        {
            $this->singleton($abstract, $concrete);
        }

        public function make(string $abstract)
        {
            if (!array_key_exists($abstract, $this->bindings)) {
                throw new RuntimeException("Stub app: binding not found: {$abstract}");
            }
            $binding = $this->bindings[$abstract];
            return $binding instanceof Closure ? $binding($this) : $binding;
        }

        public function runningInConsole(): bool
        {
            return jwt_fw()['console_running'];
        }

        public function getConfigPath(): string
        {
            return rtrim(jwt_fw()['config_path'] ?? (sys_get_temp_dir() . '/jwt-think-config'), '/') . '/';
        }

        public function getRootPath(): string
        {
            return rtrim(jwt_fw()['root_path'] ?? (sys_get_temp_dir() . '/jwt-think-root'), '/') . '/';
        }

        public function offsetExists($offset): bool
        {
            return in_array($offset, ['config', 'log', 'router'], true);
        }

        public function offsetGet($offset)
        {
            return $this->{$offset};
        }

        public function offsetSet($offset, $value): void
        {
            $this->{$offset} = $value;
        }

        public function offsetUnset($offset): void
        {
            unset($this->{$offset});
        }
    }

    class JwtTestLaravelJsonResponse
    {
        private $data = [];
        private $status = 200;

        public function setData($data)
        {
            $this->data = $data;
            return $this;
        }

        public function setStatus(int $status)
        {
            $this->status = $status;
            return $this;
        }

        public function getStatusCode(): int
        {
            return $this->status;
        }

        public function getData()
        {
            return $this->data;
        }

        public function getContent(): string
        {
            return json_encode($this->data);
        }
    }

    class JwtTestLaravelResponseFactory
    {
        public function json($data = [], int $status = 200, array $headers = [], int $options = 0)
        {
            return (new \JwtTestLaravelJsonResponse())->setData($data)->setStatus($status);
        }
    }

    class JwtTestFakeRedis
    {
        public $pong = 'PONG';
        public $data = [];
        public $calls = ['ping' => 0, 'setex' => 0, 'exists' => 0, 'close' => 0];

        public function ping()
        {
            $this->calls['ping']++;
            return $this->pong;
        }

        public function setex($key, $ttl, $value)
        {
            $this->calls['setex']++;
            $this->data[$key] = $value;
            return true;
        }

        public function exists($key)
        {
            $this->calls['exists']++;
            return isset($this->data[$key]) ? 1 : 0;
        }

        public function close()
        {
            $this->calls['close']++;
            return true;
        }
    }

    class JwtTestDbConnection
    {
        public function getPdo(): \PDO
        {
            return jwt_fw()['pdo'] ?? new \PDO('sqlite::memory:');
        }
    }

    class JwtTestLaravelRedisConnection
    {
        public function client()
        {
            return jwt_fw()['redis'] ?? new \JwtTestFakeRedis();
        }
    }

    class JwtTestThinkCacheStore
    {
        public function handler()
        {
            return jwt_fw()['redis'] ?? new \JwtTestFakeRedis();
        }
    }

    class JwtTestAttributeBag
    {
        public $data = [];

        public function set(string $key, $value): void
        {
            $this->data[$key] = $value;
        }

        public function get(string $key, $default = null)
        {
            return $this->data[$key] ?? $default;
        }
    }
}
