<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Support\Http\WebhookSignature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates callbacks from the ingestion pipeline: the `X-Auth-Hash`
 * header must be the HMAC-SHA256 of the raw body, signed with
 * `services.ingestion.secret` (falls back to the app key when unset).
 *
 * Runs before validation, so an unsigned caller always gets a 401 and can
 * never learn from a 422 whether an id exists.
 */
class VerifyIngestionSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) (config('services.ingestion.secret') ?: config('app.key'));

        abort_unless(
            WebhookSignature::verify($request->getContent(), $request->header('X-Auth-Hash'), $secret),
            401,
        );

        return $next($request);
    }
}
