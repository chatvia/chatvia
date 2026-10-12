<?php

namespace App\Http\Resources\Api\V1\Auth;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * A person as the API returns it: a chatter created through the API, or a
 * teammate. Pass a chatter's `id` as `user_id` when minting a sign-in link.
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

    public static function fromModel(Chatter|User $user): self
    {
        return new self(
            id: $user->external_id,
            name: $user->name,
            created_at: $user->created_at,
        );
    }
}
