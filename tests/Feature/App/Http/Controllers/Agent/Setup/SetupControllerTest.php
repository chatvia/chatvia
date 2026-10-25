<?php

use App\Identity\Workspace\Models\User;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

/**
 * Setup → Model: the page lists the models an admin made available (Settings
 * → AI models) with their provider's logo, their models.dev name and maker's
 * logo, marks the default, preselects the agent's own model (or the default
 * while it has none), and shows the saved generation settings and reasoning
 * effort.
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

it('gives each model the name and maker\'s logo the hosted chat shows, and none for an unlisted one', function (): void {
    $routed = testLlm('openai/gpt-test', LlmProviderKind::OpenRouter);
    Cache::memo()->forever(ModelsDevCatalog::CACHE_KEY, [
        'checked_at' => now()->addYears(100)->getTimestamp(),
        'providers' => ['openrouter' => ['openai/gpt-test' => ['name' => 'GPT Test', 'canonical_model_id' => 'openai/gpt-test']]],
    ]);

    $this->actingAs($this->owner)
        ->get(route('agents.setup', $this->agent->external_id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('catalog', fn (Collection $catalog): bool => $catalog->firstWhere('id', $routed->id)['display_name'] === 'GPT Test'
                && $catalog->firstWhere('id', $routed->id)['logo'] === route('model-logo', ['logo' => 'labs/openai'])
                && $catalog->firstWhere('id', $this->other->id)['display_name'] === null
                && $catalog->firstWhere('id', $this->other->id)['logo'] === null));
});

it('says which models cannot call tools, so the picker can warn', function (): void {
    $toolless = testLlm('no-tools-model', attributes: ['capabilities' => ['tool_call' => false]]);

    $this->actingAs($this->owner)
        ->get(route('agents.setup', $this->agent->external_id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('catalog', fn (Collection $catalog): bool => $catalog->firstWhere('id', $toolless->id)['calls_tools'] === false
                && $catalog->firstWhere('id', $this->other->id)['calls_tools'] === true));
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

it('locks the file-upload switch while the install has no chat file uploads', function (Closure $arrange, bool $available): void {
    $arrange();

    $this->actingAs($this->owner)
        ->get(route('agents.setup', $this->agent->external_id))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('fileUploadsAvailable', $available));
})->with([
    'on, with a model that reads files' => [function (): void {
        useChatFiles();
    }, true],
    'turned off by an admin' => [function (): void {
        useChatFiles();
        useChatFiles(false);
    }, false],
    'no model reads files' => [function (): void {}, false],
]);
