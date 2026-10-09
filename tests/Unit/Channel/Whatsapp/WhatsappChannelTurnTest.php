<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Models\WhatsappChat;
use Domain\Channel\Whatsapp\Services\WhatsappAdapter;
use Domain\Channel\Whatsapp\Services\WhatsappChatResolver;
use Illuminate\Support\Str;
use Tests\Support\Channel\StubChannelReplyRunner;
use Tests\Support\Whatsapp\FakeWhatsappClient;

/**
 * A WhatsApp turn through {@see HandleChannelMessageAction} with the
 * {@see WhatsappAdapter}: resolve the chat, mark-read + typing off the inbound
 * wamid, run the agent for a reply, send it back, and stamp the chat. The
 * pipeline (real LLM) is stubbed here — this asserts the wiring, not the
 * model's answer.
 */
function whatsappTurnNumber(bool $public = true): AgentWhatsappNumber
{
    $agent = Agent::factory()->create(['is_public' => $public]);

    return AgentWhatsappNumber::create([
        'agent_id' => $agent->id,
        'access_token' => 'EAAG-secret-token',
        'app_secret' => 'app-secret-value-1234567890',
        'phone_number_id' => '111222333444555',
        'waba_id' => '999888777666555',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
}

function whatsappTurnAdapter(FakeWhatsappClient $client, AgentWhatsappNumber $number, string $waId, string $wamid, ?string $name = null): WhatsappAdapter
{
    return new WhatsappAdapter($client, app(WhatsappChatResolver::class), $number, $waId, $wamid, $name);
}

it('resolves the chat, sends the agent reply, and stamps last_message_at', function (): void {
    $number = whatsappTurnNumber();
    $client = new FakeWhatsappClient;
    $runner = new StubChannelReplyRunner('Hello from the agent');

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
        ->and($chat->last_message_at)->not->toBeNull();
});

it('keeps one chat mapping across multiple inbound messages', function (): void {
    $number = whatsappTurnNumber();
    $client = new FakeWhatsappClient;
    $action = (new StubChannelReplyRunner('ok'))->handler();

    $action->execute(whatsappTurnAdapter($client, $number, '900', 'wamid.1'), 'first');
    $action->execute(whatsappTurnAdapter($client, $number, '900', 'wamid.2'), 'second');

    expect(WhatsappChat::query()->count())->toBe(1);
    expect($client->sent)->toHaveCount(2);
});

it('refuses to answer on a protected agent and creates no chat', function (): void {
    $number = whatsappTurnNumber(public: false);
    $client = new FakeWhatsappClient;
    $runner = new StubChannelReplyRunner('should never be sent');

    $runner->handler()->execute(whatsappTurnAdapter($client, $number, '4915551234567', 'wamid.IN2'), 'hello');

    // Gated before the pipeline: a single "unavailable" notice, no chat row.
    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['markdown'])->toContain('available on WhatsApp')
        ->and($client->sent[0]['markdown'])->not->toBe('should never be sent');
    expect($client->typing)->toBeEmpty();

    expect(WhatsappChat::query()->count())->toBe(0);
});
