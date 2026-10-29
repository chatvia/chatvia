<?php

use Tests\Support\Api\ActsAsApiOwner;

/**
 * The public API answers a missing token as JSON even when the client leaves
 * out `Accept: application/json` (never a redirect to the login page), and
 * 404s an id that is not a UUID before it reaches the database.
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

it('404s an id that is not a UUID instead of failing in the database', function (string $method, string $uri): void {
    $this->withToken($this->token)
        ->json($method, $uri)
        ->assertNotFound();
})->with([
    'function' => ['GET', fn (): string => '/api/v1/functions/42'],
    'conversation' => ['GET', fn (): string => "/api/v1/agents/{$this->agent->external_id}/conversations/42"],
]);
