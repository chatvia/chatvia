<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\CreateEvalCasesFromQuestionsAction;
use Domain\Evaluation\Data\CreateEvalCasesFromQuestionsData;
use Domain\Evaluation\Data\KnowledgeQuestionItemData;
use Domain\Evaluation\Enums\EvalQuestionSource;
use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Models\EvalCase;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Support\Str;
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

function cecQa(Agent $agent, string $answer, string $questions): AgentQuestionAnswer
{
    return AgentQuestionAnswer::create(['agent_id' => $agent->id, 'title' => 'Refunds', 'answer' => $answer, 'questions' => $questions]);
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

it('creates an llm_judge case from a chunk question with the chunk text as reference', function (): void {
    $agent = cecAgent();
    $chunk = cecChunk($agent, 'Refunds are available within 30 days of purchase.');

    $created = app(CreateEvalCasesFromQuestionsAction::class)->execute(
        cecData(cecChunkQuestion($chunk, 'What is the refund policy?')),
        $agent,
    );

    expect($created)->toHaveCount(1);

    $case = $created->first();

    expect($case->agent_id)->toBe($agent->id)
        ->and($case->agent_name)->toBe($agent->name)
        ->and($case->workspace_id)->toBe($agent->workspace_id)
        ->and($case->name)->toBe('What is the refund policy?')
        ->and($case->input)->toBe('What is the refund policy?')
        ->and($case->scorer_type)->toBe(ScorerType::LlmJudge)
        ->and($case->scorer_config)->toBe(['criteria' => CreateEvalCasesFromQuestionsAction::DEFAULT_CRITERIA])
        ->and($case->expected_output)->toBe('Refunds are available within 30 days of purchase.')
        ->and($case->is_active)->toBeTrue();
});

it('uses the QA pair answer as the reference for a qa question', function (): void {
    $agent = cecAgent();
    $qa = cecQa($agent, 'Refunds take 5 business days.', "How long do refunds take?\nWhen do I get my money back?");

    $created = app(CreateEvalCasesFromQuestionsAction::class)->execute(
        cecData(cecQaQuestion($qa, 'How long do refunds take?')),
        $agent,
    );

    expect($created)->toHaveCount(1)
        ->and($created->first()->expected_output)->toBe('Refunds take 5 business days.')
        ->and($created->first()->scorer_type)->toBe(ScorerType::LlmJudge);
});

it('caps the reference answer at 8000 characters', function (): void {
    $agent = cecAgent();
    $chunk = cecChunk($agent, Str::repeat('a', 9000));

    $created = app(CreateEvalCasesFromQuestionsAction::class)->execute(
        cecData(cecChunkQuestion($chunk, 'What does the document say?')),
        $agent,
    );

    expect(Str::length($created->first()->expected_output))->toBe(8000);
});

it('skips questions already present as a case input and intra-payload duplicates', function (): void {
    $agent = cecAgent();
    $chunk = cecChunk($agent, 'Some knowledge.');

    EvalCase::factory()->forAgent($agent)->create(['input' => 'WHAT IS THE REFUND POLICY?']);

    $created = app(CreateEvalCasesFromQuestionsAction::class)->execute(
        cecData(
            // Duplicate of the existing case input, differing only in casing.
            cecChunkQuestion($chunk, 'What is the refund policy?'),
            cecChunkQuestion($chunk, 'What is shipping like?'),
            // Intra-payload duplicate of the previous question.
            cecChunkQuestion($chunk, 'what is shipping like?'),
        ),
        $agent,
    );

    expect($created)->toHaveCount(1)
        ->and($created->first()->input)->toBe('What is shipping like?')
        ->and(EvalCase::query()->forAgent($agent->id)->count())->toBe(2);
});

it('rejects a chunk whose source is not attached to the agent and writes nothing', function (): void {
    $agent = cecAgent();
    $attached = cecChunk($agent, 'Attached knowledge.');
    $foreign = cecChunk($agent, 'Unattached knowledge.', attach: false);

    expect(fn () => app(CreateEvalCasesFromQuestionsAction::class)->execute(
        cecData(
            cecChunkQuestion($attached, 'A valid question?'),
            cecChunkQuestion($foreign, 'A foreign question?'),
        ),
        $agent,
    ))->toThrow(DomainValidationException::class);

    expect(EvalCase::query()->forAgent($agent->id)->count())->toBe(0);
});

it('rejects a chunk belonging to another workspace', function (): void {
    $agent = cecAgent();
    $other = cecAgent();
    $foreignChunk = cecChunk($other, 'Another workspace\'s knowledge.');

    expect(fn () => app(CreateEvalCasesFromQuestionsAction::class)->execute(
        cecData(cecChunkQuestion($foreignChunk, 'A question?')),
        $agent,
    ))->toThrow(DomainValidationException::class);

    expect(EvalCase::query()->forAgent($agent->id)->count())->toBe(0);
});

it('rejects a QA pair belonging to another agent', function (): void {
    $agent = cecAgent();
    $other = cecAgent();
    $foreignQa = cecQa($other, 'Not yours.', 'A question?');

    expect(fn () => app(CreateEvalCasesFromQuestionsAction::class)->execute(
        cecData(cecQaQuestion($foreignQa, 'A question?')),
        $agent,
    ))->toThrow(DomainValidationException::class);

    expect(EvalCase::query()->forAgent($agent->id)->count())->toBe(0);
});
