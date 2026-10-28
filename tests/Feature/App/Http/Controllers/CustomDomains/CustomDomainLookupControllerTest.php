<?php

use App\Admin\Settings\PlatformSettings;
use Domain\Agent\Models\AgentDomain;

/**
 * The question Caddy asks before it gets a certificate for a new name
 * (CustomDomainLookupController): yes for an agent's saved custom domain,
 * verified or not, and no for any other name or while custom domains are off.
 */
beforeEach(function (): void {
    withLicense();
    saveSettings(PlatformSettings::class, ['custom_domains_enabled' => true]);
});

it('allows a certificate only for a saved custom domain', function (): void {
    AgentDomain::factory()->create(['host' => 'help.acme.com']);

    $this->get(route('custom-domains.lookup', ['domain' => 'help.acme.com']))->assertOk();
    $this->get(route('custom-domains.lookup', ['domain' => 'HELP.acme.com.']))->assertOk();
    $this->get(route('custom-domains.lookup', ['domain' => 'evil.example.com']))->assertNotFound();
    $this->get(route('custom-domains.lookup'))->assertNotFound();
});

it('allows no certificate while custom domains are off', function (): void {
    AgentDomain::factory()->create(['host' => 'help.acme.com']);
    saveSettings(PlatformSettings::class, ['custom_domains_enabled' => false]);

    $this->get(route('custom-domains.lookup', ['domain' => 'help.acme.com']))->assertNotFound();
});
