<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Analysis\QueuePendingConversationAnalysisAction;
use Domain\Analytics\Jobs\AnalyzeConversationJob;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Queue;

/**
 * The scheduled scan queues the end-of-life analysis of every visible
 * conversation old enough and not analysed yet, until its attempts are used
 * up, and counts what it queued.
 */
/** @param  array<string, mixed>  $attributes */
function qpcaConversation(Agent $agent, array $attributes = []): Conversation
{
    return Conversation::factory()->create(['agent_id' => $agent->id, 'is_visible' => true, ...$attributes]);
}

it('queues only old, visible conversations not analysed yet with attempts left', function (): void {
    Queue::fake();
    config(['chatvia.analytics.analysis.scan_age_hours' => 6, 'chatvia.analytics.analysis.max_attempts' => 3]);
    $agent = Agent::factory()->create();
    $pending = qpcaConversation($agent, ['created_at' => now()->subHours(7)]);
    $retry = qpcaConversation($agent, ['created_at' => now()->subHours(9), 'analysis_attempts' => 2]);
    $skipped = collect([
        qpcaConversation($agent, ['created_at' => now()->subHour()]),
        qpcaConversation($agent, ['created_at' => now()->subHours(7), 'analyzed_at' => now()]),
        qpcaConversation($agent, ['created_at' => now()->subHours(9), 'analysis_attempts' => 3]),
        qpcaConversation($agent, ['created_at' => now()->subHours(7), 'is_visible' => false]),
    ]);

    $queued = app(QueuePendingConversationAnalysisAction::class)->execute();

    $ours = Queue::pushed(AnalyzeConversationJob::class, fn (AnalyzeConversationJob $job): bool => $job->conversation->agent_id === $agent->id);

    expect($ours->map(fn (AnalyzeConversationJob $job): int => $job->conversation->id)->sort()->values()->all())
        ->toBe(collect([$pending->id, $retry->id])->sort()->values()->all())
        ->and($skipped->pluck('id')->intersect($ours->map(fn (AnalyzeConversationJob $job): int => $job->conversation->id))->all())->toBe([])
        ->and($queued)->toBe(Queue::pushed(AnalyzeConversationJob::class)->count());
});
