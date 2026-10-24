<?php

use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Instagram\Jobs\HandleInstagramMessageJob;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Channel\MetaWebhook;

/**
 * The Instagram webhook routes a signed event to the agent by IG account id
 * (entry.id), processes each message once, never answers the account's own
 * messages, and always answers 200. The handshake and the signature check are
 * covered for every Meta channel in InteractsWithMetaWebhooksTest.
 */
beforeEach(function (): void {
    Bus::fake();
    Cache::flush();
    $this->appSecret = 'f1e2d3c4b5a6f1e2d3c4b5a6f1e2d3c4';
    config([
        'services.meta.instagram.app_secret' => $this->appSecret,
    ]);
    $this->account = AgentInstagramAccount::factory()->create(['ig_id' => '17840012345']);
});

/**
 * @param  array<string, mixed>  $message
 * @return array<string, mixed>
 */
function instagramEnvelope(array $message, string $senderId = '5550001234', string $igId = '17840012345'): array
{
    return [
        'object' => 'instagram',
        'entry' => [
            [
                'id' => $igId,
                'time' => 1700000000,
                'messaging' => [
                    [
                        'sender' => ['id' => $senderId],
                        'recipient' => ['id' => $igId],
                        'timestamp' => 1700000000,
                        'message' => $message,
                    ],
                ],
            ],
        ],
    ];
}

it('dispatches a job for a valid, signed message', function (): void {
    MetaWebhook::post(MetaApp::Instagram, instagramEnvelope(['mid' => 'm1', 'text' => 'hello']), $this->appSecret)
        ->assertOk()
        ->assertJson(['ok' => true]);

    Bus::assertDispatched(HandleInstagramMessageJob::class, fn (HandleInstagramMessageJob $job): bool => $job->igsid === '5550001234' && $job->text === 'hello' && $job->account->is($this->account));
});

it('ignores a payload for a different webhook object', function (): void {
    $payload = instagramEnvelope(['mid' => 'm1', 'text' => 'hello']);
    $payload['object'] = 'page';

    MetaWebhook::post(MetaApp::Instagram, $payload, $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleInstagramMessageJob::class);
});

it('answers 200 without dispatching for a message to no enabled account', function (Closure $miss): void {
    MetaWebhook::post(MetaApp::Instagram, instagramEnvelope(['mid' => 'm1', 'text' => 'hello'], igId: $miss->call($this)), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleInstagramMessageJob::class);
})->with([
    'an unknown account' => [fn (): string => '99999999'],
    'a disabled account' => [function (): string {
        $this->account->update(['is_enabled' => false]);

        return '17840012345';
    }],
]);

it('processes each message mid at most once', function (): void {
    MetaWebhook::post(MetaApp::Instagram, instagramEnvelope(['mid' => 'm7', 'text' => 'hello']), $this->appSecret)->assertOk();
    MetaWebhook::post(MetaApp::Instagram, instagramEnvelope(['mid' => 'm7', 'text' => 'hello']), $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleInstagramMessageJob::class, 1);
});

it('ignores echoes of the account’s own replies', function (): void {
    $echo = instagramEnvelope(['mid' => 'm10', 'text' => 'our own reply', 'is_echo' => true]);
    MetaWebhook::post(MetaApp::Instagram, $echo, $this->appSecret)->assertOk();

    $fromAccount = instagramEnvelope(['mid' => 'm11', 'text' => 'also ours'], senderId: '17840012345');
    MetaWebhook::post(MetaApp::Instagram, $fromAccount, $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleInstagramMessageJob::class);
});

it('ignores non-text messages (attachments, story replies)', function (): void {
    MetaWebhook::post(MetaApp::Instagram, instagramEnvelope(['mid' => 'm12', 'attachments' => [['type' => 'story_mention']]]), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleInstagramMessageJob::class);
});

it('handles every message in a batched delivery', function (): void {
    $payload = [
        'object' => 'instagram',
        'entry' => [
            [
                'id' => '17840012345',
                'messaging' => [
                    ['sender' => ['id' => '111'], 'message' => ['mid' => 'm20', 'text' => 'first']],
                    ['sender' => ['id' => '222'], 'message' => ['mid' => 'm21', 'text' => 'second']],
                ],
            ],
        ],
    ];

    MetaWebhook::post(MetaApp::Instagram, $payload, $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleInstagramMessageJob::class, 2);
});
