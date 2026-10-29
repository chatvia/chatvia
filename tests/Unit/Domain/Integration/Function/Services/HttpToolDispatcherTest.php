<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\FunctionInvoked;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Services\HttpToolDispatcher;
use Domain\Integration\Tool\Data\ToolCallData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Support\Http\SafeUrl;

/**
 * Running a webhook function for an agent: only an enabled function of the
 * agent's workspace that is attached to it (the pivot has no workspace
 * column, so only the dispatcher's own check keeps another workspace's
 * function out), with arguments held to its schema, sent with its method and
 * auth, with Chatvia's identity and signature headers stamped last so no
 * function header can shadow them. It connects to the address it checked,
 * keeps the webhook URL out of errors and logs, and records every call on the
 * function, the agent pivot and the analytics event. The secret never
 * travels.
 */

/**
 * An agent with the `lookup_order` function attached.
 *
 * @param  array<string, string>|null  $authConfig
 * @param  array<string, mixed>  $overrides
 * @return array{0: Agent, 1: AgentFunction}
 */
function htdFunction(string $method = 'POST', string $authType = 'none', ?array $authConfig = null, array $overrides = []): array
{
    $agent = createAgent(User::factory()->create(), ['name' => 'Support Agent']);

    $function = AgentFunction::factory()->attachedTo($agent)->create([
        'internal_name' => 'lookup_order',
        'description' => 'Look up an order by id',
        'webhook_url' => 'https://hooks.example.test/fn',
        'webhook_method' => $method,
        'auth_type' => $authType,
        'auth_config' => $authConfig,
        'parameters_schema' => [
            'type' => 'object',
            'properties' => ['order_id' => ['type' => 'string'], 'page' => ['type' => 'integer']],
        ],
        ...$overrides,
    ]);

    return [$agent, $function];
}

/** The function's schema with `order_id` required. */
function htdRequiredSchema(): array
{
    return [
        'type' => 'object',
        'properties' => ['order_id' => ['type' => 'string'], 'page' => ['type' => 'integer']],
        'required' => ['order_id'],
    ];
}

it('refuses a call it may not make, and sends nothing', function (Closure $call, string $problem, ?string $lastError): void {
    Http::fake();
    [$agent, $arguments, $function] = $call();

    $result = (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', $arguments));

    Http::assertNothingSent();
    expect($result->isError)->toBeTrue()
        ->and($result->text)->toContain($problem)
        ->and($function?->fresh()->last_error)->toBe($lastError);
})->with([
    'not attached to the agent' => [function (): array {
        $agent = createAgent(User::factory()->create());
        AgentFunction::factory()->create(['workspace_id' => $agent->workspace_id, 'internal_name' => 'lookup_order']);

        return [$agent, '{}', null];
    }, 'Tool error: Function "lookup_order" is not attached to this agent or is disabled.', null],
    'disabled' => [function (): array {
        [$agent] = htdFunction(overrides: ['is_enabled' => false]);

        return [$agent, '{}', null];
    }, 'Tool error: Function "lookup_order" is not attached to this agent or is disabled.', null],
    'another workspace\'s function with the same name' => [function (): array {
        $agent = createAgent(User::factory()->create());
        $agent->functions()->attach(AgentFunction::factory()->create(['internal_name' => 'lookup_order'])->id);

        return [$agent, '{}', null];
    }, 'Tool error: Function "lookup_order" is not attached to this agent or is disabled.', null],
    'a missing required argument' => [function (): array {
        [$agent, $function] = htdFunction(overrides: ['parameters_schema' => htdRequiredSchema()]);

        return [$agent, '{"page":2}', $function];
    }, '"order_id" is required', null],
    'an argument of the wrong type' => [function (): array {
        [$agent, $function] = htdFunction(overrides: ['parameters_schema' => htdRequiredSchema()]);

        return [$agent, '{"order_id":"ORD-1","page":"two"}', $function];
    }, '"page" must be of type integer', null],
    'an hmac function without a signing secret' => [function (): array {
        [$agent, $function] = htdFunction('POST', 'hmac', ['secret' => '']);

        return [$agent, '{}', $function];
    }, 'HMAC signing secret', 'missing hmac signing secret'],
]);

it('signs an hmac request over the timestamp and the exact bytes sent, after every function header', function (string $method, string $arguments, string $url, string $signed): void {
    $this->freezeSecond();
    [$agent] = htdFunction($method, 'hmac', ['secret' => 'shh-its-a-secret'], [
        'webhook_headers' => [
            ['key' => 'X-Chatvia-Agent-Id', 'value' => 'spoofed-agent'],
            ['key' => 'X-Chatvia-Workspace-Id', 'value' => 'spoofed-workspace'],
            ['key' => 'X-Chatvia-Conversation-Id', 'value' => 'spoofed-conversation'],
            ['key' => HttpToolDispatcher::SIGNATURE_HEADER, 'value' => 'sha256=forged'],
            ['key' => 'X-Custom', 'value' => 'kept'],
        ],
    ]);
    Http::fake(['hooks.example.test/*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', $arguments, conversationExternalId: 'conversation-uuid'));

    $timestamp = (string) now()->getTimestamp();
    $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$signed, 'shh-its-a-secret');
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === $method
        && $request->url() === $url
        && ($method === 'GET' || $request->body() === $signed)
        && $request->header(HttpToolDispatcher::TIMESTAMP_HEADER) === [$timestamp]
        && $request->header(HttpToolDispatcher::SIGNATURE_HEADER) === [$expected]
        && $request->header('X-Chatvia-Agent-Id') === [$agent->external_id]
        && $request->header('X-Chatvia-Workspace-Id') === [$agent->workspace->external_id]
        && $request->header('X-Chatvia-Conversation-Id') === ['conversation-uuid']
        && $request->header('X-Custom') === ['kept']
        && ! Str::contains(json_encode($request->headers()), 'shh-its-a-secret'));
})->with([
    'POST signs the JSON body' => ['POST', '{"order_id":"ORD-1"}', 'https://hooks.example.test/fn', '{"order_id":"ORD-1"}'],
    'GET signs the query string' => ['GET', '{"order_id":"ORD 1","page":2}', 'https://hooks.example.test/fn?order_id=ORD%201&page=2', 'order_id=ORD%201&page=2'],
]);

it('sends bearer and header auth unsigned, each under its own header', function (): void {
    [$bearer] = htdFunction('POST', 'bearer', ['token' => 'tok']);
    [$header] = htdFunction('POST', 'header', ['name' => 'X-Api-Key', 'value' => 'key-123']);
    Http::fake(['hooks.example.test/*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($bearer, ToolCallData::named('lookup_order', '{}'));
    (new HttpToolDispatcher)->dispatch($header, ToolCallData::named('lookup_order', '{}'));

    Http::assertSent(fn (ClientRequest $request): bool => $request->header('Authorization') === ['Bearer tok']
        && $request->header(HttpToolDispatcher::SIGNATURE_HEADER) === []);
    Http::assertSent(fn (ClientRequest $request): bool => $request->header('X-Api-Key') === ['key-123']
        && $request->header('Authorization') === []
        && $request->header(HttpToolDispatcher::SIGNATURE_HEADER) === []);
});

it('connects to the webhook address it checked and keeps the webhook URL out of a transport error', function (): void {
    [$agent, $function] = htdFunction(overrides: ['webhook_url' => 'https://hooks.example.test/catch/123/s3cr3t?api_key=k3y']);
    app()['env'] = 'production';
    $answers = [['93.184.216.34'], ['10.0.0.5']];
    SafeUrl::resolveHostsUsing(function (string $host) use (&$answers): array {
        return array_shift($answers) ?? ['10.0.0.5'];
    });
    $pinned = null;
    Http::fake(function (ClientRequest $request, array $options) use (&$pinned): never {
        $pinned = $options['curl'][CURLOPT_RESOLVE] ?? null;

        throw new ConnectionException('cURL error 28: Operation timed out for https://hooks.example.test/catch/123/s3cr3t?api_key=k3y');
    });
    Log::spy();

    try {
        $result = (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{}'));
    } finally {
        SafeUrl::resolveHostsUsing(null);
    }

    expect($pinned)->toBe(['hooks.example.test:443:93.184.216.34'])
        ->and($result->isError)->toBeTrue()
        ->and($result->text)->toBe('Tool error: '.HttpToolDispatcher::UNREACHABLE_RESULT)
        ->and($function->fresh()->last_error)->toContain('https://hooks.example.test/[redacted]')
        ->not->toContain('s3cr3t')
        ->not->toContain('k3y');
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => ! Str::contains(json_encode($context), ['s3cr3t', 'k3y']));
});

it('sends only the declared arguments and records each call on the function, the pivot and the analytics event', function (): void {
    Event::fake([FunctionInvoked::class]);
    [$agent, $function] = htdFunction(overrides: ['display_name' => 'Order lookup', 'last_error' => 'webhook returned 500', 'max_response_bytes' => 5]);
    Http::fake(['hooks.example.test/*' => Http::sequence()
        ->push('0123456789', 200)
        ->push('down for maintenance', 503)]);

    $ok = (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{"order_id":"ORD-1","admin":true}'));
    $afterOk = $function->fresh();
    $failed = (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{}'));

    $pivot = DB::table('agent_function')->where('agent_id', $agent->id)->where('function_id', $function->id)->first();
    expect($ok->isError)->toBeFalse()
        ->and($ok->text)->toBe('01234')
        ->and($afterOk->last_error)->toBeNull()
        ->and($afterOk->invocation_count)->toBe(1)
        ->and($afterOk->last_invoked_at)->not->toBeNull()
        ->and($failed->isError)->toBeTrue()
        ->and($failed->text)->toBe('Tool error: webhook returned 503')
        ->and($function->fresh()->last_error)->toBe('webhook returned 503')
        ->and($function->fresh()->invocation_count)->toBe(2)
        ->and((int) $pivot->invocation_count)->toBe(2)
        ->and($pivot->last_invoked_at)->not->toBeNull();
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://hooks.example.test/fn'
        && $request->body() === '{"order_id":"ORD-1"}'
        && $request->header('Content-Type') === ['application/json']);
    Event::assertDispatched(FunctionInvoked::class, fn (FunctionInvoked $event): bool => $event->agentId === $agent->id
        && $event->workspaceId === $agent->workspace_id
        && $event->functionId === $function->id
        && $event->name === 'Order lookup'
        && $event->description === 'Look up an order by id');
});
