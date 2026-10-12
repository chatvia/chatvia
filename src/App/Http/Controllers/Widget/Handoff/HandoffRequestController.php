<?php

namespace App\Http\Controllers\Widget\Handoff;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Requests\Widget\WidgetHandoffRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Actions\Handoff\RequestHandoffAction;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\Data\Handoff\WidgetHandoffRequestResultData;
use Illuminate\Http\JsonResponse;

/**
 * The visitor asks to talk to a human operator ("Live chat").
 */
readonly class HandoffRequestController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        WidgetHandoffRequest $request,
        #[WidgetAgent] Agent $agent,
        RequestHandoffAction $requestHandoff,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetHandoffRequestResultData {
        $target = $request->toData();

        if ($target->sessionId === null) {
            return response()->json(['error' => 'Missing session'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $conversation = $findConversation->execute($target->conversationId, $target->sessionId, $agent, $this->widgetViewer($request));

        if (! $conversation instanceof Conversation) {
            return response()->json(['error' => 'Conversation not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return WidgetHandoffRequestResultData::make($requestHandoff->execute($conversation), $target->conversationId);
    }
}
