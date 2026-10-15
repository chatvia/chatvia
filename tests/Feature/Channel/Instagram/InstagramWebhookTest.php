<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Jobs\HandleInstagramMessageJob;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * The webhook is public and unauthenticated, so its security IS the test
 * surface. With one Chatvia Meta app there's a single app-level Callback URL:
 * GET is Meta's verify handshake (echo hub.challenge only for the app-level
 * verify token); POSTed events must carry a valid X-Hub-Signature-256 HMAC
 * (keyed with the app secret) and are routed to the agent by IG account id
 * (entry.id); messages are processed once; echoes/self messages never dispatch;
 * and POST always answers 200.
 */
beforeEach(function (): void {
    Bus::fake();
    Cache::flush();
    $this->verifyToken = Str::random(32);
    $this->appSecret = 'f1e2d3c4b5a6f1e2d3c4b5a6f1e2d3c4';
    config([
        'services.meta.instagram.webhook_verify_token' => $this->verifyToken,
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

function postInstagramEvent(array $payload, ?string $signWith): TestResponse
{
    $headers = [];

    if ($signWith !== null) {
        $body = json_encode($payload);
        $headers = ['X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, $signWith)];
    }

    return test()->postJson(route('instagram.webhook'), $payload, $headers);
}

it('echoes the challenge on a valid GET verify handshake', function (): void {
    $response = $this->get(route('instagram.webhook.verify').'?hub.mode=subscribe&hub.verify_token='.$this->verifyToken.'&hub.challenge=challenge-123');

    $response->assertOk();
    expect($response->getContent())->toBe('challenge-123');
});

it('refuses the GET handshake for a wrong verify token', function (): void {
    $this->get(route('instagram.webhook.verify').'?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=challenge-123')
        ->assertForbidden();
});

it('dispatches a job for a valid, signed message', function (): void {
    postInstagramEvent(instagramEnvelope(['mid' => 'm1', 'text' => 'hello']), $this->appSecret)
        ->assertOk()
        ->assertJson(['ok' => true]);

    Bus::assertDispatched(HandleInstagramMessageJob::class, fn (HandleInstagramMessageJob $job): bool => $job->igsid === '5550001234' && $job->text === 'hello' && $job->account->is($this->account));
});

it('ignores a request with a bad signature', function (): void {
    postInstagramEvent(instagramEnvelope(['mid' => 'm1', 'text' => 'hello']), 'wrong-app-secret-000000000000')->assertOk();

    Bus::assertNotDispatched(HandleInstagramMessageJob::class);
});

it('trusts only the Instagram app, not the Messenger app', function (): void {
    config([
        'services.meta.messenger.app_secret' => 'messenger-app-secret-0000000000',
        'services.meta.messenger.webhook_verify_token' => 'messenger-verify-token',
    ]);

    postInstagramEvent(instagramEnvelope(['mid' => 'm1', 'text' => 'hello']), 'messenger-app-secret-0000000000')->assertOk();
    Bus::assertNotDispatched(HandleInstagramMessageJob::class);

    $this->get(route('instagram.webhook.verify').'?hub.mode=subscribe&hub.verify_token=messenger-verify-token&hub.challenge=challenge-123')
        ->assertForbidden();
});

it('ignores a request with no signature at all', function (): void {
    postInstagramEvent(instagramEnvelope(['mid' => 'm1', 'text' => 'hello']), null)->assertOk();

    Bus::assertNotDispatched(HandleInstagramMessageJob::class);
});

it('ignores a payload for a different webhook object', function (): void {
    $payload = instagramEnvelope(['mid' => 'm1', 'text' => 'hello']);
    $payload['object'] = 'page';

    postInstagramEvent($payload, $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleInstagramMessageJob::class);
});

it('returns 200 for an unknown IG account id without dispatching', function (): void {
    postInstagramEvent(instagramEnvelope(['mid' => 'm1', 'text' => 'hello'], igId: '99999999'), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleInstagramMessageJob::class);
});

it('ignores a disabled account', function (): void {
    $this->account->update(['enabled' => false]);

    postInstagramEvent(instagramEnvelope(['mid' => 'm1', 'text' => 'hello']), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleInstagramMessageJob::class);
});

it('processes each message mid at most once', function (): void {
    postInstagramEvent(instagramEnvelope(['mid' => 'm7', 'text' => 'hello']), $this->appSecret)->assertOk();
    postInstagramEvent(instagramEnvelope(['mid' => 'm7', 'text' => 'hello']), $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleInstagramMessageJob::class, 1);
});

it('ignores echoes of the account’s own replies', function (): void {
    $echo = instagramEnvelope(['mid' => 'm10', 'text' => 'our own reply', 'is_echo' => true]);
    postInstagramEvent($echo, $this->appSecret)->assertOk();

    $fromAccount = instagramEnvelope(['mid' => 'm11', 'text' => 'also ours'], senderId: '17840012345');
    postInstagramEvent($fromAccount, $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleInstagramMessageJob::class);
});

it('ignores non-text messages (attachments, story replies)', function (): void {
    postInstagramEvent(instagramEnvelope(['mid' => 'm12', 'attachments' => [['type' => 'story_mention']]]), $this->appSecret)->assertOk();

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

    postInstagramEvent($payload, $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleInstagramMessageJob::class, 2);
});
