<?php

namespace Domain\Auth\Data;

use Spatie\LaravelData\Data;

class CreateUserPayloadData extends Data
{
    public function __construct(
        /**
         * The user's name.
         */
        public string $name,
        /**
         * Optional contact email for the user.
         */
        public ?string $email = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ];
    }
}
