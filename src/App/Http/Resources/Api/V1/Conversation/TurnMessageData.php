<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Conversation;

use Spatie\LaravelData\Data;

/**
 * The assistant message inside a folded API turn ({@see MessageTurnData}).
 * Separate from {@see ConversationMessageData} because the turn's reply is
 * folded from the live stream, before the stored row (and its timestamp)
 * is readable.
 */
class TurnMessageData extends Data
{
    public function __construct(
        /**
         * The message's ID.
         */
        public string $id,
        /**
         * Always `assistant`.
         */
        public string $role,
        /**
         * The reply text.
         */
        public string $content,
    ) {}
}
