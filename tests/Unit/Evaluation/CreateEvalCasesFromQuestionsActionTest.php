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
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Support\Str;
use Support\Exceptions\DomainValidationException;

/**
 * The "From knowledge" bulk-create contract: every picked question becomes an
 * immediately-runnable llm_judge case whose reference answer is re-fetched
 * server-side (chunk text / QA answer), ids that don't belong to the agent are
 * rejected before anything is written, and duplicate questions (existing case
 * inputs or repeats within one payload) are skipped silently.
 *
 * REQUIRES the migrated test DB like CreateSourceChunkActionTest: chunks uses
 * pgvector halfvec(1024) + bm25vector.
 */
function knowledgeEvalAgent(): Agent
{
    return createAgent(User::factory()->create());
}

/**
 * A chunk on a source attached to the agent via agent_sources — the ownership
 * shape the action's EXISTS check requires. Pass attach: false to build the
 * foreign-source case.
 */
function knowledgeEvalChunk(Agent $agent, string $text, bool $attach = true): Chunk
{
    /** @var SourceFile $source */
    $source = AgentFileFactory::new()->create(['user_id' => $agent->user_id]);

    if ($attach) {
        $source->agents()->attach($agent->id);
    }

    return Chunk::create([
        'user_id' => $agent->user_id,
        'chunkable_type' => $source->getMorphClass(),
        'chunkable_id' => $source->getKey(),
        'chunk_index' => 1,
        'chunk' => $text,
        'tokens' => 1,
        'embedding' => '['.implode(',', array_fill(0, 1024, 0)).']',
        'embedding_bm25' => Bm25Tokenizer::expression($text),
        'checksum' => Str::random(32),
    ]);
}

function knowledgeEvalData(KnowledgeQuestionItemData ...$items): CreateEvalCasesFromQuestionsData
{
    return new CreateEvalCasesFromQuestionsData(items: array_values($items));
}

function chunkQuestion(Chunk $chunk, string $question): KnowledgeQuestionItemData
{
    return new KnowledgeQuestionItemData(type: EvalQuestionSource::Chunk, id: $chunk->id, question: $question);
}

function qaQuestion(AgentQuestionAnswer $qa, string $question): KnowledgeQuestionItemData
{
    return new KnowledgeQuestionItemData(type: EvalQuestionSource::Qa, id: $qa->id, question: $question);
}

it('creates an llm_judge case from a chunk question with the chunk text as reference', function (): void {
    $agent = knowledgeEvalAgent();
    $chunk = knowledgeEvalChunk($agent, 'Refunds are available within 30 days of purchase.');

    $created = app(CreateEvalCasesFromQuestionsAction::class)->execute(
        knowledgeEvalData(chunkQuestion($chunk, 'What is the refund policy?')),
        $agent,
    );

    expect($created)->toHaveCount(1);

    $case = $created->first();

    expect($case->agent_id)->toBe($agent->id)
        ->and($case->agent_name)->toBe($agent->name)
        ->and($case->workspace_id)->toBe($agent->user_id)
        ->and($case->name)->toBe('What is the refund policy?')
        ->and($case->input)->toBe('What is the refund policy?')
        ->and($case->scorer_type)->toBe(ScorerType::LlmJudge)
        ->and($case->scorer_config)->toBe(['criteria' => CreateEvalCasesFromQuestionsAction::DEFAULT_CRITERIA])
        ->and($case->expected_output)->toBe('Refunds are available within 30 days of purchase.')
        ->and($case->active)->toBeTrue();
});

it('uses the QA pair answer as the reference for a qa question', function (): void {
    $agent = knowledgeEvalAgent();
    $qa = AgentQuestionAnswer::create([
        'agent_id' => $agent->id,
        'title' => 'Refunds',
        'answer' => 'Refunds take 5 business days.',
        'questions' => "How long do refunds take?\nWhen do I get my money back?",
    ]);

    $created = app(CreateEvalCasesFromQuestionsAction::class)->execute(
        knowledgeEvalData(qaQuestion($qa, 'How long do refunds take?')),
        $agent,
    );

    expect($created)->toHaveCount(1)
        ->and($created->first()->expected_output)->toBe('Refunds take 5 business days.')
        ->and($created->first()->scorer_type)->toBe(ScorerType::LlmJudge);
});

it('caps the reference answer at 8000 characters', function (): void {
    $agent = knowledgeEvalAgent();
    $chunk = knowledgeEvalChunk($agent, str_repeat('a', 9000));

    $created = app(CreateEvalCasesFromQuestionsAction::class)->execute(
        knowledgeEvalData(chunkQuestion($chunk, 'What does the document say?')),
        $agent,
    );

    expect(mb_strlen($created->first()->expected_output))->toBe(8000);
});

it('skips questions already present as a case input and intra-payload duplicates', function (): void {
    $agent = knowledgeEvalAgent();
    $chunk = knowledgeEvalChunk($agent, 'Some knowledge.');

    EvalCase::create([
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'workspace_id' => $agent->user_id,
        'name' => 'existing',
        'input' => 'WHAT IS THE REFUND POLICY?',
        'scorer_type' => 'contains',
        'scorer_config' => ['value' => '30 days'],
        'active' => true,
    ]);

    $created = app(CreateEvalCasesFromQuestionsAction::class)->execute(
        knowledgeEvalData(
            // Duplicate of the existing case input, differing only in casing.
            chunkQuestion($chunk, 'What is the refund policy?'),
            chunkQuestion($chunk, 'What is shipping like?'),
            // Intra-payload duplicate of the previous question.
            chunkQuestion($chunk, 'what is shipping like?'),
        ),
        $agent,
    );

    expect($created)->toHaveCount(1)
        ->and($created->first()->input)->toBe('What is shipping like?')
        ->and(EvalCase::query()->forAgent($agent->id)->count())->toBe(2);
});

it('rejects a chunk whose source is not attached to the agent and writes nothing', function (): void {
    $agent = knowledgeEvalAgent();
    $attached = knowledgeEvalChunk($agent, 'Attached knowledge.');
    $foreign = knowledgeEvalChunk($agent, 'Unattached knowledge.', attach: false);

    expect(fn () => app(CreateEvalCasesFromQuestionsAction::class)->execute(
        knowledgeEvalData(
            chunkQuestion($attached, 'A valid question?'),
            chunkQuestion($foreign, 'A foreign question?'),
        ),
        $agent,
    ))->toThrow(DomainValidationException::class);

    expect(EvalCase::query()->forAgent($agent->id)->count())->toBe(0);
});

it('rejects a chunk belonging to another workspace', function (): void {
    $agent = knowledgeEvalAgent();
    $other = knowledgeEvalAgent();
    $foreignChunk = knowledgeEvalChunk($other, 'Another workspace\'s knowledge.');

    expect(fn () => app(CreateEvalCasesFromQuestionsAction::class)->execute(
        knowledgeEvalData(chunkQuestion($foreignChunk, 'A question?')),
        $agent,
    ))->toThrow(DomainValidationException::class);

    expect(EvalCase::query()->forAgent($agent->id)->count())->toBe(0);
});

it('rejects a QA pair belonging to another agent', function (): void {
    $agent = knowledgeEvalAgent();
    $other = knowledgeEvalAgent();
    $foreignQa = AgentQuestionAnswer::create([
        'agent_id' => $other->id,
        'title' => 'Foreign',
        'answer' => 'Not yours.',
        'questions' => 'A question?',
    ]);

    expect(fn () => app(CreateEvalCasesFromQuestionsAction::class)->execute(
        knowledgeEvalData(qaQuestion($foreignQa, 'A question?')),
        $agent,
    ))->toThrow(DomainValidationException::class);

    expect(EvalCase::query()->forAgent($agent->id)->count())->toBe(0);
});
