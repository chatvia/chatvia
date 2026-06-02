<?php

namespace Domain\Chat\Data\Handoff;

use App\Workspace\Models\User;
use Domain\Chat\Models\ChatConversation;
use Spatie\LaravelData\Data;

class SendHandoffAdminMessageData extends Data
{
    public function __construct(
        public ChatConversation $conversation,
        public User $admin,
        public string $content,
    ) {}
}
