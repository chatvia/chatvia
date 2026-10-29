<?php

use App\Admin\Settings\PlatformSettings;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Jobs\VerifyAgentDomainJob;
use Domain\Agent\Models\AgentDomain;
use Illuminate\Support\Facades\Bus;

/**
 * The custom domain card on the Deploy page: saving a domain stores it
 * lowercase and checks it in the background, a changed domain starts its
 * check over, and a domain another agent uses or one that would take over the
 * whole app is refused. All of it needs a license key and the admin's switch.
 */
beforeEach(function (): void {
    withLicense();
    saveSettings(PlatformSettings::class, ['custom_domains_enabled' => true]);
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('keeps a verified domain saved again as it is and starts over for a new pasted address', function (): void {
    Bus::fake([VerifyAgentDomainJob::class]);
    $domain = AgentDomain::factory()->forAgent($this->agent)->verified()->create(['host' => 'help.acme.com']);

    $this->actingAs($this->owner)->put(route('agents.domain.update', $this->agent), ['host' => 'help.acme.com']);

    expect($domain->fresh()->isVerified())->toBeTrue();

    $this->actingAs($this->owner)
        ->put(route('agents.domain.update', $this->agent), ['host' => ' https://Chat.Acme.com/chat '])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($domain->fresh())
        ->host->toBe('chat.acme.com')
        ->verified_at->toBeNull()
        ->verification_token->not->toBe($domain->verification_token);
    Bus::assertDispatched(VerifyAgentDomainJob::class, fn (VerifyAgentDomainJob $job): bool => $job->domain->is($domain));
});

it('refuses a domain another agent uses, this server\'s own address and the CNAME target', function (string $host): void {
    config(['app.url' => 'https://chat.example.com']);
    withLicense();
    saveSettings(PlatformSettings::class, ['custom_domains_enabled' => true, 'custom_domains_cname_target' => 'domains.example.com']);
    AgentDomain::factory()->create(['host' => 'help.acme.com']);

    $this->actingAs($this->owner)
        ->put(route('agents.domain.update', $this->agent), ['host' => $host])
        ->assertSessionHasErrors('host');

    expect($this->agent->domain()->exists())->toBeFalse();
})->with([
    'another agent\'s domain' => ['help.acme.com'],
    'the install\'s address' => ['Chat.Example.com'],
    'the CNAME target' => ['domains.example.com'],
]);

it('refuses custom domains until the admin turns them on, or without a license key', function (Closure $lock): void {
    $lock();

    $this->actingAs($this->owner)
        ->put(route('agents.domain.update', $this->agent), ['host' => 'help.acme.com'])
        ->assertForbidden();

    expect($this->agent->domain()->exists())->toBeFalse();
})->with([
    'switch off' => [fn () => saveSettings(PlatformSettings::class, ['custom_domains_enabled' => false])],
    'no license key' => [fn () => withoutLicense()],
]);
