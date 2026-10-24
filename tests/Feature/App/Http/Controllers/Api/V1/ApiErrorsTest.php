<?php

use Tests\Support\Api\ActsAsApiOwner;

/**
 * The public API answers every error as JSON `{message, errors?}` with its
 * status, even when the client leaves out `Accept: application/json`: never
 * a redirect to the login page or back to a form, never an HTML error page.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
});

it('answers a missing token with a JSON 401 without an Accept header', function (): void {
    $this->get('/api/v1/agents')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('answers a validation failure with a JSON 422 without an Accept header', function (): void {
    $this->withToken($this->token)
        ->post('/api/v1/agents', ['name' => ''])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message', 'errors' => ['name']]);
});

it('answers an unknown record with a JSON 404 without an Accept header', function (): void {
    $this->withToken($this->token)
        ->get('/api/v1/functions/019a0000-0000-7000-8000-000000000000')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);
});

it('404s an id that is not a UUID instead of failing in the database', function (string $method, string $uri): void {
    $this->withToken($this->token)
        ->json($method, $uri)
        ->assertNotFound();
})->with([
    'function' => ['GET', fn (): string => '/api/v1/functions/42'],
    'connector' => ['GET', fn (): string => '/api/v1/mcps/not-an-id'],
    'conversation' => ['GET', fn (): string => "/api/v1/agents/{$this->agent->external_id}/conversations/42"],
    'text' => ['DELETE', fn (): string => "/api/v1/agents/{$this->agent->external_id}/sources/texts/42"],
]);
