<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Api;

use Spatie\LaravelData\Data;

/**
 * The public API's request for a one-time sign-in link that opens an agent's
 * chat as a given chatter.
 */
class SignInLinkPayloadData extends Data
{
    public function __construct(
        /**
         * ID of the agent to sign the user into.
         */
        public string $agent_id,
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
            'agent_id' => ['required', 'string'],
            'user_id' => ['required', 'string'],
            'ttl_seconds' => ['sometimes', 'integer', 'min:60', 'max:3600'],
        ];
    }
}
