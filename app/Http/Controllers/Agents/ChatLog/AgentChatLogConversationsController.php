<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Data\Resources\Agent\ChatLog\ChatLogConversationsResourceData;
use App\Models\Agent;
use Illuminate\Http\Request;

class AgentChatLogConversationsController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return ChatLogConversationsResourceData::fromAgent(
            agent: $agent,
            page: $request->integer('page', 1),
            topicFilter: $request->query('topic'),
            sentimentFilter: $request->query('sentiment'),
            toneFilter: $request->query('tone'),
            startDate: $request->query('start_date'),
            endDate: $request->query('end_date'),
            countryFilter: $request->query('country'),
            confidenceScoreFilter: $request->query('confidence_score'),
            feedbackFilter: $request->query('feedback'),
            sourceFilter: $request->query('source'),
            onlineOnly: $request->boolean('online_only'),
        );
    }
}
