<?php

use App\Auth\Models\User;
use App\Teams\Services\TeamService;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Domain\Chat\Services\Tooling\HttpToolDispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('stamps the agent and owner identity headers on the webhook request', function (): void {
    $user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($user);

    $agent = Agent::create([
        'user_id' => $user->id,
        'team_id' => $user->primaryTeam()->id,
        'name' => 'Support Agent',
        'type' => 'knowledge_base',
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'is_public' => false,
        'analytics_enabled' => false,
        'created_via' => 'external',
    ]);

    $function = new AgentFunction;
    $function->user_id = $user->id;
    $function->name = 'lookup_order';
    $function->description = 'Look up an order by id';
    $function->parameters_schema = ['type' => 'object', 'properties' => []];
    $function->webhook_url = 'https://hooks.example.test/fn';
    $function->webhook_method = 'POST';
    $function->auth_type = 'none';
    $function->enabled = true;
    $function->save();

    $agent->functions()->attach($function->id);

    Http::fake(['*' => Http::response('{"ok":true}', 200)]);

    (new HttpToolDispatcher)->dispatch($agent, 'lookup_order', '{}');

    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.test/fn'
        && $request->header('X-Chatvia-Agent-Id') === [$agent->external_id]
        && $request->header('X-Chatvia-User-Id') === [$user->external_id]);
});
