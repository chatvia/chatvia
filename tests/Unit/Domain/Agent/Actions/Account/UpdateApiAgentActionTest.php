<?php

use Domain\Agent\Actions\Account\UpdateApiAgentAction;
use Domain\Agent\Data\Account\UpdateApiAgentData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Spatie\LaravelData\Optional;

/**
 * The public API's partial agent update: only the fields sent change, and a
 * custom system prompt and a saved library prompt replace (clear) each other.
 */
it('changes only the fields that were sent', function (): void {
    $agent = Agent::factory()->create(['name' => 'Old', 'is_public' => false, 'system_instructions' => 'Be brief.']);

    $updated = app(UpdateApiAgentAction::class)->execute($agent->workspace, $agent, new UpdateApiAgentData(
        name: 'New',
        system_instructions: Optional::create(),
        prompt_id: Optional::create(),
        metadata: Optional::create(),
        is_public: true,
    ));

    expect($updated->name)->toBe('New')
        ->and($updated->is_public)->toBeTrue()
        ->and($updated->system_instructions)->toBe('Be brief.');
});

it('sets custom instructions and clears the library prompt', function (): void {
    $agent = Agent::factory()->create(['system_instructions' => null]);
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $agent->workspace_id]);
    $agent->update(['agent_prompt_id' => $prompt->id]);

    $updated = app(UpdateApiAgentAction::class)->execute($agent->workspace, $agent, new UpdateApiAgentData(
        name: Optional::create(),
        system_instructions: 'Answer in German.',
        prompt_id: Optional::create(),
        metadata: ['ref' => 'crm-42'],
        is_public: Optional::create(),
    ));

    expect($updated->system_instructions)->toBe('Answer in German.')
        ->and($updated->agent_prompt_id)->toBeNull()
        ->and($updated->metadata)->toBe(['ref' => 'crm-42']);
});

it('links a library prompt by its external id and clears the custom instructions', function (): void {
    $agent = Agent::factory()->create(['system_instructions' => 'Be brief.']);
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $agent->workspace_id]);

    $updated = app(UpdateApiAgentAction::class)->execute($agent->workspace, $agent, new UpdateApiAgentData(
        name: Optional::create(),
        system_instructions: 'Ignored while a prompt is sent.',
        prompt_id: $prompt->external_id,
        metadata: Optional::create(),
        is_public: Optional::create(),
    ));

    expect($updated->agent_prompt_id)->toBe($prompt->id)
        ->and($updated->system_instructions)->toBeNull();
});
