<?php

use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Conversation\Services\Turn\SpectraPromptTagger;
use Spectra\Facades\Spectra;
use Spectra\Support\Tracking\RequestContext;

/**
 * A reply's call carries the prompt the agent answered with: the attached
 * library prompt or the agent itself, versioned by its instructions, so an
 * edit is a new version. Nothing is tagged while Spectra is off.
 */
beforeEach(function (): void {
    config()->set('spectra.enabled', true);
});

function promptTaggedCall(Agent $agent): RequestContext
{
    return app(SpectraPromptTagger::class)->during($agent, fn (): RequestContext => Spectra::startRequest('openai', 'gpt-4o'));
}

it('names the prompt after the agent and versions it by the instructions', function (): void {
    $agent = Agent::factory()->create(['name' => 'Support', 'system_instructions' => 'Answer briefly.']);

    $first = promptTaggedCall($agent);
    $agent->update(['system_instructions' => 'Answer in detail.']);
    $edited = promptTaggedCall($agent);

    expect($first->promptName)->toBe('Support')
        ->and($edited->promptName)->toBe('Support')
        ->and($edited->promptVersion)->not->toBe($first->promptVersion)
        ->and(promptTaggedCall($agent)->promptVersion)->toBe($edited->promptVersion);
});

it('names the prompt after the attached library prompt', function (): void {
    $library = AgentPrompt::factory()->create(['name' => 'Friendly support']);
    $agent = Agent::factory()->create(['workspace_id' => $library->workspace_id, 'agent_prompt_id' => $library->id]);

    expect(promptTaggedCall($agent)->promptName)->toBe('Friendly support');
});

it('tags nothing while Spectra is off', function (): void {
    config()->set('spectra.enabled', false);

    expect(promptTaggedCall(Agent::factory()->create())->promptName)->toBeNull();
});
