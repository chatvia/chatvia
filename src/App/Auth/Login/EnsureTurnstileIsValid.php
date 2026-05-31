<?php

namespace App\Auth\Login;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RyanChandler\LaravelCloudflareTurnstile\Rules\Turnstile;

/**
 * Fortify login-pipeline step that requires a valid Cloudflare Turnstile
 * token before any credential check runs. Skipped when Turnstile isn't
 * configured (local/test envs without TURNSTILE_SITE_KEY).
 */
class EnsureTurnstileIsValid
{
    public function handle(Request $request, callable $next): mixed
    {
        if (config('services.turnstile.key') === null || config('services.turnstile.secret') === null) {
            return $next($request);
        }

        Validator::make(
            $request->only('cf-turnstile-response'),
            ['cf-turnstile-response' => ['required', new Turnstile]],
            ['cf-turnstile-response.required' => __('Please complete the verification.')],
        )->validate();

        return $next($request);
    }
}
