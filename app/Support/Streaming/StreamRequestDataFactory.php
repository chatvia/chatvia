<?php

namespace App\Support\Streaming;

use App\Models\Agent;
use App\Models\Llm;
use App\Services\Chat\Data\StreamRequestData;
use App\Services\Chat\Data\StreamRequestMessageData;
use Illuminate\Http\Request;

class StreamRequestDataFactory
{
    /**
     * Build StreamRequestData for the main chat endpoint.
     */
    public function forChat(Request $request, Agent $agent, string $externalSessionId, string $conversationId): StreamRequestData
    {
        return new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(
                model: Llm::whereExternalName($request->input('model'))->first(),
                message: $request->input('message'),
                createdAt: $request->input('created_at'),
            ),
            externalSessionId: $externalSessionId,
            conversationId: $conversationId,
            saveUserMessageImmediately: $request->boolean('save_user_message_immediately'),
            streamSources: false,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            referrerUrl: $this->referrer($request),
            pageUrl: $request->url(),
            locale: $this->locale($request),
            requestId: $request->input('request_id'),
        );
    }

    /**
     * Build StreamRequestData for the widget chat endpoint.
     */
    public function forWidget(
        Request $request,
        Agent $agent,
        Llm $llm,
        string $sessionId,
        string $threadId,
        bool $leadCollected,
        bool $isPlayground,
    ): StreamRequestData {
        return new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(
                model: $llm,
                message: $request->input('content'),
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
            referrerUrl: $this->referrer($request),
            pageUrl: $request->input('page_url'),
            locale: $request->input('locale') ?: $request->header('accept-language'),
            requestId: $request->input('request_id') ?? $request->header('X-REQUEST-ID'),
        );
    }

    private function referrer(Request $request): ?string
    {
        return $request->header('referer') ?: $request->server('HTTP_REFERER');
    }

    private function locale(Request $request): ?string
    {
        $header = $request->header('accept-language');

        return $header ? substr($header, 0, 5) : null;
    }
}
