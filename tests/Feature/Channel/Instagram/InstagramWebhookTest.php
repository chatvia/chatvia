<?php

use Domain\Agent\Models\Agent;
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
 * covered for every Meta channel in MetaWebhookTest.
 */
beforeEach(function (): void {
    Bus::fake();
    Cache::flush();
    $this->appSecret = 'f1e2d3c4b5a6f1e2d3c4b5a6f1e2d3c4';
    config([
        'services.meta.instagram.app_secret' => $this->appSecret,
    ]);
    $agent = Agent::factory()->create();
    $this->account = AgentInstagramAccount::create([
        'agent_id' => $agent->id,
        'page_access_token' => 'EAAG-page-token-value',
        'page_id' => '998877',
        'ig_id' => '17840012345',
        'username' => 'acme.support',
        'enabled' => true,
    ]);
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

it('trusts only the Instagram app, not the Messenger app', function (): void {
    config([
        'services.meta.messenger.app_secret' => 'messenger-app-secret-0000000000',
        'services.meta.messenger.webhook_verify_token' => 'messenger-verify-token',
    ]);

    MetaWebhook::post(MetaApp::Instagram, instagramEnvelope(['mid' => 'm1', 'text' => 'hello']), 'messenger-app-secret-0000000000')->assertOk();
    Bus::assertNotDispatched(HandleInstagramMessageJob::class);

    $this->get(route('instagram.webhook.verify').'?hub.mode=subscribe&hub.verify_token=messenger-verify-token&hub.challenge=challenge-123')
        ->assertForbidden();
});

it('ignores a payload for a different webhook object', function (): void {
    $payload = instagramEnvelope(['mid' => 'm1', 'text' => 'hello']);
    $payload['object'] = 'page';

    MetaWebhook::post(MetaApp::Instagram, $payload, $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleInstagramMessageJob::class);
});

it('returns 200 for an unknown IG account id without dispatching', function (): void {
    MetaWebhook::post(MetaApp::Instagram, instagramEnvelope(['mid' => 'm1', 'text' => 'hello'], igId: '99999999'), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleInstagramMessageJob::class);
});

it('ignores a disabled account', function (): void {
    $this->account->update(['enabled' => false]);

    MetaWebhook::post(MetaApp::Instagram, instagramEnvelope(['mid' => 'm1', 'text' => 'hello']), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleInstagramMessageJob::class);
});

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
