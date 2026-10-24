<?php

use Domain\Knowledge\Ingestion\Services\ContentAnalyzer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The analysis asks for the language before anything written in it: asked
 * last, the model had already written the questions in a guessed language
 * and then named that one.
 */
beforeEach(function (): void {
    config(['services.mistral.base_url' => 'https://mistral.test/v1']);
    useSourceSummaries();
});

it('asks for the document language before the questions and topics', function (): void {
    Http::fake(['mistral.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'language' => 'en',
        'questions' => ['q1', 'q2', 'q3', 'q4', 'q5'],
        'document_type' => 'policy_document',
        'metadata' => [
            'complexity_level' => 'intermediate',
            'target_audience' => 'end_users',
            'writing_style' => 'informative',
            'estimated_reading_time_minutes' => 8,
            'key_topics' => ['purchase protection'],
            'contains_sensitive_info' => false,
        ],
        'confidence_score' => 0.9,
    ])]]]])]);

    $analysis = app(ContentAnalyzer::class)->analyze('data:application/pdf;base64,JVBERi0xLjQ=');

    expect($analysis->language)->toBe('en');
    Http::assertSent(function (Request $request): bool {
        $schema = $request['response_format']['json_schema']['schema'] ?? [];

        return array_key_first($schema['properties'] ?? []) === 'language'
            && ($schema['required'][0] ?? null) === 'language';
    });
});
