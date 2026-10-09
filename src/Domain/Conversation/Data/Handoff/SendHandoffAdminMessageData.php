<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Handoff;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;

/**
 * An operator's reply into a conversation they hold.
 */
class SendHandoffAdminMessageData extends Data
{
    public function __construct(
        public Conversation $conversation,
        public User $admin,
        public string $content,
    ) {}
}
