<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services;

use Support\Http\WebhookSignature;

/**
 * Host-independent, unforgeable signature for the public chunk page-image route
 * (`/my/chunks/{externalId}/{index}.png`).
 *
 * These images are emitted by the agent as markdown and fetched by anonymous
 * visitor browsers (and the operator dashboard), so the route cannot be behind
 * `auth`. Without a signature the route is an unauthenticated, cross-tenant IDOR
 * — anyone who guesses a (time-ordered UUIDv7) chunk id reads another tenant's
 * ingested page images. We bind each URL to a constant-time HMAC over the chunk
 * id + image index so only links the server minted are accepted.
 *
 * The signature covers ONLY the id + index (not the host), so the same link
 * stays valid on whichever host the image is rendered — the main app domain or
 * an agent's custom domain — and it never expires (it is embedded in stored
 * chat history). Mirrors the constant-time HMAC pattern of {@see WebhookSignature}.
 */
final class ChunkImageLink
{
    /** Query-string key carrying the signature. */
    public const string SIGNATURE_PARAM = 's';

    /**
     * The signed, app-root-relative path for a chunk image, e.g.
     * `/my/chunks/<uuid>/0.png?s=<hex>`.
     */
    public static function path(string $externalId, int $index): string
    {
        return '/my/chunks/'.$externalId.'/'.$index.'.png?'
            .self::SIGNATURE_PARAM.'='.self::signature($externalId, $index);
    }

    /**
     * Constant-time check that $signature was minted for this (externalId,
     * index) pair. Rejects missing/empty signatures.
     */
    public static function verify(string $externalId, int $index, ?string $signature): bool
    {
        if (blank($signature)) {
            return false;
        }

        return hash_equals(self::signature($externalId, $index), $signature);
    }

    private static function signature(string $externalId, int $index): string
    {
        return hash_hmac('sha256', 'chunk-image|'.$externalId.'|'.$index, (string) config('app.key'));
    }
}
