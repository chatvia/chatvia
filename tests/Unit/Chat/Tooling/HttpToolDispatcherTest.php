<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Dispatcher\HttpToolDispatcher;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('stamps the agent and owner identity headers on the webhook request', function (): void {
    $user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($user);

    $agent = Agent::create([
        'user_id' => $user->id,
        'team_id' => $user->primaryTeam()->id,
        'name' => 'Support Agent',
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'is_public' => false,
        'source' => 'api',
    ]);

    $function = new AgentFunction;
    $function->user_id = $user->id;
    $function->internal_name = 'lookup_order';
    $function->description = 'Look up an order by id';
    $function->parameters_schema = ['type' => 'object', 'properties' => []];
    $function->webhook_url = 'https://hooks.example.test/fn';
    $function->webhook_method = 'POST';
    $function->auth_type = 'none';
    $function->is_enabled = true;
    $function->save();

    $agent->functions()->attach($function->id);

    Http::fake(['*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, 'lookup_order', '{}');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://hooks.example.test/fn'
        && $request->header('X-Chatvia-Agent-Id') === [$agent->external_id]
        && $request->header('X-Chatvia-User-Id') === [$user->external_id]);
});

/**
 * @param  array<string, string>|null  $authConfig
 * @return array{0: Agent, 1: AgentFunction}
 */
function htdFunction(string $method = 'POST', string $authType = 'none', ?array $authConfig = null): array
{
    $user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($user);

    $agent = Agent::create([
        'user_id' => $user->id,
        'team_id' => $user->primaryTeam()->id,
        'name' => 'Support Agent',
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'is_public' => false,
        'source' => 'api',
    ]);

    $function = new AgentFunction;
    $function->user_id = $user->id;
    $function->internal_name = 'lookup_order';
    $function->description = 'Look up an order by id';
    $function->parameters_schema = ['type' => 'object', 'properties' => []];
    $function->webhook_url = 'https://hooks.example.test/fn';
    $function->webhook_method = $method;
    $function->auth_type = $authType;
    $function->auth_config = $authConfig;
    $function->is_enabled = true;
    $function->save();

    $agent->functions()->attach($function->id);

    return [$agent, $function];
}

it('calls the webhook with its configured method and a JSON body', function (string $method): void {
    [$agent] = htdFunction($method);
    Http::fake(['*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, 'lookup_order', '{"order_id":"ORD-1"}');

    Http::assertSent(fn ($request): bool => $request->method() === $method
        && $request->url() === 'https://hooks.example.test/fn'
        && $request->body() === '{"order_id":"ORD-1"}'
        && $request->header('Content-Type') === ['application/json']);
})->with(['POST', 'PATCH', 'DELETE']);

it('sends GET arguments as a query string', function (): void {
    [$agent] = htdFunction('GET');
    Http::fake(['*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, 'lookup_order', '{"order_id":"ORD 1","page":2}');

    Http::assertSent(fn ($request): bool => $request->method() === 'GET'
        && $request->url() === 'https://hooks.example.test/fn?order_id=ORD%201&page=2');
});

it('signs an hmac function request over the timestamp and the raw body', function (): void {
    $this->freezeSecond();
    [$agent] = htdFunction('POST', 'hmac', ['secret' => 'shh-its-a-secret']);
    Http::fake(['*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, 'lookup_order', '{"order_id":"ORD-1"}');

    $timestamp = (string) now()->getTimestamp();
    $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.{"order_id":"ORD-1"}', 'shh-its-a-secret');

    Http::assertSent(fn ($request): bool => $request->header(HttpToolDispatcher::TIMESTAMP_HEADER) === [$timestamp]
        && $request->header(HttpToolDispatcher::SIGNATURE_HEADER) === [$expected]
        // The secret itself never travels.
        && ! str_contains(json_encode($request->headers()), 'shh-its-a-secret'));
});

it('signs a GET hmac request over the raw query string', function (): void {
    $this->freezeSecond();
    [$agent] = htdFunction('GET', 'hmac', ['secret' => 'shh']);
    Http::fake(['*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, 'lookup_order', '{"order_id":"ORD-1"}');

    $timestamp = (string) now()->getTimestamp();
    $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.order_id=ORD-1', 'shh');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://hooks.example.test/fn?order_id=ORD-1'
        && $request->header(HttpToolDispatcher::SIGNATURE_HEADER) === [$expected]);
});

it('does not sign requests for other auth types', function (): void {
    [$agent] = htdFunction('POST', 'bearer', ['token' => 'tok']);
    Http::fake(['*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, 'lookup_order', '{}');

    Http::assertSent(fn ($request): bool => $request->header('Authorization') === ['Bearer tok']
        && $request->header(HttpToolDispatcher::SIGNATURE_HEADER) === []);
});

it('refuses to send an hmac request without a signing secret', function (): void {
    [$agent, $function] = htdFunction('POST', 'hmac', ['secret' => '']);
    Http::fake();

    $result = (new HttpToolDispatcher)->dispatch($agent, 'lookup_order', '{}');

    Http::assertNothingSent();
    expect($result->isError)->toBeTrue()
        ->and($function->fresh()->last_error)->toBe('missing hmac signing secret');
});
