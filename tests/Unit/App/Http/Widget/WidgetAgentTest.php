<?php

use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Widget\WidgetHeaders;
use Illuminate\Http\Request;

/**
 * The agent a widget request is for is looked up from its agent id header
 * once per request, and again only when the header names another agent.
 */
it('looks the agent up once per request', function (): void {
    $agent = Agent::factory()->create();
    $request = Request::create('/widget/agents/settings');
    $request->headers->set(WidgetHeaders::AGENT_ID, $agent->external_id);

    $first = WidgetAgent::from($request);

    expect($first?->is($agent))->toBeTrue()
        ->and(WidgetAgent::from($request))->toBe($first);
});

it('looks the agent up again when the header names another agent', function (): void {
    [$first, $second] = Agent::factory()->count(2)->create();
    $request = Request::create('/widget/agents/settings');

    $request->headers->set(WidgetHeaders::AGENT_ID, $first->external_id);
    $resolvedFirst = WidgetAgent::from($request);

    $request->headers->set(WidgetHeaders::AGENT_ID, $second->external_id);

    expect($resolvedFirst?->is($first))->toBeTrue()
        ->and(WidgetAgent::from($request)?->is($second))->toBeTrue();
});
