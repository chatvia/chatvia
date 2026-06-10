<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Whatsapp\Actions\ConnectWhatsappNumberAction;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Tests\Support\Whatsapp\FakeWhatsappClient;

/**
 * Connect is the only write in the WhatsApp channel that carries real
 * behaviour: it validates the token + phone number id with the Graph API,
 * stores them (and the app secret) encrypted with a fresh webhook secret,
 * and subscribes the Meta app to the WABA — and must reject bad credentials
 * without writing.
 */
it('connects a number, stores its secrets encrypted, and subscribes the app to the WABA', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeWhatsappClient;

    $number = (new ConnectWhatsappNumberAction($client))->execute(
        $agent,
        'EAAG-wa-token-value-here-xxxx',
        'app-secret-value-1234567890',
        '111222333444555',
        '999888777666555',
    );

    expect($number)->not->toBeNull();

    $row = AgentWhatsappNumber::query()->firstOrFail();

    expect($row->agent_id)->toBe($agent->id)
        ->and($row->phone_number_id)->toBe('111222333444555')
        ->and($row->waba_id)->toBe('999888777666555')
        ->and($row->display_phone_number)->toBe('+15551234567')
        ->and($row->verified_name)->toBe('Acme Support')
        ->and($row->enabled)->toBeTrue()
        ->and(strlen($row->webhook_secret))->toBe(48)
        // Encrypted casts round-trip to the original values.
        ->and($row->access_token)->toBe('EAAG-wa-token-value-here-xxxx')
        ->and($row->app_secret)->toBe('app-secret-value-1234567890')
        // The stored ciphertext is not the plaintext.
        ->and($row->getRawOriginal('access_token'))->not->toBe('EAAG-wa-token-value-here-xxxx')
        ->and($row->getRawOriginal('app_secret'))->not->toBe('app-secret-value-1234567890');

    expect($client->subscriptions)->toHaveCount(1)
        ->and($client->subscriptions[0]['wabaId'])->toBe('999888777666555');
});

it('returns null and stores nothing when Meta rejects the credentials', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeWhatsappClient;
    $client->number = null; // Meta: invalid token or phone number id.

    $number = (new ConnectWhatsappNumberAction($client))->execute($agent, 'EAAG-bogus-token-value-xxxx', 'app-secret-value-1234567890', '111', '999');

    expect($number)->toBeNull();
    expect(AgentWhatsappNumber::query()->count())->toBe(0);
    expect($client->subscriptions)->toBeEmpty();
});

it('reconnects in place — one number row per agent, secrets rotated', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeWhatsappClient;
    $action = new ConnectWhatsappNumberAction($client);

    $action->execute($agent, 'EAAG-first-token-aaaaaaaaaaaa', 'first-app-secret-aaaaaaaaaaaa', '111222333444555', '999888777666555');
    $first = AgentWhatsappNumber::query()->firstOrFail();

    $action->execute($agent, 'EAAG-second-token-bbbbbbbbbb', 'second-app-secret-bbbbbbbbbb', '111222333444555', '999888777666555');

    expect(AgentWhatsappNumber::query()->count())->toBe(1);

    $row = AgentWhatsappNumber::query()->firstOrFail();
    expect($row->id)->toBe($first->id)
        ->and($row->access_token)->toBe('EAAG-second-token-bbbbbbbbbb')
        ->and($row->app_secret)->toBe('second-app-secret-bbbbbbbbbb')
        ->and($row->webhook_secret)->not->toBe($first->webhook_secret);
});
