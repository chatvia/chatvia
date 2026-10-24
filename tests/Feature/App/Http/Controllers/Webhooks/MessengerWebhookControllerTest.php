<?php

use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Messenger\Jobs\HandleMessengerMessageJob;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Channel\MetaWebhook;

/**
 * The Messenger webhook routes a signed event to the agent by Page id,
 * processes each message once, never answers the Page's own messages (or the
 * bot would answer itself in a loop), and always answers 200. The handshake
 * and the signature check are covered for every Meta channel in
 * InteractsWithMetaWebhooksTest.
 */
beforeEach(function (): void {
    Bus::fake();
    Cache::flush();
    $this->appSecret = 'f1e2d3c4b5a6f1e2d3c4b5a6f1e2d3c4';
    config([
        'services.meta.messenger.app_secret' => $this->appSecret,
    ]);
    $this->page = AgentMessengerPage::factory()->create(['page_id' => '1234567890']);
});

/**
 * @param  array<string, mixed>  $message
 * @return array<string, mixed>
 */
function messengerEnvelope(array $message, string $senderId = '24680', string $pageId = '1234567890'): array
{
    return [
        'object' => 'page',
        'entry' => [
            [
                'id' => $pageId,
                'time' => 1700000000,
                'messaging' => [
                    [
                        'sender' => ['id' => $senderId],
                        'recipient' => ['id' => $pageId],
                        'timestamp' => 1700000000,
                        'message' => $message,
                    ],
                ],
            ],
        ],
    ];
}

it('dispatches a job for a valid, signed message', function (): void {
    MetaWebhook::post(MetaApp::Messenger, messengerEnvelope(['mid' => 'm1', 'text' => 'hello']), $this->appSecret)
        ->assertOk()
        ->assertJson(['ok' => true]);

    Bus::assertDispatched(HandleMessengerMessageJob::class, fn (HandleMessengerMessageJob $job): bool => $job->psid === '24680' && $job->text === 'hello' && $job->page->is($this->page));
});

it('answers 200 without dispatching for a message to no enabled page', function (Closure $miss): void {
    MetaWebhook::post(MetaApp::Messenger, messengerEnvelope(['mid' => 'm1', 'text' => 'hello'], pageId: $miss->call($this)), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleMessengerMessageJob::class);
})->with([
    'an unknown page' => [fn (): string => '9999999999'],
    'a disabled page' => [function (): string {
        $this->page->update(['is_enabled' => false]);

        return '1234567890';
    }],
]);

it('processes each message mid at most once', function (): void {
    MetaWebhook::post(MetaApp::Messenger, messengerEnvelope(['mid' => 'm7', 'text' => 'hello']), $this->appSecret)->assertOk();
    MetaWebhook::post(MetaApp::Messenger, messengerEnvelope(['mid' => 'm7', 'text' => 'hello']), $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleMessengerMessageJob::class, 1);
});

it('ignores echoes of the page’s own replies', function (): void {
    $echo = messengerEnvelope(['mid' => 'm10', 'text' => 'our own reply', 'is_echo' => true]);
    MetaWebhook::post(MetaApp::Messenger, $echo, $this->appSecret)->assertOk();

    $fromPage = messengerEnvelope(['mid' => 'm11', 'text' => 'also ours'], senderId: '1234567890');
    MetaWebhook::post(MetaApp::Messenger, $fromPage, $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleMessengerMessageJob::class);
});

it('ignores non-text messages (attachments, likes)', function (): void {
    MetaWebhook::post(MetaApp::Messenger, messengerEnvelope(['mid' => 'm12', 'attachments' => [['type' => 'image']]]), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleMessengerMessageJob::class);
});

it('handles every message in a batched delivery', function (): void {
    $payload = [
        'object' => 'page',
        'entry' => [
            [
                'id' => '1234567890',
                'messaging' => [
                    ['sender' => ['id' => '111'], 'message' => ['mid' => 'm20', 'text' => 'first']],
                    ['sender' => ['id' => '222'], 'message' => ['mid' => 'm21', 'text' => 'second']],
                ],
            ],
        ],
    ];

    MetaWebhook::post(MetaApp::Messenger, $payload, $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleMessengerMessageJob::class, 2);
});
