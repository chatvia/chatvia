<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Whatsapp\Jobs\HandleWhatsappMessageJob;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * The webhook is public and unauthenticated, so its security IS the test
 * surface: GET is Meta's verify handshake (echo hub.challenge only for the
 * right verify token); POSTed events must carry a valid X-Hub-Signature-256
 * HMAC; messages are processed once (wamid dedup — Meta re-delivers for up
 * to 7 days); statuses/non-text types never dispatch; cross-app payloads are
 * only re-routed to siblings sharing the same app secret; and POST always
 * answers 200.
 */
beforeEach(function (): void {
    Bus::fake();
    Cache::flush();
    $this->secret = Str::random(48);
    $this->appSecret = 'c1d2e3f4a5b6c1d2e3f4a5b6c1d2e3f4';
    $agent = Agent::factory()->create();
    $this->number = AgentWhatsappNumber::create([
        'agent_id' => $agent->id,
        'access_token' => 'EAAG-wa-token-value',
        'app_secret' => $this->appSecret,
        'phone_number_id' => '111222333444555',
        'waba_id' => '999888777666555',
        'display_phone_number' => '+15551234567',
        'verified_name' => 'Acme Support',
        'webhook_secret' => $this->secret,
        'enabled' => true,
    ]);
});

/**
 * @param  list<array<string, mixed>>  $messages
 * @return array<string, mixed>
 */
function whatsappEnvelope(array $messages, string $phoneNumberId = '111222333444555', array $contacts = []): array
{
    return [
        'object' => 'whatsapp_business_account',
        'entry' => [
            [
                'id' => '999888777666555',
                'changes' => [
                    [
                        'field' => 'messages',
                        'value' => [
                            'messaging_product' => 'whatsapp',
                            'metadata' => ['display_phone_number' => '15551234567', 'phone_number_id' => $phoneNumberId],
                            'contacts' => $contacts ?: [['profile' => ['name' => 'Sam'], 'wa_id' => '4915551234567']],
                            'messages' => $messages,
                        ],
                    ],
                ],
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function whatsappText(string $body = 'hello', string $wamid = 'wamid.AAA1', string $from = '4915551234567'): array
{
    return [
        'from' => $from,
        'id' => $wamid,
        'timestamp' => '1700000000',
        'type' => 'text',
        'text' => ['body' => $body],
    ];
}

function postWhatsappEvent(string $urlSecret, array $payload, ?string $signWith): TestResponse
{
    $headers = [];

    if ($signWith !== null) {
        $body = json_encode($payload);
        $headers = ['X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, $signWith)];
    }

    return test()->postJson(route('whatsapp.webhook', ['secret' => $urlSecret]), $payload, $headers);
}

it('echoes the challenge on a valid GET verify handshake', function (): void {
    $response = $this->get(route('whatsapp.webhook.verify', ['secret' => $this->secret]).'?hub.mode=subscribe&hub.verify_token='.$this->secret.'&hub.challenge=challenge-456');

    $response->assertOk();
    expect($response->getContent())->toBe('challenge-456');
});

it('refuses the GET handshake for a wrong verify token', function (): void {
    $this->get(route('whatsapp.webhook.verify', ['secret' => $this->secret]).'?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=x')
        ->assertForbidden();
});

it('refuses the GET handshake for an unknown secret', function (): void {
    $other = Str::random(48);

    $this->get(route('whatsapp.webhook.verify', ['secret' => $other]).'?hub.mode=subscribe&hub.verify_token='.$other.'&hub.challenge=x')
        ->assertForbidden();
});

it('dispatches a job for a valid, signed message — with the sender name from the payload', function (): void {
    postWhatsappEvent($this->secret, whatsappEnvelope([whatsappText()]), $this->appSecret)
        ->assertOk()
        ->assertJson(['ok' => true]);

    Bus::assertDispatched(HandleWhatsappMessageJob::class, function ($job) {
        return $job->waId === '4915551234567'
            && $job->text === 'hello'
            && $job->messageId === 'wamid.AAA1'
            && $job->name === 'Sam'
            && $job->number->is($this->number);
    });
});

it('ignores a request with a bad signature', function (): void {
    postWhatsappEvent($this->secret, whatsappEnvelope([whatsappText()]), 'wrong-app-secret-000000000000')->assertOk();

    Bus::assertNotDispatched(HandleWhatsappMessageJob::class);
});

it('ignores a request with no signature at all', function (): void {
    postWhatsappEvent($this->secret, whatsappEnvelope([whatsappText()]), null)->assertOk();

    Bus::assertNotDispatched(HandleWhatsappMessageJob::class);
});

it('returns 200 for an unknown number secret without dispatching', function (): void {
    postWhatsappEvent(Str::random(48), whatsappEnvelope([whatsappText()]), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleWhatsappMessageJob::class);
});

it('ignores a disabled number', function (): void {
    $this->number->update(['enabled' => false]);

    postWhatsappEvent($this->secret, whatsappEnvelope([whatsappText()]), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleWhatsappMessageJob::class);
});

it('processes each wamid at most once', function (): void {
    postWhatsappEvent($this->secret, whatsappEnvelope([whatsappText(wamid: 'wamid.DUP')]), $this->appSecret)->assertOk();
    postWhatsappEvent($this->secret, whatsappEnvelope([whatsappText(wamid: 'wamid.DUP')]), $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleWhatsappMessageJob::class, 1);
});

it('ignores status updates (delivery receipts for our own sends)', function (): void {
    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [
            [
                'id' => '999888777666555',
                'changes' => [
                    [
                        'field' => 'messages',
                        'value' => [
                            'messaging_product' => 'whatsapp',
                            'metadata' => ['phone_number_id' => '111222333444555'],
                            'statuses' => [['id' => 'wamid.OUT1', 'status' => 'delivered', 'recipient_id' => '4915551234567']],
                        ],
                    ],
                ],
            ],
        ],
    ];

    postWhatsappEvent($this->secret, $payload, $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleWhatsappMessageJob::class);
});

it('ignores non-text message types (media, reactions)', function (): void {
    $image = ['from' => '4915551234567', 'id' => 'wamid.IMG', 'type' => 'image', 'image' => ['id' => 'media-1']];

    postWhatsappEvent($this->secret, whatsappEnvelope([$image]), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleWhatsappMessageJob::class);
});

it('handles every message in a batched delivery', function (): void {
    $batch = whatsappEnvelope([
        whatsappText('first', 'wamid.B1', '111'),
        whatsappText('second', 'wamid.B2', '222'),
    ]);

    postWhatsappEvent($this->secret, $batch, $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleWhatsappMessageJob::class, 2);
});

it('walks every entry and change in one delivery — not just the first', function (): void {
    $change = fn (string $wamid, string $body): array => [
        'field' => 'messages',
        'value' => [
            'messaging_product' => 'whatsapp',
            'metadata' => ['phone_number_id' => '111222333444555'],
            'messages' => [whatsappText($body, $wamid)],
        ],
    ];

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [
            ['id' => '999888777666555', 'changes' => [$change('wamid.E1C1', 'one'), $change('wamid.E1C2', 'two')]],
            ['id' => '999888777666555', 'changes' => [$change('wamid.E2C1', 'three')]],
        ],
    ];

    postWhatsappEvent($this->secret, $payload, $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleWhatsappMessageJob::class, 3);
});

it('re-routes a sibling number’s events when both share the same Meta app', function (): void {
    $siblingAgent = Agent::factory()->create();
    $sibling = AgentWhatsappNumber::create([
        'agent_id' => $siblingAgent->id,
        'access_token' => 'EAAG-sibling-token',
        'app_secret' => $this->appSecret, // same Meta app
        'phone_number_id' => '777000111222333',
        'waba_id' => '999888777666555',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);

    postWhatsappEvent($this->secret, whatsappEnvelope([whatsappText()], phoneNumberId: '777000111222333'), $this->appSecret)->assertOk();

    Bus::assertDispatched(HandleWhatsappMessageJob::class, fn ($job) => $job->number->is($sibling));
});

it('never re-routes to a number from a different Meta app', function (): void {
    $otherAgent = Agent::factory()->create();
    AgentWhatsappNumber::create([
        'agent_id' => $otherAgent->id,
        'access_token' => 'EAAG-other-token',
        'app_secret' => 'a-completely-different-app-secret',
        'phone_number_id' => '777000111222333',
        'waba_id' => '111000111000111',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);

    postWhatsappEvent($this->secret, whatsappEnvelope([whatsappText()], phoneNumberId: '777000111222333'), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleWhatsappMessageJob::class);
});

it('never re-routes to a disabled sibling', function (): void {
    $siblingAgent = Agent::factory()->create();
    AgentWhatsappNumber::create([
        'agent_id' => $siblingAgent->id,
        'access_token' => 'EAAG-sibling-token',
        'app_secret' => $this->appSecret, // same Meta app, but switched off
        'phone_number_id' => '777000111222333',
        'waba_id' => '999888777666555',
        'webhook_secret' => Str::random(48),
        'enabled' => false,
    ]);

    postWhatsappEvent($this->secret, whatsappEnvelope([whatsappText()], phoneNumberId: '777000111222333'), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleWhatsappMessageJob::class);
});

it('drops events for a phone number id no agent has connected', function (): void {
    postWhatsappEvent($this->secret, whatsappEnvelope([whatsappText()], phoneNumberId: '000000000000000'), $this->appSecret)->assertOk();

    Bus::assertNotDispatched(HandleWhatsappMessageJob::class);
});
