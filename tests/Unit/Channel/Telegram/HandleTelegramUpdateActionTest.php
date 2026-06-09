<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Actions\HandleTelegramUpdateAction;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Models\TelegramChat;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Channel\Telegram\Services\TelegramReplyRunner;
use Illuminate\Support\Str;
use Tests\Support\Telegram\FakeTelegramClient;

/**
 * The handle Action wires the inbound message end to end: resolve the chat, run
 * the agent for a reply, send it back, and stamp the chat. The pipeline (real
 * LLM) is stubbed here — this asserts the wiring, not the model's answer.
 */
function handleBot(bool $public = true): AgentTelegramBot
{
    $agent = Agent::factory()->create(['is_public' => $public]);

    return AgentTelegramBot::create([
        'agent_id' => $agent->id,
        'bot_token' => '123:secret-token',
        'bot_username' => 'demo_bot',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
}

function stubReplyRunner(string $reply): TelegramReplyRunner
{
    return new class($reply) extends TelegramReplyRunner
    {
        public function __construct(private string $canned) {}

        public function reply(TelegramChat $chat, string $message): string
        {
            return $this->canned;
        }
    };
}

it('resolves the chat, sends the agent reply, and stamps last_message_at', function (): void {
    $bot = handleBot();
    $client = new FakeTelegramClient;

    $action = new HandleTelegramUpdateAction(
        app(TelegramChatResolver::class),
        stubReplyRunner('Hello from the agent'),
        $client,
    );

    $action->execute($bot, '555', 'hi there', ['first_name' => 'Sam']);

    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['token'])->toBe('123:secret-token')
        ->and($client->sent[0]['chatId'])->toBe('555')
        ->and($client->sent[0]['text'])->toBe('Hello from the agent');

    // A "typing…" indicator fires the moment the message lands.
    expect($client->actions)->not->toBeEmpty()
        ->and($client->actions[0]['action'])->toBe('typing')
        ->and($client->actions[0]['chatId'])->toBe('555');

    $chat = TelegramChat::query()->firstOrFail();
    expect($chat->chat_id)->toBe('555')
        ->and($chat->last_message_at)->not->toBeNull();
});

it('keeps one chat mapping across multiple inbound messages', function (): void {
    $bot = handleBot();
    $client = new FakeTelegramClient;

    $action = new HandleTelegramUpdateAction(
        app(TelegramChatResolver::class),
        stubReplyRunner('ok'),
        $client,
    );

    $action->execute($bot, '900', 'first', ['first_name' => 'Ann']);
    $action->execute($bot, '900', 'second', ['first_name' => 'Ann']);

    expect(TelegramChat::query()->count())->toBe(1);
    expect($client->sent)->toHaveCount(2);
});

it('refuses to answer on a protected agent and creates no chat', function (): void {
    $bot = handleBot(public: false);
    $client = new FakeTelegramClient;

    $action = new HandleTelegramUpdateAction(
        app(TelegramChatResolver::class),
        stubReplyRunner('should never be sent'),
        $client,
    );

    $action->execute($bot, '555', 'hello', ['first_name' => 'Sam']);

    // Gated before the pipeline: a single "unavailable" notice, no chat row.
    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['text'])->toContain('available')
        ->and($client->sent[0]['text'])->not->toBe('should never be sent');

    expect(TelegramChat::query()->count())->toBe(0);
});
