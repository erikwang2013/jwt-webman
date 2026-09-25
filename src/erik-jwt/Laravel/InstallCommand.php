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
use Erikwang2013\Jwt\Mascot;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature   = 'jwt:install';
    protected $description = 'Install erik JWT: publish config and generate secret key';

    public function handle(): int
    {
        $this->line(Mascot::banner());

        $this->call('vendor:publish', ['--tag' => 'jwt-config']);

        $secretKey = bin2hex(random_bytes(32));
        if (!JWT::writeEnvSecret(base_path('.env'), 'JWT_SECRET_KEY', $secretKey)) {
            $this->warn('.env not found or not writable — please add this line manually:');
            $this->warn("JWT_SECRET_KEY={$secretKey}");
            return 0;
        }

        $this->info('JWT plugin installed successfully!');
        $this->info("JWT_SECRET_KEY: {$secretKey}");

        return 0;
    }
}
