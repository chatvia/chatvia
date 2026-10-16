<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Actions\DisconnectTelegramBotAction;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Illuminate\Support\Str;
use Tests\Support\Telegram\FakeTelegramClient;

/**
 * Disconnecting asks Telegram to stop sending updates, then removes the bot.
 */
it('removes the webhook at Telegram and deletes the bot', function (): void {
    $bot = AgentTelegramBot::create([
        'agent_id' => Agent::factory()->create()->id,
        'bot_token' => '123:secret-token',
        'bot_username' => 'demo_bot',
        'webhook_secret' => Str::random(48),
        'is_enabled' => true,
    ]);
    $client = new FakeTelegramClient;

    new DisconnectTelegramBotAction($client)->execute($bot);

    expect($client->deleted)->toBe(['123:secret-token'])
        ->and(AgentTelegramBot::query()->count())->toBe(0);
});
