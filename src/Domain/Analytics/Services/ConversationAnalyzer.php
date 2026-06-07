<?php

namespace Domain\Analytics\Services;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\AnalyzeMessageAction;
use Domain\Analytics\Data\ConversationAnalysisData;
use Domain\Analytics\Enums\SentimentEnum;
use Domain\Analytics\Enums\ToneEnum;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Llm\Contracts\LlmProviderInterface;
use Illuminate\Support\Collection;

/**
 * One structured LLM call that, over a full conversation transcript, returns
 * every existing topic that applies + emergent suggestions, plus sentiment,
 * tone and language. Uses the provider's json_schema response format so the
 * fields always come back valid (enums for sentiment/tone), replacing the old
 * trio of TopicAnalysis / ToneAnalysis / LanguageDetection.
 */
class ConversationAnalyzer
{
    public function __construct(
        private readonly LlmProviderInterface $provider,
    ) {}

    public function analyze(string $transcript, Agent $agent): ConversationAnalysisData
    {
        /** @var Collection<int, Topic> $topics */
        $topics = $agent->topics()->get();

        $response = $this->provider->completion([
            'model' => config('chatvia.ai.models.topic'),
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'conversation_analysis',
                    'schema' => $this->schema(),
                ],
            ],
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt($topics, $this->pendingLabels($agent))],
                ['role' => 'user', 'content' => $transcript],
            ],
        ]);

        // completion() returns a Laravel HTTP Response; pull the assistant content
        // explicitly rather than relying on its string coercion (the raw body).
        $content = $response->json('choices.0.message.content');

        return $this->parse(
            is_string($content) ? $content : null,
            allowedIds: $topics->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            existingNames: $topics->pluck('topic')->map(fn ($n) => mb_strtolower(trim((string) $n)))->all(),
        );
    }

    /**
     * Per-message variant: sentiment + tone only, no topic resolution. Used by
     * {@see AnalyzeMessageAction} on the per-message
     * analysis path. Returns the same structured-output shape minus topic_ids
     * and language, so per-message and per-conversation analytics can never
     * drift on the same input.
     */
    public function analyzeMessage(string $text): ConversationAnalysisData
    {
        $response = $this->provider->completion([
            'model' => config('chatvia.ai.models.tone'),
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'message_analysis',
                    'schema' => $this->messageSchema(),
                ],
            ],
            'messages' => [
                ['role' => 'system', 'content' => $this->messageSystemPrompt()],
                ['role' => 'user', 'content' => $text],
            ],
        ]);

        $content = $response->json('choices.0.message.content');
        $data = $this->decodeJson(is_string($content) ? $content : null);

        return new ConversationAnalysisData(
            topicIds: [],
            suggestedTopics: [],
            sentiment: SentimentEnum::tryFrom(strtolower((string) ($data['sentiment'] ?? ''))) ?? SentimentEnum::NEUTRAL,
            tone: ToneEnum::tryFrom(strtolower((string) ($data['tone'] ?? ''))) ?? ToneEnum::NEUTRAL,
            language: null,
        );
    }

    /**
     * Per-message schema: sentiment + tone only. Reads ToneEnum/SentimentEnum
     * dynamically so adding a new value to either enum reaches both
     * `analyze()` and `analyzeMessage()` without a parallel update.
     *
     * @return array<string, mixed>
     */
    private function messageSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['sentiment', 'tone'],
            'properties' => [
                'sentiment' => [
                    'type' => 'string',
                    'description' => "The visitor's overall sentiment.",
                    'enum' => array_column(SentimentEnum::cases(), 'value'),
                ],
                'tone' => [
                    'type' => 'string',
                    'description' => "The visitor's overall tone.",
                    'enum' => ToneEnum::values(),
                ],
            ],
        ];
    }

    private function messageSystemPrompt(): string
    {
        return <<<'PROMPT'
        You analyse the sentiment and tone of a single visitor message to a
        customer-support agent. Return only the structured JSON shape — do not
        explain. Treat simple questions ("how do I X?", "what is your pricing?")
        as NEUTRAL by default. Only mark Confused when the visitor explicitly
        signals they are lost or struggling to understand after prior attempts.
        PROMPT;
    }

    /**
     * The structured-output contract. The provider constrains the model to emit
     * exactly this shape, so sentiment/tone are always one of the enum values
     * and language is always present.
     *
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['topic_ids', 'suggested_topics', 'sentiment', 'tone', 'language'],
            'properties' => [
                'topic_ids' => [
                    'type' => 'array',
                    'description' => 'IDs from EXISTING TOPICS that clearly apply (a conversation may have several); empty if none fit.',
                    'items' => ['type' => 'integer'],
                ],
                'suggested_topics' => [
                    'type' => 'array',
                    'description' => 'Short Title Case labels (1-4 words) for distinct themes no existing topic covers; empty if all covered.',
                    'items' => ['type' => 'string'],
                ],
                'sentiment' => [
                    'type' => 'string',
                    'description' => "The visitor's overall sentiment.",
                    'enum' => array_column(SentimentEnum::cases(), 'value'),
                ],
                'tone' => [
                    'type' => 'string',
                    'description' => "The visitor's overall tone.",
                    'enum' => ToneEnum::values(),
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'ISO 639-1 two-letter language code of the conversation, e.g. en, es, fr, ar.',
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, Topic>  $topics
     * @param  string[]  $pendingLabels
     */
    private function systemPrompt(Collection $topics, array $pendingLabels): string
    {
        $topicList = $topics->isEmpty()
            ? '(none configured)'
            : $topics->map(fn ($t): string => "  {$t->id}: {$t->topic}".($t->description ? " — {$t->description}" : ''))->implode("\n");

        $pending = empty($pendingLabels) ? '(none)' : implode(', ', $pendingLabels);

        return <<<PROMPT
        Analyze the following conversation transcript between an AI agent and a visitor.
        Detect the language, the visitor's sentiment and tone, which of the agent's
        existing topics apply, and any emergent themes no existing topic covers.

        EXISTING TOPICS (id: name):
        {$topicList}

        PENDING SUGGESTED TOPICS (reuse one of these labels verbatim if it fits, instead of inventing a near-duplicate):
        {$pending}

        Pick every existing topic that genuinely applies. Only suggest a new topic
        when no existing one fits that theme; keep suggested labels generic and
        reusable across conversations, not specific to this one.
        PROMPT;
    }

    /**
     * @param  int[]  $allowedIds
     * @param  string[]  $existingNames  lower-cased existing topic names
     */
    private function parse(?string $content, array $allowedIds, array $existingNames): ConversationAnalysisData
    {
        $data = $this->decodeJson($content);

        if ($data === null) {
            // Malformed / empty response — record nothing rather than guessing.
            return new ConversationAnalysisData([], [], SentimentEnum::NEUTRAL, ToneEnum::NEUTRAL, null);
        }

        $topicIds = collect($data['topic_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn ($id): bool => in_array($id, $allowedIds, true))
            ->unique()
            ->values()
            ->all();

        $suggested = collect($data['suggested_topics'] ?? [])
            ->filter(fn ($l): bool => is_string($l) && trim($l) !== '')
            ->map(fn ($l): string => trim($l))
            ->reject(fn ($l): bool => in_array(mb_strtolower($l), $existingNames, true))
            ->unique(fn ($l) => mb_strtolower($l))
            ->values()
            ->all();

        return new ConversationAnalysisData(
            topicIds: $topicIds,
            suggestedTopics: $suggested,
            sentiment: SentimentEnum::tryFrom(strtolower((string) ($data['sentiment'] ?? ''))) ?? SentimentEnum::NEUTRAL,
            tone: ToneEnum::tryFrom(strtolower((string) ($data['tone'] ?? ''))) ?? ToneEnum::NEUTRAL,
            language: $this->normalizeLanguage($data['language'] ?? null),
        );
    }

    /**
     * Decode the JSON object in the response. With structured output the content
     * is already a clean JSON object; the substring guard just tolerates a model
     * that wraps it in prose or fences.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJson(?string $content): ?array
    {
        if (! is_string($content) || $content === '') {
            return null;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $data = json_decode(substr($content, $start, $end - $start + 1), true);

        return is_array($data) ? $data : null;
    }

    private function normalizeLanguage(mixed $language): ?string
    {
        if (! is_string($language)) {
            return null;
        }

        $code = preg_replace('/[^a-z]/', '', strtolower(trim($language)));

        return strlen((string) $code) === 2 ? $code : null;
    }

    /**
     * Distinct emergent labels already pending for this agent — fed back to the
     * model so it reuses an existing label instead of coining a near-duplicate.
     *
     * @return string[]
     */
    private function pendingLabels(Agent $agent): array
    {
        return ConversationTopic::query()
            ->whereNotNull('suggested_label')
            ->whereHas('conversation.session', fn ($q) => $q->where('agent_id', $agent->id))
            ->distinct()
            ->pluck('suggested_label')
            ->all();
    }
}
