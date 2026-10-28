<?php

use App\Admin\Settings\PlatformSettings;
use Domain\Agent\Jobs\VerifyAgentDomainJob;
use Domain\Agent\Models\AgentDomain;
use Illuminate\Support\Facades\Bus;

/**
 * `chatvia:check:agent-domains`: checks a new domain every ten minutes for
 * its first two days and a verified one once a day, and nothing while custom
 * domains are off.
 */
beforeEach(function (): void {
    Bus::fake([VerifyAgentDomainJob::class]);
    withLicense();
    saveSettings(PlatformSettings::class, ['custom_domains_enabled' => true]);
});

it('queues a check of each domain that is due', function (): void {
    $new = AgentDomain::factory()->create();
    $verifiedYesterday = AgentDomain::factory()->verified()->create(['last_checked_at' => now()->subDay()->subMinute()]);
    AgentDomain::factory()->create(['last_checked_at' => now()->subMinutes(3)]);
    AgentDomain::factory()->create(['created_at' => now()->subDays(3)]);
    AgentDomain::factory()->verified()->create(['last_checked_at' => now()->subHour()]);

    $this->artisan('chatvia:check:agent-domains')->assertSuccessful();

    Bus::assertDispatchedTimes(VerifyAgentDomainJob::class, 2);
    Bus::assertDispatched(VerifyAgentDomainJob::class, fn (VerifyAgentDomainJob $job): bool => $job->domain->is($new));
    Bus::assertDispatched(VerifyAgentDomainJob::class, fn (VerifyAgentDomainJob $job): bool => $job->domain->is($verifiedYesterday));
});

it('checks nothing while custom domains are off', function (): void {
    AgentDomain::factory()->create();
    saveSettings(PlatformSettings::class, ['custom_domains_enabled' => false]);

    $this->artisan('chatvia:check:agent-domains')->assertSuccessful();

    Bus::assertNotDispatched(VerifyAgentDomainJob::class);
});
