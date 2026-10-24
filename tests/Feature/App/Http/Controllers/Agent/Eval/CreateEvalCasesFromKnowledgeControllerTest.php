<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Database\Factories\AgentQuestionAnswerFactory;
use Domain\Evaluation\Models\EvalCase;
use Domain\Knowledge\Ingestion\Models\Chunk;

/**
 * The "From knowledge" picker posts the questions picked from the agent's
 * chunks and Q&A pairs; each becomes one eval case. How a case is built from
 * its question is in CreateEvalCasesFromQuestionsActionTest.
 */
it('creates one case per picked question', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $file->agents()->attach($agent->id);
    $chunk = Chunk::factory()->forSource($file)->create(['chunk' => 'Refunds are available within 30 days.']);
    $qa = AgentQuestionAnswerFactory::new()->attachedTo($agent)->create(['questions' => 'How long do refunds take?']);

    $this->actingAs($owner)
        ->post(route('agents.evals.cases.from-knowledge', $agent), [
            'items' => [
                ['type' => 'chunk', 'id' => $chunk->id, 'question' => 'What is the refund policy?'],
                ['type' => 'qa', 'id' => $qa->id, 'question' => 'How long do refunds take?'],
            ],
        ])
        ->assertRedirect();

    expect(EvalCase::query()->forAgent($agent->id)->orderBy('id')->pluck('input')->all())
        ->toBe(['What is the refund policy?', 'How long do refunds take?']);
});
