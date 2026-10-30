<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Evaluation\Models\EvalCase;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Enums\LlmTask;

/**
 * The picker's "Generate with AI" button: the model's questions come back
 * as candidates tied to the chunk each is answered by, flagged when the
 * question is already a case. How a reply is read is in
 * GenerateKnowledgeQuestionsActionTest.
 */
it('returns the AI questions as picker candidates tied to their chunks', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $file->agents()->attach($agent->id);
    $chunk = Chunk::factory()->forSource($file)->create(['chunk' => 'Refunds are available within 30 days.']);
    EvalCase::factory()->forAgent($agent)->create(['input' => 'What is the refund policy?']);
    config([LlmTask::Starters->configKey() => testLlm()->id]);
    fakeLlmCompletion('{"questions": [{"passage": 1, "question": "What is the refund policy?"}, {"passage": 1, "question": "How long do I have to return an item?"}]}');

    $this->actingAs($owner)
        ->postJson(route('agents.evals.cases.generate', $agent), ['count' => 2])
        ->assertOk()
        ->assertJsonCount(2, 'questions')
        ->assertJsonPath('questions.0.type', 'chunk')
        ->assertJsonPath('questions.0.id', $chunk->id)
        ->assertJsonPath('questions.0.source', 'File')
        ->assertJsonPath('questions.0.already_added', true)
        ->assertJsonPath('questions.1.question', 'How long do I have to return an item?')
        ->assertJsonPath('questions.1.already_added', false);

    expect(EvalCase::query()->forAgent($agent->id)->count())->toBe(1);
});

it('answers an empty list without calling the model when the agent has no knowledge', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    $this->actingAs($owner)
        ->postJson(route('agents.evals.cases.generate', $agent))
        ->assertOk()
        ->assertExactJson(['questions' => []]);
});
