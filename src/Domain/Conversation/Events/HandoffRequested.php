<?php

declare(strict_types=1);

namespace Domain\Conversation\Events;

use Domain\Conversation\Models\Conversation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A visitor's conversation started waiting for a human operator. Fired once
 * per wait (a conversation already waiting or with a human does not fire it
 * again), so the staff who can take over hear about it.
 */
class HandoffRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Conversation $conversation,
    ) {}
}
