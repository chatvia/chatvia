<?php

namespace Domain\Chat\Services\Streaming\Requests;

use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;
use Domain\Chat\Services\Data\StreamRequestData;
use Domain\Chat\Services\Data\StreamRequestMessageData;
use Illuminate\Http\Request;
use RuntimeException;

class StreamRequestDataFactory
{
    /**
     * Build StreamRequestData for the main chat endpoint.
     */
    public function forChat(Request $request, Agent $agent, string $externalSessionId, string $conversationId): StreamRequestData
    {
        // Resolve the agent's library prompt + single LLM up front so
        // buildSystemPrompt() and downstream consumers don't trigger lazy
        // queries mid-stream.
        $agent->loadMissing(['agentPrompt', 'llm']);

        return new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(
                model: $this->resolveModel($agent),
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
        $agent->loadMissing(['agentPrompt', 'llm']);

        return new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(
                model: $this->resolveModel($agent),
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

    /**
     * Every agent has a single LLM configured at the agent level (`llm_id`).
     * Downstream code (StreamRequestMessageData, ChatService, LlmSource) treats
     * `model` as non-nullable, so fail fast with a clear error if an operator
     * hasn't picked one yet rather than letting a typed null surface deep in
     * the streaming pipeline.
     */
    private function resolveModel(Agent $agent): Llm
    {
        $llm = $agent->llm;

        if (! $llm) {
            throw new RuntimeException(
                "Agent {$agent->external_id} has no LLM configured. Pick a model in Setup → Model."
            );
        }

        return $llm;
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
