<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/*
 * The test tree mirrors the source tree. A test named after a class sits
 * where that class sits, with only `src/` dropped:
 * src/Domain/Knowledge/Retrieval/Services/KnowledgeBase.php is tested in
 * tests/Unit/Domain/Knowledge/Retrieval/Services/KnowledgeBaseTest.php, and
 * the controller src/App/Http/Controllers/Agent/AgentController.php in
 * tests/Feature/App/Http/Controllers/Agent/AgentControllerTest.php. A test of
 * behaviour across several classes, named for that behaviour, sits in their
 * nearest common folder. Tests of files outside src/ sit under the folder of
 * that file (config/ → tests/Unit/Config/, docker/ → tests/Unit/Docker/,
 * routes/ → tests/Feature/Routes/).
 *
 * A feature test goes through an entry point the outside world uses (an HTTP
 * route, an admin page, an artisan command, broadcasting auth); a unit test
 * calls the class itself.
 *
 * Framework-free, like the other architecture tests.
 */

/** The only folders tests/Unit holds: the three layers of src/, and the repo folders tested outside it. */
const LAYOUT_UNIT_ROOTS = ['App', 'Domain', 'Support', 'Config', 'Database', 'Docker'];

/** The only folders tests/Feature holds: the app's entry points, and the repo folders tested outside src/. */
const LAYOUT_FEATURE_ROOTS = ['App', 'Bootstrap', 'Database', 'Routes'];

/**
 * Feature tests that still wait to move to their entry point's path, because
 * other unfinished work is in them. Shrink-only: once a file has moved, it
 * leaves this list.
 */
const LAYOUT_PENDING_MOVES = [
    'tests/Feature/Admin/AdminSetupTest.php',
    'tests/Feature/Admin/AiModelsPageTest.php',
];

/** Methods of the test case that send a request or run a command. */
const LAYOUT_ENTRY_METHODS = [
    'get', 'post', 'put', 'patch', 'delete', 'options', 'call', 'json',
    'getJson', 'postJson', 'putJson', 'patchJson', 'deleteJson', 'optionsJson', 'artisan',
];

/** Static calls and functions that open a Livewire page or run a command. */
const LAYOUT_ENTRY_CALLS = ['Artisan::call', 'Livewire::test', 'Livewire::actingAs', 'livewire'];

/** @return array<string, list<string>> class basename => its paths under src/ */
function layoutSourceClasses(string $root): array
{
    $classes = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (Str::endsWith((string) $file, '.php')) {
            $classes[$file->getBasename('.php')][] = Str::chopStart($file->getPathname(), $root.'/');
        }
    }

    return $classes;
}

/** @return list<string> repo-relative paths of the PHP files under $dir */
function layoutPhpFiles(string $root, string $dir, string $suffix = '.php'): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (Str::endsWith((string) $file, $suffix)) {
            $files[] = Str::chopStart($file->getPathname(), $root.'/');
        }
    }

    sort($files);

    return $files;
}

/** Where the $suite test of the class at $sourcePath belongs: its path with `src/` dropped. */
function layoutExpectedTestPath(string $sourcePath, string $suite = 'Unit'): string
{
    return "tests/{$suite}/".Str::of($sourcePath)->chopStart('src/')->chopEnd('.php')->toString().'Test.php';
}

/** @return list<string> the tests of $suite named after exactly one class that sit away from its path */
function layoutMisplacedTests(string $root, string $suite): array
{
    $classes = layoutSourceClasses($root);
    $misplaced = [];

    foreach (layoutPhpFiles($root, "tests/{$suite}", 'Test.php') as $path) {
        // Only a test named after exactly one class has a place to be.
        $sources = $classes[Str::chopEnd(basename($path, '.php'), 'Test')] ?? [];

        if (count($sources) === 1 && ($expected = layoutExpectedTestPath($sources[0], $suite)) !== $path) {
            $misplaced[] = "{$path} (expected {$expected})";
        }
    }

    return $misplaced;
}

/** @return list<PhpToken> the code tokens of $file: no whitespace, comments or open tag */
function layoutCodeTokens(string $file): array
{
    return array_values(array_filter(
        PhpToken::tokenize((string) file_get_contents($file)),
        fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG]),
    ));
}

/**
 * Whether the method chain that ends just before $tokens[$arrow] starts at the
 * test case: `$this`, `test()` or a suite helper that returns it, followed by
 * method calls only (`$this->actingAs($user)->post(…)`). A property on the way
 * (`$this->cache->get(…)`) leaves the test case.
 *
 * @param  list<PhpToken>  $tokens
 * @param  list<string>  $caseHelpers
 */
function layoutChainStartsAtTestCase(array $tokens, int $arrow, array $caseHelpers): bool
{
    $index = $arrow - 1;

    while ($index >= 0) {
        if ($tokens[$index]->is(T_VARIABLE)) {
            return $tokens[$index]->text === '$this';
        }

        if ($tokens[$index]->text !== ')') {
            return false;
        }

        // Step back over the balanced (…) to the name of the call.
        for ($depth = 0; $index >= 0; $index--) {
            $depth += match ($tokens[$index]->text) {
                ')' => 1,
                '(' => -1,
                default => 0,
            };

            if ($depth === 0) {
                break;
            }
        }

        $name = $tokens[$index - 1] ?? null;

        if (! $name?->is(T_STRING)) {
            return false;
        }

        if (($tokens[$index - 2] ?? null)?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])) {
            $index -= 3;

            continue;
        }

        return ($name->text === 'test' && $tokens[$index + 1]->text === ')') || in_array($name->text, $caseHelpers, true);
    }

    return false;
}

/**
 * Whether $tokens send a request or run a command: an entry method on the
 * test case, a Livewire or artisan call, or a call to a suite helper that does.
 *
 * @param  list<PhpToken>  $tokens
 * @param  array{case: list<string>, entry: list<string>}  $helpers
 */
function layoutCallsEntryPoint(array $tokens, array $helpers): bool
{
    $calls = [...LAYOUT_ENTRY_CALLS, ...$helpers['entry']];

    foreach ($tokens as $index => $token) {
        $next = $tokens[$index + 1]->text ?? '';

        if ($token->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])
            && in_array($next, LAYOUT_ENTRY_METHODS, true)
            && ($tokens[$index + 2]->text ?? '') === '('
            && layoutChainStartsAtTestCase($tokens, $index, $helpers['case'])) {
            return true;
        }

        if (! $token->is(T_STRING) || ($tokens[$index - 1] ?? null)?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION])) {
            continue;
        }

        if ($next === '(' && in_array($token->text, $calls, true)) {
            return true;
        }

        if ($next === '::' && ($tokens[$index + 3]->text ?? '') === '(' && in_array($token->text.'::'.$tokens[$index + 2]->text, $calls, true)) {
            return true;
        }
    }

    return false;
}

/**
 * The bodies of the functions and static methods in $file, keyed `name` or
 * `Class::name`.
 *
 * @return array<string, list<PhpToken>>
 */
function layoutFunctionBodies(string $file): array
{
    $tokens = layoutCodeTokens($file);
    $bodies = [];
    $class = null;

    foreach ($tokens as $index => $token) {
        if ($token->is(T_CLASS) && ($tokens[$index + 1] ?? null)?->is(T_STRING)) {
            $class = $tokens[$index + 1]->text;
        }

        if (! $token->is(T_FUNCTION) || ! ($tokens[$index + 1] ?? null)?->is(T_STRING)) {
            continue;
        }

        $start = $index;

        while (isset($tokens[$start]) && ! in_array($tokens[$start]->text, ['{', ';'], true)) {
            $start++;
        }

        if (($tokens[$start]->text ?? ';') === ';') {
            continue;
        }

        for ($end = $start, $depth = 0; isset($tokens[$end]); $end++) {
            $depth += $tokens[$end]->text === '{' || $tokens[$end]->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES]) ? 1 : 0;
            $depth -= $tokens[$end]->text === '}' ? 1 : 0;

            if ($depth === 0) {
                break;
            }
        }

        $name = $tokens[$index + 1]->text;
        $bodies[$class !== null ? "{$class}::{$name}" : $name] = array_slice($tokens, $start, $end - $start + 1);
    }

    return $bodies;
}

/**
 * The suite's own helpers (tests/Pest.php, tests/Support) a test reaches an
 * entry point through: `case` hand back the test case (`withApiToken()`),
 * `entry` send a request themselves (`adminPage()`, `EmbeddedWidget::boot()`).
 *
 * @return array{case: list<string>, entry: list<string>}
 */
function layoutSuiteHelpers(string $root): array
{
    preg_match_all('/function (\w+)\([^)]*\)\s*:\s*\\\\?(?:Tests\\\\)?TestCase\b/', (string) file_get_contents($root.'/tests/Pest.php'), $matches);
    $helpers = ['case' => $matches[1], 'entry' => []];

    $bodies = [];

    foreach (['tests/Pest.php', ...layoutPhpFiles($root, 'tests/Support')] as $file) {
        $bodies += layoutFunctionBodies($root.'/'.$file);
    }

    // Again until nothing new turns up, so a helper that calls one counts too.
    do {
        $found = array_keys(array_filter($bodies, fn (array $body): bool => layoutCallsEntryPoint($body, $helpers)));
        $added = array_diff($found, $helpers['entry']);
        $helpers['entry'] = $found;
    } while ($added !== []);

    return $helpers;
}

it('puts each unit test of a class where the class sits in src/', function (): void {
    expect(layoutMisplacedTests((string) realpath(__DIR__.'/../../'), 'Unit'))->toBe([]);
});

it('puts each feature test of a class where the class sits in src/', function (): void {
    expect(layoutMisplacedTests((string) realpath(__DIR__.'/../../'), 'Feature'))->toBe([]);
});

it('keeps tests/Unit and tests/Feature to the folders of src/ and the repo folders tested outside it', function (string $suite, array $roots): void {
    $root = (string) realpath(__DIR__.'/../../');
    $strays = [];

    foreach (glob("{$root}/tests/{$suite}/*", GLOB_ONLYDIR) ?: [] as $folder) {
        if (! in_array(basename($folder), $roots, true)) {
            $strays = [...$strays, ...layoutPhpFiles($root, Str::chopStart($folder, $root.'/'))];
        }
    }

    expect(array_values(array_diff($strays, LAYOUT_PENDING_MOVES)))->toBe([]);
})->with([
    'unit' => ['Unit', LAYOUT_UNIT_ROOTS],
    'feature' => ['Feature', LAYOUT_FEATURE_ROOTS],
]);

it('sends every feature test through an entry point, and no unit test', function (): void {
    $root = (string) realpath(__DIR__.'/../../');
    $helpers = layoutSuiteHelpers($root);
    $offenders = [];

    foreach (['Feature' => true, 'Unit' => false] as $suite => $sendsRequests) {
        foreach (layoutPhpFiles($root, "tests/{$suite}", 'Test.php') as $path) {
            if (layoutCallsEntryPoint(layoutCodeTokens($root.'/'.$path), $helpers) !== $sendsRequests) {
                $offenders[] = $sendsRequests ? "{$path} (no request: a unit test)" : "{$path} (sends a request: a feature test)";
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('lists only feature tests that still wait to move', function (): void {
    $root = (string) realpath(__DIR__.'/../../');

    expect(array_values(array_filter(LAYOUT_PENDING_MOVES, fn (string $path): bool => ! is_file("{$root}/{$path}"))))->toBe([]);
});
