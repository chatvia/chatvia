<?php

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('caches the resolved template and serves later reads from cache', function (): void {
    $user = User::factory()->create();
    $template = AgentTemplate::factory()->create(['user_id' => $user->id, 'name' => 'Cached']);
    $agent = createTemplateAgent($user, ['agent_template_id' => $template->id]);

    expect($agent->resolvedTemplate()->name)->toBe('Cached')
        ->and(Cache::has(AgentTemplate::cacheKey($template->id)))->toBeTrue();

    // Mutate the row directly (bypassing Eloquent → no observer → no forget).
    DB::table('agent_templates')->where('id', $template->id)->update(['name' => 'DirectDbChange']);

    // A brand-new instance still sees the cached payload, proving the cache hit.
    expect(Agent::find($agent->id)->resolvedTemplate()->name)->toBe('Cached');
});

it('forgets the cache when the template is saved', function (): void {
    $user = User::factory()->create();
    $template = AgentTemplate::factory()->create(['user_id' => $user->id, 'name' => 'V1']);
    $agent = createTemplateAgent($user, ['agent_template_id' => $template->id]);

    $agent->resolvedTemplate();

    $template->update(['name' => 'V2']);

    expect(Cache::has(AgentTemplate::cacheKey($template->id)))->toBeFalse()
        ->and(Agent::find($agent->id)->resolvedTemplate()->name)->toBe('V2');
});

it('forgets referencing template caches when a library prompt changes', function (): void {
    $user = User::factory()->create();
    $prompt = $user->prompts()->create(['name' => 'P', 'prompt' => 'Original.']);
    $template = AgentTemplate::factory()->create([
        'user_id' => $user->id,
        'agent_prompt_id' => $prompt->id,
    ]);
    $agent = createTemplateAgent($user, ['agent_template_id' => $template->id]);

    // Populate cache (the payload eager-loads the prompt).
    expect($agent->resolveSystemInstructions())->toBe('Original.');

    $prompt->update(['prompt' => 'Edited.']);

    expect(Cache::has(AgentTemplate::cacheKey($template->id)))->toBeFalse()
        ->and(Agent::find($agent->id)->resolveSystemInstructions())->toBe('Edited.');
});
