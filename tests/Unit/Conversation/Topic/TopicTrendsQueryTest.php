<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Conversation\Topic\Queries\TopicTrendsQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The topic charts give every busy topic a count for each day of the range,
 * with zero for the days nobody talked about it.
 */
it('fills every day of the range for each busy topic', function (): void {
    $agent = Agent::factory()->create();
    $billing = Topic::query()->create(['workspace_id' => $agent->workspace_id, 'topic' => 'Billing', 'description' => 'Invoices']);
    $shipping = Topic::query()->create(['workspace_id' => $agent->workspace_id, 'topic' => 'Shipping', 'description' => 'Delivery']);

    $rollup = DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_topic_daily');
    $rollup->insert([
        ['agent_id' => $agent->id, 'workspace_id' => $agent->workspace_id, 'bucket_day' => '2026-06-01', 'topic_id' => $billing->id, 'conversation_count' => 3],
        ['agent_id' => $agent->id, 'workspace_id' => $agent->workspace_id, 'bucket_day' => '2026-06-03', 'topic_id' => $billing->id, 'conversation_count' => 2],
        ['agent_id' => $agent->id, 'workspace_id' => $agent->workspace_id, 'bucket_day' => '2026-06-02', 'topic_id' => $shipping->id, 'conversation_count' => 1],
    ]);

    $charts = app(TopicTrendsQuery::class)->forAgent($agent, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-03')->endOfDay());

    expect($charts->total_count)->toBe(2)
        ->and($charts->total_topics->toArray())->toBe([
            ['id' => $billing->id, 'name' => 'Billing', 'count' => 5],
            ['id' => $shipping->id, 'name' => 'Shipping', 'count' => 1],
        ])
        ->and($charts->topics_by_date->toArray()[0])->toBe([
            'topic_name' => 'Billing',
            'counts' => [
                ['date' => '2026-06-01', 'count' => 3],
                ['date' => '2026-06-02', 'count' => 0],
                ['date' => '2026-06-03', 'count' => 2],
            ],
        ]);
});
