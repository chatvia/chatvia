<?php

namespace Domain\Agents\Data;

use Spatie\LaravelData\Data;

class SignInLinkPayloadData extends Data
{
    public function __construct(
        /**
         * ID of the user the link signs in.
         */
        public string $user_id,
        /**
         * How long the link stays valid, in seconds (60–3600). Defaults to one hour.
         */
        public ?int $ttl_seconds = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'user_id' => ['required', 'string'],
            'ttl_seconds' => ['sometimes', 'integer', 'min:60', 'max:3600'],
        ];
    }
}
