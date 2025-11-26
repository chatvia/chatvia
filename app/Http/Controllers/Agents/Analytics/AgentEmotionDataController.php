<?php

namespace App\Http\Controllers\Agents\Analytics;

use App\Models\Agent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentEmotionDataController
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

        // Get emotion data aggregated by date
        $emotionByDate = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agent->id)
            ->where('chat_messages.role', 'user')
            ->whereRaw('DATE(chat_messages.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('DATE(chat_messages.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->selectRaw('
                DATE(chat_messages.created_at) as date,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as happy_count,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as neutral_count,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as frustrated_count,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as confused_count,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as angry_count,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as sad_count,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as anxious_count,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as excited_count,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as satisfied_count,
                COUNT(CASE WHEN chat_messages.emotion IS NULL THEN 1 END) as unspecified_count
            ', ['happy', 'neutral', 'frustrated', 'confused', 'angry', 'sad', 'anxious', 'excited', 'satisfied'])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Get total emotion counts for pie chart
        $totalEmotions = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agent->id)
            ->where('chat_messages.role', 'user')
            ->whereRaw('DATE(chat_messages.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('DATE(chat_messages.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->selectRaw('
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as happy,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as neutral,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as frustrated,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as confused,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as angry,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as sad,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as anxious,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as excited,
                COUNT(CASE WHEN chat_messages.emotion = ? THEN 1 END) as satisfied,
                COUNT(CASE WHEN chat_messages.emotion IS NULL THEN 1 END) as unspecified,
                COUNT(*) as total
            ', ['happy', 'neutral', 'frustrated', 'confused', 'angry', 'sad', 'anxious', 'excited', 'satisfied'])
            ->first();

        // Fill in missing dates with zeros
        $filledData = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $dataPoint = $emotionByDate->firstWhere('date', $dateStr);

            $filledData[] = [
                'date' => $dateStr,
                'happy' => $dataPoint?->happy_count ?? 0,
                'neutral' => $dataPoint?->neutral_count ?? 0,
                'frustrated' => $dataPoint?->frustrated_count ?? 0,
                'confused' => $dataPoint?->confused_count ?? 0,
                'angry' => $dataPoint?->angry_count ?? 0,
                'sad' => $dataPoint?->sad_count ?? 0,
                'anxious' => $dataPoint?->anxious_count ?? 0,
                'excited' => $dataPoint?->excited_count ?? 0,
                'satisfied' => $dataPoint?->satisfied_count ?? 0,
                'unspecified' => $dataPoint?->unspecified_count ?? 0,
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'emotionByDate' => $filledData,
            'totalEmotions' => [
                'happy' => $totalEmotions->happy ?? 0,
                'neutral' => $totalEmotions->neutral ?? 0,
                'frustrated' => $totalEmotions->frustrated ?? 0,
                'confused' => $totalEmotions->confused ?? 0,
                'angry' => $totalEmotions->angry ?? 0,
                'sad' => $totalEmotions->sad ?? 0,
                'anxious' => $totalEmotions->anxious ?? 0,
                'excited' => $totalEmotions->excited ?? 0,
                'satisfied' => $totalEmotions->satisfied ?? 0,
                'unspecified' => $totalEmotions->unspecified ?? 0,
                'total' => $totalEmotions->total ?? 0,
            ],
        ]);
    }
}
