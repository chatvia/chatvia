<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Services\TeamsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The client's requests are the contract with Entra and the Bot Framework
 * connector, which the Fake-based tests never reach: the client-credentials
 * grant in the right tenant, the token cached until shortly before it expires,
 * and the activity posted to (or rewritten at) the conversation's own,
 * path-encoded URL.
 */
beforeEach(function (): void {
    $this->bot = AgentTeamsBot::create([
        'agent_id' => Agent::factory()->create()->id,
        'app_id' => 'f5e1f9d2-1111-2222-3333-444455556666',
        'app_secret' => 'client-secret-value',
        'tenant_id' => null,
        'webhook_secret' => Str::random(48),
        'is_enabled' => true,
    ]);
    $this->serviceUrl = 'https://smba.trafficmanager.net/emea/';
    $this->activities = 'https://smba.trafficmanager.net/emea/v3/conversations/a%3A1conv%3Bmessageid%3D7/activities';
});

it('posts the reply as a Markdown activity with a token from the shared Bot Framework tenant', function (): void {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'BF-TOKEN', 'expires_in' => 3600]),
        'smba.trafficmanager.net/*' => Http::response(['id' => 'act-42']),
    ]);

    $id = new TeamsClient()->postMessage($this->bot, $this->serviceUrl, 'a:1conv;messageid=7', 'Hello **there**');

    expect($id)->toBe('act-42');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://login.microsoftonline.com/botframework.com/oauth2/v2.0/token'
        && $request['grant_type'] === 'client_credentials'
        && $request['client_id'] === 'f5e1f9d2-1111-2222-3333-444455556666'
        && $request['client_secret'] === 'client-secret-value'
        && $request['scope'] === 'https://api.botframework.com/.default');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === $this->activities
        && $request->hasHeader('Authorization', 'Bearer BF-TOKEN')
        && $request->data() === ['type' => 'message', 'textFormat' => 'markdown', 'text' => 'Hello **there**']);
});

it('asks for the token in the bot’s own tenant when it is single-tenant', function (): void {
    $this->bot->update(['tenant_id' => 'a0b1c2d3-9999-8888-7777-666655554444']);
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'BF-TOKEN', 'expires_in' => 3600]),
        'smba.trafficmanager.net/*' => Http::response(['id' => 'act-42']),
    ]);

    new TeamsClient()->postMessage($this->bot, $this->serviceUrl, 'a:1conv;messageid=7', 'Hello');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://login.microsoftonline.com/a0b1c2d3-9999-8888-7777-666655554444/oauth2/v2.0/token');
});

it('rewrites a posted activity in place', function (): void {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'BF-TOKEN', 'expires_in' => 3600]),
        'smba.trafficmanager.net/*' => Http::response([]),
    ]);

    $updated = new TeamsClient()->updateMessage($this->bot, $this->serviceUrl, 'a:1conv;messageid=7', '1:act/42', 'Hello there');

    expect($updated)->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === $this->activities.'/1%3Aact%2F42'
        && $request->data() === ['type' => 'message', 'textFormat' => 'markdown', 'text' => 'Hello there', 'id' => '1:act/42']);
});

it('reuses the token until five minutes before it expires', function (): void {
    $this->freezeTime();
    Http::fake([
        'login.microsoftonline.com/*' => Http::sequence()
            ->push(['access_token' => 'FIRST-TOKEN', 'expires_in' => 3600])
            ->push(['access_token' => 'SECOND-TOKEN', 'expires_in' => 3600]),
        'smba.trafficmanager.net/*' => Http::response(['id' => 'act-42']),
    ]);
    $client = new TeamsClient;
    $tokenRequests = fn (): int => count(Http::recorded(fn (Request $request): bool => Str::contains($request->url(), 'login.microsoftonline.com')));

    $client->postMessage($this->bot, $this->serviceUrl, 'a:1conv', 'one');
    $this->travel(3299)->seconds();
    $client->postMessage($this->bot, $this->serviceUrl, 'a:1conv', 'two');

    expect($tokenRequests())->toBe(1);

    $this->travel(2)->seconds();
    $client->postMessage($this->bot, $this->serviceUrl, 'a:1conv', 'three');

    expect($tokenRequests())->toBe(2);
    Http::assertSent(fn (Request $request): bool => ($request->data()['text'] ?? null) === 'three' && $request->hasHeader('Authorization', 'Bearer SECOND-TOKEN'));
});

it('sends nothing and caches nothing when Entra rejects the credentials', function (): void {
    Http::fake([
        'login.microsoftonline.com/*' => Http::sequence()
            ->push(['error' => 'invalid_client'], 401)
            ->push(['access_token' => 'BF-TOKEN', 'expires_in' => 3600]),
        'smba.trafficmanager.net/*' => Http::response(['id' => 'act-42']),
    ]);
    $client = new TeamsClient;

    expect($client->postMessage($this->bot, $this->serviceUrl, 'a:1conv', 'one'))->toBeNull();
    Http::assertSentCount(1);

    expect($client->postMessage($this->bot, $this->serviceUrl, 'a:1conv', 'two'))->toBe('act-42');
});
