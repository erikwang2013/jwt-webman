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
/* Hyperf stubs + Hyperf doubles. */
namespace Hyperf\Contract {

    if (!interface_exists(\Hyperf\Contract\ConfigInterface::class)) {
        interface ConfigInterface
        {
            public function get(string $key, $default = null);
        }
    }
}
namespace Hyperf\HttpServer\Contract {

    if (!interface_exists(\Hyperf\HttpServer\Contract\ResponseInterface::class)) {
        interface ResponseInterface
        {
            public function json(array $data): \Psr\Http\Message\ResponseInterface;
        }
    }

    if (!interface_exists(\Hyperf\HttpServer\Contract\RequestInterface::class)) {
        interface RequestInterface
        {
            public function getUri();

            public function getHeaderLine(string $name): string;
        }
    }
}
namespace Hyperf\Di\Annotation {

    if (!class_exists(\Hyperf\Di\Annotation\Aspect::class)) {
        #[\Attribute]
        class Aspect
        {
        }
    }

    if (!class_exists(\Hyperf\Di\Annotation\AbstractAnnotation::class)) {
        class AbstractAnnotation
        {
        }
    }
}
namespace Hyperf\Di\Aop {

    if (!class_exists(\Hyperf\Di\Aop\AbstractAspect::class)) {
        class AbstractAspect
        {
        }
    }

    if (!class_exists(\Hyperf\Di\Aop\ProceedingJoinPoint::class)) {
        class ProceedingJoinPoint
        {
            private $result;

            public function __construct($result = null)
            {
                $this->result = $result;
            }

            public function process()
            {
                return $this->result;
            }
        }
    }
}
namespace Hyperf\Context {

    if (!class_exists(\Hyperf\Context\Context::class)) {
        class Context
        {
            public static function set(string $key, $value): void
            {
                $GLOBALS['__jwt_fw']['context'][$key] = $value;
            }

            public static function get(string $key, $default = null)
            {
                return $GLOBALS['__jwt_fw']['context'][$key] ?? $default;
            }
        }
    }
}
namespace Hyperf\Command {

    if (!class_exists(\Hyperf\Command\Command::class)) {
        class Command
        {
            private $name = '';
            private $description = '';

            public function __construct(string $name = '')
            {
                $this->name = $name;
            }

            public function configure(): void
            {
            }

            public function setDescription(string $description): void
            {
                $this->description = $description;
            }

            public function info(string $message): void
            {
                $GLOBALS['__jwt_fw']['outputs'][] = $message;
            }

            public function warn(string $message): void
            {
                $GLOBALS['__jwt_fw']['outputs'][] = $message;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getDescription(): string
            {
                return $this->description;
            }
        }
    }
}
namespace Hyperf\Redis {

    if (!class_exists(\Hyperf\Redis\Redis::class)) {
        class Redis
        {
            public $pong = 'PONG';
            public $data = [];
            public $calls = ['ping' => 0, 'setex' => 0, 'exists' => 0];

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
        }
    }
}
namespace Hyperf\DbConnection {

    if (!class_exists(\Hyperf\DbConnection\Db::class)) {
        class Db
        {
            public static function connection($name = null)
            {
                return new \JwtTestDbConnection();
            }
        }
    }
}
namespace {
        class JwtTestHyperfResponse implements \Hyperf\HttpServer\Contract\ResponseInterface
        {
            public function json(array $data): \Psr\Http\Message\ResponseInterface
            {
                return new \JwtTestPsrResponse($data, 200);
            }
        }
        class JwtTestHyperfRequest implements \Hyperf\HttpServer\Contract\RequestInterface
        {
            private $uri;
            private $headers = [];

            public function __construct(string $path = '', array $headers = [])
            {
                $this->uri = new \JwtTestPsrUri($path);
                $this->headers = $headers;
            }

            public function getUri()
            {
                return $this->uri;
            }

            public function getHeaderLine(string $name): string
            {
                return $this->headers[$name] ?? '';
            }
        }
}
namespace {

    class JwtTestHyperfConfig implements \Hyperf\Contract\ConfigInterface
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
}
