<?php

use App\Admin\Clusters\Settings\Pages\ManageConnectorSettings;
use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\ConnectorSettings;

/**
 * Settings → Connectors: the OAuth apps of the connectors, and under
 * Advanced the services behind the built-in tools (Perplexity, OpenWeather,
 * ExchangeRate-API), which live here and not on the Platform page.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('keeps the services behind the built-in tools on Connectors, under Advanced', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManageConnectorSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['GitHub', 'Technical options. The defaults suit most installs.', 'Perplexity', 'OpenWeather', 'ExchangeRate-API']);

    $this->get(ManagePlatformSettings::getUrl())->assertDontSee(['Perplexity', 'OpenWeather', 'ExchangeRate-API']);
});

it('saves the address of a built-in tool\'s service, which the overview does not count as a service set up', function (): void {
    adminPage(ManageConnectorSettings::class, $this->admin)
        ->fillForm(['perplexity_base_url' => 'https://perplexity.example.com', 'github_client_id' => 'id', 'github_client_secret' => 'secret'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(ConnectorSettings::class, 'perplexity_base_url'))->toBe('https://perplexity.example.com')
        ->and(config('services.perplexity.base_url'))->toBe('https://perplexity.example.com')
        ->and(app(ManageConnectorSettings::class)->overviewStatus())->toBe(['1 set up', 'success']);
});
