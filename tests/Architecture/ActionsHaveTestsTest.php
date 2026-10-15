<?php

/**
 * Guardrail enforcing "every Action lands with a test."
 *
 * Goal: prevent the Action-test coverage ratio from regressing. The explicit
 * allowlist below pins the set of currently-untested Actions; the live
 * tested/total ratio is printed to STDERR when this test runs, so the figure
 * always matches the code. Any NEW Action without a test fails CI loudly, forcing the
 * author to either write the test or make an explicit decision to add the new
 * name to the allowlist (which shows up in code review).
 *
 * The allowlist is meant to SHRINK over time, never grow. When you add a
 * test for an Action in the list, this test will tell you to remove it.
 *
 * Detection is name-based: an Action `Foo\BarAction` is considered tested
 * if the code of a test file (`*Test.php`) under tests/ names the whole-word
 * `BarAction` (a call, a type-hint or `BarAction::class`). Comments and `use`
 * lines do not count, since naming a class there tests nothing, and neither
 * do helpers such as tests/Pest.php and tests/Support/: an Action they call
 * to set up other tests is not tested by that. The Eval suite is left out
 * too, as it skips itself without an API key. Multi-Action test files (e.g.
 * TakeoverAndReturnConversationActionsTest covers two Actions in one file)
 * work without renaming.
 */
const UNTESTED_ACTIONS_ALLOWLIST = [];

/** Discover all Action class files in src/ and return their basenames sans .php */
function allActionClassNames(): array
{
    $files = [];
    $base = __DIR__.'/../../src';
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
    foreach ($it as $file) {
        if ($file->isFile() && str_ends_with((string) $file, 'Action.php')) {
            $files[] = $file->getBasename('.php');
        }
    }
    sort($files);

    return $files;
}

/** $source without its comments and its `use` import lines. */
function actionTestCode(string $source): string
{
    $code = '';

    foreach (PhpToken::tokenize($source) as $token) {
        if (! $token->is([T_COMMENT, T_DOC_COMMENT])) {
            $code .= $token->text;
        }
    }

    return (string) preg_replace('/^use [^;]+;$/m', '', $code);
}

/** Does the code of a test file (`*Test.php`) under tests/ name this Action? */
function actionHasTest(string $actionShortName): bool
{
    // Cache the discovery of every test file's text once per process. EXCLUDE
    // this file from the scan — the allowlist below carries every Action's
    // name as a plain string, which would otherwise match every name and
    // make the test useless.
    static $combined = null;
    if ($combined === null) {
        $self = realpath(__FILE__);
        $combined = '';
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/..'));
        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with((string) $file, 'Test.php')) {
                continue;
            }
            if (realpath($file->getPathname()) === $self || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Eval'.DIRECTORY_SEPARATOR)) {
                continue;
            }
            $combined .= actionTestCode((string) file_get_contents($file->getPathname())).PHP_EOL;
        }
    }

    return preg_match('/\b'.preg_quote($actionShortName, '/').'\b/', $combined) === 1;
}

it('every Action either has a test or is on the explicit allowlist', function (): void {
    $allowlist = UNTESTED_ACTIONS_ALLOWLIST;
    $allActions = allActionClassNames();

    $currentlyUntested = array_values(array_filter(
        $allActions,
        fn (string $name): bool => ! actionHasTest($name),
    ));

    // Self-truthing coverage figure — printed every run so no stale comment
    // can misrepresent the real ratio (see the class docblock).
    $tested = count($allActions) - count($currentlyUntested);
    fwrite(STDERR, sprintf(
        "\nAction test coverage: %d/%d (%.0f%%) tested, %d allowlisted\n",
        $tested,
        count($allActions),
        count($allActions) > 0 ? 100 * $tested / count($allActions) : 0,
        count($allowlist),
    ));

    // (a) Drift up: new Action lacks a test AND is not on the allowlist.
    $newUntested = array_values(array_diff($currentlyUntested, $allowlist));
    expect($newUntested)->toBe([], implode("\n", [
        '',
        'New Action(s) without a test:',
        ...array_map(fn (string $n): string => "  - {$n}", $newUntested),
        '',
        'Either add a test (preferred — see ARCHITECTURE.md, "Tests")',
        'or add the class short-name to UNTESTED_ACTIONS_ALLOWLIST in this file.',
    ]));

    // (b) Drift down (cleanup signal): an entry on the allowlist now has a
    // test, so the allowlist row should be removed. This is intentionally a
    // hard failure so the list shrinks monotonically.
    $nowTested = array_values(array_intersect($allowlist, array_diff($allActions, $currentlyUntested)));
    expect($nowTested)->toBe([], implode("\n", [
        '',
        'Action(s) on the allowlist now have tests — remove them from',
        'UNTESTED_ACTIONS_ALLOWLIST so the list shrinks over time:',
        ...array_map(fn (string $n): string => "  - {$n}", $nowTested),
        '',
    ]));
});
