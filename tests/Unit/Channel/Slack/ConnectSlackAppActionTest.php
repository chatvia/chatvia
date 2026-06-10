<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Slack\Actions\ConnectSlackAppAction;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Tests\Support\Slack\FakeSlackClient;

/**
 * Connect is the only write in the Slack channel that carries real behaviour:
 * it validates the bot token with Slack, stores it (and the signing secret)
 * encrypted with a fresh webhook secret — and must reject a bad token without
 * writing. There is no webhook registration call: the workspace pastes the
 * Request URL into their Slack app themselves.
 */
it('connects an app, stores its secrets encrypted, and records the bot identity', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeSlackClient;

    $app = (new ConnectSlackAppAction($client))->execute($agent, 'xoxb-1111-token-value', 'signing-secret-value-12345678');

    expect($app)->not->toBeNull();

    $row = AgentSlackApp::query()->firstOrFail();

    expect($row->agent_id)->toBe($agent->id)
        ->and($row->team_name)->toBe('Acme Inc')
        ->and($row->bot_user_id)->toBe('U0BOT')
        ->and($row->enabled)->toBeTrue()
        ->and(strlen($row->webhook_secret))->toBe(48)
        // Encrypted casts round-trip to the original values.
        ->and($row->bot_token)->toBe('xoxb-1111-token-value')
        ->and($row->signing_secret)->toBe('signing-secret-value-12345678')
        // The stored ciphertext is not the plaintext.
        ->and($row->getRawOriginal('bot_token'))->not->toBe('xoxb-1111-token-value')
        ->and($row->getRawOriginal('signing_secret'))->not->toBe('signing-secret-value-12345678');
});

it('returns null and stores nothing when Slack rejects the token', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeSlackClient;
    $client->identity = null; // Slack: invalid token.

    $app = (new ConnectSlackAppAction($client))->execute($agent, 'xoxb-bogus', 'signing-secret-value-12345678');

    expect($app)->toBeNull();
    expect(AgentSlackApp::query()->count())->toBe(0);
});

it('reconnects in place — one app row per agent, secrets rotated', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeSlackClient;
    $action = new ConnectSlackAppAction($client);

    $action->execute($agent, 'xoxb-first-token-aaaaaaaaaa', 'first-signing-secret-aaaaaaaa');
    $first = AgentSlackApp::query()->firstOrFail();

    $action->execute($agent, 'xoxb-second-token-bbbbbbbbbb', 'second-signing-secret-bbbbbbbb');

    expect(AgentSlackApp::query()->count())->toBe(1);

    $row = AgentSlackApp::query()->firstOrFail();
    expect($row->id)->toBe($first->id)
        ->and($row->bot_token)->toBe('xoxb-second-token-bbbbbbbbbb')
        ->and($row->signing_secret)->toBe('second-signing-secret-bbbbbbbb')
        ->and($row->webhook_secret)->not->toBe($first->webhook_secret);
});
