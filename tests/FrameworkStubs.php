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
/*
 * Split into stubs/*.php in dependency order: PSR -> global -> frameworks.
 */
require_once __DIR__ . '/stubs/PsrStubs.php';
require_once __DIR__ . '/stubs/GlobalStubs.php';
require_once __DIR__ . '/stubs/LaravelStubs.php';
require_once __DIR__ . '/stubs/ThinkStubs.php';
require_once __DIR__ . '/stubs/WebmanStubs.php';
require_once __DIR__ . '/stubs/HyperfStubs.php';
