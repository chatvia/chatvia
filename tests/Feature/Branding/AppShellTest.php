<?php

/**
 * The app shell must not carry one operator's identity (analytics account,
 * social handle, website) into another install, and must not load web fonts
 * from a third-party CDN. Everything comes from config.
 */
it('loads analytics only when a Plausible domain is configured', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    config(['services.plausible.domain' => null]);
    expect(trim(view('analytics')->render()))->toBe('');

    config([
        'services.plausible.domain' => 'acme.example',
        'services.plausible.script_url' => 'https://stats.acme.example/js/script.js',
    ]);
    expect(view('analytics')->render())
        ->toContain('data-domain="acme.example"')
        ->toContain('src="https://stats.acme.example/js/script.js"');
});

it('serves the app shell with self-hosted fonts and the configured brand only', function (): void {
    config(['chatvia.brand.name' => 'Acme Chat']);

    $this->get('/login')
        ->assertOk()
        ->assertSee('fonts/inter/inter.css', false)
        ->assertSee('<meta property="og:site_name" content="Acme Chat" />', false)
        ->assertSee('images/favicons/favicon.svg', false)
        ->assertDontSee('fonts.googleapis.com', false)
        ->assertDontSee('fonts.gstatic.com', false)
        ->assertDontSee('ahmadmayahi', false)
        ->assertDontSee('https://chatvia.ai', false);
});

it('loads no web font from a third-party CDN in any Blade view or stylesheet', function (): void {
    $offenders = collect([resource_path('views'), resource_path('css')])
        ->flatMap(fn (string $dir): array => iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS))))
        ->filter(fn (SplFileInfo $file): bool => $file->isFile() && preg_match('/fonts\.(googleapis|gstatic)\.com/', (string) file_get_contents($file->getPathname())) === 1)
        ->map(fn (SplFileInfo $file): string => str_replace(base_path().'/', '', $file->getPathname()))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
