<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Tooling;

use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Models\Conversation;
use Domain\Integration\Tool\Data\ToolCallData;
use Domain\Integration\Tool\Dispatch\ToolCallerIdentity;
use Illuminate\Support\Str;
use WeakMap;

/**
 * Builds the {@see ToolCallData} a dispatcher receives for one call of a
 * turn, with who is talking ({@see ToolCallerIdentity}). The conversation id
 * comes from the client (the chat's thread id), so it is passed on only when
 * that conversation belongs to the turn's own session of the agent: a
 * receiver that scopes its writes by `X-Chatvia-Conversation-Id` can then
 * never be pointed at someone else's conversation.
 */
final class ToolCallFactory
{
    /**
     * The checked conversation id per turn, so a turn with several tool calls
     * looks it up once.
     *
     * @var WeakMap<StreamRequestData, string|null>
     */
    private WeakMap $checked;

    public function __construct()
    {
        $this->checked = new WeakMap;
    }

    public function make(StreamRequestData $request, string $name, string $arguments): ToolCallData
    {
        return ToolCallData::named($name, $arguments, $this->conversationId($request), $request->chatterExternalId);
    }

    private function conversationId(StreamRequestData $request): ?string
    {
        if (! $this->checked->offsetExists($request)) {
            $this->checked[$request] = $this->belongsToTurn($request) ? $request->conversationId : null;
        }

        return $this->checked[$request];
    }

    private function belongsToTurn(StreamRequestData $request): bool
    {
        // Both ids are uuid columns: anything else can match nothing.
        return Str::isUuid($request->conversationId) && Str::isUuid($request->externalSessionId) && Conversation::query()
            ->inSession($request->externalSessionId, (int) $request->agent->id)
            ->forAgent((int) $request->agent->id)
            ->where('external_id', $request->conversationId)
            ->exists();
    }
}
