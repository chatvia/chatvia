<?php

namespace Domain\Chat\Services\Streaming;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Llm;
use Domain\Chat\Services\Data\StreamRequestData;
use Domain\Chat\Services\Data\StreamRequestMessageData;
use Illuminate\Http\Request;

class StreamRequestDataFactory
{
    /**
     * Build StreamRequestData for the main chat endpoint.
     */
    public function forChat(Request $request, Agent $agent, string $externalSessionId, string $conversationId): StreamRequestData
    {
        // Resolve the agent's library prompt up front so buildSystemPrompt()
        // doesn't trigger a lazy query mid-stream.
        $agent->loadMissing('agentPrompt');

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
        string $sessionId,
        string $threadId,
        bool $isPlayground,
    ): StreamRequestData {
        $agent->loadMissing('agentPrompt');

        return new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(
                // The widget's model is the one selected in its Model
                // settings, persisted as the agent's active attached llm
                // (AgentModelsController + ToggleAgentLlmAction). Honor the
                // is_active pivot flag instead of grabbing an arbitrary row.
                // A templated widget instead uses the template's model list.
                model: $agent->agent_template_id
                    ? $agent->resolvedLlms()->first()
                    : $agent->activeLlms()->first(),
                message: $request->input('content'),
            ),
            externalSessionId: $sessionId,
            conversationId: $threadId,
            saveUserMessageImmediately: true,
            streamSources: false,
            streamLanguage: false,
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
