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
/* PSR interfaces + PSR concrete doubles. */
namespace Psr\Container {

    if (!interface_exists(\Psr\Container\ContainerInterface::class)) {
        interface ContainerInterface
        {
            public function get(string $id);

            public function has(string $id): bool;
        }
    }
}
namespace Psr\Http\Message {

    if (!interface_exists(\Psr\Http\Message\ResponseInterface::class)) {
        interface ResponseInterface
        {
            public function getStatusCode(): int;

            public function withStatus(int $code, string $reasonPhrase = '');

            public function getData();
        }
    }

    if (!interface_exists(\Psr\Http\Message\ServerRequestInterface::class)) {
        interface ServerRequestInterface
        {
            public function getUri();

            public function getHeaderLine(string $name): string;

            public function getAttribute(string $name, $default = null);

            public function withAttribute(string $name, $value);
        }
    }
}
namespace Psr\Http\Server {

    if (!interface_exists(\Psr\Http\Server\MiddlewareInterface::class)) {
        interface MiddlewareInterface
        {
            public function process(
                \Psr\Http\Message\ServerRequestInterface $request,
                \Psr\Http\Server\RequestHandlerInterface $handler
            ): \Psr\Http\Message\ResponseInterface;
        }
    }

    if (!interface_exists(\Psr\Http\Server\RequestHandlerInterface::class)) {
        interface RequestHandlerInterface
        {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface;
        }
    }
}
namespace {
        class JwtTestContainer implements \Psr\Container\ContainerInterface
        {
            private $services = [];

            public function __construct(array $services = [])
            {
                $this->services = $services;
            }

            public function get(string $id)
            {
                if (!array_key_exists($id, $this->services)) {
                    throw new RuntimeException("Stub container: service not found: {$id}");
                }
                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }
        }
        class JwtTestPsrResponse implements \Psr\Http\Message\ResponseInterface
        {
            private $data = [];
            private $status = 200;

            public function __construct($data = [], int $status = 200)
            {
                $this->data = $data;
                $this->status = $status;
            }

            public function getStatusCode(): int
            {
                return $this->status;
            }

            public function withStatus(int $code, string $reasonPhrase = '')
            {
                $this->status = $code;
                return $this;
            }

            public function getData()
            {
                return $this->data;
            }

            public function getBody(): string
            {
                return json_encode($this->data);
            }
        }

        class JwtTestPsrUri
        {
            private $path = '';

            public function __construct(string $path = '')
            {
                $this->path = $path;
            }

            public function getPath(): string
            {
                return $this->path;
            }
        }
        class JwtTestPsrRequest implements \Psr\Http\Message\ServerRequestInterface
        {
            private $uri;
            private $headers = [];
            private $attributes = [];

            public function __construct(string $path = '', array $headers = [], array $attributes = [])
            {
                $this->uri = new \JwtTestPsrUri($path);
                $this->headers = $headers;
                $this->attributes = $attributes;
            }

            public function getUri()
            {
                return $this->uri;
            }

            public function getHeaderLine(string $name): string
            {
                return $this->headers[$name] ?? '';
            }

            public function getAttribute(string $name, $default = null)
            {
                return $this->attributes[$name] ?? $default;
            }

            public function getAttributes(): array
            {
                return $this->attributes;
            }

            public function withAttribute(string $name, $value)
            {
                $clone = clone $this;
                $clone->attributes[$name] = $value;
                return $clone;
            }
        }
        class JwtTestPsrHandler implements \Psr\Http\Server\RequestHandlerInterface
        {
            public $lastRequest;
            public $result;

            public function __construct($result = null)
            {
                $this->result = $result ?? new \JwtTestPsrResponse();
            }

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->lastRequest = $request;
                return $this->result;
            }
        }
}
