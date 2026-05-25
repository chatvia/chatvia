<?php

namespace App\Http\Controllers\Agents\Analytics;

use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AgentToneDataController
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

        // Get tone data aggregated by date
        $toneByDate = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agent->id)
            ->where('chat_conversations.visible', true)
            ->where('chat_messages.role', 'user')
            ->whereRaw('DATE(chat_messages.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('DATE(chat_messages.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->selectRaw('
                DATE(chat_messages.created_at) as date,
                COUNT(CASE WHEN chat_messages.tone = ? THEN 1 END) as neutral_count,
                COUNT(CASE WHEN chat_messages.tone = ? THEN 1 END) as frustrated_count,
                COUNT(CASE WHEN chat_messages.tone = ? THEN 1 END) as urgent_count,
                COUNT(CASE WHEN chat_messages.tone = ? THEN 1 END) as confused_count,
                COUNT(CASE WHEN chat_messages.tone = ? THEN 1 END) as satisfied_count,
                COUNT(CASE WHEN chat_messages.tone IS NULL THEN 1 END) as unspecified_count
            ', ['neutral', 'frustrated', 'urgent', 'confused', 'satisfied'])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Get total tone counts for pie chart
        $totalTones = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agent->id)
            ->where('chat_conversations.visible', true)
            ->where('chat_messages.role', 'user')
            ->whereRaw('DATE(chat_messages.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('DATE(chat_messages.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->selectRaw('
                COUNT(CASE WHEN chat_messages.tone = ? THEN 1 END) as neutral,
                COUNT(CASE WHEN chat_messages.tone = ? THEN 1 END) as frustrated,
                COUNT(CASE WHEN chat_messages.tone = ? THEN 1 END) as urgent,
                COUNT(CASE WHEN chat_messages.tone = ? THEN 1 END) as confused,
                COUNT(CASE WHEN chat_messages.tone = ? THEN 1 END) as satisfied,
                COUNT(CASE WHEN chat_messages.tone IS NULL THEN 1 END) as unspecified,
                COUNT(*) as total
            ', ['neutral', 'frustrated', 'urgent', 'confused', 'satisfied'])
            ->first();

        // Fill in missing dates with zeros
        $filledData = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $dataPoint = $toneByDate->firstWhere('date', $dateStr);

            $filledData[] = [
                'date' => $dateStr,
                'neutral' => $dataPoint->neutral_count ?? 0,
                'frustrated' => $dataPoint->frustrated_count ?? 0,
                'urgent' => $dataPoint->urgent_count ?? 0,
                'confused' => $dataPoint->confused_count ?? 0,
                'satisfied' => $dataPoint->satisfied_count ?? 0,
                'unspecified' => $dataPoint->unspecified_count ?? 0,
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'toneByDate' => $filledData,
            'totalTones' => [
                'neutral' => $totalTones->neutral ?? 0,
                'frustrated' => $totalTones->frustrated ?? 0,
                'urgent' => $totalTones->urgent ?? 0,
                'confused' => $totalTones->confused ?? 0,
                'satisfied' => $totalTones->satisfied ?? 0,
                'unspecified' => $totalTones->unspecified ?? 0,
                'total' => $totalTones->total ?? 0,
            ],
        ]);
    }
}
