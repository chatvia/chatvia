<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Messenger\Jobs\HandleMessengerMessageJob;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * The webhook is public and unauthenticated, so its security IS the test
 * surface. With one Chatvia Meta app there's a single app-level Callback URL:
 * GET is Meta's verify handshake (echo hub.challenge only for the app-level
 * verify token); POSTed events must carry a valid X-Hub-Signature-256 HMAC
 * (keyed with the app secret) and are routed to the agent by Page id; messages
 * are processed once; echoes/self messages never dispatch (or the bot would
 * answer itself in a loop); and POST always answers 200.
 */
beforeEach(function (): void {
    Bus::fake();
    Cache::flush();
    $this->verifyToken = Str::random(32);
    $this->appSecret = 'f1e2d3c4b5a6f1e2d3c4b5a6f1e2d3c4';
    config([
        'services.meta.webhook_verify_token' => $this->verifyToken,
        'services.meta.app_secret' => $this->appSecret,
    ]);
    $agent = Agent::factory()->create();
    $this->page = AgentMessengerPage::create([
        'agent_id' => $agent->id,
        'page_access_token' => 'EAAG-page-token-value',
        'page_id' => '1234567890',
        'page_name' => 'Acme Support',
        'enabled' => true,
    ]);
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

function postMessengerEvent(array $payload, ?string $signWith): TestResponse
{
    $headers = [];

    if ($signWith !== null) {
        $body = json_encode($payload);
        $headers = ['X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, $signWith)];
    }

    return test()->postJson(route('messenger.webhook'), $payload, $headers);
}

it('echoes the challenge on a valid GET verify handshake', function (): void {
    $response = $this->get(route('messenger.webhook.verify').'?hub.mode=subscribe&hub.verify_token='.$this->verifyToken.'&hub.challenge=challenge-123');

    $response->assertOk();
    expect($response->getContent())->toBe('challenge-123');
});

it('refuses the GET handshake for a wrong verify token', function (): void {
    $this->get(route('messenger.webhook.verify').'?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=challenge-123')
        ->assertForbidden();
});

it('dispatches a job for a valid, signed message', function (): void {
    postMessengerEvent(messengerEnvelope(['mid' => 'm1', 'text' => 'hello']), $this->appSecret)
        ->assertOk()
        ->assertJson(['ok' => true]);

    Bus::assertDispatched(HandleMessengerMessageJob::class, fn ($job): bool => $job->psid === '24680' && $job->text === 'hello' && $job->page->is($this->page));
});

it('ignores a request with a bad signature', function (): void {
    postMessengerEvent(messengerEnvelope(['mid' => 'm1', 'text' => 'hello']), 'wrong-app-secret-000000000000')->assertOk();

    Bus::assertNotDispatched(HandleMessengerMessageJob::class);
});

it('ignores a request with no signature at all', function (): void {
    postMessengerEvent(messengerEnvelope(['mid' => 'm1', 'text' => 'hello']), null)->assertOk();

    Bus::assertNotDispatched(HandleMessengerMessageJob::class);
});

it('returns 200 for an unknown page id without dispatching', function (): void {
    postMessengerEvent(messengerEnvelope(['mid' => 'm1', 'text' => 'hello'], pageId: '9999999999'), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleMessengerMessageJob::class);
});

it('ignores a disabled page', function (): void {
    $this->page->update(['enabled' => false]);

    postMessengerEvent(messengerEnvelope(['mid' => 'm1', 'text' => 'hello']), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleMessengerMessageJob::class);
});

it('processes each message mid at most once', function (): void {
    postMessengerEvent(messengerEnvelope(['mid' => 'm7', 'text' => 'hello']), $this->appSecret)->assertOk();
    postMessengerEvent(messengerEnvelope(['mid' => 'm7', 'text' => 'hello']), $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleMessengerMessageJob::class, 1);
});

it('ignores echoes of the page’s own replies', function (): void {
    $echo = messengerEnvelope(['mid' => 'm10', 'text' => 'our own reply', 'is_echo' => true]);
    postMessengerEvent($echo, $this->appSecret)->assertOk();

    $fromPage = messengerEnvelope(['mid' => 'm11', 'text' => 'also ours'], senderId: '1234567890');
    postMessengerEvent($fromPage, $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleMessengerMessageJob::class);
});

it('ignores non-text messages (attachments, likes)', function (): void {
    postMessengerEvent(messengerEnvelope(['mid' => 'm12', 'attachments' => [['type' => 'image']]]), $this->appSecret)->assertOk();

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

    postMessengerEvent($payload, $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleMessengerMessageJob::class, 2);
});
