<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

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
 *   conditionals and return early.
 * - SafeDeclareStrictTypesRector adds declare(strict_types=1) only to files it
 *   can prove are free of scalar type coercion.
 *
 * Shipped migrations are skipped on purpose: they already ran in production
 * and must never change.
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
    ->withRules([
        SafeDeclareStrictTypesRector::class,
    ])
    ->withComposerBased(laravel: true);
