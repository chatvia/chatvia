<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Actions\UpdateTelegramCommandsAction;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Illuminate\Support\Str;
use Tests\Support\Telegram\FakeTelegramClient;

/**
 * Saving custom commands persists them on the bot and re-registers the full
 * command menu (built-ins + custom) with Telegram.
 */
it('stores the custom commands and re-registers the Telegram menu', function (): void {
    $agent = Agent::factory()->create();
    $bot = AgentTelegramBot::create([
        'agent_id' => $agent->id,
        'bot_token' => '123:secret-token',
        'bot_username' => 'demo_bot',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);

    $client = new FakeTelegramClient;
    $action = new UpdateTelegramCommandsAction(new TelegramCommandRouter(app(TelegramChatResolver::class), $client));

    $action->execute($bot, [
        ['command' => 'pricing', 'description' => 'See our plans', 'reply' => 'Plans start at €99/mo.'],
        ['command' => 'hours', 'description' => 'Opening hours', 'reply' => 'We are open 9–5 CET.'],
    ]);

    // Persisted on the bot row.
    expect($bot->fresh()->custom_commands)->toHaveCount(2)
        ->and($bot->fresh()->custom_commands[0]['command'])->toBe('pricing');

    // Menu re-registered: built-ins first, then the two custom commands.
    expect($client->commands)->toHaveCount(1)
        ->and(array_column($client->commands[0]['commands'], 'command'))
        ->toBe(['start', 'reset', 'help', 'pricing', 'hours']);
});
