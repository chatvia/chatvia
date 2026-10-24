<?php

namespace Domain\Conversation\Services\Streaming\Requests;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentModel;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Data\Voice\VoiceCallerData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageOrigin;
use Domain\Llm\Models\Llm;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns an incoming chat request (hosted chat or widget) into the
 * {@see StreamRequestData} a turn runs on, or into the {@see VoiceCallerData}
 * of a voice call. The one sanctioned place where the
 * domain reads the HTTP request; everything after it gets plain values.
 */
class StreamRequestDataFactory
{
    public function __construct(
        private readonly AgentModel $models,
    ) {}

    /**
     * Build StreamRequestData for the main chat endpoint.
     */
    public function forChat(Request $request, Agent $agent, string $externalSessionId, string $conversationId): StreamRequestData
    {
        // Resolve the agent's library prompt + single LLM up front so
        // buildSystemPrompt() and downstream consumers don't trigger lazy
        // queries mid-stream.
        $agent->loadMissing(['agentPrompt', 'llm']);

        // The hosted chat's viewer: a chatter, or staff opening the chat from
        // the dashboard (UseSignedInChatGuard makes their guard the default).
        $account = $request->user();
        $chatter = $account instanceof Chatter ? $account : null;
        $staff = $account instanceof User ? $account : null;

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
            // Stamp the chatter so their per-chatter limit applies; null for
            // staff senders, who are recorded as the audit actor instead.
            chatterId: $chatter?->id,
            chatterName: $chatter?->name,
            chatterExternalId: $chatter?->external_id,
            actorUserId: $staff?->id,
            actorName: $chatter?->name ?? $staff?->name,
            saveUserMessageImmediately: $request->boolean('save_user_message_immediately'),
            // The sources list under each answer (labels and public links
            // only), when the agent shows it.
            streamSources: $agent->showsSources(),
            streamReasoning: true,
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
        Chatter|User|null $account = null,
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
            // Only chatters carry a per-chatter limit. A protected widget can
            // resolve staff (a dashboard preview); they are the audit actor
            // instead, so the workspace's own use never counts as a chatter's.
            chatterId: $account instanceof Chatter ? $account->id : null,
            chatterName: $account instanceof Chatter ? $account->name : null,
            chatterExternalId: $account instanceof Chatter ? $account->external_id : null,
            actorUserId: $account instanceof User ? $account->id : null,
            actorName: $account?->name,
            saveUserMessageImmediately: true,
            // The sources list under each answer (labels and public links
            // only), when the agent shows it.
            streamSources: $agent->showsSources(),
            streamLanguage: false,
            streamReasoning: true,
            source: $isPlayground ? MessageOrigin::Playground->value : null,
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
     * Build StreamRequestData for the public API's message endpoint. The
     * conversation (and its session, possibly carrying a chatter) was created
     * up front by CreateApiConversationAction, so the viewer comes from the
     * session row, and the bearer token's account is the audit actor.
     */
    public function forApi(Request $request, Agent $agent, string $externalSessionId, string $conversationId, ?Chatter $chatter): StreamRequestData
    {
        $agent->loadMissing(['agentPrompt', 'llm']);

        /** @var User|null $staff */
        $staff = $request->user();

        return new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(
                model: $this->resolveModel($agent),
                message: $request->input('message'),
            ),
            externalSessionId: $externalSessionId,
            conversationId: $conversationId,
            // Stamp the session's chatter so their per-chatter limit applies
            // and tools see their id; the API caller stays the audit actor.
            chatterId: $chatter?->id,
            chatterName: $chatter?->name,
            chatterExternalId: $chatter?->external_id,
            actorUserId: $staff?->id,
            actorName: $chatter?->name ?? $staff?->name,
            saveUserMessageImmediately: true,
            streamSources: $agent->showsSources(),
            streamLanguage: false,
            source: ConversationSource::Api->value,
            ipAddress: $request->ip(),
            country: $request->country(),
            userAgent: $request->userAgent(),
            locale: $this->locale($request),
            // Replay snapshots are keyed globally by request id, so scope the
            // client's value to this conversation before it reaches the store.
            requestId: filled($request->input('request_id'))
                ? 'api.'.$conversationId.'.'.$request->input('request_id')
                : null,
        );
    }

    /**
     * Who asks for a voice call on the hosted chat: the same viewer and
     * request facts as a hosted-chat message.
     */
    public function voiceForChat(Request $request, Agent $agent, string $externalSessionId, string $conversationId): VoiceCallerData
    {
        $account = $request->user();
        $chatter = $account instanceof Chatter ? $account : null;
        $staff = $account instanceof User ? $account : null;

        return new VoiceCallerData(
            agent: $agent,
            surface: ConversationSource::HostedChat,
            sessionId: $externalSessionId,
            conversationId: $conversationId,
            chatterId: $chatter?->id,
            chatterName: $chatter?->name,
            chatterExternalId: $chatter?->external_id,
            actorUserId: $staff?->id,
            actorName: $chatter?->name ?? $staff?->name,
            ipAddress: $request->ip(),
            country: $request->country(),
            userAgent: $request->userAgent(),
            referrerUrl: $this->referrer($request),
            pageUrl: $request->headers->get('referer'),
            locale: $this->locale($request),
        );
    }

    /**
     * Who asks for a voice call in the widget: the same account rules and
     * request facts as a widget message.
     */
    public function voiceForWidget(
        Request $request,
        Agent $agent,
        string $sessionId,
        string $conversationId,
        bool $isPlayground,
        Chatter|User|null $account = null,
    ): VoiceCallerData {
        return new VoiceCallerData(
            agent: $agent,
            surface: ConversationSource::Widget,
            sessionId: $sessionId,
            conversationId: $conversationId,
            chatterId: $account instanceof Chatter ? $account->id : null,
            chatterName: $account instanceof Chatter ? $account->name : null,
            chatterExternalId: $account instanceof Chatter ? $account->external_id : null,
            actorUserId: $account instanceof User ? $account->id : null,
            actorName: $account?->name,
            source: $isPlayground ? MessageOrigin::Playground->value : null,
            ipAddress: $request->ip(),
            country: $request->country(),
            userAgent: $request->userAgent(),
            referrerUrl: $this->referrer($request),
            pageUrl: $request->input('page_url'),
            locale: $request->input('locale') ?: $request->header('accept-language'),
        );
    }

    /**
     * The model the agent answers with ({@see AgentModel}): its own, or the
     * default one while it has none. Downstream code (StreamRequestMessageData,
     * ChatService, LlmSource) treats `model` as non-nullable, so fail fast
     * with a clear error when no model can answer at all, rather than letting
     * a typed null surface deep in the streaming pipeline.
     */
    private function resolveModel(Agent $agent): Llm
    {
        return $this->models->for($agent) ?? throw new RuntimeException(
            "Agent {$agent->external_id} has no model, and no model can answer. Add a model in the admin panel (Settings → AI models)."
        );
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

        if (is_scalar($raw)) {
            $decoded = json_decode((string) $raw, true);
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

        return $header ? Str::substr($header, 0, 5) : null;
    }
}
