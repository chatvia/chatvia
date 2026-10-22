<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Setup → Model: an agent answers with one of the models an admin made
 * available (Settings → AI models). The page lists them, marks the default,
 * and preselects the agent's own model, or the default while it has none.
 * Saving sets the model and the answer temperature.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
    $this->default = testLlm('default-model');
    $this->other = testLlm('other-model');
    config(['chatvia.agent.default_model' => $this->default->external_name]);
});

it('lists the available models and preselects the default while the agent has none', function (): void {
    $this->agent->forceFill(['llm_id' => null])->saveQuietly();

    $this->actingAs($this->owner)
        ->get(route('agents.setup', $this->agent->external_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('selectedModel', $this->default->external_name)
            ->where('defaultModel', $this->default->external_name)
            ->where('catalog', fn (Collection $catalog): bool => $catalog->pluck('id')->contains($this->other->external_name)));
});

it('saves the model and the temperature', function (): void {
    $this->actingAs($this->owner)
        ->patch(route('agents.llm.update', $this->agent->external_id), [
            'model_external_name' => $this->other->external_name,
            'temperature' => 0.4,
            'top_k' => 0,
            'top_p' => 1,
            'presence_penalty' => 0,
            'frequency_penalty' => 0,
        ])
        ->assertSessionHasNoErrors();

    $agent = $this->agent->fresh();

    expect($agent->llm_id)->toBe($this->other->id)
        ->and((float) $agent->llmSettings()['temperature'])->toBe(0.4);
});

it('refuses a model that is not available', function (): void {
    $this->other->provider->forceFill(['is_enabled' => false])->save();

    $this->actingAs($this->owner)
        ->patch(route('agents.llm.update', $this->agent->external_id), [
            'model_external_name' => $this->other->external_name,
            'temperature' => 0.4,
            'top_k' => 0,
            'top_p' => 1,
            'presence_penalty' => 0,
            'frequency_penalty' => 0,
        ])
        ->assertSessionHasErrors('model_external_name');
});

it('sends the generation settings as numbers, even when they are stored as text', function (): void {
    $this->agent->setSetting('temperature', '0.7', 'llm_settings');
    $this->agent->setSetting('top_k', '40', 'llm_settings');

    $settings = $this->agent->fresh()->llmSettings();

    expect($settings['temperature'])->toBe(0.7)
        ->and($settings['top_k'])->toBe(40)
        ->and($settings['top_p'])->toBeFloat();

    $this->actingAs($this->owner)
        ->get(route('agents.setup', $this->agent->external_id))
        ->assertInertia(fn (Assert $page): Assert => $page->where('llmSettings.temperature', 0.7));
});

/** Save the agent's model with this reasoning effort. */
function ampSaveEffort(User $owner, string $agentId, string $model, string $effort): TestResponse
{
    return test()->actingAs($owner)->patch(route('agents.llm.update', $agentId), [
        'model_external_name' => $model,
        'temperature' => 1,
        'top_k' => 0,
        'top_p' => 1,
        'presence_penalty' => 0,
        'frequency_penalty' => 0,
        'reasoning_effort' => $effort,
    ]);
}

it('saves the reasoning effort while the admin lets agents reason', function (): void {
    config(['chatvia.ai.reasoning.enabled' => true]);

    ampSaveEffort($this->owner, $this->agent->external_id, $this->other->external_name, 'high')->assertSessionHasNoErrors();

    expect($this->agent->fresh()->settings('reasoning')->get('effort'))->toBe('high');
});

it('refuses a new effort while the admin does not let agents reason, but keeps the one saved', function (): void {
    config(['chatvia.ai.reasoning.enabled' => false]);

    ampSaveEffort($this->owner, $this->agent->external_id, $this->other->external_name, 'medium')
        ->assertSessionHasErrors(['reasoning_effort' => 'Your admin has turned reasoning off.']);

    // Saved earlier, while reasoning was on: other settings still save.
    $this->agent->setSetting('effort', 'high', 'reasoning');
    ampSaveEffort($this->owner, $this->agent->external_id, $this->other->external_name, 'high')->assertSessionHasNoErrors();
    ampSaveEffort($this->owner, $this->agent->external_id, $this->other->external_name, 'off')->assertSessionHasNoErrors();
});

it('tells the setup page the effort and whether reasoning is allowed', function (): void {
    config(['chatvia.ai.reasoning.enabled' => true]);
    $this->agent->setSetting('effort', 'low', 'reasoning');

    $this->actingAs($this->owner)
        ->get(route('agents.setup', $this->agent->external_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('reasoningEffort', 'low')
            ->where('reasoningAllowed', true));
});

it('makes the agent reason only with a reasoning model while the admin allows it', function (bool $allowed, bool $modelReasons, string $expected): void {
    config(['chatvia.ai.reasoning.enabled' => $allowed]);
    $this->other->update(['capabilities' => ['reasoning' => $modelReasons]]);
    $this->agent->forceFill(['llm_id' => $this->other->id])->saveQuietly();
    $this->agent->setSetting('effort', 'medium', 'reasoning');

    expect($this->agent->fresh()->reasoningEffort()->value)->toBe($expected);
})->with([
    'allowed, reasoning model' => [true, true, 'medium'],
    'not allowed' => [false, true, 'off'],
    'model does not reason' => [true, false, 'off'],
]);
