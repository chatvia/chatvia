<?php

namespace Domain\Channel\Telegram\Services;

use Domain\Channel\Services\RedactedException;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Throwable;

/**
 * Thin wrapper over the Telegram Bot SDK ({@see Api}), instantiated per bot token
 * (Chatvia is multi-bot — one bot per agent). Every call is best-effort: a
 * Telegram outage logs and returns a safe value rather than throwing into the
 * webhook/job path.
 */
class TelegramClient
{
    /** Telegram caps a single message at 4096 chars. */
    private const MESSAGE_LIMIT = 4096;

    /**
     * Validate a token and return the bot's identity, or null if invalid.
     *
     * @return array{id: int, username: ?string, first_name: ?string}|null
     */
    public function getMe(string $token): ?array
    {
        try {
            $me = $this->api($token)->getMe();

            return [
                'id' => (int) $me->get('id'),
                'username' => $me->get('username'),
                'first_name' => $me->get('first_name'),
            ];
        } catch (Throwable $e) {
            Log::warning('Telegram getMe failed', RedactedException::context($e));

            return null;
        }
    }

    /**
     * Point the bot at our webhook. `secret_token` is echoed back by Telegram in
     * the `X-Telegram-Bot-Api-Secret-Token` header so we can verify authenticity.
     */
    public function setWebhook(string $token, string $url, string $secret): bool
    {
        try {
            $this->api($token)->setWebhook([
                'url' => $url,
                'secret_token' => $secret,
                'allowed_updates' => ['message'],
            ]);

            return true;
        } catch (Throwable $e) {
            Log::warning('Telegram setWebhook failed', RedactedException::context($e));

            return false;
        }
    }

    public function deleteWebhook(string $token): bool
    {
        try {
            $this->api($token)->deleteWebhook();

            return true;
        } catch (Throwable $e) {
            Log::warning('Telegram deleteWebhook failed', RedactedException::context($e));

            return false;
        }
    }

    /**
     * Send a reply, rendering the model's Markdown as Telegram HTML (see
     * {@see TelegramFormatter}). If Telegram rejects the HTML — or the message is
     * over the 4096-char limit — fall back to stripped plain text, chunked.
     */
    public function sendMessage(string $token, int|string $chatId, string $text): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $api = $this->api($token);
        $formatter = new TelegramFormatter;
        $html = $formatter->toHtml($text);

        // The common case: one message that renders as HTML.
        if (mb_strlen($html) <= self::MESSAGE_LIMIT) {
            try {
                $api->sendMessage(['chat_id' => $chatId, 'text' => $html, 'parse_mode' => 'HTML']);

                return;
            } catch (Throwable $e) {
                Log::warning('Telegram sendMessage (HTML) failed; retrying as plain text', [...RedactedException::context($e), 'chat_id' => $chatId]);
                // fall through to plain text
            }
        }

        // Too long, or HTML rejected: stripped plain text, split at the limit.
        foreach ($this->chunk($formatter->toPlain($text)) as $part) {
            try {
                $api->sendMessage(['chat_id' => $chatId, 'text' => $part]);
            } catch (Throwable $e) {
                Log::warning('Telegram sendMessage failed', [...RedactedException::context($e), 'chat_id' => $chatId]);

                return;
            }
        }
    }

    /**
     * Register the bot's command menu — the "/" autocomplete list Telegram shows
     * users. Replaces the full set on each call, so pass every command you want
     * visible. The SDK JSON-serialises the array for us. Best-effort.
     *
     * @param  list<array{command: string, description: string}>  $commands
     */
    public function setMyCommands(string $token, array $commands): bool
    {
        try {
            $this->api($token)->setMyCommands(['commands' => $commands]);

            return true;
        } catch (Throwable $e) {
            Log::warning('Telegram setMyCommands failed', RedactedException::context($e));

            return false;
        }
    }

    /**
     * Show the "typing…" indicator in the chat. Telegram clears it after ~5s (or
     * when the next message arrives), so callers re-send it during a long
     * generation to keep it alive. Best-effort.
     */
    public function sendChatAction(string $token, int|string $chatId, string $action = 'typing'): void
    {
        try {
            $this->api($token)->sendChatAction(['chat_id' => $chatId, 'action' => $action]);
        } catch (Throwable $e) {
            Log::warning('Telegram sendChatAction failed', [...RedactedException::context($e), 'chat_id' => $chatId]);
        }
    }

    protected function api(string $token): Api
    {
        return new Api($token);
    }

    /**
     * @return list<string>
     */
    private function chunk(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        return mb_strlen($text) <= self::MESSAGE_LIMIT ? [$text] : mb_str_split($text, self::MESSAGE_LIMIT);
    }
}
