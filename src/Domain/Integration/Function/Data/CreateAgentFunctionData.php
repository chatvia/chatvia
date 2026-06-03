<?php

namespace Domain\Integration\Function\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

class CreateAgentFunctionData extends Data
{
    /**
     * @param  array<string, mixed>  $parameters_schema
     * @param  array<string, string>|null  $auth_config  Shape depends on auth_type:
     *                                                   bearer → ['token' => '...'], header → ['name' => '...', 'value' => '...'],
     *                                                   hmac → ['secret' => '...'], none → null.
     */
    public function __construct(
        public User $user,
        public string $internal_name,
        public ?string $display_name,
        public string $description,
        public array $parameters_schema,
        public string $webhook_url,
        public string $webhook_method,
        public string $auth_type,
        public ?array $auth_config,
        // null → seed from webhook_method (GET → false, write verbs → true).
        public ?bool $requires_approval = null,
    ) {}
}
