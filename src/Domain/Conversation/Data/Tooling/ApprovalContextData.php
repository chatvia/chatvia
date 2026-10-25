<?php

namespace Domain\Conversation\Data\Tooling;

use Domain\Conversation\Services\ChatContext;

/**
 * What a paused turn must remember to resume after the person approves or
 * denies its gated tool calls: the session, the model, who was talking (for
 * the prompt and the governance audit), the visitor's message and how the
 * turn was configured. Stored as JSON in `tool_approval_checkpoints.context`;
 * {@see toArray()} and {@see fromArray()} keep that shape exactly.
 */
final readonly class ApprovalContextData
{
    public function __construct(
        public string $externalSessionId,
        public int $modelId,
        public ?string $chatterName,
        public ?int $chatterId,
        public ?int $actorUserId,
        public ?string $actorName,
        public string $userMessage,
        public ?string $queryLanguage,
        public ?string $source,
        public bool $streamSources,
        // The text streamed before the pause. Stored for the record; a resumed
        // turn starts a fresh answer.
        public string $preface,
        // The signed-in chatter's public id, so an approved call still tells
        // the webhook or MCP server which chatter is talking.
        public ?string $chatterExternalId = null,
    ) {}

    public static function fromContext(ChatContext $context): self
    {
        $request = $context->requestData;

        return new self(
            externalSessionId: $request->externalSessionId,
            modelId: $request->message->model->id,
            chatterName: $request->chatterName,
            chatterId: $request->chatterId,
            actorUserId: $request->actorUserId,
            actorName: $request->actorName,
            userMessage: $context->payload->message,
            queryLanguage: $context->queryLanguage,
            source: $request->source,
            streamSources: $request->streamSources,
            preface: $context->streamedMessage,
            chatterExternalId: $request->chatterExternalId,
        );
    }

    /**
     * @param  array<string, mixed>  $stored  a checkpoint's `context` column
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            externalSessionId: (string) ($stored['external_session_id'] ?? ''),
            modelId: (int) ($stored['model_id'] ?? 0),
            chatterName: $stored['chatter_name'] ?? null,
            chatterId: $stored['chatter_id'] ?? null,
            actorUserId: $stored['actor_user_id'] ?? null,
            actorName: $stored['actor_name'] ?? null,
            userMessage: (string) ($stored['user_message'] ?? ''),
            queryLanguage: $stored['query_language'] ?? null,
            source: $stored['source'] ?? null,
            streamSources: (bool) ($stored['stream_sources'] ?? false),
            preface: (string) ($stored['preface'] ?? ''),
            chatterExternalId: $stored['chatter_external_id'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'external_session_id' => $this->externalSessionId,
            'model_id' => $this->modelId,
            'chatter_name' => $this->chatterName,
            'chatter_id' => $this->chatterId,
            // Lets the resume attribute the approve/deny decision to whoever
            // is signed in, in the governance audit.
            'actor_user_id' => $this->actorUserId,
            'actor_name' => $this->actorName,
            'user_message' => $this->userMessage,
            'query_language' => $this->queryLanguage,
            'source' => $this->source,
            'stream_sources' => $this->streamSources,
            'preface' => $this->preface,
            'chatter_external_id' => $this->chatterExternalId,
        ];
    }
}
