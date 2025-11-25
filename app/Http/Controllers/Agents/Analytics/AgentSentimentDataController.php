<?php

namespace App\Http\Controllers\Agents\Analytics;

use App\Models\Agent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentSentimentDataController
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

        // Get sentiment data aggregated by date
        $sentimentByDate = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agent->id)
            ->where('chat_messages.role', 'user')
            ->whereRaw('DATE(chat_messages.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('DATE(chat_messages.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->selectRaw('
                DATE(chat_messages.created_at) as date,
                COUNT(CASE WHEN chat_messages.sentiment = ? THEN 1 END) as positive_count,
                COUNT(CASE WHEN chat_messages.sentiment = ? THEN 1 END) as negative_count,
                COUNT(CASE WHEN chat_messages.sentiment = ? THEN 1 END) as neutral_count,
                COUNT(CASE WHEN chat_messages.sentiment IS NULL THEN 1 END) as unspecified_count
            ', ['positive', 'negative', 'neutral'])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Get total sentiment counts for pie chart
        $totalSentiments = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agent->id)
            ->where('chat_messages.role', 'user')
            ->whereRaw('DATE(chat_messages.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('DATE(chat_messages.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->selectRaw('
                COUNT(CASE WHEN chat_messages.sentiment = ? THEN 1 END) as positive,
                COUNT(CASE WHEN chat_messages.sentiment = ? THEN 1 END) as negative,
                COUNT(CASE WHEN chat_messages.sentiment = ? THEN 1 END) as neutral,
                COUNT(CASE WHEN chat_messages.sentiment IS NULL THEN 1 END) as unspecified,
                COUNT(*) as total
            ', ['positive', 'negative', 'neutral'])
            ->first();

        // Fill in missing dates with zeros
        $filledData = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $dataPoint = $sentimentByDate->firstWhere('date', $dateStr);

            $filledData[] = [
                'date' => $dateStr,
                'positive' => $dataPoint?->positive_count ?? 0,
                'negative' => $dataPoint?->negative_count ?? 0,
                'neutral' => $dataPoint?->neutral_count ?? 0,
                'unspecified' => $dataPoint?->unspecified_count ?? 0,
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'sentimentByDate' => $filledData,
            'totalSentiments' => [
                'positive' => $totalSentiments->positive ?? 0,
                'negative' => $totalSentiments->negative ?? 0,
                'neutral' => $totalSentiments->neutral ?? 0,
                'unspecified' => $totalSentiments->unspecified ?? 0,
                'total' => $totalSentiments->total ?? 0,
            ],
        ]);
    }
}
