<?php

use Domain\Analytics\Actions\Recording\RecordConversationTopicAction;
use Domain\Analytics\Data\Recording\TopicAssignmentRecordData;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('upserts and increments the topic daily rollup', function (): void {
    $action = app(RecordConversationTopicAction::class);
    $agentId = 930001;
    $userId = 700001;
    $topicId = 77;
    $when = Carbon::parse('2026-06-01 09:00');

    $action->execute(TopicAssignmentRecordData::fromEvent(new ConversationTopicAssigned($agentId, $userId, $topicId, $when)));
    $action->execute(TopicAssignmentRecordData::fromEvent(new ConversationTopicAssigned($agentId, $userId, $topicId, $when)));

    $count = DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_topic_daily')
        ->where('agent_id', $agentId)
        ->where('bucket_day', '2026-06-01')
        ->where('topic_id', $topicId)
        ->value('conversation_count');

    expect((int) $count)->toBe(2);
});

it('buckets topics by their own day', function (): void {
    $action = app(RecordConversationTopicAction::class);
    $agentId = 930002;
    $userId = 700002;

    $action->execute(TopicAssignmentRecordData::fromEvent(new ConversationTopicAssigned($agentId, $userId, 5, Carbon::parse('2026-06-01 09:00'))));
    $action->execute(TopicAssignmentRecordData::fromEvent(new ConversationTopicAssigned($agentId, $userId, 5, Carbon::parse('2026-06-02 09:00'))));

    $days = DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_topic_daily')
        ->where('agent_id', $agentId)
        ->where('topic_id', 5)
        ->count();

    expect($days)->toBe(2);
});
