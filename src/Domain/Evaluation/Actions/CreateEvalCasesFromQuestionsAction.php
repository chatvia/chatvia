<?php

namespace Domain\Evaluation\Actions;

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Data\CreateEvalCasesFromQuestionsData;
use Domain\Evaluation\Enums\EvalQuestionSource;
use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Models\EvalCase;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Support\Exceptions\DomainValidationException;

/**
 * Bulk-creates eval cases from knowledge-base questions picked in the
 * "From knowledge" dialog. Every case is immediately runnable: scored by
 * the LLM judge against the question's source text (the chunk's content,
 * or the QA pair's answer) stored as the reference answer. Reference text
 * is re-fetched here by id with ownership scoping — client-supplied text
 * is never trusted. Questions already present as a case input are skipped
 * silently (case-insensitive), so re-picking is idempotent.
 */
class CreateEvalCasesFromQuestionsAction
{
    public const string DEFAULT_CRITERIA = 'The answer must be factually consistent with the reference answer and must not contradict it.';

    // Matches the expected_output cap in SaveEvalCaseRequest.
    private const int MAX_REFERENCE_LENGTH = 8000;

    /**
     * @return Collection<int, EvalCase>
     *
     * @throws DomainValidationException when a picked question no longer exists for this agent
     */
    public function execute(CreateEvalCasesFromQuestionsData $data, Agent $agent): Collection
    {
        $items = collect($data->items);

        $chunks = $this->chunksForAgent($items->where('type', EvalQuestionSource::Chunk)->pluck('id')->unique(), $agent);
        $qas = $this->qaPairsForAgent($items->where('type', EvalQuestionSource::Qa)->pluck('id')->unique(), $agent);

        foreach ($items as $item) {
            $found = $item->type === EvalQuestionSource::Chunk ? $chunks->has($item->id) : $qas->has($item->id);

            if (! $found) {
                throw new DomainValidationException('items', __('Some selected questions are no longer available.'));
            }
        }

        $existing = EvalCase::query()->forAgent($agent->id)->pluck('input')
            ->mapWithKeys(fn (string $input): array => [mb_strtolower(trim($input)) => true]);

        return DB::transaction(function () use ($items, $agent, $chunks, $qas, $existing): Collection {
            $created = new Collection;

            foreach ($items as $item) {
                $normalized = mb_strtolower(trim($item->question));

                if ($existing->has($normalized)) {
                    continue;
                }

                $existing->put($normalized, true);

                $reference = $item->type === EvalQuestionSource::Chunk
                    ? $chunks[$item->id]->chunk
                    : $qas[$item->id]->answer;

                $created->push(EvalCase::create([
                    'agent_id' => $agent->id,
                    'agent_name' => $agent->name,
                    'workspace_id' => $agent->user_id,
                    'name' => $item->question,
                    'input' => $item->question,
                    'scorer_type' => ScorerType::LlmJudge->value,
                    'scorer_config' => ['criteria' => self::DEFAULT_CRITERIA],
                    'expected_output' => mb_substr($reference, 0, self::MAX_REFERENCE_LENGTH),
                    'active' => true,
                ]));
            }

            return $created;
        });
    }

    /**
     * Chunks scoped to sources attached to this agent. Both sides of the
     * sourceable/chunkable comparison store morph aliases (`source_file`,
     * ...), never FQCNs — same EXISTS as ChunkQueryBuilder::questionsForAgent.
     *
     * @param  Collection<int, int>  $ids
     * @return Collection<int, Chunk>
     */
    private function chunksForAgent(Collection $ids, Agent $agent): Collection
    {
        if ($ids->isEmpty()) {
            return new Collection;
        }

        return Chunk::query()
            ->whereIn('id', $ids)
            ->where('user_id', $agent->user_id)
            ->whereExists(function ($query) use ($agent): void {
                $query->select(DB::raw(1))
                    ->from('agent_sources as s')
                    ->whereColumn('s.sourceable_id', 'chunks.chunkable_id')
                    ->whereColumn('s.sourceable_type', 'chunks.chunkable_type')
                    ->where('s.agent_id', $agent->id);
            })
            ->get(['id', 'chunk'])
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, AgentQuestionAnswer>
     */
    private function qaPairsForAgent(Collection $ids, Agent $agent): Collection
    {
        if ($ids->isEmpty()) {
            return new Collection;
        }

        return AgentQuestionAnswer::query()
            ->where('agent_id', $agent->id)
            ->whereIn('id', $ids)
            ->get(['id', 'answer'])
            ->keyBy('id');
    }
}
