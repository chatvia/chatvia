<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Data\Account;

use Spatie\LaravelData\Data;

/**
 * API payload for creating a chatter (POST /api/v1/chatters).
 */
class CreateUserPayloadData extends Data
{
    public function __construct(
        /**
         * The user's name.
         */
        public string $name,
        /**
         * IDs of the agents this user is granted access to. The user can only
         * sign in to agents they are granted on. Provide at least one.
         *
         * @var list<string>
         */
        public array $agent_ids,
        /**
         * The user's login email. Must be unique among your chatters.
         */
        public ?string $email = null,
        /**
         * The user's password for email/password login. Minimum 8 characters.
         */
        public ?string $password = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'agent_ids' => ['required', 'array', 'min:1'],
            'agent_ids.*' => ['required', 'string'],
            // Unique among the caller's own chatters only (checked when the
            // chatter is created), so the answer never tells whether an email
            // is used anywhere else.
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
