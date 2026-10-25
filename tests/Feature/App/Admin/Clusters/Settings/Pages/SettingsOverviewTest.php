<?php

use App\Admin\Clusters\Settings\Pages\ManageSplittingSettings;
use App\Admin\Clusters\Settings\Pages\SettingsOverview;
use App\Admin\Clusters\Settings\SettingsCluster;
use App\Admin\Settings\BrandSettings;
use App\Admin\Settings\SignInSettings;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\License\LicenseKeys;

/**
 * The Settings overview of the admin panel: a tile for every Settings page,
 * grouped by topic, with each page's status, and a search of all Settings in
 * place of the tiles. It asks an install without a license for support and
 * thanks the one that has one.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('shows every settings page, and the overview links to each one', function (): void {
    $this->actingAs($this->admin, 'admin');

    $overview = $this->get(SettingsOverview::getUrl())->assertOk();

    foreach (SettingsCluster::getClusteredComponents() as $page) {
        $this->get($page::getUrl())->assertOk()->assertSee($page::getNavigationLabel());

        if ($page !== SettingsOverview::class) {
            $overview->assertSee($page::getUrl());
        }
    }

    expect(collect(SettingsCluster::getClusteredComponents())->map(fn (string $page): string => $page::getSlug())->sort()->values()->all())
        ->toBe(['ai-models', 'ai-providers', 'branding', 'channels', 'connectors', 'email', 'knowledge', 'observability', 'overview', 'platform', 'search', 'security', 'sign-in', 'splitting', 'storage', 'widget', 'workspaces']);
});

it('groups the tiles by topic', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(SettingsOverview::getUrl())
        ->assertSeeInOrder([
            'Access and security', 'Sign-in', 'Workspaces', 'Security',
            'AI and knowledge', 'AI providers', 'AI models', 'Knowledge', 'Splitting',
            'Connections', 'Channels', 'Connectors',
            'Platform and brand', 'Platform', 'Email', 'Storage', 'Branding',
        ]);
});

it('marks a page of optional services that has none set up as optional', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(SettingsOverview::getUrl())
        ->assertSeeInOrder(['Sign-in', 'Optional'])
        ->assertSee(['Sign-up open', 'Turnstile off', 'Not sending email yet'])
        ->assertDontSee('of 4 set up');
});

it('names a service that is not finished, then counts the ones set up', function (): void {
    saveSettings(SignInSettings::class, ['google_client_id' => 'id', 'google_client_secret' => 'secret', 'github_client_id' => 'id']);

    $this->actingAs($this->admin, 'admin')
        ->get(SettingsOverview::getUrl())
        ->assertSee('GitHub is not finished');

    saveSettings(SignInSettings::class, ['github_client_secret' => 'secret']);

    $this->get(SettingsOverview::getUrl())->assertSee('2 set up');
});

it('shows the finds in place of the tiles, and says when there are none', function (): void {
    Filament::setCurrentPanel('admin');

    Livewire::actingAs($this->admin, 'admin')
        ->test(SettingsOverview::class)
        ->assertSee('Access and security')
        ->set('search', 'chunk size')
        ->assertDontSee('Access and security')
        ->assertSee(['1 result', 'Settings › Splitting'])
        ->assertSeeHtml(['<mark>Chunk</mark> <mark>size</mark>', ManageSplittingSettings::getUrl().'#splitting-splitting'])
        ->set('search', 'qqqq')
        ->assertSee('Nothing in Settings matches “qqqq”');
});

it('asks an install without a license for support', function (): void {
    LicenseKeys::trust();
    config(['app.url' => 'https://chat.acme.com']);

    $this->actingAs($this->admin, 'admin')
        ->get(SettingsOverview::getUrl())
        ->assertOk()
        ->assertSee('Support ChatVia.ai')
        ->assertSee('Buy a license')
        ->assertSee('Hire me')
        ->assertSee('https://mayahi.net/services')
        ->assertDontSee('Thank you for your support');
});

it('thanks an install with an active license', function (): void {
    LicenseKeys::trust();
    config(['app.url' => 'https://chat.acme.com']);
    saveSettings(BrandSettings::class, ['license_key' => LicenseKeys::activatedFor('chat.acme.com')]);

    $this->actingAs($this->admin, 'admin')
        ->get(SettingsOverview::getUrl())
        ->assertOk()
        ->assertSee('Thank you for your support')
        ->assertSee('licensed to Acme Ltd')
        ->assertDontSee('Support ChatVia.ai');
});
