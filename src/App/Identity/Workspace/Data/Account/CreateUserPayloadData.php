<?php

namespace App\Identity\Workspace\Data\Account;

use Spatie\LaravelData\Data;

class CreateUserPayloadData extends Data
{
    public function __construct(
        /**
         * The user's name.
         */
        public string $name,
        /**
         * ID of the agent this user is granted access to. The user can only
         * sign in to agents they are granted on.
         */
        public string $agent_id,
        /**
         * The user's login email. Must be globally unique.
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
            'agent_id' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
