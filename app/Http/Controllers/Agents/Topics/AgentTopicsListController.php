<?php

namespace App\Http\Controllers\Agents\Topics;

use App\Models\Agent;
use App\Models\ChatTopic;
use Illuminate\Http\Request;

class AgentTopicsListController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(! has_permission_to_agent($agent, 'agent.topics.manage'), 403);

        $search = $request->input('search');
        $all = $request->input('all', false);

        // If 'all' parameter is true, return all topics available to this user (system + user's own)
        if ($all) {
            $query = ChatTopic::query()
                ->forUser($request->user())
                ->orderBy('topic');

            if ($search) {
                $query->where('topic', 'ilike', "%{$search}%");
            }

            $topics = $query->get();

            return response()->json($topics);
        }

        // Otherwise, return only the agent's topics
        $query = $agent->topics()->orderBy('chat_topics.topic');

        if ($search) {
            $query->where('chat_topics.topic', 'ilike', "%{$search}%");
        }

        $topics = $query->get(['chat_topics.*']);

        return response()->json($topics);
    }
}
