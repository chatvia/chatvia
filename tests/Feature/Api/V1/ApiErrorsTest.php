<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;

/*
|--------------------------------------------------------------------------
| Public API — one error shape
|--------------------------------------------------------------------------
|
| An API error is always JSON `{message, errors?}` with its status, even when
| the client leaves out `Accept: application/json`: never a redirect to the
| login page or back to a form, never an HTML error page.
*/

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->token = $this->owner->createToken('account')->plainTextToken;
});

it('answers a missing token with a JSON 401 without an Accept header', function (): void {
    $this->get('/api/v1/agents')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('answers a validation failure with a JSON 422 without an Accept header', function (): void {
    $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
        ->post('/api/v1/agents', ['name' => ''])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message', 'errors' => ['name']]);
});

it('answers an unknown record with a JSON 404 without an Accept header', function (): void {
    $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
        ->get('/api/v1/functions/019a0000-0000-7000-8000-000000000000')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);
});

it('404s an id that is not a UUID instead of failing in the database', function (string $uri): void {
    $this->withHeaders(['Authorization' => 'Bearer '.$this->token, 'Accept' => 'application/json'])
        ->getJson($uri)
        ->assertNotFound();
})->with([
    'function' => '/api/v1/functions/42',
    'connector' => '/api/v1/mcps/not-an-id',
]);
