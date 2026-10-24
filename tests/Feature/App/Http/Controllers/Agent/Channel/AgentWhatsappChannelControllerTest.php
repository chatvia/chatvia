<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Channel\Services\MetaOAuthClient;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Services\WhatsappClient;
use Illuminate\Testing\TestResponse;
use Tests\Support\Channel\FakeMetaOAuthClient;
use Tests\Support\Channel\Whatsapp\FakeWhatsappClient;

/**
 * WhatsApp connects via Embedded Signup: the Facebook JS SDK popup hands the
 * browser an OAuth code and the chosen WABA/phone ids, POSTed to one endpoint
 * (no redirect/callback/picker round-trip like Messenger). The connect action
 * owns the rules; this pins the wiring, the payload validation and the field
 * error each refusal shows.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['is_public' => true]);

    $this->oauth = new FakeMetaOAuthClient;
    $this->app->instance(MetaOAuthClient::class, $this->oauth);
    $this->app->instance(WhatsappClient::class, new FakeWhatsappClient);
});

/**
 * POST the Embedded Signup payload as the agent's owner.
 *
 * @param  array<string, string>  $overrides
 */
function awcPost(Agent $agent, array $overrides = []): TestResponse
{
    return test()->actingAs(test()->owner)->post(route('agents.channels.whatsapp.connect', $agent), [
        'code' => 'ES-CODE',
        'waba_id' => '999888777666555',
        'phone_number_id' => '111222333444555',
        ...$overrides,
    ]);
}

it('connects the chosen WhatsApp number from the Embedded Signup payload', function (): void {
    awcPost($this->agent)->assertRedirect()->assertSessionHasNoErrors();

    $number = AgentWhatsappNumber::query()->where('agent_id', $this->agent->id)->sole();
    expect($number->phone_number_id)->toBe('111222333444555')
        ->and($number->waba_id)->toBe('999888777666555')
        ->and($number->access_token)->toBe('EAAG-business-token');
});

it('explains why the number could not be connected', function (Closure $refuse, string $error): void {
    $refuse->call($this);

    awcPost($this->agent)->assertSessionHasErrors(['whatsapp' => $error]);

    expect(AgentWhatsappNumber::query()->where('agent_id', $this->agent->id)->exists())->toBeFalse();
})->with([
    'a protected agent' => [
        fn (): bool => $this->agent->update(['is_public' => false]),
        'WhatsApp is available for public agents only. Make this agent public on the Security page, then connect it.',
    ],
    'a number another agent holds' => [
        fn (): AgentWhatsappNumber => AgentWhatsappNumber::factory()->create(['phone_number_id' => '111222333444555']),
        'That WhatsApp number is already connected to another agent. Disconnect it there first.',
    ],
    'a code Meta rejects' => [
        fn (): null => $this->oauth->businessToken = null,
        'Meta couldn’t complete the WhatsApp connection. Please try connecting again.',
    ],
]);

it('needs numeric ids in the Embedded Signup payload', function (): void {
    awcPost($this->agent, ['phone_number_id' => 'not-a-number'])->assertSessionHasErrors('phone_number_id');

    expect(AgentWhatsappNumber::query()->count())->toBe(0);
});
