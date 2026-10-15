<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Actions\Sources\CreateSourceSummaryAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * The summary chunk's stored cost must come from the token counts the provider
 * reports for the analysis and summary calls (the prompt holds the whole
 * document), priced from config — not from the prompt text counted as output
 * at hard-coded prices. The PDF goes inside the request, so the model reads
 * the document even when it could not open a link to this site.
 */
beforeEach(function (): void {
    Storage::fake();

    config([
        'services.mistral.api_key' => 'test-key',
        'services.mistral.base_url' => 'https://mistral.test/v1',
        'chatvia.ai.pricing.summary_input_per_million_tokens' => 2.0,
        'chatvia.ai.pricing.summary_output_per_million_tokens' => 8.0,
        'chatvia.ai.pricing.embedding_per_million_tokens' => 0.0,
    ]);

    app()->bind(EmbeddingsProviderInterface::class, fn (): EmbeddingsProviderInterface => new class implements EmbeddingsProviderInterface
    {
        public function embed(string $text, bool $returnAsText = false): string|array
        {
            $vector = array_fill(0, 1024, 0.1);

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
            return 'fake-embed';
        }
    });
});

it('prices the summary chunk from the provider usage of both calls', function (): void {
    Http::fake(function (Request $request) {
        if (isset($request['response_format'])) {
            return Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'questions' => ['q1', 'q2', 'q3', 'q4', 'q5'],
                    'document_type' => 'user_manual',
                    'metadata' => [
                        'complexity_level' => 'beginner',
                        'target_audience' => 'end_users',
                        'writing_style' => 'instructional',
                        'estimated_reading_time_minutes' => 3,
                        'key_topics' => ['setup'],
                        'contains_sensitive_info' => false,
                    ],
                    'language' => 'en',
                    'confidence_score' => 0.9,
                ])]]],
                'usage' => ['prompt_tokens' => 10_000, 'completion_tokens' => 500],
            ]);
        }

        return Http::response([
            'choices' => [['message' => ['content' => 'A short summary of the manual.']]],
            'usage' => ['prompt_tokens' => 10_000, 'completion_tokens' => 300],
        ]);
    });

    $source = AgentFileFactory::new()->create([
        'filepath' => 'users/x/agents/y/files/manual.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::put($source->filepath, '%PDF-1.4 test');

    $chunk = app(CreateSourceSummaryAction::class)->execute($source);

    // Analysis: 10,000 × 2.0 + 500 × 8.0 per 1M tokens = 2.4 cents.
    // Summary:  10,000 × 2.0 + 300 × 8.0 per 1M tokens = 2.24 cents.
    expect($chunk->cost_in_micro_cents)->toBe(4_640_000)
        ->and($chunk->chunk)->toBe('A short summary of the manual.');
});

it('skips the summary, without any LLM call, when no LLM can read PDF documents', function (): void {
    config(['services.mistral.api_key' => null, 'chatvia.ai.document_llm' => 'auto']);
    Http::fake();

    $source = AgentFileFactory::new()->create([
        'filepath' => 'users/x/agents/y/files/manual.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::put($source->filepath, '%PDF-1.4 test');

    expect(app(CreateSourceSummaryAction::class)->execute($source))->toBeNull()
        ->and($source->summary()->exists())->toBeFalse();

    Http::assertNothingSent();
});

it('reads the document with the configured summary and analysis models', function (): void {
    config([
        'chatvia.ai.models.summary' => 'summary-model-x',
        'chatvia.ai.models.analysis' => 'analysis-model-x',
    ]);

    Http::fake(function (Request $request) {
        if (isset($request['response_format'])) {
            return Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'questions' => ['q1', 'q2', 'q3', 'q4', 'q5'],
                    'document_type' => 'faq',
                    'metadata' => [
                        'complexity_level' => 'beginner',
                        'target_audience' => 'end_users',
                        'writing_style' => 'informative',
                        'estimated_reading_time_minutes' => 1,
                        'key_topics' => ['refunds'],
                        'contains_sensitive_info' => false,
                    ],
                    'language' => 'en',
                    'confidence_score' => 0.9,
                ])]]],
            ]);
        }

        return Http::response(['choices' => [['message' => ['content' => 'Summary.']]]]);
    });

    $source = AgentFileFactory::new()->create([
        'filepath' => 'users/x/agents/y/files/faq.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::put($source->filepath, '%PDF-1.4 test');

    app(CreateSourceSummaryAction::class)->execute($source);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://mistral.test/v1/chat/completions'
        && $request['model'] === 'analysis-model-x' && isset($request['response_format']));
    Http::assertSent(fn (Request $request): bool => $request['model'] === 'summary-model-x' && ! isset($request['response_format']));
});

it('sends the PDF itself, and asks for the summary in the detected language', function (): void {
    Http::fake(function (Request $request) {
        if (isset($request['response_format'])) {
            return Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'questions' => ['q1', 'q2', 'q3', 'q4', 'q5'],
                    'document_type' => 'other',
                    'metadata' => [
                        'complexity_level' => 'beginner',
                        'target_audience' => 'general_public',
                        'writing_style' => 'narrative',
                        'estimated_reading_time_minutes' => 1,
                        'key_topics' => ['familie'],
                        'contains_sensitive_info' => false,
                    ],
                    'language' => 'da',
                    'confidence_score' => 0.9,
                ])]]],
            ]);
        }

        return Http::response(['choices' => [['message' => ['content' => 'Resumé.']]]]);
    });

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
        && str_contains($request['messages'][0]['content'][0]['text'], 'The document is written in Danish.'));
});
