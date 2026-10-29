<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Services\ChannelConnectionGuard;
use Domain\Channel\Whatsapp\Actions\ConnectWhatsappNumberAction;
use Domain\Channel\Whatsapp\Data\ConnectWhatsappData;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Pest\Expectation;
use Tests\Support\Channel\FakeMetaOAuthClient;
use Tests\Support\Channel\Whatsapp\FakeWhatsappClient;

/**
 * Connect is the only write in the WhatsApp channel that carries real
 * behaviour: it exchanges the Embedded Signup code for a business token,
 * validates the number, subscribes the WABA, registers the number, and
 * stores the token, one row per agent replaced in place on a reconnect. It
 * must reject a bad code without writing. ChannelConnectionGuardTest has the
 * shared refusals.
 */
beforeEach(function (): void {
    config(['services.meta.whatsapp.register_pin' => '123456']);
});

it('connects a number: exchanges the code, subscribes the WABA, registers the number, and reconnects in place', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeWhatsappClient;
    $oauth = new FakeMetaOAuthClient;
    $action = new ConnectWhatsappNumberAction($oauth, $client, new ChannelConnectionGuard);
    $data = new ConnectWhatsappData(code: 'ES-CODE', wabaId: '999888777666555', phoneNumberId: '111222333444555');

    $number = $action->execute($agent, $data);
    $oauth->businessToken = 'EAAG-second-token-bbbbbbbbbb';
    $action->execute($agent, $data);

    $row = AgentWhatsappNumber::query()->sole();
    expect($row->id)->toBe($number->id)
        ->and($row->agent_id)->toBe($agent->id)
        ->and($row->phone_number_id)->toBe('111222333444555')
        ->and($row->waba_id)->toBe('999888777666555')
        ->and($row->display_phone_number)->toBe('+15551234567')
        ->and($row->verified_name)->toBe('Acme Support')
        ->and($row->is_enabled)->toBeTrue()
        ->and($row->access_token)->toBe('EAAG-second-token-bbbbbbbbbb')
        ->and($client->subscriptions[0]['wabaId'])->toBe('999888777666555')
        ->and($client->registrations[0]['phoneNumberId'])->toBe('111222333444555')
        ->and($client->registrations[0]['pin'])->toBe('123456');
});

it('throws a rejection and stores nothing when the code cannot be exchanged', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeWhatsappClient;
    $oauth = new FakeMetaOAuthClient;
    $oauth->businessToken = null;

    expect(fn (): AgentWhatsappNumber => new ConnectWhatsappNumberAction($oauth, $client, new ChannelConnectionGuard)->execute(
        $agent,
        new ConnectWhatsappData(code: 'BAD', wabaId: '999', phoneNumberId: '111'),
    ))->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::Rejected));

    expect(AgentWhatsappNumber::query()->count())->toBe(0)
        ->and($client->subscriptions)->toBeEmpty()
        ->and($client->registrations)->toBeEmpty();
});
