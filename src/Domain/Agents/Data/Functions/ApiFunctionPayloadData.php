<?php

namespace Domain\Agents\Data\Functions;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Shared payload shape for `POST /api/v1/functions` and
 * `PATCH /api/v1/functions/{function}`. Every field is `Optional` so the
 * update path can distinguish "not present" from `null`.
 */
class ApiFunctionPayloadData extends Data
{
    public function __construct(
        public string|Optional $name,
        public string|Optional $description,
        /** @var array<string, mixed>|Optional */
        public array|Optional $parameters_schema,
        public string|Optional $webhook_url,
        public string|Optional $webhook_method,
        /** @var array<string, mixed>|null|Optional */
        public array|null|Optional $webhook_headers,
        public string|Optional $auth_type,
        public string|null|Optional $auth_header_name,
        public string|null|Optional $auth_value,
        public int|Optional $timeout_ms,
        public bool|Optional $is_enabled,
    ) {}
}
