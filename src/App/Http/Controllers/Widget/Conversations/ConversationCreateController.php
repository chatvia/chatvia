<?php

namespace App\Http\Controllers\Widget\Conversations;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Widget\Data\Conversation\WidgetConversationStoredData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "New chat" in the widget: starts an empty conversation in the visitor's
 * session.
 */
class ConversationCreateController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        Request $request,
        #[WidgetAgent] Agent $agent,
        StartConversationAction $startConversation,
    ): JsonResponse|WidgetConversationStoredData {
        $sessionId = $this->widgetSessionId($request);

        if ($sessionId === null) {
            return response()->json(['error' => 'Missing session'], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new WidgetConversationStoredData(
            id: $startConversation->execute(new ChatScopeData($agent, $sessionId), ConversationSource::Widget),
        );
    }
}
