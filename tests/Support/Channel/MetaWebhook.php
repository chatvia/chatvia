<?php

declare(strict_types=1);

namespace Tests\Support\Channel;

use Domain\Channel\Enums\MetaApp;
use Illuminate\Testing\TestResponse;

/**
 * Calls the webhook of a Meta channel (Messenger, Instagram, WhatsApp) the
 * way Meta does: events are POSTed with an `X-Hub-Signature-256` HMAC of the
 * body, and the verify handshake is a GET with the `hub.*` query.
 */
final class MetaWebhook
{
    /**
     * POST an event signed with $secret; unsigned when $secret is null.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function post(MetaApp $app, array $payload, ?string $secret): TestResponse
    {
        // postJson() sends json_encode($payload), so this is the signed body.
        $headers = $secret === null
            ? []
            : ['X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', (string) json_encode($payload), $secret)];

        return test()->postJson(route($app->value.'.webhook'), $payload, $headers);
    }

    /** Meta's GET verify handshake with this verify token. */
    public static function verify(MetaApp $app, string $verifyToken, string $challenge = 'challenge-123'): TestResponse
    {
        return test()->get(route($app->value.'.webhook.verify', [
            'hub.mode' => 'subscribe',
            'hub.verify_token' => $verifyToken,
            'hub.challenge' => $challenge,
        ]));
    }
}
