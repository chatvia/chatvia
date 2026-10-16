<?php

namespace App\Http\Requests\Concerns;

use Domain\Integration\Function\Enums\FunctionAuthType;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Illuminate\Validation\Rule;

/**
 * The webhook-call auth rules of a function definition, shared by the
 * dashboard and API function requests. The dashboard's edit form skips the
 * credential requirement: a blank `auth_value` there means "keep the stored
 * secret" (checked in that request's after() hook).
 */
trait ValidatesFunctionAuth
{
    /**
     * @return array<string, mixed>
     */
    protected function functionAuthRules(bool $requireCredential = true): array
    {
        return [
            // The HTTP method of the webhook call. Defaults to POST.
            'webhook_method' => ['sometimes', Rule::enum(FunctionWebhookMethod::class)],
            // How the webhook call authenticates: `none`, `bearer`, `header` or `hmac`.
            'auth_type' => ['sometimes', Rule::enum(FunctionAuthType::class)],
            // The header the secret is sent in, with the `header` auth type.
            'auth_header_name' => ['nullable', 'required_if:auth_type,header', 'string', 'max:128'],
            // The secret: the bearer token, header value or HMAC key. Stored
            // encrypted and never returned.
            'auth_value' => $requireCredential
                ? ['nullable', 'required_if:auth_type,bearer,header,hmac', 'string', 'max:8192']
                : ['nullable', 'string', 'max:8192'],
        ];
    }
}
