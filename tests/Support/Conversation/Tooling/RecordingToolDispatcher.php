<?php

declare(strict_types=1);

namespace Tests\Support\Conversation\Tooling;

use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Contracts\ToolDispatcherInterface;
use Domain\Integration\Tool\Data\ToolCallData;
use Domain\Integration\Tool\Data\ToolDispatchResult;

/**
 * A tool dispatcher that runs nothing: it keeps every call it was given, so a
 * test can tell what ran and what never did, and answers "result of {name}"
 * (or the fixed result it was made with).
 */
final class RecordingToolDispatcher implements ToolDispatcherInterface
{
    /** @var list<array{name: string, arguments: string, conversation: ?string, chatter: ?string}> */
    public array $calls = [];

    public function __construct(private readonly ?string $result = null) {}

    public function dispatch(Agent $agent, ToolCallData $call): ToolDispatchResult
    {
        $this->calls[] = [
            'name' => $call->name->value,
            'arguments' => $call->arguments,
            'conversation' => $call->conversationExternalId,
            'chatter' => $call->chatterExternalId,
        ];

        return ToolDispatchResult::text($this->result ?? "result of {$call->name}");
    }
}
