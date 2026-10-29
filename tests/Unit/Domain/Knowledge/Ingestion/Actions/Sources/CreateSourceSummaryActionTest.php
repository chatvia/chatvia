<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Ingestion\Actions\Sources\CreateSourceSummaryAction;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * The summary chunk (chunk_index 0) comes from one analysis call and one
 * summary call, both to the model for source summaries. Without a model that
 * reads PDF files, or with summaries off, no model is called.
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
    'summaries off' => [fn (): ?Llm => useSourceSummaries(false)],
]);
