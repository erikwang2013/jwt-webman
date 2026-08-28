<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik
 * Author: erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt;

trait MiddlewareSupport
{
    public static function matchesExcept(array $except, string $path): bool
    {
        // Frameworks pass the path with or without a leading slash; compare both sides slash-normalized
        $path = trim($path, '/');
        foreach ($except as $pattern) {
            try {
                // URL-style entries like "/api/login" match the slash-stripped request path
                $pattern = trim($pattern, '/');
                if (preg_match('#^' . $pattern . '$#', $path)) {
                    return true;
                }
            } catch (\Throwable $e) {
                error_log('JWT middleware: invalid except pattern "' . $pattern . '": ' . $e->getMessage());
            }
        }
        return false;
    }
}
