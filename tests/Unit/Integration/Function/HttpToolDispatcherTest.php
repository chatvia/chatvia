<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\FunctionInvoked;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Services\HttpToolDispatcher;
use Domain\Integration\Tool\Data\ToolCallData;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Support\Http\SafeUrl;

/**
 * Running a webhook function for an agent: only an enabled function of the
 * agent's workspace that is attached to it, sent with its method and auth,
 * with Chatvia's identity and signature headers stamped last. Every call is
 * recorded on the function, the agent pivot and the analytics event.
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
        ...$overrides,
    ]);

    return [$agent, $function];
}

it('stamps the agent and workspace identity headers on the webhook request', function (): void {
    [$agent] = htdFunction();
    Http::fake(['hooks.example.test/*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{}'));

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://hooks.example.test/fn'
        && $request->header('X-Chatvia-Agent-Id') === [$agent->external_id]
        && $request->header('X-Chatvia-Workspace-Id') === [$agent->workspace->external_id]);
});

it('calls the webhook with its configured method and a JSON body', function (string $method): void {
    [$agent] = htdFunction($method);
    Http::fake(['hooks.example.test/*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{"order_id":"ORD-1"}'));

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === $method
        && $request->url() === 'https://hooks.example.test/fn'
        && $request->body() === '{"order_id":"ORD-1"}'
        && $request->header('Content-Type') === ['application/json']);
})->with(['POST', 'PATCH', 'DELETE']);

it('sends GET arguments as a query string', function (): void {
    [$agent] = htdFunction('GET');
    Http::fake(['hooks.example.test/*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{"order_id":"ORD 1","page":2}'));

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://hooks.example.test/fn?order_id=ORD%201&page=2');
});

it('refuses a function the agent may not call, and sends nothing', function (Agent $agent): void {
    Http::fake();

    $result = (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{}'));

    expect($result->isError)->toBeTrue()
        ->and($result->text)->toBe('Tool error: Function "lookup_order" is not attached to this agent or is disabled.');
    Http::assertNothingSent();
})->with([
    'not attached to the agent' => [function (): Agent {
        $agent = createAgent(User::factory()->create());
        AgentFunction::factory()->create(['workspace_id' => $agent->workspace_id, 'internal_name' => 'lookup_order']);

        return $agent;
    }],
    'disabled' => [function (): Agent {
        [$agent] = htdFunction(overrides: ['is_enabled' => false]);

        return $agent;
    }],
    'another workspace\'s function with the same name' => [function (): Agent {
        $agent = createAgent(User::factory()->create());
        // Attached through the pivot, which has no workspace column: only the
        // dispatcher's own workspace check keeps it out.
        $agent->functions()->attach(AgentFunction::factory()->create(['internal_name' => 'lookup_order'])->id);

        return $agent;
    }],
]);

it('lets no function header shadow the identity or signature headers', function (): void {
    $this->freezeSecond();
    [$agent] = htdFunction('POST', 'hmac', ['secret' => 'shh'], [
        'webhook_headers' => [
            ['key' => 'X-Chatvia-Agent-Id', 'value' => 'spoofed-agent'],
            ['key' => 'X-Chatvia-Workspace-Id', 'value' => 'spoofed-workspace'],
            ['key' => 'X-Chatvia-Conversation-Id', 'value' => 'spoofed-conversation'],
            ['key' => HttpToolDispatcher::SIGNATURE_HEADER, 'value' => 'sha256=forged'],
            ['key' => 'X-Custom', 'value' => 'kept'],
        ],
    ]);
    Http::fake(['hooks.example.test/*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{}', conversationExternalId: 'conversation-uuid'));

    $signature = 'sha256='.hash_hmac('sha256', now()->getTimestamp().'.[]', 'shh');

    Http::assertSent(fn (ClientRequest $request): bool => $request->header('X-Chatvia-Agent-Id') === [$agent->external_id]
        && $request->header('X-Chatvia-Workspace-Id') === [$agent->workspace->external_id]
        && $request->header('X-Chatvia-Conversation-Id') === ['conversation-uuid']
        && $request->header(HttpToolDispatcher::SIGNATURE_HEADER) === [$signature]
        && $request->header('X-Custom') === ['kept']);
});

it('signs an hmac function request over the timestamp and the raw body', function (): void {
    $this->freezeSecond();
    [$agent] = htdFunction('POST', 'hmac', ['secret' => 'shh-its-a-secret']);
    Http::fake(['hooks.example.test/*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{"order_id":"ORD-1"}'));

    $timestamp = (string) now()->getTimestamp();
    $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.{"order_id":"ORD-1"}', 'shh-its-a-secret');

    Http::assertSent(fn (ClientRequest $request): bool => $request->header(HttpToolDispatcher::TIMESTAMP_HEADER) === [$timestamp]
        && $request->header(HttpToolDispatcher::SIGNATURE_HEADER) === [$expected]
        // The secret itself never travels.
        && ! str_contains(json_encode($request->headers()), 'shh-its-a-secret'));
});

it('signs a GET hmac request over the raw query string', function (): void {
    $this->freezeSecond();
    [$agent] = htdFunction('GET', 'hmac', ['secret' => 'shh']);
    Http::fake(['hooks.example.test/*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{"order_id":"ORD-1"}'));

    $timestamp = (string) now()->getTimestamp();
    $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.order_id=ORD-1', 'shh');

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://hooks.example.test/fn?order_id=ORD-1'
        && $request->header(HttpToolDispatcher::SIGNATURE_HEADER) === [$expected]);
});

it('does not sign requests for other auth types', function (): void {
    [$agent] = htdFunction('POST', 'bearer', ['token' => 'tok']);
    Http::fake(['hooks.example.test/*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{}'));

    Http::assertSent(fn (ClientRequest $request): bool => $request->header('Authorization') === ['Bearer tok']
        && $request->header(HttpToolDispatcher::SIGNATURE_HEADER) === []);
});

it('sends header auth under the configured header name', function (): void {
    [$agent] = htdFunction('POST', 'header', ['name' => 'X-Api-Key', 'value' => 'key-123']);
    Http::fake(['hooks.example.test/*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{}'));

    Http::assertSent(fn (ClientRequest $request): bool => $request->header('X-Api-Key') === ['key-123']
        && $request->header('Authorization') === []);
});

it('refuses to send an hmac request without a signing secret', function (): void {
    [$agent, $function] = htdFunction('POST', 'hmac', ['secret' => '']);
    Http::fake();

    $result = (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{}'));

    Http::assertNothingSent();
    expect($result->isError)->toBeTrue()
        ->and($function->fresh()->last_error)->toBe('missing hmac signing secret');
});

it('records a non-2xx answer on the function and returns it as a tool error', function (): void {
    [$agent, $function] = htdFunction();
    Http::fake(['hooks.example.test/*' => Http::response('down for maintenance', 503)]);

    $result = (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{}'));

    expect($result->isError)->toBeTrue()
        ->and($result->text)->toBe('Tool error: webhook returned 503')
        ->and($function->fresh()->last_error)->toBe('webhook returned 503')
        ->and($function->fresh()->invocation_count)->toBe(1);
});

it('records a successful call on the function, the agent pivot and the analytics event', function (): void {
    Event::fake([FunctionInvoked::class]);
    [$agent, $function] = htdFunction(overrides: ['display_name' => 'Order lookup', 'last_error' => 'webhook returned 500']);
    Http::fake(['hooks.example.test/*' => Http::response('{"ok":true}', 200)]);

    $result = (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{}'));

    $fresh = $function->fresh();
    $pivot = DB::table('agent_function')->where('agent_id', $agent->id)->where('function_id', $function->id)->first();

    expect($result->isError)->toBeFalse()
        ->and($result->text)->toBe('{"ok":true}')
        ->and($fresh->last_error)->toBeNull()
        ->and($fresh->invocation_count)->toBe(1)
        ->and($fresh->last_invoked_at)->not->toBeNull()
        ->and((int) $pivot->invocation_count)->toBe(1)
        ->and($pivot->last_invoked_at)->not->toBeNull();

    Event::assertDispatched(FunctionInvoked::class, fn (FunctionInvoked $event): bool => $event->agentId === $agent->id
        && $event->workspaceId === $agent->workspace_id
        && $event->functionId === $function->id
        && $event->name === 'Order lookup'
        && $event->description === 'Look up an order by id');
});

it('cuts the response to the function\'s size limit', function (): void {
    [$agent] = htdFunction(overrides: ['max_response_bytes' => 5]);
    Http::fake(['hooks.example.test/*' => Http::response('0123456789', 200)]);

    expect((new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{}'))->text)->toBe('01234');
});

it('names Chatvia in the User-Agent', function (): void {
    [$agent] = htdFunction();
    Http::fake(['hooks.example.test/*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{}'));

    Http::assertSent(fn (ClientRequest $request): bool => $request->header('User-Agent') === ['Chatvia/Tools']);
});

it('connects to the webhook address it checked, not a fresh DNS answer', function (): void {
    [$agent] = htdFunction();
    app()['env'] = 'production';
    $answers = [['93.184.216.34'], ['10.0.0.5']];
    SafeUrl::resolveHostsUsing(function (string $host) use (&$answers): array {
        return array_shift($answers) ?? ['10.0.0.5'];
    });

    $pinned = null;
    Http::fake(function (ClientRequest $request, array $options) use (&$pinned) {
        $pinned = $options['curl'][CURLOPT_RESOLVE] ?? null;

        return Http::response('{"ok":true}', 200);
    });

    try {
        (new HttpToolDispatcher)->dispatch($agent, ToolCallData::named('lookup_order', '{}'));
    } finally {
        SafeUrl::resolveHostsUsing(null);
    }

    expect($pinned)->toBe(['hooks.example.test:443:93.184.216.34']);
});
