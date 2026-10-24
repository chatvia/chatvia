<?php

use App\Identity\Workspace\Models\User;
use Domain\Llm\Enums\LlmProviderKind;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

/**
 * Setup → Model: the page lists the models an admin made available (Settings
 * → AI models) with their provider's logo, marks the default, preselects the
 * agent's own model (or the default while it has none), and shows the saved
 * generation settings and reasoning effort.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
    $this->default = testLlm('default-model');
    $this->other = testLlm('other-model');
    config(['chatvia.agent.default_model' => (string) $this->default->id]);
});

it('lists the available models and preselects the default while the agent has none', function (): void {
    $this->agent->forceFill(['llm_id' => null])->saveQuietly();

    $this->actingAs($this->owner)
        ->get(route('agents.setup', $this->agent->external_id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('selectedModel', $this->default->id)
            ->where('defaultModel', $this->default->id)
            ->where('catalog', fn (Collection $catalog): bool => $catalog->pluck('id')->contains($this->other->id)));
});

it('gives each model its provider logo, and none for a server of your own', function (): void {
    $routed = testLlm('openai/gpt-test', LlmProviderKind::OpenRouter);

    $this->actingAs($this->owner)
        ->get(route('agents.setup', $this->agent->external_id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('catalog', fn (Collection $catalog): bool => $catalog->firstWhere('id', $routed->id)['provider_icon'] === asset('images/llms/openrouter.svg')
                && $catalog->firstWhere('id', $this->other->id)['provider_icon'] === null));
});

it('sends the saved generation settings as numbers', function (): void {
    $this->agent->setSetting('temperature', '0.7', 'llm_settings');

    $this->actingAs($this->owner)
        ->get(route('agents.setup', $this->agent->external_id))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('llmSettings.temperature', 0.7));
});

it('tells the setup page the effort and whether reasoning is allowed', function (): void {
    config(['chatvia.ai.reasoning.enabled' => true]);
    $this->agent->setSetting('effort', 'low', 'reasoning');

    $this->actingAs($this->owner)
        ->get(route('agents.setup', $this->agent->external_id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('reasoningEffort', 'low')
            ->where('reasoningAllowed', true));
});
