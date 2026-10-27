<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Database\Factories\AgentQuestionAnswerFactory;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Data\KnowledgeQuestionCandidateData;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Queries\KnowledgeQuestionCandidatesQuery;
use Domain\Knowledge\Ingestion\Models\Chunk;

/**
 * The "From knowledge" picker contract: QA pairs come first, split per line
 * and deduped case-insensitively, then the questions stored on the chunks of
 * the agent's sources; a question already used as a case input is flagged so
 * the UI can disable it.
 */
/** @return array<string, array<string, mixed>> the candidates by question */
function lkqCandidates(Agent $agent): array
{
    return app(KnowledgeQuestionCandidatesQuery::class)->get($agent)
        ->mapWithKeys(fn (KnowledgeQuestionCandidateData $candidate): array => [$candidate->question => $candidate->toArray()])
        ->all();
}

it('lists QA-pair questions, deduped, and flags ones already used as a case input', function (): void {
    $agent = createAgent(User::factory()->create());
    AgentQuestionAnswerFactory::new()->attachedTo($agent)->create(['questions' => "How long do refunds take?\nHow long do refunds take?\nWhen do I get my money back?"]);
    EvalCase::factory()->forAgent($agent)->create(['input' => 'how long do refunds take?']);

    $candidates = lkqCandidates($agent);

    expect(array_keys($candidates))->toBe(['How long do refunds take?', 'When do I get my money back?'])
        ->and($candidates['How long do refunds take?']['already_added'])->toBeTrue()
        ->and($candidates['When do I get my money back?']['already_added'])->toBeFalse()
        ->and($candidates['How long do refunds take?']['type'])->toBe('qa')
        ->and($candidates['How long do refunds take?']['source'])->toBe('Refunds');
});

it('lists the questions of the agent\'s chunks after its QA pairs, flagging used ones', function (): void {
    $agent = createAgent(User::factory()->create());
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $file->agents()->attach($agent->id);
    Chunk::factory()->forSource($file)->create(['questions' => "What is A?\nWhat is B?"]);
    AgentQuestionAnswerFactory::new()->attachedTo($agent)->create(['questions' => 'How do refunds work?']);
    EvalCase::factory()->forAgent($agent)->create(['input' => 'what is a?']);

    $candidates = lkqCandidates($agent);

    expect(array_keys($candidates))->toBe(['How do refunds work?', 'What is A?', 'What is B?'])
        ->and($candidates['What is A?']['type'])->toBe('chunk')
        ->and($candidates['What is A?']['source'])->toBe('File')
        ->and($candidates['What is A?']['already_added'])->toBeTrue()
        ->and($candidates['What is B?']['already_added'])->toBeFalse();
});

it('returns an empty list for an agent with no knowledge', function (): void {
    $agent = createAgent(User::factory()->create());

    expect(app(KnowledgeQuestionCandidatesQuery::class)->get($agent))->toBeEmpty();
});
