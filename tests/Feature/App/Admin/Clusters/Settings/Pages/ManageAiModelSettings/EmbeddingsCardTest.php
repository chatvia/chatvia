<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\Jobs\ReindexEmbeddingsJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Bus;

/**
 * The Embeddings card of Settings → AI models: the embedding service is
 * refused when it cannot return the vector size the database stores, and a
 * new embedding model for trained sources is saved only once the admin
 * confirms it, then re-indexes them.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('refuses an embedding provider that cannot return the stored vector size', function (): void {
    Chunk::query()->delete();
    config(['chatvia.embeddings.dimensions' => 2048]);
    connectAiProviders(AiProvider::Mistral);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertFormFieldEnabled('embedding_provider')
        ->fillForm(['embedding_provider' => 'mistral'])
        ->call('save')
        ->assertHasFormErrors(['embedding_provider']);

    expect(savedSetting(AiSettings::class, 'embedding_provider'))->toBeNull();
});

it('asks before it changes the embedding model of trained sources, then saves it and re-indexes', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    Chunk::factory()->create(['chunk' => 'Trained text.']);
    $before = savedSetting(AiSettings::class, 'openai_embedding_model');
    Bus::fake();
    $this->app->bind(DeferredCallbackCollection::class, fn (): DeferredCallbackCollection => new class extends DeferredCallbackCollection
    {
        public function offsetSet(mixed $offset, mixed $value): void
        {
            $value();
        }
    });

    $page = adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertFormFieldEnabled('embedding_provider')
        ->fillForm(['embedding_provider' => 'openai', 'openai_embedding_model' => 'text-embedding-3-large'])
        ->call('save')
        ->assertActionMounted('changeEmbeddings');

    expect(savedSetting(AiSettings::class, 'openai_embedding_model'))->toBe($before);
    Bus::assertNotDispatched(ReindexEmbeddingsJob::class);

    $page->callMountedAction();

    expect(savedSetting(AiSettings::class, 'embedding_provider'))->toBe('openai')
        ->and(savedSetting(AiSettings::class, 'openai_embedding_model'))->toBe('text-embedding-3-large');
    Bus::assertDispatched(ReindexEmbeddingsJob::class);
});
