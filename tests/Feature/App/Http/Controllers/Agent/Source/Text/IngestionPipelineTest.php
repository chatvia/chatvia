<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * The knowledge ingestion pipeline end to end, from the upload to the stored
 * chunks. Text sources are the simplest real path:
 *
 *   POST agents.sources.text.store
 *     → CreateAgentSourceTextAction (storage write, pivot attach)
 *     → TextIngestionJob → IngestTextAction → Bus::batch(TextChunkIngestionJob)
 *     → CreateSourceChunksAction → Chunk rows (plain text, morph-aliased)
 *
 * The embeddings provider is TestCase's default fake (deterministic, a
 * different vector per text, no outside call); the summary job is the only
 * queue entry faked in the deep test and text enhancement is off there, so
 * the chunking batch runs for real on the sync queue without a model call.
 * Searching the stored chunks is the retrieval tests' (KnowledgeBaseTest,
 * HybridChunkSearchTest).
 */
beforeEach(function (): void {
    Storage::fake();
});

/**
 * Clean (single-spaced, trimmed) text over 500 characters, so it passes the
 * request's minimum and survives TextCleaner unchanged: the checksum can be
 * checked against the raw input.
 */
function ipText(): string
{
    return 'Chatvia refund policy. Customers can request a full refund within thirty days of their purchase by contacting the support team. '
        .'Refunds are processed back to the original payment method within five business days. '
        .'Annual subscriptions are refunded on a pro rata basis after the first month of service has elapsed. '
        .'Add-on message credits are non-refundable once consumed, but unused credit packs purchased in the last thirty days qualify for the standard refund. '
        .'Enterprise agreements follow the refund terms negotiated in the master services agreement instead of this policy. '
        .'For any refund question, contact billing support with the invoice number.';
}

it('creates an owned text source, stores the content, attaches it to the agent and queues ingestion', function (): void {
    Bus::fake([TextIngestionJob::class]);

    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $content = ipText();

    $this->actingAs($owner)
        ->postJson(route('agents.sources.text.store', $agent), ['content' => $content])
        ->assertOk();

    $source = SourceText::query()->where('workspace_id', $owner->workspace()->id)->sole();

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

it('ingests an uploaded text into plain-text chunks stored under the source_text morph alias', function (): void {
    // Fake ONLY the LLM-bound summary job and turn text enhancement off;
    // TextIngestionJob and the chunking batch run for real on the sync
    // queue, driving the full upload → split → embed → chunk-row path.
    Queue::fake([SummaryIngestionJob::class]);
    config(['chatvia.ai.chunker.enhance_text' => false]);

    $owner = User::factory()->create();
    $agent = createAgent($owner);

    $this->actingAs($owner)
        ->postJson(route('agents.sources.text.store', $agent), ['content' => ipText()])
        ->assertOk();

    $source = SourceText::query()->where('workspace_id', $owner->workspace()->id)->sole();

    // A typed text is split as plain text (by the simple fake splitter here).
    expect(app(TextSplitter::class)->kinds)->toBe([ContentKind::Text]);

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
        ->and($chunks->first()->workspace_id)->toBe($owner->workspace()->id)
        ->and($chunks->first()->chunk)->toContain('refund')
        ->and($chunks->first()->checksum)->not->toBe('');

    // chunkable_type must equal the pivot's sourceable_type or the
    // agent_sources ↔ chunks join in HybridChunkSearch silently returns
    // zero rows (documented project footgun).
    $pivotType = DB::table('agent_sources')->where('agent_id', $agent->id)->value('sourceable_type');
    expect($pivotType)->toBe($chunks->first()->chunkable_type);

    // With enhancement off neither a summary nor an enhanced chunk is made.
    Queue::assertNotPushed(SummaryIngestionJob::class);
    expect($chunks->contains('is_enhanced', true))->toBeFalse();
});
