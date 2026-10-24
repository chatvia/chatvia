<?php

use Domain\Conversation\Models\AgentActivity;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Uri;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * An agent's tool-call audit log through the public API: only that agent's
 * rows, by public ids, filtered by outcome, newest first and paged by a
 * cursor. A cursor the log did not hand out starts again at the first page.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/audit";
});

it("lists only the agent's rows, by public ids", function (): void {
    AgentActivity::factory()->forAgent($this->agent)->create(['tool_name' => 'mine']);
    AgentActivity::factory()->forAgent(createAgent($this->owner, ['name' => 'Other']))->create(['tool_name' => 'other']);

    $response = $this->withToken($this->token)
        ->getJson($this->uri)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.tool_name', 'mine')
        ->assertJsonPath('data.0.agent_id', $this->agent->external_id);

    // Rows expose public ids only, no integer primary keys.
    expect($response->json('data.0'))->not->toHaveKey('id');
});

it('filters the rows by outcome', function (): void {
    AgentActivity::factory()->forAgent($this->agent)->create(['outcome' => 'denied', 'is_gated' => true]);
    AgentActivity::factory()->forAgent($this->agent)->create();

    $this->withToken($this->token)
        ->getJson("{$this->uri}?outcome=denied")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.outcome', 'denied');
});

it('pages the log newest first and walks it with the cursor', function (): void {
    $first = AgentActivity::factory()->forAgent($this->agent)->create();
    $second = AgentActivity::factory()->forAgent($this->agent)->create();

    $page = $this->withToken($this->token)
        ->getJson("{$this->uri}?per_page=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.trace_id', $second->trace_id);

    $this->withToken($this->token)
        ->getJson(Uri::of($this->uri)->withQuery(['per_page' => 1, 'cursor' => $page->json('next_cursor')])->value())
        ->assertOk()
        ->assertJsonPath('data.0.trace_id', $first->trace_id)
        ->assertJsonPath('next_cursor', null);
});

it('starts again at the first page for a cursor it did not hand out', function (): void {
    AgentActivity::factory()->forAgent($this->agent)->create();
    $newest = AgentActivity::factory()->forAgent($this->agent)->create();

    // The column the log's own cursor carries.
    $handedOut = Cursor::fromEncoded($this->withToken($this->token)->getJson("{$this->uri}?per_page=1")->json('next_cursor'));
    $column = array_key_first($handedOut->toArray());

    foreach (['not-a-cursor', new Cursor(['other' => 1])->encode(), new Cursor([$column => 'abc'])->encode()] as $cursor) {
        $this->withToken($this->token)
            ->getJson(Uri::of($this->uri)->withQuery(['per_page' => 1, 'cursor' => $cursor])->value())
            ->assertOk()
            ->assertJsonPath('data.0.trace_id', $newest->trace_id);
    }
});
