<?php

namespace App\Http\Controllers\Webhooks\Concerns;

use Domain\Channel\Enums\MetaApp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The Meta-platform webhooks (Messenger, WhatsApp, Instagram) share one
 * handshake: a GET verify (`hub.mode` / `hub.verify_token` / `hub.challenge`
 * — PHP mangles the dots to underscores in the query bag) and POSTs signed
 * with `X-Hub-Signature-256`, an HMAC-SHA256 of the raw body keyed with the
 * secret of the channel's Meta app ({@see MetaApp}).
 */
trait InteractsWithMetaWebhooks
{
    /**
     * Answer Meta's GET verify handshake: echo `hub.challenge` back when the
     * verify token matches, 403 otherwise (a 200 without the challenge would
     * still fail Meta's check, but a 403 surfaces the misconfiguration).
     */
    protected function metaChallengeResponse(Request $request, ?string $verifyToken): Response
    {
        if (
            $verifyToken !== null
            && $request->query('hub_mode') === 'subscribe'
            && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))
        ) {
            return response((string) $request->query('hub_challenge', ''), 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Whether the POST carries a valid signature from the channel's Meta app.
     * An unset app secret verifies nothing.
     */
    protected function isSignedByMeta(Request $request, MetaApp $app): bool
    {
        $appSecret = $app->appSecret();

        return $appSecret !== '' && $this->hasValidMetaSignature($request, $appSecret);
    }

    private function hasValidMetaSignature(Request $request, string $appSecret): bool
    {
        $header = (string) $request->header('X-Hub-Signature-256');

        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $header);
    }
}
