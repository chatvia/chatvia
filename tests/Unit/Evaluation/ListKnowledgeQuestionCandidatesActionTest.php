<?php

use App\Identity\Workspace\Models\User;
use Domain\Evaluation\Actions\ListKnowledgeQuestionCandidatesAction;
use Domain\Evaluation\Models\EvalCase;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;

/**
 * The "From knowledge" picker contract: QA pairs are split per-line and deduped
 * case-insensitively, and questions already used as a case input are flagged so
 * the UI can disable them.
 *
 * REQUIRES the migrated + seeded test DB (createAgent reuses the seeded demo
 * user, like the sibling eval action tests).
 */
it('lists QA-pair questions, deduped, and flags ones already used as a case input', function (): void {
    $agent = createAgent(User::factory()->create());

    AgentQuestionAnswer::create([
        'agent_id' => $agent->id,
        'title' => 'Refunds',
        'answer' => 'Refunds take 5 business days.',
        'questions' => "How long do refunds take?\nHow long do refunds take?\nWhen do I get my money back?",
    ]);

    EvalCase::create([
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'workspace_id' => $agent->user_id,
        'name' => 'existing',
        'input' => 'how long do refunds take?',
        'scorer_type' => 'contains',
        'scorer_config' => ['value' => '5 days'],
        'active' => true,
    ]);

    $candidates = array_map(fn ($candidate): array => $candidate->toArray(), app(ListKnowledgeQuestionCandidatesAction::class)->execute($agent));

    $questions = array_column($candidates, 'question');

    // Case-insensitive dedupe collapses the repeated line.
    expect($questions)->toBe(['How long do refunds take?', 'When do I get my money back?']);

    $byQuestion = collect($candidates)->keyBy('question');

    expect($byQuestion['How long do refunds take?']['already_added'])->toBeTrue()
        ->and($byQuestion['When do I get my money back?']['already_added'])->toBeFalse()
        ->and($byQuestion['How long do refunds take?']['type'])->toBe('qa')
        ->and($byQuestion['How long do refunds take?']['source'])->toBe('Refunds');
});

it('returns an empty list for an agent with no knowledge', function (): void {
    $agent = createAgent(User::factory()->create());

    expect(app(ListKnowledgeQuestionCandidatesAction::class)->execute($agent))->toBe([]);
});
