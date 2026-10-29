<?php

use Domain\Analytics\Actions\Recording\RecordConversationTopicAction;
use Domain\Analytics\Data\Recording\TopicAssignmentRecordData;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A topic assigned to a conversation counts once in the agent's daily topic
 * rollup, on the day it was assigned.
 */
it('upserts and increments the topic daily rollup', function (): void {
    $action = app(RecordConversationTopicAction::class);
    $agentId = 930001;
    $workspaceId = 700001;
    $topicId = 77;
    $when = Carbon::parse('2026-06-01 09:00');

    $action->execute(TopicAssignmentRecordData::fromEvent(new ConversationTopicAssigned($agentId, $workspaceId, $topicId, $when)));
    $action->execute(TopicAssignmentRecordData::fromEvent(new ConversationTopicAssigned($agentId, $workspaceId, $topicId, $when)));

    $count = DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_topic_daily')
        ->where('agent_id', $agentId)
        ->where('bucket_day', '2026-06-01')
        ->where('topic_id', $topicId)
        ->value('conversation_count');

    expect((int) $count)->toBe(2);
});
