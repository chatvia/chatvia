<?php

declare(strict_types=1);

/*
 * The test tree mirrors the source tree. A unit test named after a class sits
 * where that class sits, with only `src/` dropped:
 * src/Domain/Knowledge/Retrieval/Services/KnowledgeBase.php is tested in
 * tests/Unit/Domain/Knowledge/Retrieval/Services/KnowledgeBaseTest.php,
 * src/App/Http/… in tests/Unit/App/Http/…, src/Support/… in
 * tests/Unit/Support/…. A test of behaviour across several classes, named for
 * that behaviour, may sit in any folder of its context. Tests of files outside
 * src/ sit under the folder of that file (config/ → tests/Unit/Config/).
 *
 * Framework-free, like the other architecture tests.
 */

/** The only folders tests/Unit holds: the three layers of src/, and the repo folders tested outside it. */
const LAYOUT_UNIT_ROOTS = ['App', 'Domain', 'Support', 'Config', 'Database'];

/** @return array<string, list<string>> class basename => its paths under src/ */
function layoutSourceClasses(string $root): array
{
    $classes = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (str_ends_with((string) $file, '.php')) {
            $classes[$file->getBasename('.php')][] = substr($file->getPathname(), strlen($root) + 1);
        }
    }

    return $classes;
}

/** Where the unit test of the class at $sourcePath belongs: its path with `src/` dropped. */
function layoutExpectedTestPath(string $sourcePath): string
{
    return 'tests/Unit/'.substr($sourcePath, strlen('src/'), -strlen('.php')).'Test.php';
}

it('puts each unit test of a class where the class sits in src/', function (): void {
    $root = (string) realpath(__DIR__.'/../../');
    $classes = layoutSourceClasses($root);
    $misplaced = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/tests/Unit', FilesystemIterator::SKIP_DOTS)) as $file) {
        $path = substr($file->getPathname(), strlen($root) + 1);

        if (! str_ends_with($path, 'Test.php')) {
            continue;
        }

        // Only a test named after exactly one class has a place to be.
        $sources = $classes[substr($file->getBasename('.php'), 0, -strlen('Test'))] ?? [];

        if (count($sources) === 1 && ($expected = layoutExpectedTestPath($sources[0])) !== $path) {
            $misplaced[] = "{$path} (expected {$expected})";
        }
    }

    sort($misplaced);

    expect($misplaced)->toBe([]);
});

it('keeps tests/Unit to the folders of src/ and the repo folders tested outside it', function (): void {
    $root = (string) realpath(__DIR__.'/../../');
    $folders = array_map('basename', glob($root.'/tests/Unit/*', GLOB_ONLYDIR) ?: []);

    expect(array_values(array_diff($folders, LAYOUT_UNIT_ROOTS)))->toBe([]);
});
