<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Dispatch;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Services\HttpToolDispatcher;
use Domain\Integration\Mcp\Services\McpToolDispatcher;
use Domain\Integration\Tool\Contracts\ToolDispatcherInterface;
use Domain\Integration\Tool\Data\ToolCallData;
use Domain\Integration\Tool\Data\ToolDispatchResult;

/**
 * The single `ToolDispatcherInterface` binding the rest of the app talks to.
 * It looks at the tool name and forwards to the right backend:
 *   - `mcp__{slug}__{tool}` → McpToolDispatcher
 *   - anything else        → HttpToolDispatcher (webhook AgentFunctions)
 *
 * Every result is held to the admin's longest tool result (Settings →
 * Security → Tool calls), so one large reply, such as a long page from a
 * connector, cannot use up the model's context and fail the turn.
 */
class RoutingToolDispatcher implements ToolDispatcherInterface
{
    /** Added where a result is cut, so the model knows it saw only the start. */
    public const string CUT_NOTE = '[The tool result was longer than %d characters and was cut here. Use what is above, and say so if the answer may be incomplete.]';

    /** The smallest limit honoured, whatever the setting says. */
    private const int MIN_RESULT_CHARS = 1000;

    public function __construct(
        private readonly HttpToolDispatcher $http,
        private readonly McpToolDispatcher $mcp,
    ) {}

    public function dispatch(Agent $agent, ToolCallData $call): ToolDispatchResult
    {
        $result = $call->name->isMcp()
            ? $this->mcp->dispatch($agent, $call)
            : $this->http->dispatch($agent, $call);

        return $this->capped($result);
    }

    private function capped(ToolDispatchResult $result): ToolDispatchResult
    {
        $limit = max(self::MIN_RESULT_CHARS, (int) config('chatvia.agent.tools.max_result_chars'));

        if (mb_strlen($result->text) <= $limit) {
            return $result;
        }

        return new ToolDispatchResult(
            mb_substr($result->text, 0, $limit)."\n\n".sprintf(self::CUT_NOTE, $limit),
            $result->artifacts,
            $result->isError,
        );
    }
}
