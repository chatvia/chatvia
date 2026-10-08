<?php

use Support\Cms\Marketing\Models\Post;

/**
 * Pages must not carry one operator's identity (company, address, VAT,
 * analytics account, social handle) into another install. Everything comes
 * from config and is hidden when unset.
 */
function setCompany(array $company): void
{
    config(['chatvia.company' => array_merge([
        'name' => null, 'address' => null, 'email' => null, 'vat_id' => null,
    ], $company)]);
}

it('shows the configured company in the site footer', function (): void {
    setCompany([
        'name' => 'Acme ApS',
        'address' => "Main Street 1\n1000 Copenhagen, Denmark",
        'vat_id' => 'DK12345678',
    ]);

    $this->get('/en/pricing')
        ->assertOk()
        ->assertSeeInOrder(['Acme ApS', 'Main Street 1', '1000 Copenhagen, Denmark', 'VAT DK12345678'], false);
});

it('renders no company block when none is configured', function (): void {
    setCompany([]);

    $this->get('/en/pricing')
        ->assertOk()
        ->assertDontSee('Akkadian')
        ->assertDontSee('35709738')
        ->assertDontSee('Næstved');
});

it('uses the configured contact email on the legal pages', function (): void {
    setCompany(['email' => 'legal@acme.example']);

    $this->get(route('refund'))->assertOk()->assertSee('mailto:legal@acme.example', false);
    $this->get(route('terms'))->assertOk()->assertSee('mailto:legal@acme.example', false);
    $this->get(route('privacy'))->assertOk()->assertSee('mailto:legal@acme.example', false);
});

it('shows no operator email on the legal pages when none is configured', function (): void {
    setCompany([]);

    foreach (['refund', 'terms', 'privacy', 'dpa', 'contact'] as $page) {
        $this->get(route($page))->assertOk()->assertDontSee('info@chatvia');
    }
});

it('fills the processor side of the DPA from config', function (): void {
    config(['chatvia.brand.name' => 'Acme Chat']);
    setCompany(['name' => 'Acme ApS', 'address' => "Main Street 1\n1000 Copenhagen", 'email' => 'dpa@acme.example', 'vat_id' => 'DK12345678']);

    $html = view('pdf.dpa', dpaViewData())->render();

    expect($html)
        ->toContain('Acme ApS')
        ->toContain('(VAT DK12345678)')
        ->toContain('Main Street 1, 1000 Copenhagen')
        ->toContain('dpa@acme.example')
        ->toContain('the Acme Chat service')
        ->not->toContain('Akkadian')
        ->not->toContain('chatvia.ai');
});

it('falls back to the brand name on the DPA when no company is configured', function (): void {
    config(['chatvia.brand.name' => 'Acme Chat']);
    setCompany([]);

    $html = view('pdf.dpa', dpaViewData())->render();

    expect($html)
        ->toContain('For the Processor — Acme Chat')
        ->not->toContain('VAT')
        ->not->toContain('countersign, contact');
});

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
    config(['chatvia.brand.name' => 'Acme Chat', 'chatvia.brand.twitter_handle' => null]);

    $this->get('/login')
        ->assertOk()
        ->assertSee('fonts/inter/inter.css', false)
        ->assertSee('<meta property="og:site_name" content="Acme Chat" />', false)
        ->assertDontSee('fonts.googleapis.com', false)
        ->assertDontSee('fonts.gstatic.com', false)
        ->assertDontSee('ahmadmayahi', false)
        ->assertDontSee('twitter:site', false)
        ->assertDontSee('aggregateRating', false)
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

it('names the configured brand and author on the marketing pages', function (): void {
    config(['chatvia.brand.name' => 'Acme Chat', 'chatvia.founder.name' => 'Jane Founder']);
    Post::factory()->create();

    expect(view('landing.partials.newsletter')->render())->toContain('Sign up for Acme Chat');
    $this->get(route('blog'))->assertOk()->assertSee('Jane Founder')->assertDontSee('Mayahi');
});

it('adds the twitter handle only when one is configured', function (): void {
    config(['chatvia.brand.twitter_handle' => '@acme']);

    $this->get('/login')->assertOk()->assertSee('<meta name="twitter:site" content="@acme" />', false);
});

/** @return array<string, mixed> */
function dpaViewData(): array
{
    return [
        'companyName' => 'Customer GmbH',
        'registrationNumber' => null,
        'registeredAddress' => null,
        'country' => null,
        'contactEmail' => null,
        'signatoryName' => null,
        'signatoryTitle' => null,
        'bodyHtml' => '<p>Body</p>',
        'generatedAt' => '1 January 2026',
    ];
}
