<?php

namespace Domain\Conversation\Data\Handoff;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;

class SendHandoffAdminMessageData extends Data
{
    public function __construct(
        public Conversation $conversation,
        public User $admin,
        public string $content,
    ) {}
}
