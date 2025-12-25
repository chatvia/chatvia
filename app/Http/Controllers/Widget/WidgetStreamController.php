<?php

namespace App\Http\Controllers\Widget;

use App\Models\Llm;
use App\Services\Chat\ChatStreamService;
use App\Services\Chat\Data\StreamRequestData;
use App\Services\Chat\Data\StreamRequestMessageData;
use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;

readonly class WidgetStreamController
{
    public function __construct(private ChatStreamService $chatStreamService) {}

    public function __invoke(Request $request, WidgetVerification $verification)
    {
        $sessionId = $request->header('X-SESSION-ID');
        $threadId = $request->header('X-THREAD-ID');

        $message = $request->input('content');

        $session = $verification->verifySessionExternalId($sessionId);

        abort_if(
            ! $session,
            '403',
            'Invalid session id'
        );

        $agent = current_agent_from_widget();
        $leadCollected = $agent
            ->leads()
            ->with('conversation.session')
            ->whereHas('conversation', function ($query) use ($session) {
                $query->where('chat_session_id', $session->id);
            })
            ->exists();

        /** @var Llm $llm */
        $llm = $agent->llms()->first();

        $referer = $request->header('referer') ?: $request->server('HTTP_REFERER');

        $isPlayground = str_starts_with($referer, route('agents.widget.preview', ['agent' => $agent]));

        return $this->chatStreamService->stream(
            new StreamRequestData(
                agent: $agent,
                message: new StreamRequestMessageData(
                    model: $llm,
                    message: $message,
                ),
                externalSessionId: $sessionId,
                conversationId: $threadId,
                saveUserMessageImmediately: true,
                streamSources: false,
                streamLanguage: false,
                canCollectLead: $agent->canCollectLeads(),
                leadAlreadyCollected: $leadCollected,
                source: $isPlayground ? 'playground' : null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                referrerUrl: $referer,
                pageUrl: $request->input('page_url'),
                locale: $request->input('locale') ?: $request->header('accept-language'),
            ),
        );
    }
}
