<?php

namespace Domain\Conversation\Services\Streaming\Requests;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Llm\Models\Llm;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Turns an incoming chat request (hosted chat or widget) into the
 * {@see StreamRequestData} a turn runs on. The one sanctioned place where the
 * domain reads the HTTP request; everything after it gets plain values.
 */
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

        $chatter = $this->chatter($request);
        // Any authenticated user (chatter, owner, or teammate) is the audit actor.
        $actor = $request->user() instanceof User ? $request->user() : null;

        return new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(
                model: $this->resolveModel($agent),
                message: $request->input('message'),
                createdAt: $request->input('created_at'),
                attachments: $this->attachments($request),
            ),
            externalSessionId: $externalSessionId,
            conversationId: $conversationId,
            // The hosted /chat surface is reachable by chatters signed in via an
            // agent sign-in link, not just workspace owners/employees. Stamp the
            // chatter so their per-chatter limit applies here too; null for
            // owner/employee senders.
            chatterUserId: $chatter?->id,
            chatterName: $chatter?->name,
            chatterExternalId: $chatter?->external_id,
            actorUserId: $actor?->id,
            actorName: $actor?->name,
            saveUserMessageImmediately: $request->boolean('save_user_message_immediately'),
            streamSources: false,
            ipAddress: $request->ip(),
            country: $request->country(),
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
        ?User $chatter = null,
    ): StreamRequestData {
        $agent->loadMissing(['agentPrompt', 'llm']);

        return new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(
                model: $this->resolveModel($agent),
                message: $request->input('content'),
                attachments: $this->attachments($request),
            ),
            externalSessionId: $sessionId,
            conversationId: $threadId,
            // Only actual chatters carry a per-chatter limit. A protected widget
            // can resolve an owner/employee (e.g. previewing); never stamp them,
            // or their billing user_credits would be mistaken for a chatter cap.
            chatterUserId: $chatter instanceof User && $chatter->isChatter() ? $chatter->id : null,
            chatterName: $chatter instanceof User && $chatter->isChatter() ? $chatter->name : null,
            chatterExternalId: $chatter instanceof User && $chatter->isChatter() ? $chatter->external_id : null,
            // The audit actor is whoever is signed in — any type (owner/teammate
            // previewing, or a chatter); unlike chatterUserId it is not gated to
            // chatters and never affects billing.
            actorUserId: $chatter?->id,
            actorName: $chatter?->name,
            saveUserMessageImmediately: true,
            streamSources: false,
            streamLanguage: false,
            source: $isPlayground ? 'playground' : null,
            ipAddress: $request->ip(),
            country: $request->country(),
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

    /**
     * The authenticated chatter (web guard) on the hosted /chat surface, or
     * null when the sender is a workspace owner/employee. Only chatters carry a
     * per-chatter message limit and get addressed by name, so non-chatter
     * senders stay unstamped.
     */
    private function chatter(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User && $user->isChatter() ? $user : null;
    }

    private function referrer(Request $request): ?string
    {
        return $request->header('referer') ?: $request->server('HTTP_REFERER');
    }

    /**
     * Accept attachments either as a top-level `attachments` array of objects
     * (preferred) or as a JSON-encoded string for older clients/multipart
     * uploads. Every entry is normalised to the shape persisted on the
     * message metadata and surfaced to the LLM.
     *
     * @return array<int, array{id: string, url: string, filename: string, mime_type: string, size_bytes: int}>
     */
    private function attachments(Request $request): array
    {
        $raw = $request->input('attachments', []);

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entry) {
            if (! is_array($entry) || empty($entry['url']) || empty($entry['filename'])) {
                continue;
            }
            $out[] = [
                'id' => (string) ($entry['id'] ?? ''),
                'url' => (string) $entry['url'],
                'filename' => (string) $entry['filename'],
                'mime_type' => (string) ($entry['mime_type'] ?? 'application/octet-stream'),
                'size_bytes' => (int) ($entry['size_bytes'] ?? 0),
            ];
        }

        return $out;
    }

    private function locale(Request $request): ?string
    {
        $header = $request->header('accept-language');

        return $header ? substr($header, 0, 5) : null;
    }
}
