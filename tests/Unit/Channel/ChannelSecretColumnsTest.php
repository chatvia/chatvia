<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Every channel connection holds a credential that can send messages (or mint
 * tokens) on the workspace's behalf, so each one is encrypted at rest. The
 * `webhook_secret` stays plain: the webhook route looks the connection up by it.
 */
it('casts the channel credential to encrypted', function (string $model, string $column): void {
    /** @var Model $connection */
    $connection = new $model;

    expect($connection->getCasts())->toHaveKey($column, 'encrypted');
})->with([
    'Instagram page token' => [AgentInstagramAccount::class, 'page_access_token'],
    'Messenger page token' => [AgentMessengerPage::class, 'page_access_token'],
    'Slack bot token' => [AgentSlackApp::class, 'bot_token'],
    'Slack signing secret' => [AgentSlackApp::class, 'signing_secret'],
    'Teams client secret' => [AgentTeamsBot::class, 'app_secret'],
    'Telegram bot token' => [AgentTelegramBot::class, 'bot_token'],
    'WhatsApp access token' => [AgentWhatsappNumber::class, 'access_token'],
]);

it('stores the credential as ciphertext that decrypts to the token', function (): void {
    $bot = AgentTelegramBot::create([
        'agent_id' => Agent::factory()->create()->id,
        'bot_token' => '123456:ABCDEF_token_value_here',
        'bot_username' => 'demo_bot',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);

    $raw = AgentTelegramBot::query()->whereKey($bot->id)->firstOrFail()->getRawOriginal('bot_token');

    expect($raw)->not->toContain('ABCDEF_token_value_here')
        ->and(Crypt::decryptString($raw))->toBe('123456:ABCDEF_token_value_here');
});
