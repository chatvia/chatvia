<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Jobs\HandleTelegramUpdateJob;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * The webhook is public and unauthenticated, so its security IS the test surface:
 * the {secret} segment routes to the agent AND must match the echoed
 * X-Telegram-Bot-Api-Secret-Token header; updates are processed once; and it
 * always answers 200 (a non-2xx makes Telegram retry, even on a spoofed call).
 */
beforeEach(function (): void {
    Bus::fake();
    Cache::flush();
    $this->secret = Str::random(48);
    $agent = Agent::factory()->create();
    $this->bot = AgentTelegramBot::create([
        'agent_id' => $agent->id,
        'bot_token' => '123:token',
        'bot_username' => 'demo_bot',
        'webhook_secret' => $this->secret,
        'enabled' => true,
    ]);
});

function tgUpdate(int $updateId = 1, string $text = 'hello'): array
{
    return [
        'update_id' => $updateId,
        'message' => [
            'message_id' => 10,
            'text' => $text,
            'chat' => ['id' => 555],
            'from' => ['first_name' => 'Sam', 'username' => 'sam_t'],
        ],
    ];
}

function postUpdate(string $urlSecret, ?string $headerSecret, array $payload): TestResponse
{
    $headers = $headerSecret !== null ? ['X-Telegram-Bot-Api-Secret-Token' => $headerSecret] : [];

    return test()->postJson(route('telegram.webhook', ['secret' => $urlSecret]), $payload, $headers);
}

it('dispatches a job for a valid, authenticated update', function (): void {
    postUpdate($this->secret, $this->secret, tgUpdate())
        ->assertOk()
        ->assertJson(['ok' => true]);

    Bus::assertDispatched(HandleTelegramUpdateJob::class, function ($job) {
        return $job->chatId === '555' && $job->text === 'hello' && $job->bot->is($this->bot);
    });
});

it('ignores an update whose header secret does not match', function (): void {
    postUpdate($this->secret, 'wrong-secret', tgUpdate())->assertOk();

    Bus::assertNotDispatched(HandleTelegramUpdateJob::class);
});

it('ignores an update with no header secret at all', function (): void {
    postUpdate($this->secret, null, tgUpdate())->assertOk();

    Bus::assertNotDispatched(HandleTelegramUpdateJob::class);
});

it('returns 200 for an unknown bot secret without dispatching', function (): void {
    postUpdate(Str::random(48), Str::random(48), tgUpdate())->assertOk();

    Bus::assertNotDispatched(HandleTelegramUpdateJob::class);
});

it('ignores a disabled bot', function (): void {
    $this->bot->update(['enabled' => false]);

    postUpdate($this->secret, $this->secret, tgUpdate())->assertOk();

    Bus::assertNotDispatched(HandleTelegramUpdateJob::class);
});

it('processes each update_id at most once', function (): void {
    postUpdate($this->secret, $this->secret, tgUpdate(updateId: 7))->assertOk();
    postUpdate($this->secret, $this->secret, tgUpdate(updateId: 7))->assertOk();

    Bus::assertDispatchedTimes(HandleTelegramUpdateJob::class, 1);
});

it('does not dispatch for a non-text message', function (): void {
    $payload = ['update_id' => 3, 'message' => ['chat' => ['id' => 555]]];

    postUpdate($this->secret, $this->secret, $payload)->assertOk();

    Bus::assertNotDispatched(HandleTelegramUpdateJob::class);
});
