<?php

namespace Domain\Channel\Services;

use Throwable;

/**
 * Log context for an exception thrown by a messaging-channel API call, with
 * credentials removed. HTTP client exceptions put the request URL (and
 * sometimes the request or response body) in their message: every Telegram
 * Bot API URL carries the bot token in its path (`/bot<token>/sendMessage`),
 * and a Meta token exchange body carries `client_secret` / `access_token`.
 * Logging `$e->getMessage()` as-is could write those secrets to the log, so
 * the channel clients log this instead.
 */
final class RedactedException
{
    private const int MAX_MESSAGE_LENGTH = 300;

    /**
     * @return array{exception: class-string<Throwable>, code: int|string, message: string}
     */
    public static function context(Throwable $e): array
    {
        return [
            'exception' => $e::class,
            'code' => $e->getCode(),
            'message' => self::redact($e->getMessage()),
        ];
    }

    public static function redact(string $message): string
    {
        $patterns = [
            // Telegram bot token in a URL path: /bot123456:AA...
            '~/bot\d+:[A-Za-z0-9_-]+~' => '/bot[redacted]',
            // A bare Telegram bot token.
            '~\b\d{5,}:[A-Za-z0-9_-]{20,}\b~' => '[redacted]',
            // Any query string: Meta sends secrets and tokens as query params.
            '~(https?://[^\s?#"\'<>]+)\?[^\s#"\'<>]*~i' => '$1?[redacted]',
            // Secrets in a form body or a URL fragment that slipped through.
            '~\b(access_token|client_secret|fb_exchange_token|input_token|code|token)=[^&\s"\'<>]+~i' => '$1=[redacted]',
            // Authorization header values.
            '#\b(Bearer|OAuth)\s+[A-Za-z0-9._~+/=-]+#i' => '$1 [redacted]',
        ];

        $redacted = preg_replace(array_keys($patterns), array_values($patterns), $message) ?? '';

        return mb_strlen($redacted) > self::MAX_MESSAGE_LENGTH
            ? mb_substr($redacted, 0, self::MAX_MESSAGE_LENGTH).'…'
            : $redacted;
    }
}
