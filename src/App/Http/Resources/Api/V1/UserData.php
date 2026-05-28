<?php

namespace App\Http\Resources\Api\V1;

use App\Auth\Models\User;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Support\Enums\CreatedVia;

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
         * Optional contact email for the user.
         */
        public ?string $contact_email,
        /**
         * Origin of the user — `external` when created through the API,
         * `internal` otherwise.
         */
        public CreatedVia $created_via,
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
            contact_email: $user->contact_email,
            created_via: $user->created_via,
            created_at: $user->created_at,
        );
    }
}
