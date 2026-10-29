<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Services\ChannelConnectionGuard;
use Domain\Channel\Telegram\Actions\ConnectTelegramBotAction;
use Domain\Channel\Telegram\Data\ConnectTelegramData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Pest\Expectation;
use Tests\Support\Channel\Telegram\FakeTelegramClient;

/**
 * Connect is the only write in the Telegram channel that carries real
 * behaviour: it validates the token with Telegram, stores it with a fresh
 * webhook secret and header secret, registers our webhook and the "/" menu,
 * and must reject a bad token without writing. The header secret is not the
 * one in the URL: a webhook URL seen in a log or a proxy must not be enough
 * to forge an update. A reconnect rotates both secrets in place.
 */
function ctbAction(FakeTelegramClient $client): ConnectTelegramBotAction
{
    return new ConnectTelegramBotAction($client, new TelegramCommandRouter(app(TelegramChatResolver::class), $client), new ChannelConnectionGuard);
}

it('connects a bot and registers the webhook with a header secret that is not in its URL', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeTelegramClient;

    $bot = ctbAction($client)->execute($agent, new ConnectTelegramData('123456:ABCDEF_token_value_here'));

    $row = AgentTelegramBot::query()->firstOrFail();
    expect($bot->is($row))->toBeTrue()
        ->and($row->agent_id)->toBe($agent->id)
        ->and($row->bot_username)->toBe('demo_bot')
        ->and($row->is_enabled)->toBeTrue()
        ->and(strlen($row->webhook_secret))->toBe(48)
        ->and(strlen($row->header_secret))->toBe(64)
        ->and($client->webhooks)->toHaveCount(1)
        ->and($client->webhooks[0]['secret'])->toBe($row->header_secret)
        ->and($client->webhooks[0]['secret'])->not->toBe($row->webhook_secret)
        ->and($client->webhooks[0]['url'])->toContain('telegram/webhook/'.$row->webhook_secret)
        ->and($client->webhooks[0]['url'])->not->toContain($row->header_secret)
        ->and($client->commands)->toHaveCount(1);
});

it('throws a rejection and stores nothing when Telegram rejects the token', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeTelegramClient;
    $client->me = null;

    expect(fn (): AgentTelegramBot => ctbAction($client)->execute($agent, new ConnectTelegramData('bogus')))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::Rejected));

    expect(AgentTelegramBot::query()->count())->toBe(0)
        ->and($client->webhooks)->toBeEmpty();
});

it('reconnects in place — one bot row per agent, token and secrets rotated', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeTelegramClient;
    $action = ctbAction($client);

    $action->execute($agent, new ConnectTelegramData('111:first_token_value_aaaaaaaaaa'));
    $first = AgentTelegramBot::query()->firstOrFail();

    $action->execute($agent, new ConnectTelegramData('222:second_token_value_bbbbbbbbbb'));

    $row = AgentTelegramBot::query()->sole();
    expect($row->id)->toBe($first->id)
        ->and($row->bot_token)->toBe('222:second_token_value_bbbbbbbbbb')
        ->and($row->webhook_secret)->not->toBe($first->webhook_secret)
        ->and($row->header_secret)->not->toBe($first->header_secret);
});
