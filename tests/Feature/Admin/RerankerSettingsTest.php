<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Llm\Enums\AiProvider;
use Livewire\Livewire;

/**
 * The Reranker card of Settings → AI models: the service, the OpenRouter
 * model from the strongest rerankers, and the address of a self-hosted one.
 * What is saved is the reranker the search and tool routing use. A provider
 * that is not connected cannot be picked.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    app()->forgetInstance(Reranker::class);
});

it('offers the strongest OpenRouter rerankers, best first', function (): void {
    $fields = Livewire::actingAs($this->admin, 'admin')->test(ManageAiModelSettings::class)->instance()->form->getFlatFields(withHidden: true);

    expect(array_slice(array_keys($fields['openrouter_rerank_model']->getOptions()), 0, 3))->toBe(['cohere/rerank-4-pro', 'voyageai/rerank-3', 'qwen/qwen3-reranker-8b'])
        ->and(array_keys($fields['cohere_rerank_model']->getOptions()))->toBe(['rerank-v4.0-pro', 'rerank-v4.0-fast'])
        ->and(array_keys($fields['reranker']->getOptions()))->toBe(['auto', 'openrouter', 'cohere', 'custom', 'none']);
});

it('saves an OpenRouter reranker, and the search uses it', function (): void {
    connectAiProviders(AiProvider::OpenRouter);

    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageAiModelSettings::class)
        ->fillForm(['reranker' => 'openrouter', 'openrouter_rerank_model' => 'qwen/qwen3-reranker-8b'])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetScopedInstances();

    expect(app(AiSettings::class)->openrouter_rerank_model)->toBe('qwen/qwen3-reranker-8b')
        ->and(app(Reranker::class)->model())->toBe('qwen/qwen3-reranker-8b');
});

it('saves a self-hosted reranker with its address and model', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageAiModelSettings::class)
        ->fillForm([
            'reranker' => 'custom',
            'rerank_custom_url' => 'https://reranker.example.com/v1',
            'rerank_custom_model' => 'Qwen/Qwen3-Reranker-4B',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('chatvia.ai.rerank.custom.url'))->toBe('https://reranker.example.com/v1')
        ->and(app(Reranker::class)->model())->toBe('Qwen/Qwen3-Reranker-4B');
});

it('refuses a reranker whose provider is not connected', function (): void {
    config(['services.openrouter.api_key' => null]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageAiModelSettings::class)
        ->fillForm(['reranker' => 'openrouter'])
        ->call('save')
        ->assertHasFormErrors(['reranker' => 'Connect OpenRouter in AI providers first.']);
});

it('refuses a reranker that is not offered', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageAiModelSettings::class)
        ->fillForm(['reranker' => 'qwen'])
        ->call('save')
        ->assertHasFormErrors(['reranker']);
});
