<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Auth;

use App\Identity\Chatter\Models\Chatter;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * A chatter (end-user) as the API returns it, with the access controls the
 * workspace manages: the monthly message cap and the deactivation switch.
 */
class ChatterData extends Data
{
    public function __construct(
        /**
         * The chatter's ID.
         */
        public string $id,
        /**
         * The chatter's name.
         */
        public string $name,
        /**
         * The chatter's email address.
         */
        public ?string $email,
        /**
         * Whether the account is switched off. A deactivated chatter cannot
         * sign in or send messages.
         */
        public bool $deactivated,
        /**
         * The chatter's monthly message cap. Null means no limit.
         */
        public ?int $messages_limit,
        /**
         * When the chatter was created.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(Chatter $chatter): self
    {
        return new self(
            id: $chatter->external_id,
            name: $chatter->name,
            email: $chatter->email,
            deactivated: $chatter->isDeactivated(),
            messages_limit: $chatter->messageLimit?->monthly_limit,
            created_at: $chatter->created_at,
        );
    }
}
