<?php

namespace Domain\Channel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared transport for the Meta Graph–backed channel clients (Messenger,
 * Instagram, WhatsApp), all plain HTTP with no SDK. Holds the best-effort
 * {@see call} helper and the message {@see chunk} splitter; the per-message
 * character cap and the log label vary per channel and are supplied by the
 * subclass. Each channel posts its own message shape on top.
 *
 * Every call is best-effort: a Meta outage logs and returns a safe value rather
 * than throwing into the webhook/job path.
 */
abstract class MetaGraphClient
{
    /**
     * The versioned Graph base URL (e.g. `https://graph.facebook.com/v25.0`);
     * the version comes from `services.meta.graph_version`.
     */
    protected function graphBaseUrl(): string
    {
        return 'https://graph.facebook.com/'.config('services.meta.graph_version', 'v25.0');
    }

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
                // An object, so a POST without parameters sends `{}`, not `[]`.
                : $pending->asJson()->post($this->graphBaseUrl().$path, (object) $params);

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
        $text = Str::trim($text);

        if ($text === '') {
            return [];
        }

        return Str::length($text) <= $this->messageLimit() ? [$text] : Str::of($text)->split($this->messageLimit())->all();
    }
}
