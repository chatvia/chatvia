<?php

use App\Admin\Settings\AiSettings;
use App\Admin\Settings\Jobs\ReindexEmbeddingsJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

/**
 * Vectors of two embedding models cannot be compared, so a change of the
 * embedding service or model in use (from any place that saves the AI
 * settings) embeds everything again, in the queue. The job is queued once
 * the save is done (a deferred callback), so these tests run deferred
 * callbacks at once.
 */
beforeEach(function (): void {
    $this->withoutDefer();
});

function rnemSettings(string $provider, string $openAiModel = 'text-embedding-3-small'): AiSettings
{
    $settings = app(AiSettings::class);
    $settings->embedding_provider = $provider;
    $settings->openai_embedding_model = $openAiModel;
    $settings->save();

    return $settings;
}

it('queues a re-index of everything when the embedding in use changes', function (string $property, string $value): void {
    Bus::fake();
    $settings = rnemSettings('openai');
    Bus::fake();

    $settings->{$property} = $value;
    $settings->save();

    Bus::assertDispatched(ReindexEmbeddingsJob::class);
})->with([
    'the model' => ['openai_embedding_model', 'text-embedding-3-large'],
    'the service' => ['embedding_provider', 'openrouter'],
]);

it('queues the re-index on the long ingestion queue', function (): void {
    Queue::fake();
    $settings = rnemSettings('openai');

    $settings->openai_embedding_model = 'text-embedding-3-large';
    $settings->save();

    Queue::assertPushedOn('ingestion.chunks', ReindexEmbeddingsJob::class);
});

it('leaves the vectors alone when another service\'s model or another setting changes', function (): void {
    Bus::fake();
    $settings = rnemSettings('openai');
    Bus::fake();

    $settings->jina_embedding_model = 'jina-embeddings-v4';
    $settings->history_messages = 12;
    $settings->save();

    Bus::assertNotDispatched(ReindexEmbeddingsJob::class);
});
