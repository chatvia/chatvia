<?php

/*
 * Source-level guardrails for the Vue dashboard + hosted chat (resources/js).
 * There is no JavaScript test runner for resources/js, so these checks read the
 * source files directly, like ConventionsTest does for PHP. Each one pins a bug
 * that was silent in the browser (invalid CSS dropped without an error, a
 * third-party request leaking the visitor's IP, storage access that throws in
 * an iframe).
 *
 * Framework-free (Architecture suite): paths are resolved relative to this file.
 */

$frontendRoot = realpath(__DIR__.'/../../resources/js');

/**
 * Relative paths (from resources/js) of .vue/.ts/.js files whose source, with
 * CSS/JS block comments removed, matches $pattern.
 *
 * @param  list<string>  $allowed
 * @return list<string>
 */
$frontendOffenders = function (string $pattern, array $allowed = []) use ($frontendRoot): array {
    $offenders = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($frontendRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        $path = (string) $file;
        if (! $file->isFile() || ! preg_match('/\.(vue|ts|js)$/', $path)) {
            continue;
        }

        $relative = ltrim(str_replace($frontendRoot, '', $path), DIRECTORY_SEPARATOR);
        if (in_array($relative, $allowed, true)) {
            continue;
        }

        // Block comments may explain a rule by quoting the forbidden form.
        $source = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));
        if (preg_match($pattern, $source) === 1) {
            $offenders[] = $relative;
        }
    }

    sort($offenders);

    return $offenders;
};

/**
 * Source of one file under resources/js. A renamed or moved file fails the test
 * instead of reading as an empty string, which would pass every check silently.
 */
$frontendSource = function (string $relative) use ($frontendRoot): string {
    $path = $frontendRoot.'/'.$relative;

    expect(is_file($path))->toBeTrue("resources/js/{$relative} does not exist; update the path in this test");

    return (string) file_get_contents($path);
};

it('never wraps a theme CSS variable in hsl()', function () use ($frontendOffenders): void {
    // Theme variables hold full color functions (`--primary: hsl(0 0% 9%)`), so
    // `hsl(var(--primary))` is invalid CSS and the browser silently drops it.
    expect($frontendOffenders('/hsl\(\s*var\(--/'))->toBe([]);
});

it('does not load connector logos from a third-party icon CDN', function () use ($frontendOffenders): void {
    expect($frontendOffenders('/cdn\.simpleicons\.org/'))->toBe([]);
});

it('does not load web fonts from Google at runtime', function () use ($frontendOffenders): void {
    // The hosted chat is visitor-facing: a Google Fonts @import sends every
    // visitor's IP address to Google.
    expect($frontendOffenders('/fonts\.(googleapis|gstatic)\.com/'))->toBe([]);
});

it('leaves no console.log debug output in the frontend', function () use ($frontendOffenders, $frontendRoot): void {
    // These files show console.log inside code samples for customers.
    $codeSamples = [
        'pages/agents/Deploy.vue',
        'pages/agents/security/Identity.examples.ts',
    ];

    foreach ($codeSamples as $sample) {
        expect(is_file($frontendRoot.'/'.$sample))->toBeTrue("resources/js/{$sample} does not exist; update the allowlist");
    }

    expect($frontendOffenders('/console\.log\(/', $codeSamples))->toBe([]);
});

it('reads localStorage through safeStorage on the boot and hosted-chat paths', function () use ($frontendSource): void {
    // The hosted chat can run in an iframe where touching localStorage throws;
    // at boot that would stop the whole app from mounting.
    $files = [
        'utils/eventBus.ts',
        'composables/useAppearance.ts',
        'components/chat/ChatLayout.vue',
        'components/chat/ChatSidebar.vue',
        'components/chat/Header.vue',
        'pages/chat/Chat.vue',
    ];

    foreach ($files as $file) {
        expect($frontendSource($file))->not->toMatch('/\blocalStorage\.(getItem|setItem|removeItem)\(/', $file);
    }
});

it('uses one shared Echo connection', function () use ($frontendOffenders): void {
    // app.ts configures Echo once (configureEcho); a second `new Echo()` opens a
    // second Reverb socket with a duplicated config.
    expect($frontendOffenders('/new\s+Echo\s*\(/'))->toBe([]);
});

it('builds the delete confirmation on AlertDialog', function () use ($frontendSource): void {
    $source = $frontendSource('components/common/DeleteConfirmationDialog.vue');

    expect($source)->toContain('<AlertDialog')
        ->and($source)->not->toContain("from '@/components/ui/dialog'");
});

it('only puts attributes the embed script reads into the Command Bar snippet', function () use ($frontendSource): void {
    // Shakomako's embed.js never reads data-trigger / data-placement; the bar
    // takes both from the agent's saved settings. Every install snippet the
    // Deploy page shows is built in installSnippets.ts.
    $source = $frontendSource('components/agents/deploy/installSnippets.ts');

    expect($source)->toContain('data-mode="bar"');

    expect($source)->not->toContain('data-trigger')
        ->and($source)->not->toContain('data-placement');
});
