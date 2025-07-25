<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardStatsController
{
    public function __invoke(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return [
            'agents' => [
                'total' => $user->agents()->count(),
                'recent' => $user->agents()->select(['id', 'name', 'sub_domain'])->latest()->limit(5)->get(),
            ],
            'messages' => $user->creditSystem()->getTotalSentMessages(),
            'minutes' => '',
            'performance' => [
                'avg_response_time' => '',
                'members' => $user->members()->count(),
                'files' => $user->agentFiles()->count(),
            ],
            'weekly_usage' => $this->getWeeklyMessagesVolume($user),
        ];
    }

    private function getWeeklyMessagesVolume(User $user): array
    {
        $startDate = now()->subDays(6)->startOfDay();
        $endDate = now()->endOfDay();

        // Get daily message counts from user's agents using the date index
        $dailyMessages = DB::table('messages')
            ->join('agents', 'messages.agent_id', '=', 'agents.id')
            ->where('agents.user_id', $user->id)
            ->where('messages.role', 'assistant')
            ->whereRaw('date(messages.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('date(messages.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->selectRaw('DATE(messages.created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Generate last 7 days with day names
        $weeklyData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateKey = $date->format('Y-m-d');
            $dayName = $date->format('D'); // Mon, Tue, Wed, etc.

            $weeklyData[] = [
                'day' => $dayName,
                'value' => $dailyMessages[$dateKey] ?? 0,
            ];
        }

        return $weeklyData;
    }
}
