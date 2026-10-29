<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Services\ChannelConnectionGuard;
use Domain\Channel\Slack\Actions\ConnectSlackAppAction;
use Domain\Channel\Slack\Data\ConnectSlackData;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Pest\Expectation;
use Tests\Support\Channel\Slack\FakeSlackClient;

/**
 * Connect is the only write in the Slack channel that carries real behaviour:
 * it validates the bot token with Slack, stores it (and the signing secret)
 * with a fresh webhook secret, one row per agent whose secrets rotate on a
 * reconnect, and must reject a bad token without writing. There is no
 * webhook registration call: the workspace pastes the Request URL into their
 * Slack app themselves.
 */
it('connects an app with a fresh webhook secret and the bot identity, rotating the secrets in place on a reconnect', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $action = new ConnectSlackAppAction(new FakeSlackClient, new ChannelConnectionGuard);

    $action->execute($agent, new ConnectSlackData('xoxb-first-token-aaaaaaaaaa', 'first-signing-secret-aaaaaaaa'));
    $first = AgentSlackApp::query()->sole();
    $action->execute($agent, new ConnectSlackData('xoxb-second-token-bbbbbbbbbb', 'second-signing-secret-bbbbbbbb'));

    $row = AgentSlackApp::query()->sole();
    expect($row->id)->toBe($first->id)
        ->and($row->agent_id)->toBe($agent->id)
        ->and($row->team_name)->toBe('Acme Inc')
        ->and($row->bot_user_id)->toBe('U0BOT')
        ->and($row->is_enabled)->toBeTrue()
        ->and($row->bot_token)->toBe('xoxb-second-token-bbbbbbbbbb')
        ->and($row->signing_secret)->toBe('second-signing-secret-bbbbbbbb')
        ->and(strlen($row->webhook_secret))->toBe(48)
        ->and($row->webhook_secret)->not->toBe($first->webhook_secret);
});

it('throws a rejection and stores nothing when Slack rejects the token', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeSlackClient;
    $client->identity = null;

    expect(fn (): AgentSlackApp => new ConnectSlackAppAction($client, new ChannelConnectionGuard)->execute($agent, new ConnectSlackData('xoxb-bogus', 'signing-secret-value-12345678')))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::Rejected));

    expect(AgentSlackApp::query()->count())->toBe(0);
});
