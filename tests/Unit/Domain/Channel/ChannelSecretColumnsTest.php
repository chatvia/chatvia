<?php

use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Illuminate\Support\Facades\Crypt;

/**
 * Every channel connection holds a credential that can send messages (or mint
 * tokens) on the workspace's behalf, so each one is stored encrypted: the raw
 * column never holds the plain value. The `webhook_secret` stays plain, as the
 * webhook route looks the connection up by it.
 */
it('stores the channel credential encrypted', function (string $model, string $column): void {
    $connection = $model::factory()->create([$column => 'plain-credential-1234567890']);

    $stored = $model::query()->whereKey($connection->getKey())->toBase()->value($column);

    expect($stored)->not->toContain('plain-credential-1234567890')
        ->and(Crypt::decryptString($stored))->toBe('plain-credential-1234567890');
})->with([
    'Instagram page token' => [AgentInstagramAccount::class, 'page_access_token'],
    'Messenger page token' => [AgentMessengerPage::class, 'page_access_token'],
    'Slack bot token' => [AgentSlackApp::class, 'bot_token'],
    'Slack signing secret' => [AgentSlackApp::class, 'signing_secret'],
    'Teams client secret' => [AgentTeamsBot::class, 'app_secret'],
    'Telegram bot token' => [AgentTelegramBot::class, 'bot_token'],
    'Telegram header secret' => [AgentTelegramBot::class, 'header_secret'],
    'WhatsApp access token' => [AgentWhatsappNumber::class, 'access_token'],
]);
