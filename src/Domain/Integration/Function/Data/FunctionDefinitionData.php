<?php

namespace Domain\Integration\Function\Data;

use Domain\Integration\Function\Enums\FunctionAuthType;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The definition of a webhook function, as both the dashboard form and the
 * public API (`/api/v1/functions`) send it. A field the caller did not send
 * is `Optional`, so an update only changes what it was given and a create
 * falls back to the defaults.
 *
 * Credentials arrive flat (`auth_value`, `auth_header_name`) and are stored
 * as the typed, encrypted `auth_config` the auth type needs.
 */
class FunctionDefinitionData extends Data
{
    /**
     * @param  array<string, mixed>|Optional  $parameters_schema
     * @param  array<string, mixed>|null|Optional  $webhook_headers
     */
    public function __construct(
        public string|Optional $internal_name = new Optional,
        public string|null|Optional $display_name = new Optional,
        public string|Optional $description = new Optional,
        public array|Optional $parameters_schema = new Optional,
        public string|Optional $webhook_url = new Optional,
        public FunctionWebhookMethod|Optional $webhook_method = new Optional,
        public array|null|Optional $webhook_headers = new Optional,
        public FunctionAuthType|Optional $auth_type = new Optional,
        public string|null|Optional $auth_header_name = new Optional,
        public string|null|Optional $auth_value = new Optional,
        public int|Optional $timeout_ms = new Optional,
        public bool|Optional $is_enabled = new Optional,
        public bool|Optional $requires_approval = new Optional,
    ) {}

    /**
     * The credential value, or null when none was sent (or it was blank).
     */
    public function authValue(): ?string
    {
        return $this->auth_value instanceof Optional || $this->auth_value === '' ? null : $this->auth_value;
    }

    /**
     * The custom header name for header auth, or null when none was sent.
     */
    public function authHeaderName(): ?string
    {
        return $this->auth_header_name instanceof Optional || $this->auth_header_name === '' ? null : $this->auth_header_name;
    }
}
