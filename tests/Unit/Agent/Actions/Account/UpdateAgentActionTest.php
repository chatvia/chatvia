<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Account\UpdateAgentAction;
use Domain\Agent\Data\Account\UpdateAgentData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;

/**
 * UpdateAgentAction owns the agent_prompt_id ↔ system_instructions
 * mutual-exclusion rule (a library prompt and a custom instruction
 * string can never both be set). Test each branch concretely.
 */
function uaaOwnedPrompt(User $user): AgentPrompt
{
    return AgentPrompt::factory()->create(['workspace_id' => $user->workspace()->id]);
}

function uaaData(Agent $agent, array $overrides = []): UpdateAgentData
{
    return new UpdateAgentData(...array_merge([
        'agent' => $agent,
        'actor' => $agent->workspace->owner,
        'name' => null,
        'slug' => null,
        'changePrompt' => false,
        'promptExternalId' => null,
        'changeSystemInstructions' => false,
        'systemInstructions' => null,
    ], $overrides));
}

it('updates name when name is supplied', function (): void {
    $agent = Agent::factory()->create(['name' => 'Original']);

    $updated = app(UpdateAgentAction::class)->execute(uaaData($agent, ['name' => 'Renamed']));

    expect($updated->name)->toBe('Renamed');
});

it('switching to a library prompt clears system_instructions', function (): void {
    $agent = Agent::factory()->create(['system_instructions' => 'Old custom text']);
    $prompt = uaaOwnedPrompt($agent->workspace->owner);

    $updated = app(UpdateAgentAction::class)->execute(uaaData($agent, [
        'changePrompt' => true,
        'promptExternalId' => $prompt->external_id,
    ]));

    expect($updated->agent_prompt_id)->toBe($prompt->id)
        ->and($updated->system_instructions)->toBeNull();
});

it('switching to custom system_instructions clears agent_prompt_id', function (): void {
    $agent = Agent::factory()->create();
    $prompt = uaaOwnedPrompt($agent->workspace->owner);
    $agent->update(['agent_prompt_id' => $prompt->id]);

    $updated = app(UpdateAgentAction::class)->execute(uaaData($agent, [
        'changeSystemInstructions' => true,
        'systemInstructions' => 'New custom instructions',
    ]));

    expect($updated->system_instructions)->toBe('New custom instructions')
        ->and($updated->agent_prompt_id)->toBeNull();
});

it('clearing the library prompt by sending null clears both prompt and instructions', function (): void {
    $agent = Agent::factory()->create();
    $prompt = uaaOwnedPrompt($agent->workspace->owner);
    $agent->update(['agent_prompt_id' => $prompt->id]);

    $updated = app(UpdateAgentAction::class)->execute(uaaData($agent, [
        'changePrompt' => true,
        'promptExternalId' => null, // explicit null = "no prompt selected"
    ]));

    expect($updated->agent_prompt_id)->toBeNull()
        ->and($updated->system_instructions)->toBeNull();
});
