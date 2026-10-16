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
use Tests\Support\Telegram\FakeTelegramClient;

function telegramConnectAction(FakeTelegramClient $client): ConnectTelegramBotAction
{
    return new ConnectTelegramBotAction($client, new TelegramCommandRouter(app(TelegramChatResolver::class), $client), new ChannelConnectionGuard);
}

/**
 * Connect is the only write in the Telegram channel that carries real behaviour:
 * it validates the token with Telegram, stores it with a fresh webhook
 * secret, and registers our webhook — and must reject a bad token without writing.
 */
it('connects a bot and registers the webhook with its fresh secret', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeTelegramClient;

    $bot = telegramConnectAction($client)->execute($agent, new ConnectTelegramData('123456:ABCDEF_token_value_here'));

    expect($bot)->not->toBeNull();

    $row = AgentTelegramBot::query()->firstOrFail();

    expect($row->agent_id)->toBe($agent->id)
        ->and($row->bot_username)->toBe('demo_bot')
        ->and($row->is_enabled)->toBeTrue()
        ->and(strlen($row->webhook_secret))->toBe(48);

    expect($client->webhooks)->toHaveCount(1)
        ->and($client->webhooks[0]['secret'])->toBe($row->webhook_secret)
        ->and($client->webhooks[0]['url'])->toContain('telegram/webhook');

    // The built-in command menu (/start, /reset, /help) is registered with Telegram.
    expect($client->commands)->toHaveCount(1)
        ->and(array_column($client->commands[0]['commands'], 'command'))->toBe(['start', 'reset', 'help']);
});

it('throws a rejection and stores nothing when Telegram rejects the token', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeTelegramClient;
    $client->me = null; // Telegram: invalid token.

    expect(fn (): AgentTelegramBot => telegramConnectAction($client)->execute($agent, new ConnectTelegramData('bogus')))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::Rejected));

    expect(AgentTelegramBot::query()->count())->toBe(0);
    expect($client->webhooks)->toBeEmpty();
});

it('reconnects in place — one bot row per agent, token rotated', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeTelegramClient;
    $action = telegramConnectAction($client);

    $action->execute($agent, new ConnectTelegramData('111:first_token_value_aaaaaaaaaa'));
    $first = AgentTelegramBot::query()->firstOrFail();

    $action->execute($agent, new ConnectTelegramData('222:second_token_value_bbbbbbbbbb'));

    expect(AgentTelegramBot::query()->count())->toBe(1);

    $row = AgentTelegramBot::query()->firstOrFail();
    expect($row->id)->toBe($first->id)
        ->and($row->bot_token)->toBe('222:second_token_value_bbbbbbbbbb')
        ->and($row->webhook_secret)->not->toBe($first->webhook_secret);
});

it('refuses a protected agent before calling Telegram', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    $client = new FakeTelegramClient;

    expect(fn (): AgentTelegramBot => telegramConnectAction($client)->execute($agent, new ConnectTelegramData('123456:ABCDEF_token_value_here')))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::ProtectedAgent));

    expect(AgentTelegramBot::query()->count())->toBe(0)
        ->and($client->webhooks)->toBeEmpty();
});
