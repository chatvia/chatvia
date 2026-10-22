<?php

namespace Domain\Analytics\Services;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Analysis\AnalyzeMessageAction;
use Domain\Analytics\Data\ConversationAnalysisData;
use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
use Domain\Analytics\Queries\Topics\SuggestedTopicsQuery;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ResponseSchemaData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Services\StructuredCompletion;
use Domain\Llm\Services\TaskModels;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;

/**
 * One structured LLM call that, over a full conversation transcript, returns
 * every existing topic that applies + emergent suggestions, plus sentiment,
 * tone and language. Uses the provider's json_schema response format, which
 * keeps sentiment/tone inside their enums when the model follows the schema.
 * The prompts live in resources/prompts/analytics.
 *
 * When the response cannot be parsed (empty, cut off, not JSON, or an HTTP
 * error from the provider) both methods return null instead of a guessed
 * "neutral" result, so callers save nothing and the item can be analyzed
 * again later.
 */
class ConversationAnalyzer
{
    /** Most pending suggestion labels shown to the model. */
    private const int MAX_PENDING_LABELS = 50;

    public function __construct(
        private readonly StructuredCompletion $completion,
        private readonly TaskModels $models,
    ) {}

    public function analyze(string $transcript, Agent $agent): ?ConversationAnalysisData
    {
        /** @var Collection<int, Topic> $topics */
        $topics = $agent->topics()->get();

        $data = $this->ask(new ChatRequestData(
            model: $this->models->model(LlmTask::ConversationAnalysis),
            tag: AiCallTag::ConversationAnalysis,
            messages: [
                ChatMessageData::system($this->conversationPrompt($topics, $this->pendingLabels($agent))),
                ChatMessageData::user($transcript),
            ],
            responseSchema: new ResponseSchemaData('conversation_analysis', $this->conversationSchema(), strict: false),
        ));

        if ($data === null) {
            return null;
        }

        return new ConversationAnalysisData(
            topicIds: $this->allowedTopicIds($data['topic_ids'] ?? [], $topics),
            suggestedTopics: $this->newSuggestions($data['suggested_topics'] ?? [], $topics),
            sentiment: $this->sentiment($data),
            tone: $this->tone($data),
            language: $this->normalizeLanguage($data['language'] ?? null),
        );
    }

    /**
     * Per-message variant: sentiment + tone only, no topic resolution. Used by
     * {@see AnalyzeMessageAction} on the per-message analysis path. Returns the
     * same structured-output shape minus topic_ids and language, so
     * per-message and per-conversation analytics can never drift on the same
     * input. Null when the response cannot be parsed.
     */
    public function analyzeMessage(string $text): ?ConversationAnalysisData
    {
        $data = $this->ask(new ChatRequestData(
            model: $this->models->model(LlmTask::ConversationAnalysis),
            tag: AiCallTag::ConversationAnalysis,
            messages: [
                ChatMessageData::system(trim(prompt('analytics/message-analysis'))),
                ChatMessageData::user($text),
            ],
            responseSchema: new ResponseSchemaData('message_analysis', $this->messageSchema(), strict: false),
        ));

        if ($data === null) {
            return null;
        }

        return new ConversationAnalysisData(
            topicIds: [],
            suggestedTopics: [],
            sentiment: $this->sentiment($data),
            tone: $this->tone($data),
            language: null,
        );
    }

    /**
     * The decoded JSON reply, or null when there is none. A provider HTTP
     * error counts as "no answer" so the item stays pending for a retry.
     *
     * @return array<array-key, mixed>|null
     */
    private function ask(ChatRequestData $request): ?array
    {
        try {
            return $this->completion->send($request)->data;
        } catch (RequestException) {
            return null;
        }
    }

    /**
     * @param  Collection<int, Topic>  $topics
     * @param  list<string>  $pendingLabels
     */
    private function conversationPrompt(Collection $topics, array $pendingLabels): string
    {
        $topicList = $topics->isEmpty()
            ? '(none configured)'
            : $topics->map(fn (Topic $topic): string => "  {$topic->id}: {$topic->topic}".($topic->description ? " — {$topic->description}" : ''))->implode("\n");

        return trim(prompt('analytics/conversation-analysis', [
            'topicList' => $topicList,
            'pendingLabels' => $pendingLabels === [] ? '(none)' : implode(', ', $pendingLabels),
        ]));
    }

    /**
     * Per-message schema: sentiment + tone only.
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
                'sentiment' => $this->sentimentProperty(),
                'tone' => $this->toneProperty(),
            ],
        ];
    }

    /**
     * The structured-output contract. The provider constrains the model to emit
     * exactly this shape, so sentiment/tone are always one of the enum values
     * and language is always present.
     *
     * @return array<string, mixed>
     */
    private function conversationSchema(): array
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
                'sentiment' => $this->sentimentProperty(),
                'tone' => $this->toneProperty(),
                'language' => [
                    'type' => 'string',
                    'description' => 'ISO 639-1 two-letter language code of the conversation, e.g. en, es, fr, ar.',
                ],
            ],
        ];
    }

    /**
     * Read from the enum, so a new sentiment value reaches both schemas.
     *
     * @return array<string, mixed>
     */
    private function sentimentProperty(): array
    {
        return [
            'type' => 'string',
            'description' => "The visitor's overall sentiment.",
            'enum' => array_column(Sentiment::cases(), 'value'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toneProperty(): array
    {
        return [
            'type' => 'string',
            'description' => "The visitor's overall tone.",
            'enum' => Tone::values(),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function sentiment(array $data): Sentiment
    {
        return Sentiment::tryFrom(strtolower((string) ($data['sentiment'] ?? ''))) ?? Sentiment::Neutral;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function tone(array $data): Tone
    {
        return Tone::tryFrom(strtolower((string) ($data['tone'] ?? ''))) ?? Tone::Neutral;
    }

    /**
     * Only ids of topics the agent actually has.
     *
     * @param  Collection<int, Topic>  $topics
     * @return list<int>
     */
    private function allowedTopicIds(mixed $ids, Collection $topics): array
    {
        $allowed = $topics->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        return collect(is_array($ids) ? $ids : [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => in_array($id, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Suggested labels, trimmed and de-duplicated, minus any that match an
     * existing topic name.
     *
     * @param  Collection<int, Topic>  $topics
     * @return list<string>
     */
    private function newSuggestions(mixed $labels, Collection $topics): array
    {
        $existingNames = $topics->pluck('topic')->map(fn (mixed $name): string => mb_strtolower(trim((string) $name)))->all();

        return collect(is_array($labels) ? $labels : [])
            ->filter(fn (mixed $label): bool => is_string($label) && filled($label))
            ->map(fn (string $label): string => trim($label))
            ->reject(fn (string $label): bool => in_array(mb_strtolower($label), $existingNames, true))
            ->unique(fn (string $label): string => mb_strtolower($label))
            ->values()
            ->all();
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
     * Only the {@see self::MAX_PENDING_LABELS} most frequent of the suggestion
     * window count: the list goes into every analysis prompt, and labels are
     * free text, so their number has no natural limit.
     *
     * @return list<string>
     */
    private function pendingLabels(Agent $agent): array
    {
        return ConversationTopic::query()
            ->whereNotNull('suggested_label')
            ->whereIn('chat_conversation_id', SuggestedTopicsQuery::recentConversationIds($agent))
            ->groupBy('suggested_label')
            ->orderByRaw('count(*) desc')
            ->orderBy('suggested_label')
            ->limit(self::MAX_PENDING_LABELS)
            ->pluck('suggested_label')
            ->all();
    }
}
