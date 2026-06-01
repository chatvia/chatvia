<?php

namespace App\Http\Resources\Api\V1\Auth;

use App\Auth\Models\User;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * An end-user created through the API. Pass `id` as `user_id` when minting a
 * sign-in link.
 */
class UserData extends Data
{
    public function __construct(
        /**
         * The user's ID.
         */
        public string $id,
        /**
         * The user's name.
         */
        public string $name,
        /**
         * When the user was created.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->external_id,
            name: $user->name,
            created_at: $user->created_at,
        );
    }
}
