<?php

namespace Support\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Support\Exceptions\UnsafeUrlException;

/**
 * Sends one HMAC-signed JSON POST to a customer-configured webhook (SIEM
 * security events, CRM form submissions). One place for the delivery rules so
 * they cannot drift between callers:
 *
 *  - the URL passes {@see SafeUrl::assert()} right before sending, because a
 *    URL that was safe at save time can later resolve to an internal address;
 *  - redirects are never followed, so a public host cannot bounce the request
 *    into the internal network after the check;
 *  - the raw body is signed with {@see WebhookSignature} and sent as
 *    `X-Chatvia-Signature: sha256=<hex>`, with the event name in `X-Chatvia-Event`;
 *  - any non-2xx answer throws, so a queued caller retries. That includes a
 *    3xx: with redirects off, a redirect means the event was not delivered.
 *
 * The `X-Chatvia-*` header names are a public contract that receivers verify,
 * so they keep this name even when the product is rebranded.
 */
final class SignedWebhookSender
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws UnsafeUrlException when the URL is not safe to call
     * @throws RequestException on a non-2xx response
     * @throws ConnectionException on a transport error
     */
    public function send(string $url, string $event, array $payload, string $secret): Response
    {
        SafeUrl::assert($url);

        $body = (string) json_encode($payload);

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->withOptions(SafeUrl::clientOptions())
            ->withHeaders([
                'X-Chatvia-Event' => $event,
                'X-Chatvia-Signature' => 'sha256='.WebhookSignature::sign($body, $secret),
            ])
            ->withBody($body, 'application/json')
            ->post($url);

        if (! $response->successful()) {
            throw new RequestException($response);
        }

        return $response;
    }
}
