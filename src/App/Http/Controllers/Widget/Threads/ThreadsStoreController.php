<?php

namespace App\Http\Controllers\Widget\Threads;

use App\Widget\WidgetVerification;
use Domain\Chat\Enums\ConversationSource;
use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Widget\Data\Thread\WidgetThreadStoredData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThreadsStoreController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, ChatMemory $memory): JsonResponse|WidgetThreadStoredData
    {
        if (! $widgetVerification->verifyByAgentExternalId($request->header('X-AGENT-ID'), $request)) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        return new WidgetThreadStoredData(
            id: $memory
                ->setAgent(current_agent_from_widget())
                ->setExternalSessionId((string) $request->header('X-SESSION-ID'))
                ->createConversation('New Chat', ConversationSource::Widget),
        );
    }
}
