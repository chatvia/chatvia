<?php

namespace Domain\Agent\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\SafeUrl;

/**
 * Save-time SSRF validation for any user-supplied outbound URL (function
 * webhook URLs, custom MCP server URLs). Delegates to Support\Http\SafeUrl so
 * the rule and the dispatch-time guard share one implementation.
 *
 * Save-time validation alone is bypassable (DNS rebinding, redirects), so the
 * dispatchers (HttpToolDispatcher, McpClient) MUST also call SafeUrl::assert()
 * immediately before connecting and disable redirect following.
 */
class SafeWebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        try {
            SafeUrl::assert($value);
        } catch (UnsafeUrlException $e) {
            $fail($e->getMessage());
        }
    }
}
