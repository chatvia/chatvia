<?php

namespace Domain\Agents\Data;

use App\Auth\Models\User;
use Spatie\LaravelData\Data;

class CreateAgentFunctionData extends Data
{
    /**
     * @param  array<string, mixed>  $parameters_schema
     */
    public function __construct(
        public User $user,
        public string $name,
        public string $description,
        public array $parameters_schema,
        public string $webhook_url,
        public string $webhook_method,
        public string $auth_type,
        public ?string $auth_value,
    ) {}
}
