<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Analysis\QueuePendingConversationAnalysisAction;
use Domain\Analytics\Jobs\AnalyzeConversationJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\Queue;

function pendingAnalysisConversation(Agent $agent, array $attributes = []): Conversation
{
    $session = Session::create(['agent_id' => $agent->id]);

    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => 'widget',
    ]);

    $conversation->forceFill($attributes)->save();

    return $conversation;
}

it('queues only old conversations that are not analysed yet', function (): void {
    Queue::fake();
    config(['chatvia.analytics.analysis.scan_age_hours' => 6]);
    $agent = Agent::factory()->create();

    $pending = pendingAnalysisConversation($agent, ['created_at' => now()->subHours(7)]);
    $tooYoung = pendingAnalysisConversation($agent, ['created_at' => now()->subHour()]);
    $analysed = pendingAnalysisConversation($agent, ['created_at' => now()->subHours(7), 'analyzed_at' => now()]);

    // The seeded test database may hold other pending conversations, so only
    // this agent's rows are asserted on.
    $queued = app(QueuePendingConversationAnalysisAction::class)->execute(chunkSize: 1);

    expect($queued)->toBeGreaterThanOrEqual(1);
    Queue::assertPushed(AnalyzeConversationJob::class, fn (AnalyzeConversationJob $job): bool => $job->conversation->is($pending));
    Queue::assertNotPushed(AnalyzeConversationJob::class, fn (AnalyzeConversationJob $job): bool => $job->conversation->is($tooYoung) || $job->conversation->is($analysed));
});

it('stops queueing a conversation once its analysis attempts are used up', function (): void {
    Queue::fake();
    config(['chatvia.analytics.analysis.scan_age_hours' => 6, 'chatvia.analytics.analysis.max_attempts' => 3]);
    $agent = Agent::factory()->create();

    $retry = pendingAnalysisConversation($agent, ['created_at' => now()->subHours(9), 'analysis_attempts' => 2]);
    $exhausted = pendingAnalysisConversation($agent, ['created_at' => now()->subHours(9), 'analysis_attempts' => 3]);

    app(QueuePendingConversationAnalysisAction::class)->execute();

    Queue::assertPushed(AnalyzeConversationJob::class, fn (AnalyzeConversationJob $job): bool => $job->conversation->is($retry));
    Queue::assertNotPushed(AnalyzeConversationJob::class, fn (AnalyzeConversationJob $job): bool => $job->conversation->is($exhausted));
});
