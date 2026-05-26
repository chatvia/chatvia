<?php

namespace Domain\Chat\Data;

use App\Auth\Models\User;
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
