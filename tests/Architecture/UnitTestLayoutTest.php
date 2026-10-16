<?php

declare(strict_types=1);

/*
 * A unit test named after a class sits where that class sits in src/:
 * src/Domain/Knowledge/Retrieval/Services/KnowledgeBase.php is tested in
 * tests/Unit/Knowledge/Retrieval/Services/KnowledgeBaseTest.php, App classes
 * drop the App segment (tests/Unit/Http/…), and Support classes keep it
 * (tests/Unit/Support/…). A test of behaviour across several classes, named
 * for that behaviour, may sit in any folder of its context.
 *
 * Framework-free, like the other architecture tests.
 */

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

/** Where the unit test of the class at $sourcePath belongs. */
function layoutExpectedTestPath(string $sourcePath): string
{
    $relative = match (true) {
        str_starts_with($sourcePath, 'src/Domain/') => substr($sourcePath, strlen('src/Domain/')),
        str_starts_with($sourcePath, 'src/App/') => substr($sourcePath, strlen('src/App/')),
        default => substr($sourcePath, strlen('src/')),
    };

    return 'tests/Unit/'.substr($relative, 0, -strlen('.php')).'Test.php';
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
