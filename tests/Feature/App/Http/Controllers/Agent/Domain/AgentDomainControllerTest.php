<?php

use App\Admin\Settings\PlatformSettings;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Enums\DomainCheck;
use Domain\Agent\Jobs\VerifyAgentDomainJob;
use Domain\Agent\Models\AgentDomain;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

/**
 * The custom domain card on the Deploy page (AgentDomainController,
 * AgentDomainCheckController): saving a domain checks it in the background,
 * "Check again" checks it at once, and removing it puts the chat back on
 * APP_URL only. All of it needs a license key and the admin's switch.
 */
beforeEach(function (): void {
    withLicense();
    saveSettings(PlatformSettings::class, ['custom_domains_enabled' => true]);

    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('saves a pasted address as a lowercase domain and checks it in the background', function (): void {
    Bus::fake([VerifyAgentDomainJob::class]);

    $this->actingAs($this->owner)
        ->put(route('agents.domain.update', $this->agent), ['host' => ' https://Help.Acme.com/chat '])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $domain = $this->agent->domain()->sole();

    expect($domain->host)->toBe('help.acme.com')
        ->and($domain->isVerified())->toBeFalse();

    Bus::assertDispatched(VerifyAgentDomainJob::class, fn (VerifyAgentDomainJob $job): bool => $job->domain->is($domain));
});

it('starts over when the domain changes, and keeps a verified one saved again as it is', function (): void {
    Bus::fake([VerifyAgentDomainJob::class]);
    $domain = AgentDomain::factory()->forAgent($this->agent)->verified()->create(['host' => 'help.acme.com']);

    $this->actingAs($this->owner)->put(route('agents.domain.update', $this->agent), ['host' => 'help.acme.com']);

    expect($domain->fresh()->isVerified())->toBeTrue();

    $this->actingAs($this->owner)->put(route('agents.domain.update', $this->agent), ['host' => 'chat.acme.com']);

    expect($domain->fresh())
        ->host->toBe('chat.acme.com')
        ->verified_at->toBeNull()
        ->verification_token->not->toBe($domain->verification_token);
});

it('refuses a domain another agent uses', function (): void {
    AgentDomain::factory()->create(['host' => 'help.acme.com']);

    $this->actingAs($this->owner)
        ->put(route('agents.domain.update', $this->agent), ['host' => 'help.acme.com'])
        ->assertSessionHasErrors('host');

    expect($this->agent->domain()->exists())->toBeFalse();
});

it('checks the domain at once and shows what it found', function (): void {
    $domain = AgentDomain::factory()->forAgent($this->agent)->create(['host' => 'help.acme.com']);
    Http::fake(['https://help.acme.com/*' => Http::response($domain->verification_token)]);

    $this->actingAs($this->owner)
        ->post(route('agents.domain.checks.store', $this->agent))
        ->assertRedirect();

    expect($domain->fresh())
        ->isVerified()->toBeTrue()
        ->last_check->toBe(DomainCheck::Reached);
});

it('removes the domain', function (): void {
    AgentDomain::factory()->forAgent($this->agent)->verified()->create();

    $this->actingAs($this->owner)
        ->delete(route('agents.domain.destroy', $this->agent))
        ->assertRedirect();

    expect($this->agent->domain()->exists())->toBeFalse();
});

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
