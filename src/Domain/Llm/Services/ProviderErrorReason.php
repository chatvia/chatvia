<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * What an AI provider said went wrong when it refused a request, short enough
 * to show a person. Through OpenRouter, the message is often only "Provider
 * returned error": the provider behind it and what it said come in the
 * error's metadata.
 */
final readonly class ProviderErrorReason
{
    private const int MAX_LENGTH = 300;

    public function of(Response $response): ?string
    {
        $message = $this->text($response->json('error.message') ?? $response->json('message'));
        $upstream = $this->upstreamReason($response->json('error.metadata.raw'));
        $upstreamProvider = $this->text($response->json('error.metadata.provider_name'));

        if ($upstream !== null) {
            $said = ($upstreamProvider !== null ? "{$upstreamProvider} said" : 'The provider said').": {$upstream}";
            $message = $message !== null && $message !== $upstream ? Str::finish($message, '.')." {$said}" : $said;
        }

        return $message !== null ? Str::limit($message, self::MAX_LENGTH) : null;
    }

    /** The message inside OpenRouter's `metadata.raw`: JSON from the provider behind it, or plain text. */
    private function upstreamReason(mixed $raw): ?string
    {
        $raw = $this->text($raw);

        if ($raw === null || ! Str::isJson($raw)) {
            return $raw;
        }

        $decoded = json_decode($raw, true);

        return $this->text(data_get($decoded, 'error.message') ?? data_get($decoded, 'message') ?? data_get($decoded, 'detail') ?? data_get($decoded, 'error'));
    }

    /** A value of the error as one line of text; null when it is missing, empty or not text. */
    private function text(mixed $value): ?string
    {
        return filled($value) && is_scalar($value) && ! is_bool($value) ? Str::squish((string) $value) : null;
    }
}
