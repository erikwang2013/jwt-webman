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
 * Yii3: PSR-17 response factory, Symfony Console (which yii-console is built
 * on) and Yiisoft\Yii\Console\ExitCode.
 */
namespace Psr\Http\Message {

    if (!interface_exists(\Psr\Http\Message\ResponseFactoryInterface::class)) {
        interface ResponseFactoryInterface
        {
            public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface;
        }
    }
}
namespace Symfony\Component\Console\Attribute {

    if (!class_exists(\Symfony\Component\Console\Attribute\AsCommand::class)) {
        #[\Attribute(\Attribute::TARGET_CLASS)]
        class AsCommand
        {
            public function __construct(
                public string $name = '',
                public string $description = ''
            ) {
            }
        }
    }
}
namespace Symfony\Component\Console\Input {

    if (!interface_exists(\Symfony\Component\Console\Input\InputInterface::class)) {
        interface InputInterface
        {
        }
    }
}
namespace Symfony\Component\Console\Output {

    if (!interface_exists(\Symfony\Component\Console\Output\OutputInterface::class)) {
        interface OutputInterface
        {
            public function writeln($messages, int $options = 0): void;
        }
    }
}
namespace Symfony\Component\Console\Command {

    if (!class_exists(\Symfony\Component\Console\Command\Command::class)) {
        abstract class Command
        {
            private ?string $name;

            public function __construct(?string $name = null)
            {
                $this->name = $name;
            }

            public function setName(?string $name): void
            {
                $this->name = $name;
            }

            /**
             * Named arguments from #[AsCommand] can be swapped into name/description by
             * position, exactly like the real Symfony attribute.
             */
            private function attribute(): ?\Symfony\Component\Console\Attribute\AsCommand
            {
                $attributes = (new \ReflectionClass($this))
                    ->getAttributes(\Symfony\Component\Console\Attribute\AsCommand::class);

                return $attributes === [] ? null : $attributes[0]->newInstance();
            }

            public function getName(): string
            {
                $attribute = $this->attribute();
                if ($attribute !== null && $attribute->name !== '') {
                    return $attribute->name;
                }

                return (string) $this->name;
            }

            public function getDescription(): string
            {
                $attribute = $this->attribute();

                return $attribute === null ? '' : $attribute->description;
            }

            abstract protected function execute(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Output\OutputInterface $output
            ): int;

            public function run(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Output\OutputInterface $output
            ): int {
                return $this->execute($input, $output);
            }
        }
    }
}
namespace Yiisoft\Yii\Console {

    if (!class_exists(\Yiisoft\Yii\Console\ExitCode::class)) {
        class ExitCode
        {
            public const OK = 0;
            public const UNKNOWN_ERROR = 1;
        }
    }
}
namespace {

    class JwtTestYii3Stream
    {
        private string $content = '';

        public function write(string $data): int
        {
            $this->content .= $data;

            return strlen($data);
        }

        public function getContents(): string
        {
            return $this->content;
        }
    }

    class JwtTestYii3Response implements \Psr\Http\Message\ResponseInterface
    {
        private int $status;
        private array $headers = [];
        private \JwtTestYii3Stream $body;

        public function __construct(int $status = 200)
        {
            $this->status = $status;
            $this->body = new \JwtTestYii3Stream();
        }

        public function getStatusCode(): int
        {
            return $this->status;
        }

        public function withStatus(int $code, string $reasonPhrase = '')
        {
            $clone = clone $this;
            $clone->status = $code;

            return $clone;
        }

        public function getData()
        {
            return json_decode($this->body->getContents(), true);
        }

        public function withHeader(string $name, $value)
        {
            $clone = clone $this;
            $clone->headers[strtolower($name)] = $value;

            return $clone;
        }

        public function getHeaderLine(string $name): string
        {
            return $this->headers[strtolower($name)] ?? '';
        }

        public function getBody(): \JwtTestYii3Stream
        {
            return $this->body;
        }
    }

    class JwtTestYii3ResponseFactory implements \Psr\Http\Message\ResponseFactoryInterface
    {
        public function createResponse(int $code = 200, string $reasonPhrase = ''): \Psr\Http\Message\ResponseInterface
        {
            return new \JwtTestYii3Response($code);
        }
    }

    class JwtTestSymfonyInput implements \Symfony\Component\Console\Input\InputInterface
    {
    }

    class JwtTestSymfonyOutput implements \Symfony\Component\Console\Output\OutputInterface
    {
        public function writeln($messages, int $options = 0): void
        {
            $GLOBALS['__jwt_fw']['outputs'][] = is_array($messages) ? implode("\n", $messages) : (string) $messages;
        }
    }
}
