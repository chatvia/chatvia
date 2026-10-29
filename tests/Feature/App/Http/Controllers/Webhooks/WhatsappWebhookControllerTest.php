<?php

use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Whatsapp\Jobs\HandleWhatsappMessageJob;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Channel\MetaWebhook;

/**
 * The WhatsApp webhook routes a signed event to the agent by the phone number
 * id that received it, processes each message once (Meta re-delivers for up
 * to 7 days) and walks every entry and change of a delivery, and always
 * answers 200. The handshake and the signature check are covered for every Meta
 * channel in InteractsWithMetaWebhooksTest.
 */
beforeEach(function (): void {
    Bus::fake();
    Cache::flush();
    $this->appSecret = 'c1d2e3f4a5b6c1d2e3f4a5b6c1d2e3f4';
    config([
        'services.meta.whatsapp.app_secret' => $this->appSecret,
    ]);
    $this->number = AgentWhatsappNumber::factory()->create(['phone_number_id' => '111222333444555']);
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

it('dispatches a job for a valid, signed message with the sender name from the payload, once per wamid', function (): void {
    MetaWebhook::post(MetaApp::Whatsapp, whatsappEnvelope([whatsappText()]), $this->appSecret)
        ->assertOk()
        ->assertJson(['ok' => true]);
    MetaWebhook::post(MetaApp::Whatsapp, whatsappEnvelope([whatsappText()]), $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleWhatsappMessageJob::class, 1);

    Bus::assertDispatched(HandleWhatsappMessageJob::class, fn (HandleWhatsappMessageJob $job): bool => $job->waId === '4915551234567'
        && $job->text === 'hello'
        && $job->messageId === 'wamid.AAA1'
        && $job->name === 'Sam'
        && $job->number->is($this->number));
});

it('walks every entry and change in one delivery, not just the first', function (): void {
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

    MetaWebhook::post(MetaApp::Whatsapp, $payload, $this->appSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleWhatsappMessageJob::class, 3);
});
