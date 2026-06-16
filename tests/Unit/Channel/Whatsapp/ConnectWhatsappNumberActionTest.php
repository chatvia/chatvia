<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Services\MetaOAuthClient;
use Domain\Channel\Whatsapp\Actions\ConnectWhatsappNumberAction;
use Domain\Channel\Whatsapp\Data\ConnectWhatsappData;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Tests\Support\Whatsapp\FakeWhatsappClient;

/**
 * Connect is the only write in the WhatsApp channel that carries real
 * behaviour: it exchanges the Embedded Signup code for a business token,
 * validates the number, subscribes the WABA + registers the number, and stores
 * the token (encrypted) — and must reject a bad code/number without writing.
 */
function fakeWhatsappOauth(?string $token = 'EAAG-business-token-value-xxxx'): MetaOAuthClient
{
    return new class($token) extends MetaOAuthClient
    {
        public function __construct(private ?string $token) {}

        public function exchangeEmbeddedSignupCode(string $code): ?string
        {
            return $this->token;
        }
    };
}

beforeEach(function (): void {
    config(['services.meta.whatsapp_register_pin' => '123456']);
});

it('connects a number: exchanges the code, subscribes the WABA, registers the number, stores the token encrypted', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeWhatsappClient;

    $number = (new ConnectWhatsappNumberAction(fakeWhatsappOauth(), $client))->execute(
        $agent,
        new ConnectWhatsappData(code: 'ES-CODE', wabaId: '999888777666555', phoneNumberId: '111222333444555'),
    );

    expect($number)->not->toBeNull();

    $row = AgentWhatsappNumber::query()->firstOrFail();

    expect($row->agent_id)->toBe($agent->id)
        ->and($row->phone_number_id)->toBe('111222333444555')
        ->and($row->waba_id)->toBe('999888777666555')
        ->and($row->display_phone_number)->toBe('+15551234567')
        ->and($row->verified_name)->toBe('Acme Support')
        ->and($row->enabled)->toBeTrue()
        // The legacy BYO secrets are no longer collected.
        ->and($row->app_secret)->toBeNull()
        ->and($row->webhook_secret)->toBeNull()
        // The business token round-trips through the encrypted cast.
        ->and($row->access_token)->toBe('EAAG-business-token-value-xxxx')
        ->and($row->getRawOriginal('access_token'))->not->toBe('EAAG-business-token-value-xxxx');

    expect($client->subscriptions)->toHaveCount(1)
        ->and($client->subscriptions[0]['wabaId'])->toBe('999888777666555');

    expect($client->registrations)->toHaveCount(1)
        ->and($client->registrations[0]['phoneNumberId'])->toBe('111222333444555')
        ->and($client->registrations[0]['pin'])->toBe('123456');
});

it('returns null and stores nothing when the code cannot be exchanged', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeWhatsappClient;

    $number = (new ConnectWhatsappNumberAction(fakeWhatsappOauth(token: null), $client))->execute(
        $agent,
        new ConnectWhatsappData(code: 'BAD', wabaId: '999', phoneNumberId: '111'),
    );

    expect($number)->toBeNull();
    expect(AgentWhatsappNumber::query()->count())->toBe(0);
    expect($client->subscriptions)->toBeEmpty()
        ->and($client->registrations)->toBeEmpty();
});

it('returns null when Meta rejects the number after a valid code exchange', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeWhatsappClient;
    $client->number = null; // numberInfo: invalid phone number id

    $number = (new ConnectWhatsappNumberAction(fakeWhatsappOauth(), $client))->execute(
        $agent,
        new ConnectWhatsappData(code: 'ES-CODE', wabaId: '999', phoneNumberId: '111'),
    );

    expect($number)->toBeNull();
    expect(AgentWhatsappNumber::query()->count())->toBe(0);
});

it('skips number registration when no PIN is configured', function (): void {
    config(['services.meta.whatsapp_register_pin' => null]);

    $agent = Agent::factory()->create();
    $client = new FakeWhatsappClient;

    (new ConnectWhatsappNumberAction(fakeWhatsappOauth(), $client))->execute(
        $agent,
        new ConnectWhatsappData(code: 'ES-CODE', wabaId: '999888777666555', phoneNumberId: '111222333444555'),
    );

    expect($client->registrations)->toBeEmpty()
        ->and($client->subscriptions)->toHaveCount(1);
    expect(AgentWhatsappNumber::query()->count())->toBe(1);
});

it('reconnects in place — one number row per agent, token rotated', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeWhatsappClient;

    (new ConnectWhatsappNumberAction(fakeWhatsappOauth('EAAG-first-token-aaaaaaaaaaaa'), $client))
        ->execute($agent, new ConnectWhatsappData(code: 'C1', wabaId: '999888777666555', phoneNumberId: '111222333444555'));
    $first = AgentWhatsappNumber::query()->firstOrFail();

    (new ConnectWhatsappNumberAction(fakeWhatsappOauth('EAAG-second-token-bbbbbbbbbb'), $client))
        ->execute($agent, new ConnectWhatsappData(code: 'C2', wabaId: '999888777666555', phoneNumberId: '111222333444555'));

    expect(AgentWhatsappNumber::query()->count())->toBe(1);

    $row = AgentWhatsappNumber::query()->firstOrFail();
    expect($row->id)->toBe($first->id)
        ->and($row->access_token)->toBe('EAAG-second-token-bbbbbbbbbb');
});
