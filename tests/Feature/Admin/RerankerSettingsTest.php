<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Llm\Enums\AiProvider;
use Filament\Forms\Components\Select;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The Reranker card of Settings → AI models: the service, the OpenRouter
 * model from the strongest rerankers, and the address of a self-hosted one.
 * What is saved is the reranker the search and tool routing use. No
 * "Automatic": the list shows the reranker in use and greys out a provider
 * that is not connected. A new reranker, model or server is tested with a
 * short rerank as it is saved, and refused when that fails.
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

function rerankerField(Testable $page): Select
{
    return $page->instance()->form->getFlatFields(withHidden: true)['reranker'];
}

function savedReranker(): ?string
{
    app()->forgetScopedInstances();

    return app(AiSettings::class)->reranker;
}

/** Whether a request is a rerank (the page also lists OpenRouter's models). */
function rerankCall(Request $request): bool
{
    return Str::endsWith(Uri::of($request->url())->getUri()->getPath(), '/rerank');
}

/** A rerank that works: the first text ranks first. */
function rerankResponse(): array
{
    return ['results' => [['index' => 0, 'relevance_score' => 0.9], ['index' => 1, 'relevance_score' => 0.1]]];
}

it('offers the strongest OpenRouter rerankers, best first', function (): void {
    $fields = Livewire::actingAs($this->admin, 'admin')->test(ManageAiModelSettings::class)->instance()->form->getFlatFields(withHidden: true);

    expect(array_slice(array_keys($fields['openrouter_rerank_model']->getOptions()), 0, 3))->toBe(['cohere/rerank-4-pro', 'voyageai/rerank-3', 'qwen/qwen3-reranker-8b'])
        ->and(array_keys($fields['cohere_rerank_model']->getOptions()))->toBe(['rerank-v4.0-pro', 'rerank-v4.0-fast'])
        ->and(array_keys($fields['reranker']->getOptions()))->toBe(['openrouter', 'cohere', 'custom', 'none']);
});

it('shows the reranker in use, marked connected, with no Automatic', function (): void {
    connectAiProviders(AiProvider::OpenRouter);

    $field = rerankerField(adminPage(ManageAiModelSettings::class, $this->admin)->assertFormSet(['reranker' => 'openrouter']));

    expect($field->getOptions())->toBe([
        'openrouter' => 'OpenRouter (connected)',
        'cohere' => 'Cohere Rerank (not connected)',
        'custom' => 'Self-hosted',
        'none' => 'Off (a basic similarity check)',
    ])
        ->and($field->canSelectPlaceholder())->toBeFalse()
        ->and($field->isOptionDisabled('cohere', 'Cohere Rerank'))->toBeTrue()
        ->and($field->isOptionDisabled('openrouter', 'OpenRouter'))->toBeFalse()
        ->and($field->isOptionDisabled('custom', 'Self-hosted'))->toBeFalse()
        ->and($field->isOptionDisabled('none', 'Off'))->toBeFalse();
});

it('shows Off when no reranker is connected', function (): void {
    adminPage(ManageAiModelSettings::class, $this->admin)->assertFormSet(['reranker' => 'none']);
});

it('tests a new OpenRouter model with a rerank before it saves it, and the search uses it', function (): void {
    connectAiProviders(AiProvider::OpenRouter);
    Http::fake(['openrouter.ai/api/v1/rerank' => Http::response(rerankResponse())]);

    // OpenRouter is in use already: only its model changes.
    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['reranker' => 'openrouter', 'openrouter_rerank_model' => 'qwen/qwen3-reranker-8b'])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetScopedInstances();

    expect(app(AiSettings::class)->openrouter_rerank_model)->toBe('qwen/qwen3-reranker-8b')
        ->and(app(Reranker::class)->model())->toBe('qwen/qwen3-reranker-8b');

    // One call, though a save reads the form twice.
    expect(Http::recorded(rerankCall(...)))->toHaveCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/rerank'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['model'] === 'qwen/qwen3-reranker-8b');
});

it('tests Cohere Rerank with its default model before it saves it', function (): void {
    connectAiProviders(AiProvider::Cohere);
    config(['chatvia.ai.reranker' => 'none']);
    Http::fake(['api.cohere.com/v2/rerank' => Http::response(rerankResponse())]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertFormSet(['reranker' => 'none'])
        ->fillForm(['reranker' => 'cohere'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedReranker())->toBe('cohere')
        ->and(Http::recorded(rerankCall(...)))->toHaveCount(1);
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

    expect(savedReranker())->toBeNull();
});

it('saves a self-hosted reranker with its address and model, once it answers', function (): void {
    Http::fake(['reranker.example.com/*' => Http::response(rerankResponse())]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([
            'reranker' => 'custom',
            'rerank_custom_url' => 'https://reranker.example.com/v1',
            'rerank_custom_model' => 'Qwen/Qwen3-Reranker-4B',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('chatvia.ai.rerank.custom.url'))->toBe('https://reranker.example.com/v1')
        ->and(app(Reranker::class)->model())->toBe('Qwen/Qwen3-Reranker-4B')
        ->and(Http::recorded(rerankCall(...)))->toHaveCount(1);
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
        ->and(savedReranker())->toBeNull();
});

it('does not test the reranker again when it is unchanged', function (): void {
    connectAiProviders(AiProvider::OpenRouter);
    config(['chatvia.ai.reranker' => 'openrouter']);
    Http::fake();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertFormSet(['reranker' => 'openrouter'])
        ->call('save')
        ->assertHasNoFormErrors();

    Http::assertNotSent(rerankCall(...));
});

it('turns the reranker off without a call', function (): void {
    connectAiProviders(AiProvider::OpenRouter);
    config(['chatvia.ai.reranker' => 'openrouter']);
    Http::fake();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['reranker' => 'none'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedReranker())->toBe('none');
    Http::assertNotSent(rerankCall(...));
});

it('refuses a reranker whose provider is not connected', function (): void {
    Http::fake();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['reranker' => 'openrouter'])
        ->call('save')
        ->assertHasFormErrors(['reranker' => 'OpenRouter is not connected. Add its key in AI providers first.']);

    Http::assertNotSent(rerankCall(...));
});

it('refuses a reranker that is not offered', function (): void {
    Http::fake();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['reranker' => 'qwen'])
        ->call('save')
        ->assertHasFormErrors(['reranker']);
});
