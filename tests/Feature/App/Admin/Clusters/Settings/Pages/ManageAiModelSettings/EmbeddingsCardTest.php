<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Enums\AiProvider;

/**
 * The Embeddings card of Settings → AI models: the embedding service is
 * refused when it cannot return the vector size the database stores, and
 * stays open once sources are trained, since a change re-indexes them.
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

it('keeps the embedding provider and models open once a source is trained', function (): void {
    Chunk::factory()->create(['chunk' => 'Trained text.']);

    // A change warns and re-indexes instead of being locked.
    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertFormFieldEnabled('embedding_provider')
        ->assertFormFieldEnabled('scaleway_embedding_model');
});
