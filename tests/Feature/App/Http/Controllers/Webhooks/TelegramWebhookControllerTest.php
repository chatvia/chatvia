<?php

use Domain\Channel\Telegram\Jobs\HandleTelegramUpdateJob;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * The webhook is public and unauthenticated, so its security IS the test surface:
 * the {secret} segment routes to the agent, and the
 * X-Telegram-Bot-Api-Secret-Token header must carry the bot's separate header
 * secret (the URL alone is not enough); updates are processed once; and it
 * always answers 200 (a non-2xx makes Telegram retry, even on a spoofed call).
 */
beforeEach(function (): void {
    Bus::fake();
    Cache::flush();
    $this->secret = Str::random(48);
    $this->headerSecret = Str::random(64);
    $this->bot = AgentTelegramBot::factory()->create(['webhook_secret' => $this->secret, 'header_secret' => $this->headerSecret]);
});

/**
 * @return array<string, mixed>
 */
function twUpdate(int $updateId = 1, string $text = 'hello'): array
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

/**
 * @param  array<string, mixed>  $payload
 */
function twPost(string $urlSecret, ?string $headerSecret, array $payload): TestResponse
{
    $headers = $headerSecret !== null ? ['X-Telegram-Bot-Api-Secret-Token' => $headerSecret] : [];

    return test()->postJson(route('telegram.webhook', ['secret' => $urlSecret]), $payload, $headers);
}

it('dispatches a job for a valid, authenticated update', function (): void {
    twPost($this->secret, $this->headerSecret, twUpdate())
        ->assertOk()
        ->assertJson(['ok' => true]);

    Bus::assertDispatched(HandleTelegramUpdateJob::class, fn (HandleTelegramUpdateJob $job): bool => $job->chatId === '555' && $job->text === 'hello' && $job->bot->is($this->bot) && jobQueue($job) === 'messages');
});

it('ignores an update whose header does not carry the header secret', function (Closure $headerSecret): void {
    twPost($this->secret, $headerSecret->call($this), twUpdate())->assertOk();

    Bus::assertNotDispatched(HandleTelegramUpdateJob::class);
})->with([
    'another secret' => [fn (): string => 'wrong-secret'],
    'the secret from the URL' => [fn (): string => $this->secret],
    'no header at all' => [fn (): ?string => null],
]);

it('answers 200 without dispatching when no enabled bot has the webhook secret', function (Closure $miss): void {
    $secret = $miss->call($this);

    twPost($secret, $this->headerSecret, twUpdate())->assertOk();

    Bus::assertNotDispatched(HandleTelegramUpdateJob::class);
})->with([
    'an unknown secret' => [fn (): string => Str::random(48)],
    'a disabled bot' => [function (): string {
        $this->bot->update(['is_enabled' => false]);

        return $this->secret;
    }],
]);

it('processes each update_id at most once', function (): void {
    twPost($this->secret, $this->headerSecret, twUpdate(updateId: 7))->assertOk();
    twPost($this->secret, $this->headerSecret, twUpdate(updateId: 7))->assertOk();

    Bus::assertDispatchedTimes(HandleTelegramUpdateJob::class, 1);
});

it('does not dispatch for a non-text message', function (): void {
    $payload = ['update_id' => 3, 'message' => ['chat' => ['id' => 555]]];

    twPost($this->secret, $this->headerSecret, $payload)->assertOk();

    Bus::assertNotDispatched(HandleTelegramUpdateJob::class);
});
