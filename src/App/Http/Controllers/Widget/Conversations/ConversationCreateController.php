<?php

namespace App\Http\Controllers\Widget\Conversations;

use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Data\Conversation\WidgetConversationStoredData;
use Illuminate\Http\Request;

class ConversationCreateController
{
    public function __invoke(Request $request, ChatMemory $memory): WidgetConversationStoredData
    {
        return new WidgetConversationStoredData(
            id: $memory
                ->withContext(current_agent_from_widget(), (string) $request->header('X-SESSION-ID'))
                ->createConversation('New Chat', ConversationSource::Widget),
        );
    }
}
