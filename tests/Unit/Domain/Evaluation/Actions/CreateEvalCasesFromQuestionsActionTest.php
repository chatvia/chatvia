<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Database\Factories\AgentQuestionAnswerFactory;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\CreateEvalCasesFromQuestionsAction;
use Domain\Evaluation\Data\CreateEvalCasesFromQuestionsData;
use Domain\Evaluation\Data\KnowledgeQuestionItemData;
use Domain\Evaluation\Enums\EvalQuestionSource;
use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Models\EvalCase;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Support\Exceptions\DomainValidationException;

/**
 * The "From knowledge" bulk-create contract: every picked question becomes an
 * immediately-runnable llm_judge case whose reference answer is re-fetched
 * server-side (chunk text / QA answer), ids that don't belong to the agent are
 * rejected before anything is written, and duplicate questions (existing case
 * inputs or repeats within one payload) are skipped silently.
 */
function cecAgent(): Agent
{
    return createAgent(User::factory()->create());
}

/**
 * A chunk on a source attached to the agent via agent_sources — the ownership
 * shape the action's EXISTS check requires. Pass attach: false to build the
 * foreign-source case.
 */
function cecChunk(Agent $agent, string $text, bool $attach = true): Chunk
{
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);

    if ($attach) {
        $source->agents()->attach($agent->id);
    }

    return Chunk::factory()->forSource($source)->create(['chunk' => $text]);
}

function cecData(KnowledgeQuestionItemData ...$items): CreateEvalCasesFromQuestionsData
{
    return new CreateEvalCasesFromQuestionsData(items: array_values($items));
}

function cecChunkQuestion(Chunk $chunk, string $question): KnowledgeQuestionItemData
{
    return new KnowledgeQuestionItemData(type: EvalQuestionSource::Chunk, id: $chunk->id, question: $question);
}

function cecQaQuestion(AgentQuestionAnswer $qa, string $question): KnowledgeQuestionItemData
{
    return new KnowledgeQuestionItemData(type: EvalQuestionSource::Qa, id: $qa->id, question: $question);
}

it('creates one llm_judge case per new question with the chunk text as reference', function (): void {
    $agent = cecAgent();
    $chunk = cecChunk($agent, 'Refunds are available within 30 days of purchase.');
    EvalCase::factory()->forAgent($agent)->create(['input' => 'WHAT IS SHIPPING LIKE?']);

    $created = app(CreateEvalCasesFromQuestionsAction::class)->execute(
        cecData(
            cecChunkQuestion($chunk, 'What is shipping like?'),
            cecChunkQuestion($chunk, 'What is the refund policy?'),
            cecChunkQuestion($chunk, 'what is the refund policy?'),
        ),
        $agent,
    );

    $case = $created->first();

    expect($created)->toHaveCount(1)
        ->and($case->agent_id)->toBe($agent->id)
        ->and($case->workspace_id)->toBe($agent->workspace_id)
        ->and($case->input)->toBe('What is the refund policy?')
        ->and($case->scorer_type)->toBe(ScorerType::LlmJudge)
        ->and($case->expected_output)->toBe('Refunds are available within 30 days of purchase.')
        ->and($case->is_active)->toBeTrue()
        ->and(EvalCase::query()->forAgent($agent->id)->count())->toBe(2);
});

it('rejects knowledge the agent does not own and writes nothing', function (Closure $foreignItem): void {
    $agent = cecAgent();
    $attached = cecChunk($agent, 'Attached knowledge.');

    expect(fn () => app(CreateEvalCasesFromQuestionsAction::class)->execute(
        cecData(cecChunkQuestion($attached, 'A valid question?'), $foreignItem($agent)),
        $agent,
    ))->toThrow(DomainValidationException::class);

    expect(EvalCase::query()->forAgent($agent->id)->count())->toBe(0);
})->with([
    'a chunk whose source is not attached' => [fn (Agent $agent): KnowledgeQuestionItemData => cecChunkQuestion(cecChunk($agent, 'Unattached knowledge.', attach: false), 'A foreign question?')],
    'a chunk of another workspace' => [fn (): KnowledgeQuestionItemData => cecChunkQuestion(cecChunk(cecAgent(), 'Another workspace\'s knowledge.'), 'A foreign question?')],
    'a QA pair of another agent' => [fn (): KnowledgeQuestionItemData => cecQaQuestion(AgentQuestionAnswerFactory::new()->attachedTo(cecAgent())->create(['answer' => 'Not yours.', 'questions' => 'A question?']), 'A question?')],
]);
