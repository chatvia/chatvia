<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/**
 * Guardrail for "every class says what it is for."
 *
 * Every class, interface, trait and enum under src/ needs a class-level
 * docblock that starts with at least one sentence of plain prose: at least
 * DOCBLOCK_MIN_WORDS words before the first `@` tag. Attributes may sit
 * between the docblock and the declaration. Anonymous classes are not checked.
 *
 * The allowlist below pins the files that do not have such a docblock yet. It
 * may only shrink:
 *  - a NEW class without a docblock fails the test, so the author writes one
 *    (or adds the file to the allowlist, which shows up in code review);
 *  - an allowlisted file that is documented now, or no longer exists, also
 *    fails the test, so its row is removed and the list keeps getting shorter.
 *
 * The live coverage ratio is printed to STDERR on every run, so no comment
 * can go stale.
 *
 * Framework-free (Architecture suite): files are found relative to this file
 * and read with PHP's tokenizer, so no class is loaded.
 */
const DOCBLOCK_MIN_WORDS = 5;

/** Paths (from the project root) of files with an undocumented class. Shrink only. */
const UNDOCUMENTED_CLASSES_ALLOWLIST = [];

/** @return list<string> paths, relative to the project root, of every .php file under src/ */
function docblockSourceFiles(): array
{
    $root = (string) realpath(__DIR__.'/../..');
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/src', FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && Str::endsWith((string) $file, '.php')) {
            $files[] = Str::of($file->getPathname())->chopStart($root.DIRECTORY_SEPARATOR)->replace(DIRECTORY_SEPARATOR, '/')->toString();
        }
    }

    sort($files);

    return $files;
}

/**
 * The named class, interface, trait and enum declarations in $source, mapped
 * to the docblock written right above them (null when there is none).
 * Whitespace, comments, modifiers and attributes may sit in between.
 *
 * @return array<string, string|null> declared name => docblock
 */
function docblockDeclarations(string $source): array
{
    $tokens = PhpToken::tokenize($source);
    $declarations = [];
    $docblock = null;
    $previous = null;
    $attributeDepth = 0;

    foreach ($tokens as $index => $token) {
        // Skip the inside of `#[...]`: brackets in attribute arguments nest.
        if ($attributeDepth > 0) {
            $attributeDepth += match ($token->text) {
                '[' => 1,
                ']' => -1,
                default => 0,
            };

            continue;
        }

        if ($token->is(T_ATTRIBUTE)) {
            $attributeDepth = 1;

            continue;
        }

        if ($token->is(T_DOC_COMMENT)) {
            $docblock = $token->text;

            continue;
        }

        if ($token->is([T_WHITESPACE, T_COMMENT, T_FINAL, T_ABSTRACT, T_READONLY])) {
            continue;
        }

        // `Foo::class` and `new class (...)` use the keyword without declaring a name.
        $isDeclaration = $token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])
            && ! $previous?->is([T_DOUBLE_COLON, T_NEW]);

        if ($isDeclaration) {
            $declarations[docblockDeclaredName($tokens, $index)] = $docblock;
        }

        $docblock = null;
        $previous = $token;
    }

    return $declarations;
}

/**
 * The name that follows the declaration keyword at $tokens[$index].
 *
 * @param  list<PhpToken>  $tokens
 */
function docblockDeclaredName(array $tokens, int $index): string
{
    for ($next = $index + 1, $count = count($tokens); $next < $count; $next++) {
        if ($tokens[$next]->is(T_STRING)) {
            return $tokens[$next]->text;
        }
    }

    return '(unnamed)';
}

/** How many words of prose a docblock has before its first `@` tag line. */
function docblockProseWordCount(string $docblock): int
{
    $prose = [];

    foreach (Str::of($docblock)->split('/\R/') as $line) {
        $line = Str::of($line)->replaceMatches('#^\s*/\*\*|\*/\s*$#', '')->ltrim(" \t*")->toString();

        if (Str::startsWith($line, '@')) {
            break;
        }

        $prose[] = $line;
    }

    return Str::matchAll("/\\p{L}[\\p{L}\\p{N}_'’-]*/u", implode(' ', $prose))->count();
}

/**
 * Every source file with at least one declaration that lacks a prose
 * docblock, mapped to those declarations' names; plus the declaration totals.
 *
 * @return array{offenders: array<string, list<string>>, declarations: int, undocumented: int}
 */
function docblockCoverage(): array
{
    $root = (string) realpath(__DIR__.'/../..');
    $offenders = [];
    $declarations = 0;
    $undocumented = 0;

    foreach (docblockSourceFiles() as $path) {
        foreach (docblockDeclarations((string) file_get_contents($root.'/'.$path)) as $name => $docblock) {
            $declarations++;

            if ($docblock === null || docblockProseWordCount($docblock) < DOCBLOCK_MIN_WORDS) {
                $undocumented++;
                $offenders[$path][] = $name;
            }
        }
    }

    ksort($offenders);

    return ['offenders' => $offenders, 'declarations' => $declarations, 'undocumented' => $undocumented];
}

it('every class under src/ has a prose docblock or is on the explicit allowlist', function (): void {
    $coverage = docblockCoverage();
    $offenders = array_keys($coverage['offenders']);
    $documented = $coverage['declarations'] - $coverage['undocumented'];

    fwrite(STDERR, sprintf(
        "\nClass docblock coverage: %d/%d (%.0f%%) documented, %d files allowlisted\n",
        $documented,
        $coverage['declarations'],
        $coverage['declarations'] > 0 ? 100 * $documented / $coverage['declarations'] : 0,
        count(UNDOCUMENTED_CLASSES_ALLOWLIST),
    ));

    // (a) Drift up: a class without a docblock in a file that is not allowlisted.
    $newOffenders = array_values(array_diff($offenders, UNDOCUMENTED_CLASSES_ALLOWLIST));
    expect($newOffenders)->toBe([], implode("\n", [
        '',
        'Class(es) without a docblock that says what they are for:',
        ...array_map(
            fn (string $path): string => sprintf('  - %s (%s)', $path, implode(', ', $coverage['offenders'][$path])),
            $newOffenders,
        ),
        '',
        sprintf('Add a class docblock that starts with at least %d words of plain prose', DOCBLOCK_MIN_WORDS),
        '(what the class is, why it exists). Attributes may sit between it and the class.',
    ]));

    // (b) Drift down: an allowlisted file is documented now (or was removed).
    // A hard failure on purpose, so the allowlist only ever shrinks.
    $nowClean = array_values(array_diff(UNDOCUMENTED_CLASSES_ALLOWLIST, $offenders));
    expect($nowClean)->toBe([], implode("\n", [
        '',
        'These files are documented now (or no longer exist). Remove them from',
        'UNDOCUMENTED_CLASSES_ALLOWLIST so the list shrinks over time:',
        ...array_map(fn (string $path): string => "  - {$path}", $nowClean),
        '',
    ]));
});
