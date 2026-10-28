<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\PlatformSettings;
use Domain\Agent\Services\CustomDomainLookup;

/**
 * Settings → Platform → Custom domains: the CNAME target customers point
 * their domain at, for an install whose own address cannot take other names.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    config(['app.url' => 'https://chat.example.com']);
});

it('saves the CNAME target as a lowercase host, and an empty box goes back to the install\'s address', function (): void {
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['custom_domains_cname_target' => ' Domains.Example.com '])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(PlatformSettings::class, 'custom_domains_cname_target'))->toBe('domains.example.com')
        ->and(app(CustomDomainLookup::class)->cnameTarget())->toBe('domains.example.com');

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['custom_domains_cname_target' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(CustomDomainLookup::class)->cnameTarget())->toBe('chat.example.com');
});

it('refuses a CNAME target that is not a host name', function (): void {
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['custom_domains_cname_target' => 'https://domains.example.com/'])
        ->call('save')
        ->assertHasFormErrors(['custom_domains_cname_target']);

    expect(savedSetting(PlatformSettings::class, 'custom_domains_cname_target'))->toBeNull();
});
