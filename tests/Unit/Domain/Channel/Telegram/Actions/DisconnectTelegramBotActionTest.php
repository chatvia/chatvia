<?php

use Domain\Channel\Telegram\Actions\DisconnectTelegramBotAction;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Tests\Support\Channel\Telegram\FakeTelegramClient;

/**
 * Disconnecting asks Telegram to stop sending updates, then removes the bot.
 */
it('removes the webhook at Telegram and deletes the bot', function (): void {
    $bot = AgentTelegramBot::factory()->create(['bot_token' => '123:secret-token']);
    $client = new FakeTelegramClient;

    new DisconnectTelegramBotAction($client)->execute($bot);

    expect($client->deleted)->toBe(['123:secret-token'])
        ->and(AgentTelegramBot::query()->count())->toBe(0);
});
