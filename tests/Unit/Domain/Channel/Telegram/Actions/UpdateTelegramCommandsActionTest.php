<?php

use Domain\Channel\Telegram\Actions\UpdateTelegramCommandsAction;
use Domain\Channel\Telegram\Data\TelegramCommandData;
use Domain\Channel\Telegram\Data\UpdateTelegramCommandsData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Tests\Support\Channel\Telegram\FakeTelegramClient;

/**
 * Saving commands persists them on the bot and re-registers the full "/" menu
 * (built-ins merged with the stored overrides + customs) with Telegram.
 */
it('stores the commands and re-registers the Telegram menu', function (): void {
    $bot = AgentTelegramBot::factory()->create();

    $client = new FakeTelegramClient;
    $action = new UpdateTelegramCommandsAction(new TelegramCommandRouter(app(TelegramChatResolver::class), $client));

    $action->execute($bot, new UpdateTelegramCommandsData(commands: [
        new TelegramCommandData(command: 'start', description: 'Start', type: 'message', value: 'Hey there!'),
        new TelegramCommandData(command: 'pricing', description: 'See our plans', type: 'prompt', value: 'Explain our pricing.'),
    ]));

    // Persisted on the bot row, verbatim.
    expect($bot->fresh()->commands)->toHaveCount(2)
        ->and($bot->fresh()->commands[1])->toMatchArray(['command' => 'pricing', 'type' => 'prompt']);

    // Re-registered once, with the new command; TelegramCommandRouterTest owns the menu's order.
    expect($client->commands)->toHaveCount(1)
        ->and(array_column($client->commands[0]['commands'], 'command'))->toContain('pricing');
});
