<?php

declare(strict_types=1);

/*
 * PSR-4 autoloading is case-sensitive on Linux (production, CI) but not on
 * macOS. A folder or file renamed only by case on a Mac (e.g. `QA` → `Qa`)
 * keeps its OLD spelling in git, and the class then fails to autoload on the
 * server. This test reads the paths git actually tracks and checks each one
 * against the namespace and class declared inside the file, case-sensitively.
 *
 * Framework-free, like the other architecture tests.
 */

/** @var array<string, string> PSR-4 prefix => base path, from composer.json. */
const PSR4_ROOTS = [
    'App\\' => 'src/App/',
    'Domain\\' => 'src/Domain/',
    'Support\\' => 'src/Support/',
];

it('tracks every class file under the exact case of its namespace and class name', function (): void {
    $root = realpath(__DIR__.'/../../');
    $output = [];
    $exitCode = 1;

    exec('git -C '.escapeshellarg((string) $root).' ls-files -- src 2>/dev/null', $output, $exitCode);

    if ($exitCode !== 0 || $output === []) {
        $this->markTestSkipped('git is not available, so tracked paths cannot be checked.');
    }

    $mismatches = [];

    foreach ($output as $path) {
        if (! str_ends_with($path, '.php') || $path === 'src/Support/helpers.php') {
            continue;
        }

        // A tracked file deleted in the working tree (not yet committed) has
        // nothing to check.
        if (! is_file($root.'/'.$path)) {
            continue;
        }

        $source = file_get_contents($root.'/'.$path);

        // A class file this check cannot read is a finding too, so a file
        // never slips past it unchecked.
        if ($source === false
            || preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) !== 1
            || preg_match('/^(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $source, $class) !== 1) {
            $mismatches[] = "{$path} (no namespace or class declaration found)";

            continue;
        }

        $fqcn = $namespace[1].'\\'.$class[1];

        foreach (PSR4_ROOTS as $prefix => $base) {
            if (str_starts_with($fqcn, $prefix)) {
                $expected = $base.str_replace('\\', '/', substr($fqcn, strlen($prefix))).'.php';

                if ($expected !== $path) {
                    $mismatches[] = "{$path} (expected {$expected})";
                }
            }
        }
    }

    expect($mismatches)->toBe([]);
});
