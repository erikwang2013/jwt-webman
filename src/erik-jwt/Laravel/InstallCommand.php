<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Laravel;

use Erikwang2013\Jwt\JWT;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature   = 'jwt:install';
    protected $description = 'Install erik JWT: publish config and generate secret key';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'jwt-config']);

        $secretKey = bin2hex(random_bytes(32));
        JWT::writeEnvSecret(base_path('.env'), 'JWT_SECRET_KEY', $secretKey);

        $this->info('JWT plugin installed successfully!');
        $this->info("JWT_SECRET_KEY: {$secretKey}");

        return 0;
    }
}
