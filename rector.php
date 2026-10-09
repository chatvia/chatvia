<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\StrictArrayParamDimFetchRector;

/*
 * Rector keeps the code on current PHP and Laravel idioms. `composer refactor`
 * applies it; `composer ci:check` (refactor:check) fails when it would still
 * change something.
 *
 * - withPhpSets(): every PHP upgrade rule up to the version in composer.json
 *   (PHP 8.5).
 * - withComposerBased(laravel: true): the Laravel rules for the installed
 *   framework version (Laravel 13).
 * - The prepared sets add missing types, remove dead code, simplify
 *   conditionals and return early. The code-quality set also adds
 *   declare(strict_types=1), but only to files it can prove are free of
 *   scalar type coercion (SafeDeclareStrictTypesRector).
 *
 * Shipped migrations are skipped on purpose: they already ran in production
 * and must never change. Generated caches are skipped too.
 *
 * Two rules are skipped in tests:
 * - turning `fn () => $this->method()` into `$this->method(...)` changes what
 *   `$this` is bound to when a test rebinds the closure with `->call()`;
 * - typing a closure parameter as `array` because the body reads `$x['key']`
 *   breaks Laravel's HTTP fakes, which pass ArrayAccess Request objects.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/database/factories',
        __DIR__.'/database/seeders',
    ])
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withSkip([
        __DIR__.'/bootstrap/cache',
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class => [__DIR__.'/tests'],
        StrictArrayParamDimFetchRector::class => [__DIR__.'/tests'],
    ])
    ->withComposerBased(laravel: true);
