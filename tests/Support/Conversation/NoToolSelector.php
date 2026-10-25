<?php

declare(strict_types=1);

namespace Tests\Support\Conversation;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;

/**
 * Offers no tools on any turn, so a streaming source runs without the tool
 * catalog or its embeddings.
 */
final class NoToolSelector extends ToolSelector
{
    public function __construct()
    {
        // No parent constructor: nothing is looked up.
    }

    public function selectForTurn(Agent $agent, string $userMessage, ?string $conversationId): array
    {
        return [];
    }

    public function selectForVisitorTurn(Agent $agent, string $userMessage, ?string $conversationId): array
    {
        return [];
    }
}
