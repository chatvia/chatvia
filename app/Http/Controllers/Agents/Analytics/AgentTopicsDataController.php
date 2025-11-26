<?php

namespace App\Http\Controllers\Agents\Analytics;

use App\Models\Agent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentTopicsDataController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        // Get date range from request or use default (last 7 days)
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->subDays(6)->startOfDay();

        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        // Get topics by date for the agent (using chat_topic_id foreign key)
        $topicsByDate = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_topics', 'chat_topics.id', '=', 'chat_conversations.chat_topic_id')
            ->where('chat_sessions.agent_id', $agent->id)
            ->whereNotNull('chat_conversations.chat_topic_id')
            ->whereRaw('DATE(chat_conversations.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('DATE(chat_conversations.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->groupBy('date', 'chat_topics.id', 'chat_topics.topic')
            ->select([
                DB::raw('DATE(chat_conversations.created_at) as date'),
                'chat_topics.id as topic_id',
                'chat_topics.topic as topic_name',
                DB::raw('COUNT(chat_conversations.id) as count'),
            ])
            ->orderBy('date')
            ->orderBy('topic_name')
            ->get();

        // Get total counts per topic
        $totalTopics = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_topics', 'chat_topics.id', '=', 'chat_conversations.chat_topic_id')
            ->where('chat_sessions.agent_id', $agent->id)
            ->whereNotNull('chat_conversations.chat_topic_id')
            ->whereRaw('DATE(chat_conversations.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('DATE(chat_conversations.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->groupBy('chat_topics.id', 'chat_topics.topic')
            ->select([
                'chat_topics.id as topic_id',
                'chat_topics.topic as topic_name',
                DB::raw('COUNT(chat_conversations.id) as total_count'),
            ])
            ->orderByDesc('total_count')
            ->limit(10)  // Top 10 topics
            ->get();

        // Prepare data for the chart - organize by topic and date
        $topicData = [];
        $dates = [];

        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->addDay();
        }

        foreach ($totalTopics as $topic) {
            $topicData[$topic->topic_name] = [];

            foreach ($dates as $date) {
                $record = $topicsByDate->first(function ($item) use ($topic, $date) {
                    return $item->topic_id === $topic->topic_id && $item->date === $date;
                });

                $topicData[$topic->topic_name][] = [
                    'date' => $date,
                    'count' => $record ? $record->count : 0,
                ];
            }
        }

        return response()->json([
            'topicsByDate' => $topicData,
            'totalTopics' => $totalTopics->map(function ($topic) {
                return [
                    'id' => $topic->topic_id,
                    'name' => $topic->topic_name,
                    'count' => $topic->total_count,
                ];
            }),
            'totalCount' => $totalTopics->count(),
        ]);
    }
}
