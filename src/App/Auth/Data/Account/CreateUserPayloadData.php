<?php

namespace App\Auth\Data\Account;

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
         * Optional login email. Leave null for a token-only user (no password
         * login). When set, it must be globally unique.
         */
        public ?string $email = null,
        /**
         * Required when `email` is set — the user's password for email/password
         * login. Ignored otherwise.
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
            'email' => ['sometimes', 'nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required_with:email', 'nullable', 'string', 'min:8', 'max:255'],
        ];
    }
}
