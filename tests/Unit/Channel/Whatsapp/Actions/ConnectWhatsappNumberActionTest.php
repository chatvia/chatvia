<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Services\ChannelConnectionGuard;
use Domain\Channel\Whatsapp\Actions\ConnectWhatsappNumberAction;
use Domain\Channel\Whatsapp\Data\ConnectWhatsappData;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Pest\Expectation;
use Tests\Support\Whatsapp\FakeWhatsappClient;
use Tests\Support\Whatsapp\FakeWhatsappOAuthClient;

/**
 * Connect is the only write in the WhatsApp channel that carries real
 * behaviour: it exchanges the Embedded Signup code for a business token,
 * validates the number, subscribes the WABA + registers the number, and stores
 * the token — and must reject a bad code/number without writing.
 */
beforeEach(function (): void {
    config(['services.meta.whatsapp.register_pin' => '123456']);
});

it('connects a number: exchanges the code, subscribes the WABA and registers the number', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeWhatsappClient;

    $number = new ConnectWhatsappNumberAction(new FakeWhatsappOAuthClient, $client, new ChannelConnectionGuard)->execute(
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
        ->and($row->enabled)->toBeTrue();

    expect($client->subscriptions)->toHaveCount(1)
        ->and($client->subscriptions[0]['wabaId'])->toBe('999888777666555');

    expect($client->registrations)->toHaveCount(1)
        ->and($client->registrations[0]['phoneNumberId'])->toBe('111222333444555')
        ->and($client->registrations[0]['pin'])->toBe('123456');
});

it('throws a rejection and stores nothing when the code cannot be exchanged', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeWhatsappClient;
    $oauth = new FakeWhatsappOAuthClient;
    $oauth->token = null; // Meta: the code is invalid or expired.

    expect(fn (): AgentWhatsappNumber => new ConnectWhatsappNumberAction($oauth, $client, new ChannelConnectionGuard)->execute(
        $agent,
        new ConnectWhatsappData(code: 'BAD', wabaId: '999', phoneNumberId: '111'),
    ))->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::Rejected));

    expect(AgentWhatsappNumber::query()->count())->toBe(0);
    expect($client->subscriptions)->toBeEmpty()
        ->and($client->registrations)->toBeEmpty();
});

it('throws a rejection when Meta rejects the number after a valid code exchange', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeWhatsappClient;
    $client->number = null; // numberInfo: invalid phone number id

    expect(fn (): AgentWhatsappNumber => new ConnectWhatsappNumberAction(new FakeWhatsappOAuthClient, $client, new ChannelConnectionGuard)->execute(
        $agent,
        new ConnectWhatsappData(code: 'ES-CODE', wabaId: '999', phoneNumberId: '111'),
    ))->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::Rejected));

    expect(AgentWhatsappNumber::query()->count())->toBe(0);
});

it('skips number registration when no PIN is configured', function (): void {
    config(['services.meta.whatsapp.register_pin' => null]);

    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeWhatsappClient;

    new ConnectWhatsappNumberAction(new FakeWhatsappOAuthClient, $client, new ChannelConnectionGuard)->execute(
        $agent,
        new ConnectWhatsappData(code: 'ES-CODE', wabaId: '999888777666555', phoneNumberId: '111222333444555'),
    );

    expect($client->registrations)->toBeEmpty()
        ->and($client->subscriptions)->toHaveCount(1);
    expect(AgentWhatsappNumber::query()->count())->toBe(1);
});

it('reconnects in place — one number row per agent, token rotated', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeWhatsappClient;

    $oauth = new FakeWhatsappOAuthClient;
    $action = new ConnectWhatsappNumberAction($oauth, $client, new ChannelConnectionGuard);

    $oauth->token = 'EAAG-first-token-aaaaaaaaaaaa';
    $action->execute($agent, new ConnectWhatsappData(code: 'C1', wabaId: '999888777666555', phoneNumberId: '111222333444555'));
    $first = AgentWhatsappNumber::query()->firstOrFail();

    $oauth->token = 'EAAG-second-token-bbbbbbbbbb';
    $action->execute($agent, new ConnectWhatsappData(code: 'C2', wabaId: '999888777666555', phoneNumberId: '111222333444555'));

    expect(AgentWhatsappNumber::query()->count())->toBe(1);

    $row = AgentWhatsappNumber::query()->firstOrFail();
    expect($row->id)->toBe($first->id)
        ->and($row->access_token)->toBe('EAAG-second-token-bbbbbbbbbb');
});

it('refuses a protected agent before exchanging the code', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    $client = new FakeWhatsappClient;

    expect(fn (): AgentWhatsappNumber => new ConnectWhatsappNumberAction(new FakeWhatsappOAuthClient, $client, new ChannelConnectionGuard)
        ->execute($agent, new ConnectWhatsappData(code: 'ES-CODE', wabaId: '999888777666555', phoneNumberId: '111222333444555')))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::ProtectedAgent));

    expect(AgentWhatsappNumber::query()->count())->toBe(0)
        ->and($client->subscriptions)->toBeEmpty();
});

it('refuses a number that already powers another agent, before any Meta call', function (): void {
    $client = new FakeWhatsappClient;
    $action = new ConnectWhatsappNumberAction(new FakeWhatsappOAuthClient, $client, new ChannelConnectionGuard);
    $data = new ConnectWhatsappData(code: 'ES-CODE', wabaId: '999888777666555', phoneNumberId: '111222333444555');

    $action->execute(Agent::factory()->create(['is_public' => true]), $data);
    $client->subscriptions = [];

    $other = Agent::factory()->create(['is_public' => true]);

    expect(fn (): AgentWhatsappNumber => $action->execute($other, $data))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::TakenByAnotherAgent));

    expect(AgentWhatsappNumber::query()->where('agent_id', $other->id)->exists())->toBeFalse()
        ->and($client->subscriptions)->toBeEmpty();
});
