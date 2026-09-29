<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Yii3;

use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\Mascot;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Yii\Console\ExitCode;

/**
 * 生成密钥并写入 .env：./yii jwt:install
 *
 * Yii3 没有"发布配置"这一步 —— config-plugin 已经把本包的 config/params.php
 * 并进 params，所以这里只做一件真正需要人工的事：生成 JWT_SECRET_KEY。
 */
#[AsCommand(
    name: 'jwt:install',
    description: 'Generate a JWT secret key and store it in .env'
)]
final class InstallCommand extends Command
{
    private ?string $rootPath;

    /**
     * @param string|null $rootPath 应用根目录，缺省取当前工作目录（./yii 就在根目录下运行）
     */
    public function __construct(?string $rootPath = null)
    {
        parent::__construct();

        $this->rootPath = $rootPath;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(Mascot::banner());

        $rootPath  = $this->rootPath ?? (string) getcwd();
        $secretKey = bin2hex(random_bytes(32));

        if (!JWT::writeEnvSecret($rootPath . '/.env', 'JWT_SECRET_KEY', $secretKey)) {
            $output->writeln('<comment>.env not found or not writable — please add this line manually:</comment>');
            $output->writeln('<comment>JWT_SECRET_KEY=' . $secretKey . '</comment>');

            return ExitCode::OK;
        }

        $output->writeln('<info>JWT plugin installed successfully!</info>');
        $output->writeln('<info>JWT_SECRET_KEY: ' . $secretKey . '</info>');

        return ExitCode::OK;
    }
}
