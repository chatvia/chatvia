<?php

use Domain\Agent\Actions\Account\UpdateAgentAction;
use Domain\Agent\Data\Account\UpdateAgentData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Spatie\LaravelData\Optional;

/**
 * UpdateAgentAction owns the agent_prompt_id and system_instructions
 * exclusion rule: a library prompt and a custom instruction string are never
 * both set, so choosing one clears the other.
 */
function uaaData(Agent $agent, array $overrides = []): UpdateAgentData
{
    return new UpdateAgentData(...array_merge([
        'agent' => $agent,
        'actor' => $agent->workspace->owner,
        'name' => new Optional,
        'slug' => new Optional,
        'promptExternalId' => new Optional,
        'systemInstructions' => new Optional,
    ], $overrides));
}

it('clears the custom instructions for a library prompt, and the prompt for custom instructions', function (): void {
    $agent = Agent::factory()->create(['system_instructions' => 'Old custom text']);
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $agent->workspace_id]);

    $withPrompt = app(UpdateAgentAction::class)->execute(uaaData($agent, ['promptExternalId' => $prompt->external_id]));
    $withInstructions = app(UpdateAgentAction::class)->execute(uaaData($withPrompt->fresh(), ['systemInstructions' => 'New custom instructions']));

    expect($withPrompt->agent_prompt_id)->toBe($prompt->id)
        ->and($withPrompt->system_instructions)->toBeNull()
        ->and($withInstructions->system_instructions)->toBe('New custom instructions')
        ->and($withInstructions->agent_prompt_id)->toBeNull();
});
