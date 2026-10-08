<?php

use Domain\Channel\Telegram\Services\TelegramClient;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;

/**
 * Every Telegram Bot API URL carries the bot token in its path, and SDK
 * transport exceptions repeat that URL in their message. A failed call must
 * log the exception without the token.
 */
it('logs a failed Telegram call without the bot token', function (): void {
    Log::spy();

    $token = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw';

    $client = new class extends TelegramClient
    {
        protected function api(string $token): Api
        {
            $api = Mockery::mock(Api::class);
            $api->shouldReceive('sendChatAction')->andThrow(new RuntimeException(
                "cURL error 6: Could not resolve host for https://api.telegram.org/bot{$token}/sendChatAction"
            ));
            $api->shouldReceive('getMe')->andThrow(new RuntimeException(
                "Unauthorized for https://api.telegram.org/bot{$token}/getMe"
            ));

            return $api;
        }
    };

    $client->sendChatAction($token, 42);
    expect($client->getMe($token))->toBeNull();

    Log::shouldHaveReceived('warning')->twice()->withArgs(function (string $message, array $context = []): bool {
        return ! str_contains($message.json_encode($context), 'AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw')
            && str_contains((string) ($context['message'] ?? ''), '/bot[redacted]/');
    });
});
