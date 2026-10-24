<?php

use Tests\Support\Api\ActsAsApiOwner;

/**
 * The public API's read-only analytics of an agent, one endpoint per
 * dashboard report (overview, sentiment, tone, topics, sources): each answers
 * with its report's shape, and a date range that ends before it starts is a
 * field error. The numbers are the analytics queries' and are tested there.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
});

it('answers each analytics endpoint with its report shape', function (string $report, string $key, mixed $empty): void {
    $this->withToken($this->token)
        ->getJson("/api/v1/agents/{$this->agent->external_id}/analytics/{$report}")
        ->assertOk()
        ->assertJsonPath("data.{$key}", $empty);
})->with([
    'overview' => ['overview', 'total_messages', 0],
    'sentiment' => ['sentiment', 'totals.total', 0],
    'tone' => ['tone', 'totals.total', 0],
    'topics' => ['topics', 'total_count', 0],
    'sources' => ['sources', 'knowledge', []],
]);

it('rejects a date range that ends before it starts', function (): void {
    $this->withToken($this->token)
        ->getJson("/api/v1/agents/{$this->agent->external_id}/analytics/overview?start_date=2026-10-10&end_date=2026-10-01")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});
