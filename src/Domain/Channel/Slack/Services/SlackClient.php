<?php

namespace Domain\Channel\Slack\Services;

use Domain\Channel\Services\RedactedException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin wrapper over the Slack Web API (plain HTTP — no SDK), called per bot
 * token (Chatvia is multi-app — one Slack app per agent). Every call is
 * best-effort: a Slack outage logs and returns a safe value rather than
 * throwing into the webhook/job path.
 *
 * Replies stream: {@see postMessage} drops a placeholder and returns its `ts`,
 * then {@see updateMessage} rewrites it in place as the agent generates.
 */
class SlackClient
{
    /** Slack rejects message text over 40k chars. */
    private const int MESSAGE_LIMIT = 40000;

    /**
     * Validate a bot token and return the app's identity, or null if invalid.
     *
     * @return array{team: ?string, user_id: ?string}|null
     */
    public function authTest(string $token): ?array
    {
        $response = $this->call($token, 'auth.test');

        if ($response === null) {
            return null;
        }

        return [
            'team' => $response['team'] ?? null,
            'user_id' => $response['user_id'] ?? null,
        ];
    }

    /**
     * Look up a conversation's name/kind (used once, to title the Chatvia
     * conversation). Needs no extra scope for channels the bot is in.
     *
     * @return array{name: ?string, is_im: bool}|null
     */
    public function channelInfo(string $token, string $channelId): ?array
    {
        $response = $this->call($token, 'conversations.info', ['channel' => $channelId]);

        if ($response === null) {
            return null;
        }

        return [
            'name' => $response['channel']['name'] ?? null,
            'is_im' => (bool) ($response['channel']['is_im'] ?? false),
        ];
    }

    /**
     * Post a message (the model's Markdown rendered as mrkdwn — see
     * {@see SlackFormatter}) and return its `ts`, the handle later
     * {@see updateMessage} calls stream into. Replies in-thread when the
     * triggering message was in one.
     */
    public function postMessage(string $token, string $channelId, string $markdown, ?string $threadTs = null): ?string
    {
        $text = $this->render($markdown);

        if ($text === '') {
            return null;
        }

        $response = $this->call($token, 'chat.postMessage', array_filter([
            'channel' => $channelId,
            'text' => $text,
            'thread_ts' => $threadTs,
        ]));

        return $response['ts'] ?? null;
    }

    /**
     * Rewrite a previously posted message in place — this is what makes the
     * reply visibly stream in Slack.
     */
    public function updateMessage(string $token, string $channelId, string $ts, string $markdown): bool
    {
        $text = $this->render($markdown);

        if ($text === '') {
            return false;
        }

        return $this->call($token, 'chat.update', [
            'channel' => $channelId,
            'ts' => $ts,
            'text' => $text,
        ]) !== null;
    }

    /**
     * Call a Slack Web API method; null on transport failure or `ok: false`.
     *
     * @return array<string, mixed>|null
     */
    protected function call(string $token, string $method, array $params = []): ?array
    {
        try {
            $response = Http::withToken($token)
                ->asJson()
                ->post("https://slack.com/api/{$method}", (object) $params)
                ->json();
        } catch (Throwable $e) {
            Log::warning("Slack {$method} failed", RedactedException::context($e));

            return null;
        }

        if (! is_array($response) || ($response['ok'] ?? false) !== true) {
            Log::warning("Slack {$method} returned an error", ['error' => $response['error'] ?? 'malformed response']);

            return null;
        }

        return $response;
    }

    private function render(string $markdown): string
    {
        $text = (new SlackFormatter)->toMrkdwn($markdown);

        if (mb_strlen($text) > self::MESSAGE_LIMIT) {
            Log::warning('Slack message truncated at limit', ['length' => mb_strlen($text)]);
            $text = mb_substr($text, 0, self::MESSAGE_LIMIT - 1).'…';
        }

        return $text;
    }
}
