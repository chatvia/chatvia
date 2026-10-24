<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Analysis\QueuePendingConversationAnalysisAction;
use Domain\Analytics\Jobs\AnalyzeConversationJob;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Collection;
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

it('queues only old conversations that are not analysed yet', function (): void {
    Queue::fake();
    config(['chatvia.analytics.analysis.scan_age_hours' => 6]);
    $agent = Agent::factory()->create();

    $pending = qpcaConversation($agent, ['created_at' => now()->subHours(7)]);
    $tooYoung = qpcaConversation($agent, ['created_at' => now()->subHour()]);
    $analysed = qpcaConversation($agent, ['created_at' => now()->subHours(7), 'analyzed_at' => now()]);

    app(QueuePendingConversationAnalysisAction::class)->execute();

    Queue::assertPushed(AnalyzeConversationJob::class, fn (AnalyzeConversationJob $job): bool => $job->conversation->is($pending));
    Queue::assertNotPushed(AnalyzeConversationJob::class, fn (AnalyzeConversationJob $job): bool => $job->conversation->is($tooYoung) || $job->conversation->is($analysed));
});

it('walks every chunk and counts every conversation it queues', function (): void {
    Queue::fake();
    config(['chatvia.analytics.analysis.scan_age_hours' => 6]);
    $agent = Agent::factory()->create();

    $pending = Collection::times(3, fn (): Conversation => qpcaConversation($agent, ['created_at' => now()->subHours(7)]));

    $queued = app(QueuePendingConversationAnalysisAction::class)->execute(chunkSize: 1);

    // The seeded test database may hold other pending conversations, so only
    // this agent's jobs are compared one by one.
    $ours = Queue::pushed(AnalyzeConversationJob::class, fn (AnalyzeConversationJob $job): bool => $job->conversation->agent_id === $agent->id);

    expect($ours->map(fn (AnalyzeConversationJob $job): int => $job->conversation->id)->sort()->values()->all())
        ->toBe($pending->pluck('id')->sort()->values()->all())
        ->and($queued)->toBe(Queue::pushed(AnalyzeConversationJob::class)->count());
});

it('stops queueing a conversation once its analysis attempts are used up', function (): void {
    Queue::fake();
    config(['chatvia.analytics.analysis.scan_age_hours' => 6, 'chatvia.analytics.analysis.max_attempts' => 3]);
    $agent = Agent::factory()->create();

    $retry = qpcaConversation($agent, ['created_at' => now()->subHours(9), 'analysis_attempts' => 2]);
    $exhausted = qpcaConversation($agent, ['created_at' => now()->subHours(9), 'analysis_attempts' => 3]);

    app(QueuePendingConversationAnalysisAction::class)->execute();

    Queue::assertPushed(AnalyzeConversationJob::class, fn (AnalyzeConversationJob $job): bool => $job->conversation->is($retry));
    Queue::assertNotPushed(AnalyzeConversationJob::class, fn (AnalyzeConversationJob $job): bool => $job->conversation->is($exhausted));
});

it('skips hidden conversations, which were never used', function (): void {
    Queue::fake();
    config(['chatvia.analytics.analysis.scan_age_hours' => 6]);
    $agent = Agent::factory()->create();

    $hidden = qpcaConversation($agent, ['created_at' => now()->subHours(7), 'is_visible' => false]);

    app(QueuePendingConversationAnalysisAction::class)->execute();

    Queue::assertNotPushed(AnalyzeConversationJob::class, fn (AnalyzeConversationJob $job): bool => $job->conversation->is($hidden));
});
