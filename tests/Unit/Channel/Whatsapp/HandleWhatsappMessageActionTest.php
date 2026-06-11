<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Services\ChannelAccessGate;
use Domain\Channel\Whatsapp\Actions\HandleWhatsappMessageAction;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Models\WhatsappChat;
use Domain\Channel\Whatsapp\Services\WhatsappChatResolver;
use Domain\Channel\Whatsapp\Services\WhatsappReplyRunner;
use Illuminate\Support\Str;
use Tests\Support\Whatsapp\FakeWhatsappClient;

/**
 * The handle Action wires the inbound message end to end: resolve the chat,
 * mark-read + typing off the inbound wamid, run the agent for a reply, send
 * it back, and stamp the chat. The pipeline (real LLM) is stubbed here —
 * this asserts the wiring, not the model's answer.
 */
function whatsappHandleNumber(bool $public = true): AgentWhatsappNumber
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

function stubWhatsappReplyRunner(string $reply): WhatsappReplyRunner
{
    return new class($reply) extends WhatsappReplyRunner
    {
        public ?string $received = null;

        public ?string $receivedMessageId = null;

        public function __construct(private string $canned) {}

        public function reply(WhatsappChat $chat, string $message, string $inboundMessageId): string
        {
            $this->received = $message;
            $this->receivedMessageId = $inboundMessageId;

            return $this->canned;
        }
    };
}

function whatsappHandleAction(FakeWhatsappClient $client, WhatsappReplyRunner $runner): HandleWhatsappMessageAction
{
    return new HandleWhatsappMessageAction(
        app(WhatsappChatResolver::class),
        $runner,
        $client,
        new ChannelAccessGate,
    );
}

it('resolves the chat, sends the agent reply, and stamps last_message_at', function (): void {
    $number = whatsappHandleNumber();
    $client = new FakeWhatsappClient;
    $runner = stubWhatsappReplyRunner('Hello from the agent');

    whatsappHandleAction($client, $runner)
        ->execute($number, '4915551234567', 'hi there', 'wamid.IN1', 'Sam');

    // The pipeline gets the user's text and the wamid (for typing refreshes).
    expect($runner->received)->toBe('hi there')
        ->and($runner->receivedMessageId)->toBe('wamid.IN1');

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
    $number = whatsappHandleNumber();
    $client = new FakeWhatsappClient;
    $action = whatsappHandleAction($client, stubWhatsappReplyRunner('ok'));

    $action->execute($number, '900', 'first', 'wamid.1');
    $action->execute($number, '900', 'second', 'wamid.2');

    expect(WhatsappChat::query()->count())->toBe(1);
    expect($client->sent)->toHaveCount(2);
});

it('refuses to answer on a protected agent and creates no chat', function (): void {
    $number = whatsappHandleNumber(public: false);
    $client = new FakeWhatsappClient;
    $runner = stubWhatsappReplyRunner('should never be sent');

    whatsappHandleAction($client, $runner)->execute($number, '4915551234567', 'hello', 'wamid.IN2');

    // Gated before the pipeline: a single "unavailable" notice, no chat row.
    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['markdown'])->toContain('available')
        ->and($client->sent[0]['markdown'])->not->toBe('should never be sent');
    expect($client->typing)->toBeEmpty();

    expect(WhatsappChat::query()->count())->toBe(0);
});
