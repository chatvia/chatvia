<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Messages;

use Domain\Conversation\Actions\Messages\RegenerateMessageAction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The visitor message to send again after {@see RegenerateMessageAction}
 * removed it and its answer from the history — answered to the chat client,
 * which re-streams the turn.
 */
#[TypeScript]
class RegeneratedTurnData extends Data
{
    public function __construct(
        public string $user_message_content,
        public string $created_at,
    ) {}
}
