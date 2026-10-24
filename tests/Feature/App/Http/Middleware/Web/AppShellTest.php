<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The app shell every page is served in (resources/views/app.blade.php,
 * the root view of the Inertia middleware) must not carry one operator's
 * identity (tracking code, social handle, website) into another install,
 * and must not load web fonts from a third-party CDN. The tracking code an
 * admin sets goes into it only in production.
 */
it('adds the tracking code only when one is set, and only in production', function (?string $code, string $environment, bool $shown): void {
    $snippet = '<script defer src="https://stats.acme.example/js/script.js"></script>';
    config(['chatvia.tracking_code' => $code === null ? null : $snippet]);
    app()->detectEnvironment(fn (): string => $environment);

    $page = $this->get('/login')->assertOk();

    $shown ? $page->assertSee($snippet, false) : $page->assertDontSee('stats.acme.example', false);
})->with([
    'set, in production' => ['set', 'production', true],
    'not set' => [null, 'production', false],
    'set, outside production' => ['set', 'local', false],
]);

it('serves the app shell with self-hosted fonts and the product name only', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('fonts/inter/inter.css', false)
        ->assertSee('<meta property="og:site_name" content="ChatVia.ai" />', false)
        ->assertSee('images/favicons/favicon.svg', false)
        ->assertDontSee('fonts.googleapis.com', false)
        ->assertDontSee('fonts.gstatic.com', false)
        ->assertDontSee('ahmadmayahi', false)
        ->assertDontSee('https://chatvia.ai', false);
});

it('loads no web font from a third-party CDN in any Blade view or stylesheet', function (): void {
    $offenders = collect([...File::allFiles(resource_path('views')), ...File::allFiles(resource_path('css'))])
        ->filter(fn (SplFileInfo $file): bool => Str::isMatch('/fonts\.(googleapis|gstatic)\.com/', File::get($file->getPathname())))
        ->map(fn (SplFileInfo $file): string => Str::replace(base_path().'/', '', $file->getPathname()))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
