<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Ingestion\Actions\Sources\CreateSourceSummaryAction;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Enums\LlmProviderKind;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The summary chunk (chunk_index 0) comes from one analysis call and one
 * summary call, both to the model for source summaries. The PDF goes inside
 * the request, so the model reads the document even when it could not open
 * a link to this site.
 */
beforeEach(function (): void {
    Storage::fake();

    config(['services.mistral.base_url' => 'https://mistral.test/v1']);
    useSourceSummaries(name: 'mistral-summary-x');
});

/**
 * The analysis model answers with this document type, language and topics
 * (and five questions); the summary model answers with $summary.
 *
 * @param  list<string>  $topics
 */
function cssFakeModels(string $documentType, string $language, array $topics, string $summary): void
{
    Http::fake(function (Request $request) use ($documentType, $language, $topics, $summary) {
        if (isset($request['response_format'])) {
            return Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'questions' => ['q1', 'q2', 'q3', 'q4', 'q5'],
                    'document_type' => $documentType,
                    'metadata' => [
                        'complexity_level' => 'beginner',
                        'target_audience' => 'end_users',
                        'writing_style' => 'instructional',
                        'estimated_reading_time_minutes' => 3,
                        'key_topics' => $topics,
                        'contains_sensitive_info' => false,
                    ],
                    'language' => $language,
                    'confidence_score' => 0.9,
                ])]]],
            ]);
        }

        return Http::response(['choices' => [['message' => ['content' => $summary]]]]);
    });
}

it('stores the summary chunk with the analysis questions and metadata', function (): void {
    cssFakeModels('user_manual', 'en', ['setup'], 'A short summary of the manual.');

    $source = AgentFileFactory::new()->create([
        'filepath' => 'users/x/agents/y/files/manual.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::put($source->filepath, '%PDF-1.4 test');

    $chunk = app(CreateSourceSummaryAction::class)->execute($source);

    expect($chunk->chunk)->toBe('A short summary of the manual.')
        ->and($chunk->chunk_index)->toBe(0)
        ->and($chunk->questions)->toBe("q1\nq2\nq3\nq4\nq5")
        ->and($chunk->metadata['key_topics'] ?? null)->toBe(['setup'])
        ->and($source->fresh()->document_classification)->toBe('user_manual');
});

it('skips the summary, without any LLM call, when no model reads PDF files or summaries are off', function (Closure $setUp): void {
    $setUp();
    Http::fake();

    $source = AgentFileFactory::new()->create([
        'filepath' => 'users/x/agents/y/files/manual.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::put($source->filepath, '%PDF-1.4 test');

    expect(app(CreateSourceSummaryAction::class)->execute($source))->toBeNull()
        ->and($source->summary()->exists())->toBeFalse();

    Http::assertNothingSent();
})->with([
    'no model reads PDF files' => [fn () => config(['chatvia.ai.source_summaries.model' => (string) testLlm('text-only', LlmProviderKind::Mistral, ['capabilities' => ['modalities' => ['input' => ['text']]]])->id])],
    'summaries off' => [fn (): ?\Domain\Llm\Models\Llm => useSourceSummaries(false)],
]);

it('skips a source that already has its summary, so a retry does not pay for it twice', function (): void {
    Http::fake();

    $source = AgentFileFactory::new()->create([
        'filepath' => 'users/x/agents/y/files/manual.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::put($source->filepath, '%PDF-1.4 test');
    $summary = Chunk::factory()->forSource($source)->summary()->create(['chunk' => 'The summary written before.']);

    expect(app(CreateSourceSummaryAction::class)->execute($source))->toBeNull()
        ->and($source->summary()->pluck('id')->all())->toBe([$summary->id]);

    Http::assertNothingSent();
});

it('reads the document with the model for source summaries, and names it on the chunk', function (): void {
    cssFakeModels('faq', 'en', ['refunds'], 'Summary.');

    $source = AgentFileFactory::new()->create([
        'filepath' => 'users/x/agents/y/files/faq.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::put($source->filepath, '%PDF-1.4 test');

    $chunk = app(CreateSourceSummaryAction::class)->execute($source);

    expect($chunk->chunked_using_model)->toBe('mistral-summary-x');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://mistral.test/v1/chat/completions'
        && $request['model'] === 'mistral-summary-x' && isset($request['response_format']));
    Http::assertSent(fn (Request $request): bool => $request['model'] === 'mistral-summary-x' && ! isset($request['response_format']));
});

it('reads the document with a model of another provider, as a file part', function (): void {
    useSourceSummaries(name: 'gpt-x', kind: LlmProviderKind::Custom);
    cssFakeModels('faq', 'en', ['refunds'], 'Summary.');

    $source = AgentFileFactory::new()->create([
        'filepath' => 'users/x/agents/y/files/faq.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::put($source->filepath, '%PDF-1.4 test');

    $chunk = app(CreateSourceSummaryAction::class)->execute($source);

    expect($chunk->chunk)->toBe('Summary.')
        ->and($chunk->chunked_using_model)->toBe('gpt-x');
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://llm.test/v1/chat/completions'
        && $request['model'] === 'gpt-x'
        && ($request['messages'][0]['content'][1]['file']['file_data'] ?? null) === 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 test'));
});

it('sends the PDF itself, and asks for the summary in the detected language', function (): void {
    cssFakeModels('other', 'da', ['familie'], 'Resumé.');

    $source = AgentFileFactory::new()->create([
        'filepath' => 'users/x/agents/y/files/dania.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::put($source->filepath, '%PDF-1.4 Dania');

    app(CreateSourceSummaryAction::class)->execute($source);

    $documents = Http::recorded()->map(fn (array $pair): mixed => $pair[0]['messages'][0]['content'][1]['document_url']);

    expect($documents->all())->toBe(array_fill(0, 2, 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 Dania')))
        ->and($source->fresh()->language)->toBe('da');

    Http::assertSent(fn (Request $request): bool => ! isset($request['response_format'])
        && Str::contains($request['messages'][0]['content'][0]['text'], 'The document is written in Danish.'));
});
