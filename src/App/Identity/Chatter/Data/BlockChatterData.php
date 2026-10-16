<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Data;

use App\Identity\Chatter\Enums\ChatterBlockNotice;
use App\Identity\Chatter\Models\Chatter;
use Domain\Security\Data\SecurityActorData;
use Spatie\LaravelData\Data;

/**
 * A request to block a chatter who abused an agent: who, whether to tell them
 * by email, the staff member doing it, the internal reason (shown on the
 * People page, never in the email) and the agent the abuse was on.
 */
class BlockChatterData extends Data
{
    public function __construct(
        public Chatter $chatter,
        public ChatterBlockNotice $notice,
        public SecurityActorData $actor,
        public ?string $reason = null,
        public ?int $agentId = null,
    ) {}
}
