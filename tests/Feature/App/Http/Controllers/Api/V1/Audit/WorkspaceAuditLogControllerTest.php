<?php

use Domain\Conversation\Models\AgentActivity;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Uri;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * The workspace's tool-call audit log through the public API: the rows of
 * every agent of the workspace, filtered by connector or outcome (an unknown
 * outcome is a field error). A cursor the log did not hand out starts again
 * at the first page.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
});

it('lists the rows of every agent of the workspace', function (): void {
    AgentActivity::factory()->forAgent($this->agent)->create(['tool_name' => 'mine']);
    AgentActivity::factory()->forAgent(createAgent($this->owner, ['name' => 'Other']))->create(['tool_name' => 'other']);

    $response = $this->withToken($this->token)->getJson('/api/v1/audit')->assertOk();

    expect(collect($response->json('data'))->pluck('tool_name')->sort()->values()->all())->toBe(['mine', 'other']);
});

it('filters the rows by connector', function (): void {
    AgentActivity::factory()->forAgent($this->agent)->create();
    AgentActivity::factory()->forAgent($this->agent)->ofConnector('slack', 'Slack')->create();

    $this->withToken($this->token)
        ->getJson('/api/v1/audit?connector=slack')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.connector_name', 'Slack');
});

it('rejects an unknown outcome as a field error', function (): void {
    $this->withToken($this->token)
        ->getJson('/api/v1/audit?outcome=exploded')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['outcome']);
});

it('starts again at the first page for a cursor it did not hand out', function (): void {
    AgentActivity::factory()->forAgent($this->agent)->create();
    $newest = AgentActivity::factory()->forAgent($this->agent)->create();

    // The column the log's own cursor carries.
    $handedOut = Cursor::fromEncoded($this->withToken($this->token)->getJson('/api/v1/audit?per_page=1')->json('next_cursor'));
    $column = array_key_first($handedOut->toArray());

    foreach (['not-a-cursor', new Cursor(['other' => 1])->encode(), new Cursor([$column => 'abc'])->encode()] as $cursor) {
        $this->withToken($this->token)
            ->getJson(Uri::of('/api/v1/audit')->withQuery(['per_page' => 1, 'cursor' => $cursor])->value())
            ->assertOk()
            ->assertJsonPath('data.0.trace_id', $newest->trace_id);
    }
});
