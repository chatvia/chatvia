<?php

declare(strict_types=1);

namespace Tests\Support\Channel\Telegram;

use Domain\Channel\Telegram\Services\TelegramClient;

/**
 * In-memory stand-in for {@see TelegramClient} so tests never touch the Telegram
 * Bot API. Records outbound sends/webhook registrations and lets a test pretend a
 * token is valid (`$me`) or rejected (`$me = null`).
 */
class FakeTelegramClient extends TelegramClient
{
    /** @var array{id: int, username: ?string, first_name: ?string}|null */
    public ?array $me = ['id' => 42, 'username' => 'demo_bot', 'first_name' => 'Demo'];

    /** @var list<array{token: string, url: string, secret: string}> */
    public array $webhooks = [];

    /** @var list<string> */
    public array $deleted = [];

    /** @var list<array{token: string, chatId: int|string, text: string}> */
    public array $sent = [];

    /** @var list<array{token: string, chatId: int|string, action: string}> */
    public array $actions = [];

    /** @var list<array{token: string, commands: list<array{command: string, description: string}>}> */
    public array $commands = [];

    public function getMe(string $token): ?array
    {
        return $this->me;
    }

    public function setMyCommands(string $token, array $commands): bool
    {
        $this->commands[] = ['token' => $token, 'commands' => $commands];

        return true;
    }

    public function setWebhook(string $token, string $url, string $secret): bool
    {
        $this->webhooks[] = ['token' => $token, 'url' => $url, 'secret' => $secret];

        return true;
    }

    public function deleteWebhook(string $token): bool
    {
        $this->deleted[] = $token;

        return true;
    }

    public function sendMessage(string $token, int|string $chatId, string $text): void
    {
        $this->sent[] = ['token' => $token, 'chatId' => $chatId, 'text' => $text];
    }

    public function sendChatAction(string $token, int|string $chatId, string $action = 'typing'): void
    {
        $this->actions[] = ['token' => $token, 'chatId' => $chatId, 'action' => $action];
    }
}
