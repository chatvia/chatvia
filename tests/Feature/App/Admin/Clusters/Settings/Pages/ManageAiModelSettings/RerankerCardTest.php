<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The Reranker card of Settings → AI models: the service, the OpenRouter
 * model from the strongest rerankers, and the address of a self-hosted one.
 * What is saved is the reranker the search and tool routing use. A new
 * reranker, model or server is tested with a short rerank as it is saved,
 * and refused when that fails. What the pickers share is in
 * ServiceChoicesTest.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    app()->forgetInstance(Reranker::class);

    config([
        'chatvia.ai.reranker' => 'auto',
        AiProvider::OpenRouter->apiKeyConfig() => null,
        AiProvider::Cohere->apiKeyConfig() => null,
    ]);
});

/** A rerank that works: the first text ranks first. */
function rcRerankResponse(): array
{
    return ['results' => [['index' => 0, 'relevance_score' => 0.9], ['index' => 1, 'relevance_score' => 0.1]]];
}

it('offers the strongest OpenRouter rerankers, best first', function (): void {
    $fields = adminPage(ManageAiModelSettings::class, $this->admin)->instance()->form->getFlatFields(withHidden: true);

    expect(array_slice(array_keys($fields['openrouter_rerank_model']->getOptions()), 0, 3))->toBe(['cohere/rerank-4-pro', 'voyageai/rerank-3', 'qwen/qwen3-reranker-8b'])
        ->and(array_keys($fields['cohere_rerank_model']->getOptions()))->toBe(['rerank-v4.0-pro', 'rerank-v4.0-fast']);
});

it('tests a new OpenRouter model with a rerank before it saves it, and the search uses it', function (): void {
    connectAiProviders(AiProvider::OpenRouter);
    Http::fake(['openrouter.ai/api/v1/rerank' => Http::response(rcRerankResponse())]);

    // OpenRouter is in use already: only its model changes.
    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['reranker' => 'openrouter', 'openrouter_rerank_model' => 'qwen/qwen3-reranker-8b'])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetScopedInstances();

    expect(app(AiSettings::class)->openrouter_rerank_model)->toBe('qwen/qwen3-reranker-8b')
        ->and(app(Reranker::class)->model())->toBe('qwen/qwen3-reranker-8b');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/rerank'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['model'] === 'qwen/qwen3-reranker-8b');
});

it('tests Cohere Rerank with its default model before it saves it', function (): void {
    connectAiProviders(AiProvider::Cohere);
    config(['chatvia.ai.reranker' => 'none']);
    Http::fake(['api.cohere.com/v2/rerank' => Http::response(rcRerankResponse())]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertFormSet(['reranker' => 'none'])
        ->fillForm(['reranker' => 'cohere'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'reranker'))->toBe('cohere');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cohere.com/v2/rerank'
        && $request['model'] === 'rerank-v4.0-pro');
});

it('refuses OpenRouter when the test rerank is refused', function (): void {
    connectAiProviders(AiProvider::OpenRouter);
    config(['chatvia.ai.reranker' => 'none']);
    Http::fake(['openrouter.ai/api/v1/rerank' => Http::response(['error' => ['message' => 'No auth credentials found']], 401)]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['reranker' => 'openrouter'])
        ->call('save')
        ->assertHasFormErrors(['reranker' => 'OpenRouter refused this key. Check that you copied all of it.']);

    expect(savedSetting(AiSettings::class, 'reranker'))->toBeNull();
});

it('saves a self-hosted reranker with its address and model, once it answers', function (): void {
    Http::fake(['reranker.example.com/*' => Http::response(rcRerankResponse())]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([
            'reranker' => 'custom',
            'rerank_custom_url' => 'https://reranker.example.com/v1',
            'rerank_custom_model' => 'Qwen/Qwen3-Reranker-4B',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('chatvia.ai.rerank.custom.url'))->toBe('https://reranker.example.com/v1')
        ->and(app(Reranker::class)->model())->toBe('Qwen/Qwen3-Reranker-4B');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://reranker.example.com/v1/rerank'
        && $request['model'] === 'Qwen/Qwen3-Reranker-4B');
});

it('says why a self-hosted reranker failed', function (): void {
    Http::fake(['reranker.example.com/*' => Http::response(['message' => 'The model `Qwen/Nope` does not exist.'], 404)]);

    $page = adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([
            'reranker' => 'custom',
            'rerank_custom_url' => 'https://reranker.example.com/v1',
            'rerank_custom_model' => 'Qwen/Nope',
        ])
        ->call('save')
        ->assertHasFormErrors(['reranker']);

    expect($page->errors()->first('data.reranker'))->toBe('Your reranker server could not rerank with this model (error 404): The model `Qwen/Nope` does not exist.')
        ->and(savedSetting(AiSettings::class, 'reranker'))->toBeNull();
});

it('refuses a reranker that is not offered', function (): void {
    Http::fake();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['reranker' => 'qwen'])
        ->call('save')
        ->assertHasFormErrors(['reranker']);
});
