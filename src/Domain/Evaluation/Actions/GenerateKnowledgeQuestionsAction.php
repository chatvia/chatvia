<?php

namespace Domain\Evaluation\Actions;

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Data\KnowledgeQuestionCandidateData;
use Domain\Evaluation\Enums\EvalQuestionSource;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Services\KnowledgeSourceLabel;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ResponseSchemaData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Exceptions\LlmCallFailedException;
use Domain\Llm\Services\ProviderErrorReason;
use Domain\Llm\Services\StructuredCompletion;
use Domain\Llm\Services\TaskModels;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Writes eval questions with AI for the "From knowledge" picker: a random
 * sample of the agent's chunks goes to the model for quick tasks (the one
 * that suggests conversation starters), which answers with questions that
 * each name the passage they come from. Every question comes back as a
 * picker candidate tied to that chunk, so adding it builds the same AI-judge
 * case as a mined question ({@see CreateEvalCasesFromQuestionsAction}: the
 * chunk's text is the reference answer). Nothing is saved here; the person
 * ticks the questions to keep. Questions that name no passage of the sample,
 * are too long for a case name, or repeat another are dropped.
 */
class GenerateKnowledgeQuestionsAction
{
    /** Chunks sent to the model per click: enough variety for ten questions in one call. */
    private const int SAMPLE_SIZE = 12;

    private const int MAX_COUNT = 10;

    /** The question doubles as the case name, a 255-char column. */
    private const int MAX_QUESTION_LENGTH = 255;

    public function __construct(
        private readonly StructuredCompletion $completion,
        private readonly TaskModels $models,
        private readonly ProviderErrorReason $reasons,
    ) {}

    /**
     * @return Collection<int, KnowledgeQuestionCandidateData> up to $count questions; empty when the agent has no chunks
     *
     * @throws LlmCallFailedException when the model's provider cannot be reached or refuses the call
     */
    public function execute(Agent $agent, int $count): Collection
    {
        $count = max(1, min(self::MAX_COUNT, $count));
        $sample = Chunk::query()->randomContentForAgent($agent, self::SAMPLE_SIZE);

        if ($sample === []) {
            return new Collection;
        }

        $existing = EvalCase::query()->forAgent($agent->id)->pluck('input')
            ->mapWithKeys(fn (string $input): array => [Str::lower(Str::trim($input)) => true]);

        return $this->questions($this->ask($sample, $count))
            ->filter(fn (array $item): bool => isset($sample[$item['passage'] - 1]) && Str::length($item['question']) <= self::MAX_QUESTION_LENGTH)
            ->unique(fn (array $item): string => Str::lower($item['question']))
            ->take($count)
            ->values()
            ->map(function (array $item, int $i) use ($sample, $existing): KnowledgeQuestionCandidateData {
                $chunk = $sample[$item['passage'] - 1];

                return new KnowledgeQuestionCandidateData(
                    key: "ai:{$chunk['chunk_id']}:{$i}",
                    type: EvalQuestionSource::Chunk,
                    id: $chunk['chunk_id'],
                    question: $item['question'],
                    source: KnowledgeSourceLabel::for($chunk['source_type']),
                    already_added: $existing->has(Str::lower($item['question'])),
                );
            });
    }

    /**
     * One call: the passages, numbered from 1, and how many questions to write.
     *
     * @param  list<array{chunk_id: int, text: string, source_type: string}>  $sample
     * @return array<array-key, mixed>|null the decoded reply
     */
    private function ask(array $sample, int $count): ?array
    {
        $model = $this->models->model(LlmTask::Starters);
        $passages = collect($sample)->mapWithKeys(fn (array $row, int $i): array => [$i + 1 => $row['text']])->all();

        try {
            return $this->completion->send(new ChatRequestData(
                model: $model,
                messages: [
                    ChatMessageData::system(Str::trim(prompt('Evaluation/knowledge-questions-system', ['count' => $count]))),
                    ChatMessageData::user(Str::rtrim(prompt('Evaluation/knowledge-questions-user', ['passages' => $passages]), "\n")),
                ],
                tag: AiCallTag::EvaluationQuestions,
                temperature: 0.8,
                responseSchema: new ResponseSchemaData('knowledge_questions', [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['questions'],
                    'properties' => [
                        'questions' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['passage', 'question'],
                                'properties' => [
                                    'passage' => ['type' => 'integer', 'description' => 'The number of the passage the question is answered by.'],
                                    'question' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ], strict: false),
            ))->data;
        } catch (ConnectionException $exception) {
            throw LlmCallFailedException::unreachable($model, $exception);
        } catch (RequestException $exception) {
            throw LlmCallFailedException::refused($model, $exception->response->status(), $this->reasons->of($exception->response), $exception);
        }
    }

    /**
     * The usable items of a reply: the `questions` list (or a bare list, from
     * a model that skipped the object), each with a passage number and a
     * trimmed, non-empty question.
     *
     * @param  array<array-key, mixed>|null  $data
     * @return Collection<int, array{passage: int, question: non-empty-string}>
     */
    private function questions(?array $data): Collection
    {
        $items = $data['questions'] ?? $data ?? [];

        return collect(is_array($items) ? $items : [])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['question'] ?? null))
            ->map(fn (array $item): array => [
                'passage' => (int) ($item['passage'] ?? 0),
                'question' => Str::trim((string) $item['question']),
            ])
            ->filter(fn (array $item): bool => $item['question'] !== '')
            ->values();
    }
}
