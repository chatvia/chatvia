<?php

namespace Domain\Channel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared transport for the Meta Graph–backed channel clients (Messenger,
 * Instagram), both plain HTTP with no SDK. Holds the best-effort {@see call}
 * helper and the message {@see chunk} splitter; the Graph host, the per-message
 * character cap, and the log label vary per channel and are supplied by the
 * subclass.
 *
 * Every call is best-effort: a Meta outage logs and returns a safe value rather
 * than throwing into the webhook/job path. (WhatsApp talks to the same Graph
 * host but posts a different message shape and so keeps its own client.)
 */
abstract class MetaGraphClient
{
    /**
     * The versioned Graph base URL for this channel
     * (e.g. `https://graph.facebook.com/v25.0`).
     */
    abstract protected function graphBaseUrl(): string;

    /**
     * The platform's single-message character cap.
     */
    abstract protected function messageLimit(): int;

    /**
     * Human-readable channel label used in log lines (e.g. `Messenger`).
     */
    abstract protected function logLabel(): string;

    /**
     * Call a Graph API endpoint; null on transport failure or a Graph error.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    protected function call(string $token, string $method, string $path, array $params = []): ?array
    {
        try {
            $pending = Http::withToken($token);

            $response = $method === 'get'
                ? $pending->get($this->graphBaseUrl().$path, $params)
                : $pending->asJson()->post($this->graphBaseUrl().$path, $params);

            $json = $response->json();

            if (! $response->successful() || ! is_array($json) || isset($json['error'])) {
                Log::warning("{$this->logLabel()} {$path} returned an error", ['error' => $json['error']['message'] ?? 'malformed response']);

                return null;
            }

            return $json;
        } catch (Throwable $e) {
            Log::warning("{$this->logLabel()} {$path} failed", RedactedException::context($e));

            return null;
        }
    }

    /**
     * @return list<string>
     */
    protected function chunk(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        return mb_strlen($text) <= $this->messageLimit() ? [$text] : mb_str_split($text, $this->messageLimit());
    }
}
