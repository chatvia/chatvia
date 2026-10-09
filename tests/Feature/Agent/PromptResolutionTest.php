<?php

use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;

it('resolves the attached library prompt first', function (): void {
    $libraryPrompt = AgentPrompt::factory()->create(['prompt' => 'Library body']);

    $agent = Agent::factory()->create([
        'system_instructions' => 'Custom body',
        'agent_prompt_id' => $libraryPrompt->id,
    ]);

    expect($agent->resolveSystemInstructions())->toBe('Library body');
});

it('falls back to the custom system_instructions when no library prompt is attached', function (): void {
    $agent = Agent::factory()->create([
        'system_instructions' => 'Custom body',
        'agent_prompt_id' => null,
    ]);

    expect($agent->resolveSystemInstructions())->toBe('Custom body');
});

it('falls back to the built-in default when neither is set', function (): void {
    $agent = Agent::factory()->create([
        'system_instructions' => null,
        'agent_prompt_id' => null,
    ]);

    expect($agent->resolveSystemInstructions())->toBe(prompt('knowledge/system'));
});
