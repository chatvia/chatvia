<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Channel\Services\MetaOAuthClient;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Services\WhatsappClient;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Testing\TestResponse;
use Tests\Support\Whatsapp\FakeWhatsappClient;
use Tests\Support\Whatsapp\FakeWhatsappOAuthClient;

/**
 * WhatsApp connects via Embedded Signup: the Facebook JS SDK popup hands the
 * browser an OAuth code + the chosen WABA/phone ids, POSTed to one endpoint
 * (no redirect/callback/picker round-trip like Messenger). These tests pin the
 * public-agent gate, the duplicate-number guard, and the final connect.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
    $this->agent->update(['is_public' => true]);

    $this->app->instance(MetaOAuthClient::class, new FakeWhatsappOAuthClient);
    $this->app->instance(WhatsappClient::class, new FakeWhatsappClient);
});

/**
 * POST the Embedded Signup payload as the agent's owner.
 *
 * @param  array<string, string>  $overrides
 */
function postWhatsappConnect(Agent $agent, array $overrides = []): TestResponse
{
    return test()->actingAs(test()->owner)->post(route('agents.channels.whatsapp.connect', $agent), array_merge([
        'code' => 'ES-CODE',
        'waba_id' => '999888777666555',
        'phone_number_id' => '111222333444555',
    ], $overrides));
}

it('connects the chosen WhatsApp number from the Embedded Signup payload', function (): void {
    postWhatsappConnect($this->agent)->assertRedirect();

    $number = AgentWhatsappNumber::query()->where('agent_id', $this->agent->id)->first();
    expect($number)->not->toBeNull()
        ->and($number->phone_number_id)->toBe('111222333444555')
        ->and($number->waba_id)->toBe('999888777666555')
        ->and($number->access_token)->toBe('EAAG-business-token')
        ->and($number->is_enabled)->toBeTrue();
});

it('refuses to connect a protected (non-public) agent', function (): void {
    $this->agent->update(['is_public' => false]);

    postWhatsappConnect($this->agent)->assertSessionHasErrors('whatsapp');

    expect(AgentWhatsappNumber::query()->count())->toBe(0);
});

it('rejects a phone number already connected to another agent', function (): void {
    $otherAgent = createAgent($this->owner);
    AgentWhatsappNumber::create([
        'agent_id' => $otherAgent->id,
        'access_token' => 'EAAG-other',
        'phone_number_id' => '111222333444555',
        'waba_id' => '999888777666555',
        'is_enabled' => true,
    ]);

    postWhatsappConnect($this->agent)->assertSessionHasErrors('whatsapp');

    expect(AgentWhatsappNumber::query()->where('agent_id', $this->agent->id)->count())->toBe(0);
});

it('surfaces an error when Meta cannot complete the connection', function (): void {
    $this->app->instance(MetaOAuthClient::class, tap(new FakeWhatsappOAuthClient, function (FakeWhatsappOAuthClient $client): void {
        $client->token = null;
    }));

    postWhatsappConnect($this->agent)->assertSessionHasErrors('whatsapp');

    expect(AgentWhatsappNumber::query()->count())->toBe(0);
});

it('validates the Embedded Signup payload (numeric ids required)', function (): void {
    postWhatsappConnect($this->agent, ['phone_number_id' => 'not-a-number'])
        ->assertSessionHasErrors('phone_number_id');

    expect(AgentWhatsappNumber::query()->count())->toBe(0);
});
