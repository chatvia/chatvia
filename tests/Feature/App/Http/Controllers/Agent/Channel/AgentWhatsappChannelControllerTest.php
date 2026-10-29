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
 * browser an OAuth code and the chosen WABA/phone ids, POSTed to one endpoint.
 * A protected agent and a number another agent holds are refused.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['is_public' => true]);
    $this->app->instance(MetaOAuthClient::class, new FakeMetaOAuthClient);
    $this->app->instance(WhatsappClient::class, new FakeWhatsappClient);
});

/** POST the Embedded Signup payload as the agent's owner. */
function awcPost(Agent $agent): TestResponse
{
    return test()->actingAs(test()->owner)->post(route('agents.channels.whatsapp.connect', $agent), [
        'code' => 'ES-CODE',
        'waba_id' => '999888777666555',
        'phone_number_id' => '111222333444555',
    ]);
}

it('connects the chosen WhatsApp number from the Embedded Signup payload', function (): void {
    awcPost($this->agent)->assertRedirect()->assertSessionHasNoErrors();

    $number = AgentWhatsappNumber::query()->where('agent_id', $this->agent->id)->sole();
    expect($number->phone_number_id)->toBe('111222333444555')
        ->and($number->waba_id)->toBe('999888777666555')
        ->and($number->access_token)->toBe('EAAG-business-token');
});

it('refuses the number and connects nothing', function (Closure $refuse): void {
    $refuse->call($this);

    awcPost($this->agent)->assertSessionHasErrors('whatsapp');

    expect(AgentWhatsappNumber::query()->where('agent_id', $this->agent->id)->exists())->toBeFalse();
})->with([
    'a protected agent' => [fn (): bool => $this->agent->update(['is_public' => false])],
    'a number another agent holds' => [fn (): AgentWhatsappNumber => AgentWhatsappNumber::factory()->create(['phone_number_id' => '111222333444555'])],
]);
