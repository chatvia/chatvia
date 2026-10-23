<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Models\WhatsappChat;
use Domain\Channel\Whatsapp\Services\WhatsappAdapter;
use Domain\Channel\Whatsapp\Services\WhatsappChatResolver;
use Illuminate\Support\Carbon;
use Tests\Support\Channel\StubChannelReplyRunner;
use Tests\Support\Whatsapp\FakeWhatsappClient;

/**
 * A WhatsApp turn through {@see HandleChannelMessageAction} with the
 * {@see WhatsappAdapter}: resolve the chat, mark-read + typing off the inbound
 * wamid, run the agent for a reply, send it back, and stamp the chat. The
 * pipeline (real LLM) is stubbed here — this asserts the wiring, not the
 * model's answer.
 */
function whatsappTurnNumber(): AgentWhatsappNumber
{
    $agent = Agent::factory()->create(['is_public' => true]);

    return AgentWhatsappNumber::create([
        'agent_id' => $agent->id,
        'access_token' => 'EAAG-secret-token',
        'phone_number_id' => '111222333444555',
        'waba_id' => '999888777666555',
        'is_enabled' => true,
    ]);
}

function whatsappTurnAdapter(FakeWhatsappClient $client, AgentWhatsappNumber $number, string $waId, string $wamid, ?string $name = null): WhatsappAdapter
{
    return new WhatsappAdapter($client, app(WhatsappChatResolver::class), $number, $waId, $wamid, $name);
}

it('sends the agent reply in the sender’s chat and stamps it when it replies', function (): void {
    $number = whatsappTurnNumber();
    $client = new FakeWhatsappClient;
    $runner = new StubChannelReplyRunner('Hello from the agent');
    $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
    app(WhatsappChatResolver::class)->resolve($number, '4915551234567', 'Sam');
    $this->travelTo(Carbon::parse('2026-10-01 10:05:00'));

    $runner->handler()->execute(whatsappTurnAdapter($client, $number, '4915551234567', 'wamid.IN1', 'Sam'), 'hi there');

    expect($runner->received())->toBe('hi there');

    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['token'])->toBe('EAAG-secret-token')
        ->and($client->sent[0]['phoneNumberId'])->toBe('111222333444555')
        ->and($client->sent[0]['to'])->toBe('4915551234567')
        ->and($client->sent[0]['markdown'])->toBe('Hello from the agent');

    // Mark-read + "typing…" fires off the inbound wamid the moment it lands.
    expect($client->typing)->not->toBeEmpty()
        ->and($client->typing[0]['messageId'])->toBe('wamid.IN1');

    $chat = WhatsappChat::query()->firstOrFail();
    expect($chat->wa_id)->toBe('4915551234567')
        ->and($chat->name)->toBe('Sam')
        ->and($chat->last_message_at->toDateTimeString())->toBe('2026-10-01 10:05:00');
});
