<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentQuestionAnswerFactory;
use Inertia\ResponseFactory;
use Inertia\Testing\AssertableInertia;

/**
 * The Evals page loads the "From knowledge" question candidates only when the
 * picker asks for them (a partial reload): building them reads every chunk of
 * the agent. Which questions they are is in
 * KnowledgeQuestionCandidatesQueryTest.
 */
it('leaves the knowledge questions out of the first load and serves them on a partial reload', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    AgentQuestionAnswerFactory::new()->attachedTo($agent)->create(['answer' => 'Within 30 days.', 'questions' => 'How do refunds work?']);

    $this->actingAs($owner)
        ->get(route('agents.evals', $agent))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('agents/Evals/index')
            ->missing('knowledgeQuestions'));

    $response = $this->actingAs($owner)->get(route('agents.evals', $agent), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(ResponseFactory::class)->getVersion(),
        'X-Inertia-Partial-Component' => 'agents/Evals/index',
        'X-Inertia-Partial-Data' => 'knowledgeQuestions',
    ]);

    expect($response->json('props.knowledgeQuestions.*.question'))->toBe(['How do refunds work?']);
});
