<?php

namespace App\Http\Controllers\Widget;

use App\Models\Llm;
use App\Services\Chat\ChatStreamService;
use App\Services\Chat\Data\StreamRequestData;
use App\Services\Chat\Data\StreamRequestMessageData;
use Illuminate\Http\Request;

class WidgetStreamController
{
    public function __construct(private readonly ChatStreamService $chatStreamService) {}

    public function __invoke(Request $request)
    {
        $sessionId = $request->header('x-session-id');
        $threadId = $request->header('x-thread-id');

        $message = $request->input('content');

        return $this->chatStreamService->stream(
            new StreamRequestData(
                agent: current_agent_from_widget(),
                message: new StreamRequestMessageData(
                    model: Llm::whereExternalName('llama-4')->first(),
                    message: $message,
                ),
                sessionId: $sessionId,
                conversationId: $threadId,
                saveUserMessageImmediately: true,
                streamSources: false,
                streamLanguage: false,
            ),
        );
    }
}
