<?php

namespace App\Http\Controllers\Widget\Handoff;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Requests\Widget\WidgetHandoffRequest;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Actions\Handoff\RequestHandoffAction;
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
        RequestHandoffAction $requestHandoff,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetHandoffRequestResultData {
        $target = $request->toData();

        if ($target->sessionId === null) {
            return response()->json(['error' => 'Missing session'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $conversation = $findConversation->execute($target->conversationId, $target->sessionId, $this->widgetAgent());

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return WidgetHandoffRequestResultData::make($requestHandoff->execute($conversation), $target->conversationId);
    }
}
