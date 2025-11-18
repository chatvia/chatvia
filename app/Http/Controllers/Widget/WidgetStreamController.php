<?php

namespace App\Http\Controllers\Widget;

use App\Models\Llm;
use App\Services\Chat\ChatStreamService;
use App\Services\Chat\Data\StreamRequestData;
use App\Services\Chat\Data\StreamRequestMessageData;
use Illuminate\Http\Request;

readonly class WidgetStreamController
{
    public function __construct(private ChatStreamService $chatStreamService) {}

    public function __invoke(Request $request)
    {
        $sessionId = $request->header('x-session-id');
        $threadId = $request->header('x-thread-id');

        $message = $request->input('content');

        $agent = current_agent_from_widget();
        $leadCollected = $agent
            ->leads()
            ->with('conversation.session')
            ->whereHas('conversation', function ($query) use ($sessionId) {
                $query->where('chat_session_id', $sessionId);
            })
            ->exists();

        /** @var Llm $llm */
        $llm = $agent->llms()->first();

        $referer = $request->header('referer') ?: $request->server('HTTP_REFERER');

        $persistMessage = ! str_starts_with($referer, route('agents.widget.preview', ['agent' => $agent->id]));

        return $this->chatStreamService->stream(
            new StreamRequestData(
                agent: $agent,
                message: new StreamRequestMessageData(
                    model: $llm,
                    message: $message,
                ),
                sessionId: $sessionId,
                conversationId: $threadId,
                saveUserMessageImmediately: true, // TODO: this is a work-around, needs to be fixed
                persistMessage: $persistMessage,
                streamSources: false,
                streamLanguage: false,
                canCollectLead: $agent->canCollectLeads(),
                leadAlreadyCollected: $leadCollected,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
        );
    }
}
