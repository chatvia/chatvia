<?php

namespace Domain\Agents\Data;

use Spatie\LaravelData\Data;

class UpdateAgentFunctionData extends Data
{
    /**
     * @param  array<string, mixed>  $parameters_schema
     *
     * `auth_value` and `auth_header_name` are the raw flat inputs. A null/blank
     * `auth_value` means "keep the existing secret" — the action merges against
     * the stored config rather than wiping it.
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters_schema,
        public string $webhook_url,
        public string $webhook_method,
        public string $auth_type,
        public ?string $auth_header_name,
        public ?string $auth_value,
    ) {}
}
