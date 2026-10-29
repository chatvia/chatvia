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

it('dispatches a job for a valid, signed message, once per message mid', function (): void {
    MetaWebhook::post(MetaApp::Messenger, messengerEnvelope(['mid' => 'm1', 'text' => 'hello']), $this->appSecret)
        ->assertOk()
        ->assertJson(['ok' => true]);
    MetaWebhook::post(MetaApp::Messenger, messengerEnvelope(['mid' => 'm1', 'text' => 'hello']), $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleMessengerMessageJob::class, 1);
    Bus::assertDispatched(HandleMessengerMessageJob::class, fn (HandleMessengerMessageJob $job): bool => $job->psid === '24680' && $job->text === 'hello' && $job->page->is($this->page));
});

it('answers 200 without dispatching for no enabled page or the page’s own replies', function (): void {
    MetaWebhook::post(MetaApp::Messenger, messengerEnvelope(['mid' => 'm2', 'text' => 'hello'], pageId: '9999999999'), $this->appSecret)->assertOk();

    $echo = messengerEnvelope(['mid' => 'm10', 'text' => 'our own reply', 'is_echo' => true]);
    MetaWebhook::post(MetaApp::Messenger, $echo, $this->appSecret)->assertOk();

    $fromPage = messengerEnvelope(['mid' => 'm11', 'text' => 'also ours'], senderId: '1234567890');
    MetaWebhook::post(MetaApp::Messenger, $fromPage, $this->appSecret)->assertOk();

    $this->page->update(['is_enabled' => false]);
    MetaWebhook::post(MetaApp::Messenger, messengerEnvelope(['mid' => 'm3', 'text' => 'hello']), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleMessengerMessageJob::class);
});
