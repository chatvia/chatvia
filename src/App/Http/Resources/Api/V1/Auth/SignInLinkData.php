<?php

namespace App\Http\Resources\Api\V1\Auth;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * A ready-to-use sign-in link. Open `url` to log the end-user into the agent's
 * chat. The link works until `expires_at`.
 */
class SignInLinkData extends Data
{
    public function __construct(
        /**
         * The ready-to-open sign-in URL.
         */
        public string $url,
        /**
         * When the link expires.
         */
        public CarbonInterface $expires_at,
    ) {}
}
