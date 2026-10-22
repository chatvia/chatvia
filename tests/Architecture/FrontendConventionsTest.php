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

it('builds form controls on the shared ui components', function () use ($frontendOffenders, $frontendRoot): void {
    // Native <select>, checkbox, radio, range and <textarea> controls take the
    // browser's look, so they differ from the rest of the dashboard and from
    // one OS to the next. Use Select, Checkbox, RadioGroup, Slider and Textarea
    // from components/ui instead.
    $custom = [
        'components/agents/ColorPicker.vue',          // the shade slider paints the theme's color ramp
        'components/agents/forms/FormPreview.vue',    // copies the chat widget's own form controls
        'components/common/JsonCodeEditor.vue',       // a transparent textarea over the highlighted code
        'pages/AnalyticsChat.vue',                    // the chat composer's borderless textarea
    ];

    foreach ($custom as $file) {
        expect(is_file($frontendRoot.'/'.$file))->toBeTrue("resources/js/{$file} does not exist; update the allowlist");
    }

    $offenders = array_values(array_filter(
        $frontendOffenders('/<select\b|<textarea\b|type="(checkbox|radio|range)"/', $custom),
        fn (string $file): bool => ! str_starts_with($file, 'components/ui/'),
    ));

    expect($offenders)->toBe([]);
});

it('reads localStorage only through safeStorage', function () use ($frontendOffenders): void {
    // The hosted chat can run in an iframe where touching localStorage throws;
    // at boot that would stop the whole app from mounting. Even `typeof
    // localStorage` reads the global, so it throws there too.
    expect($frontendOffenders('/\blocalStorage\s*\.|\btypeof\s+localStorage\b/', ['lib/safeStorage.ts', 'lib/safeStorage.test.ts']))->toBe([]);
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
