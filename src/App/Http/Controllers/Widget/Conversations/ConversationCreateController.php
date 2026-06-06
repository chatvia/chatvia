<?php

namespace App\Http\Controllers\Widget\Conversations;

use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Data\Conversation\WidgetConversationStoredData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationCreateController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, ChatMemory $memory): JsonResponse|WidgetConversationStoredData
    {
        if (! $widgetVerification->verifyByAgentExternalId($request->header('X-AGENT-ID'), $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        return new WidgetConversationStoredData(
            id: $memory
                ->withContext(current_agent_from_widget(), (string) $request->header('X-SESSION-ID'))
                ->createConversation('New Chat', ConversationSource::Widget),
        );
    }
}
