<?php
declare(strict_types=1);
/*
 * Framework stubs for integration tests. Minimal stand-ins for the framework
 * base classes (Laravel / ThinkPHP / Webman / Hyperf / Yii2 / Yii3 / PSR) so
 * the JWT integration layer can be exercised with pure PHPUnit, without
 * installing any framework. Every definition is guarded: if the real package
 * is installed, the stub is skipped. All state lives in $GLOBALS['__jwt_fw']
 * and is reset per test via jwt_fw_reset().
 */
/*
 * Yii2. AuthMethod::beforeAction mirrors the real one from
 * framework/filters/auth/AuthMethod.php: authenticate() is called, an
 * UnauthorizedHttpException is swallowed for optional actions, and a null
 * identity falls through to challenge() + handleFailure().
 */
namespace yii\base {

    if (!class_exists(\yii\base\UnknownPropertyException::class)) {
        class UnknownPropertyException extends \Exception
        {
        }
    }

    if (!class_exists(\yii\base\InvalidCallException::class)) {
        class InvalidCallException extends \Exception
        {
        }
    }

    if (!class_exists(\yii\base\InvalidConfigException::class)) {
        class InvalidConfigException extends \Exception
        {
        }
    }

    /*
     * 关键：真实 Yii2 的 BaseObject/Component 对未声明属性是【抛异常】而非动态创建，
     * 所以这里不能加 #[\AllowDynamicProperties]，否则桩会掩盖真实环境下的
     * UnknownPropertyException（本包就曾在 $request->jwt_payload 上踩过这个坑）。
     */
    if (!class_exists(\yii\base\BaseObject::class)) {
        class BaseObject
        {
            public function __construct($config = [])
            {
                foreach ($config as $name => $value) {
                    $this->$name = $value;
                }
                if (method_exists($this, 'init')) {
                    $this->init();
                }
            }

            public function __get($name)
            {
                $getter = 'get' . $name;
                if (method_exists($this, $getter)) {
                    return $this->$getter();
                }
                if (method_exists($this, 'set' . $name)) {
                    throw new InvalidCallException('Getting write-only property: ' . get_class($this) . '::' . $name);
                }

                throw new UnknownPropertyException('Getting unknown property: ' . get_class($this) . '::' . $name);
            }

            public function __set($name, $value)
            {
                $setter = 'set' . $name;
                if (method_exists($this, $setter)) {
                    $this->$setter($value);

                    return;
                }
                if (method_exists($this, 'get' . $name)) {
                    throw new InvalidCallException('Setting read-only property: ' . get_class($this) . '::' . $name);
                }

                throw new UnknownPropertyException('Setting unknown property: ' . get_class($this) . '::' . $name);
            }
        }
    }

    if (!class_exists(\yii\base\Component::class)) {
        class Component extends BaseObject
        {
        }
    }
}
namespace yii\web {

    if (!interface_exists(\yii\web\IdentityInterface::class)) {
        interface IdentityInterface
        {
            public static function findIdentity($id);

            public static function findIdentityByAccessToken($token, $type = null);

            public function getId();

            public function getAuthKey();

            public function validateAuthKey($authKey);
        }
    }

    if (!class_exists(\yii\web\HttpException::class)) {
        class HttpException extends \Exception
        {
            public $statusCode;

            public function __construct($status, $message = null, $code = 0, $previous = null)
            {
                $this->statusCode = $status;
                parent::__construct((string) $message, $code, $previous);
            }
        }
    }

    if (!class_exists(\yii\web\UnauthorizedHttpException::class)) {
        class UnauthorizedHttpException extends HttpException
        {
            public function __construct($message = null, $code = 0, $previous = null)
            {
                parent::__construct(401, $message, $code, $previous);
            }
        }
    }

    if (!class_exists(\yii\web\HeaderCollection::class)) {
        class HeaderCollection
        {
            private $headers = [];

            public function get($name, $default = null)
            {
                return $this->headers[strtolower($name)] ?? $default;
            }

            public function set($name, $value)
            {
                $this->headers[strtolower($name)] = $value;
            }
        }
    }

    if (!class_exists(\yii\web\Request::class)) {
        class Request extends \yii\base\Component
        {
            private $headers;

            public function __construct(array $headers = [])
            {
                $this->headers = new HeaderCollection();
                foreach ($headers as $name => $value) {
                    $this->headers->set($name, $value);
                }
            }

            public function getHeaders(): HeaderCollection
            {
                return $this->headers;
            }
        }
    }

    if (!class_exists(\yii\web\Response::class)) {
        class Response extends \yii\base\Component
        {
            private $headers;

            public function __construct()
            {
                $this->headers = new HeaderCollection();
            }

            public function getHeaders(): HeaderCollection
            {
                return $this->headers;
            }
        }
    }

    if (!class_exists(\yii\web\User::class)) {
        class User extends \yii\base\Component
        {
            private $identity;

            public function getIdentity($autoRenew = true)
            {
                return $this->identity;
            }

            public function login(IdentityInterface $identity, $duration = 0)
            {
                $this->identity = $identity;
                $GLOBALS['__jwt_fw']['called'][] = 'user.login';

                return true;
            }

            public function getIsGuest(): bool
            {
                return $this->identity === null;
            }
        }
    }
}
namespace yii\filters\auth {

    if (!interface_exists(\yii\filters\auth\AuthInterface::class)) {
        interface AuthInterface
        {
            public function authenticate($user, $request, $response);

            public function challenge($response);

            public function handleFailure($response);
        }
    }

    if (!class_exists(\yii\filters\auth\AuthMethod::class)) {
        abstract class AuthMethod extends \yii\base\Component implements AuthInterface
        {
            public $user;
            public $request;
            public $response;
            public $optional = [];

            public function beforeAction($action)
            {
                $response = $this->response ?: \Yii::$app->getResponse();

                try {
                    $identity = $this->authenticate(
                        $this->user ?: \Yii::$app->getUser(),
                        $this->request ?: \Yii::$app->getRequest(),
                        $response
                    );
                } catch (\yii\web\UnauthorizedHttpException $e) {
                    if ($this->isOptional($action)) {
                        return true;
                    }
                    throw $e;
                }

                if ($identity !== null || $this->isOptional($action)) {
                    return true;
                }

                $this->challenge($response);
                $this->handleFailure($response);

                return false;
            }

            public function challenge($response)
            {
            }

            public function handleFailure($response)
            {
                throw new \yii\web\UnauthorizedHttpException('Your request was made with invalid credentials.');
            }

            protected function isOptional($action): bool
            {
                foreach ($this->optional as $pattern) {
                    if ($pattern === $action || fnmatch($pattern, (string) $action)) {
                        return true;
                    }
                }

                return false;
            }
        }
    }
}
namespace yii\console {

    if (!class_exists(\yii\console\ExitCode::class)) {
        class ExitCode
        {
            public const OK = 0;
            public const UNKNOWN_ERROR = 1;
        }
    }

    if (!class_exists(\yii\console\Controller::class)) {
        class Controller
        {
            public function stdout(string $message, ...$args): void
            {
                $GLOBALS['__jwt_fw']['outputs'][] = $message;
            }
        }
    }
}
namespace yii\helpers {

    if (!class_exists(\yii\helpers\Console::class)) {
        class Console
        {
            public const FG_GREEN = 1;
            public const FG_YELLOW = 2;
            public const FG_RED = 3;
        }
    }
}
namespace {

    /* Minimal application double: params + components, as JwtService / JwtAuth use them. */
    class JwtTestYiiApp
    {
        public $params = [];
        private $components = [];

        public function __construct(array $params = [], array $components = [])
        {
            $this->params = $params;
            $this->components = $components;
        }

        public function set(string $id, $component): void
        {
            $this->components[$id] = $component;
        }

        public function has(string $id, bool $checkInstance = false): bool
        {
            return isset($this->components[$id]);
        }

        public function get(string $id, bool $throwException = true)
        {
            if (!isset($this->components[$id])) {
                throw new \yii\base\InvalidConfigException("Unknown component: {$id}");
            }

            return $this->components[$id];
        }

        public function getResponse()
        {
            return $this->get('response');
        }

        public function getUser()
        {
            return $this->get('user');
        }

        public function getRequest()
        {
            return $this->get('request');
        }
    }

    if (!class_exists(\Yii::class)) {
        class Yii
        {
            /** @var JwtTestYiiApp|null */
            public static $app;

            public static $aliases = [];

            public static function getAlias(string $alias, bool $throwException = true): string
            {
                if (isset(self::$aliases[$alias])) {
                    return self::$aliases[$alias];
                }
                if ($throwException) {
                    throw new \yii\base\InvalidConfigException("Invalid alias: {$alias}");
                }

                return $alias;
            }

            public static function setAlias(string $alias, string $path): void
            {
                self::$aliases[$alias] = $path;
            }
        }
    }
}
