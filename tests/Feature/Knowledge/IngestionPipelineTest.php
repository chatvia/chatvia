<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Domain\Knowledge\Ingestion\Actions\Chunks\CreateSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Text\TextEnhanceChunkJob;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * End-to-end coverage for the knowledge ingestion pipeline (review §10: no
 * upload→chunk→embed→retrieve test). Text sources are the simplest real path:
 *
 *   POST agents.sources.text.store
 *     → CreateAgentSourceTextAction (storage write, pivot attach)
 *     → TextIngestionJob → IngestTextAction → Bus::batch(TextChunkIngestionJob)
 *     → CreateSourceChunkAction → Chunk rows (plaintext, morph-aliased)
 *     → KnowledgeBase::search (4-channel hybrid RRF over pgvector + bm25)
 *
 * The embeddings provider is faked at the container boundary (house style — no
 * external call); the LLM-bound jobs (SummaryIngestionJob, TextEnhanceChunkJob)
 * are the only queue entries faked in the deep test, so the chunking batch runs
 * for real on the sync queue.
 *
 * REQUIRES the migrated + seeded Postgres test DB (pgvector + bm25 + tokenizer
 * extensions): chunks.embedding is vector(1024) and the retrieval test executes
 * the live HybridChunkSearch SQL.
 */
function ingestionPipelineEmbeddingFake(): void
{
    // Deterministic per-text vectors (seeded off the content) so distinct
    // chunks don't collapse to identical embeddings and hide ranking bugs.
    app()->bind(EmbeddingsProviderInterface::class, fn (): EmbeddingsProviderInterface => new class implements EmbeddingsProviderInterface
    {
        public function embed(string $text, bool $returnAsText = false): string|array
        {
            $base = 0.05 + (crc32($text) % 97) / 1000;
            $vector = array_fill(0, 1024, $base);

            return $returnAsText ? '['.implode(',', $vector).']' : $vector;
        }

        public function embedQuery(string $text, bool $returnAsText = false): string|array
        {
            return $this->embed($text, $returnAsText);
        }

        public function embedBatch(array $texts): array
        {
            return array_map(fn (string $text): array => (array) $this->embed($text), $texts);
        }

        public function getDimensions(): int
        {
            return 1024;
        }

        public function getModelName(): string
        {
            return 'fake-embed-model';
        }
    });

    // No reranking service here: results keep their unranked order.
    app()->instance(Reranker::class, new NullReranker);
}

/**
 * A workspace owner with its default team.
 */
function ingestionPipelineOwner(): User
{
    $owner = User::factory()->create();

    app(TeamService::class)->createDefaultTeam($owner);

    // Re-fetch: primaryTeam() memoizes (a documented footgun) and the team was
    // created after the instance was loaded.
    return User::query()->findOrFail($owner->id);
}

/**
 * Clean (single-spaced, trimmed) text >500 chars so it passes the FormRequest
 * minimum and survives TextCleaner unchanged — letting the checksum assert
 * against the raw input.
 */
function ingestionPipelineText(): string
{
    return 'Chatvia refund policy. Customers can request a full refund within thirty days of their purchase by contacting the support team. '
        .'Refunds are processed back to the original payment method within five business days. '
        .'Annual subscriptions are refunded on a pro rata basis after the first month of service has elapsed. '
        .'Add-on message credits are non-refundable once consumed, but unused credit packs purchased in the last thirty days qualify for the standard refund. '
        .'Enterprise agreements follow the refund terms negotiated in the master services agreement instead of this policy. '
        .'For any refund question, contact billing support with the invoice number.';
}

/**
 * Seed one ingested chunk for an agent directly at the action layer (used by
 * the retrieval test to set up a second, foreign workspace cheaply).
 */
function ingestionPipelineSeedChunk(User $owner, Agent $agent, string $content): SourceText
{
    $source = SourceText::create([
        'user_id' => $owner->id,
        'filepath' => 'users/'.$owner->external_id.'/agents/'.$agent->external_id.'/train/text/'.Str::uuid()->toString().'.txt',
        'excerpt' => Str::limit($content, 200),
        'length' => 1,
        'checksum' => md5($content),
    ]);

    $agent->texts()->attach($source->id);

    app(CreateSourceChunkAction::class)->execute($agent, $source, new PageData(
        pageNumber: 1,
        content: $content,
        checksum: hash('xxh128', $content),
    ));

    return $source;
}

beforeEach(function (): void {
    Storage::fake();
    ingestionPipelineEmbeddingFake();
});

it('creates an owned text source, stores the content, attaches it to the agent and queues ingestion', function (): void {
    Bus::fake([TextIngestionJob::class]);

    $owner = ingestionPipelineOwner();
    $agent = createAgent($owner);
    $content = ingestionPipelineText();

    $this->actingAs($owner)
        ->postJson(route('agents.sources.text.store', $agent), ['content' => $content])
        ->assertOk();

    $source = SourceText::query()->where('user_id', $owner->id)->sole();

    expect($source->checksum)->toBe(md5($content))
        ->and($source->status)->toBe(SourceStatus::Pending)
        ->and($source->excerpt)->not->toBe('')
        ->and($source->length)->toBeGreaterThanOrEqual(1);

    // The raw content lives in storage, not the DB.
    Storage::assertExists($source->filepath);
    expect(Storage::get($source->filepath))->toBe($content);

    // Attached to the agent through the polymorphic pivot using the morph
    // alias — the value HybridChunkSearch later joins on.
    $pivot = DB::table('agent_sources')->where('agent_id', $agent->id)->first();
    expect($pivot)->not->toBeNull()
        ->and($pivot->sourceable_type)->toBe('source_text')
        ->and((int) $pivot->sourceable_id)->toBe($source->id);

    Bus::assertDispatched(TextIngestionJob::class, fn (TextIngestionJob $job): bool => $job->sourceText->is($source) && $job->agent->is($agent));
});

it('rejects a workspace that does not own the agent and creates nothing', function (): void {
    Bus::fake([TextIngestionJob::class]);

    $owner = ingestionPipelineOwner();
    $agent = createAgent($owner);
    $stranger = ingestionPipelineOwner();

    $this->actingAs($stranger)
        ->postJson(route('agents.sources.text.store', $agent), ['content' => ingestionPipelineText()])
        ->assertForbidden();

    expect(SourceText::query()->whereIn('user_id', [$owner->id, $stranger->id])->count())->toBe(0);
    Bus::assertNotDispatched(TextIngestionJob::class);
});

it('rejects an unauthenticated upload', function (): void {
    $owner = ingestionPipelineOwner();
    $agent = createAgent($owner);

    $this->postJson(route('agents.sources.text.store', $agent), ['content' => ingestionPipelineText()])
        ->assertUnauthorized();
});

it('ingests an uploaded text into plaintext chunks stored under the source_text morph alias', function (): void {
    // Fake ONLY the LLM-bound enrichment jobs; TextIngestionJob and the
    // chunking batch run for real on the sync queue, driving the full
    // upload → split → embed → chunk-row path.
    Queue::fake([SummaryIngestionJob::class, TextEnhanceChunkJob::class]);

    $owner = ingestionPipelineOwner();
    $agent = createAgent($owner);

    $this->actingAs($owner)
        ->postJson(route('agents.sources.text.store', $agent), ['content' => ingestionPipelineText()])
        ->assertOk();

    $source = SourceText::query()->where('user_id', $owner->id)->sole();

    // The batch completed inline: terminal status + bookkeeping written. The
    // batch id sits in the same column for every source type.
    expect($source->status)->toBe(SourceStatus::Completed)
        ->and($source->expected_chunks)->toBeGreaterThanOrEqual(1)
        ->and($source->internal_chunking_batch_id)->not->toBeNull();

    $chunks = Chunk::query()
        ->where('chunkable_id', $source->id)
        ->where('chunkable_type', 'source_text') // the morph ALIAS — never the FQCN
        ->get();

    expect($chunks)->toHaveCount($source->expected_chunks)
        ->and($chunks->first()->chunkable_type)->toBe((new SourceText)->getMorphClass())
        ->and($chunks->first()->user_id)->toBe($owner->id)
        ->and($chunks->first()->chunk)->toContain('refund') // decrypted via the model cast
        ->and($chunks->first()->checksum)->not->toBe('');

    // chunkable_type must equal the pivot's sourceable_type or the
    // agent_sources ↔ chunks join in HybridChunkSearch silently returns
    // zero rows (documented project footgun).
    $pivotType = DB::table('agent_sources')->where('agent_id', $agent->id)->value('sourceable_type');
    expect($pivotType)->toBe($chunks->first()->chunkable_type);

    // Content is stored as plaintext at rest.
    $rawChunk = DB::table('chunks')->where('id', $chunks->first()->id)->value('chunk');
    expect($rawChunk)->toContain('refund');

    // The enrichment legs were handed to the queue, one enhance per chunk.
    Queue::assertPushed(SummaryIngestionJob::class, 1);
    Queue::assertPushed(TextEnhanceChunkJob::class, $chunks->count());
});

it('retrieves ingested chunks for the owning agent only via hybrid search', function (): void {
    $owner = ingestionPipelineOwner();
    $agent = createAgent($owner);
    $source = ingestionPipelineSeedChunk($owner, $agent, ingestionPipelineText());

    // A second workspace with its own agent + chunk: retrieval must never
    // cross the agent_sources boundary between them.
    $otherOwner = User::factory()->create();
    $otherAgent = createAgent($otherOwner);
    $otherSource = ingestionPipelineSeedChunk(
        $otherOwner,
        $otherAgent,
        'Shipping policy for the other workspace. Orders ship within two business days from the warehouse.'
    );

    $results = app(KnowledgeBase::class)->search($agent, 'What is the refund policy?');

    expect($results)->not->toBeEmpty()
        ->and(collect($results)->pluck('chunkable_id')->map(fn ($id): int => (int) $id)->unique()->all())->toBe([$source->id])
        ->and(collect($results)->pluck('chunkable_type')->unique()->all())->toBe(['source_text'])
        ->and($results[0]->chunk)->toContain('refund') // decrypted for the LLM consumer
        ->and($results[0]->confidence_score)->toBeGreaterThan(0.0);

    $otherResults = app(KnowledgeBase::class)->search($otherAgent, 'What is the refund policy?');

    expect(collect($otherResults)->pluck('chunkable_id')->map(fn ($id): int => (int) $id)->unique()->all())->toBe([$otherSource->id]);
});
