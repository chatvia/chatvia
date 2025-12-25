<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Lead;
use App\Models\SourceFile;
use App\Models\SourceWebsite;
use App\Models\Team;
use App\Models\User;
use App\Models\UserQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardStatsController
{
    public function __invoke(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $team = current_team();
        $agentIds = $team ? $team->agents()->pluck('id') : collect();

        // Count conversations for user's agents
        $totalConversations = DB::table('chat_conversations')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->whereIn('chat_sessions.agent_id', $agentIds)
            ->where('chat_conversations.visible', true)
            ->count();

        // Count total leads for user's agents
        $totalLeads = Lead::whereIn('agent_id', $agentIds)->count();

        // Active sessions in the last 24 hours
        $activeSessions = DB::table('chat_sessions')
            ->whereIn('agent_id', $agentIds)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        // Knowledge sources breakdown
        $knowledgeSources = $this->getKnowledgeSourcesBreakdown($user, $agentIds);

        // Messages this period vs last period
        $messagesTrend = $this->getMessagesTrend($team);

        // Usage stats
        $usage = $this->getUsageStats($user);

        return [
            'agents' => [
                'total' => $team ? $team->agents()->count() : 0,
                'widget_count' => $team ? $team->agents()->where('type', 'widget')->count() : 0,
                'knowledge_base_count' => $team ? $team->agents()->where('type', 'knowledge_base')->count() : 0,
            ],
            'messages' => [
                'total' => $user->creditSystem()->getTotalSentMessages(),
                'available' => $user->consumableMessages(),
                'trend' => $messagesTrend,
            ],
            'conversations' => $totalConversations,
            'leads' => $totalLeads,
            'active_sessions' => $activeSessions,
            'knowledge_sources' => $knowledgeSources,
            'team' => [
                'members' => $team ? $team->members()->count() : 0,
            ],
            'weekly_usage' => $this->getWeeklyMessagesVolume($team),
            'monthly_usage' => $this->getMonthlyMessagesVolume($team),
            'usage' => $usage,
        ];
    }

    private function getUsageStats(User $user): array
    {
        /** @var UserQuota $quota */
        $quota = $user->quota;

        $totalSentMessages = $user->creditSystem()->getTotalSentMessages();
        $consumableMessages = $user->consumableMessages();
        $pagesConsumed = $user->pagesConsumed();
        $minutesConsumed = $user->minutesConsumed();
        $linksConsumed = $user->linksConsumed();

        return [
            'plan' => $user->plan ?? 'Free',
            'messages' => [
                'used' => $totalSentMessages,
                'limit' => $totalSentMessages + $consumableMessages,
                'remaining' => $consumableMessages,
            ],
            'pages' => [
                'used' => $pagesConsumed,
                'limit' => $quota->pages_limit,
                'remaining' => max(0, $quota->pages_limit - $pagesConsumed),
            ],
            'minutes' => [
                'used' => $minutesConsumed,
                'limit' => $quota->minutes_limit,
                'remaining' => max(0, $quota->minutes_limit - $minutesConsumed),
            ],
            'links' => [
                'used' => $linksConsumed,
                'limit' => $quota->links_limit,
                'remaining' => max(0, $quota->links_limit - $linksConsumed),
            ],
        ];
    }

    private function getKnowledgeSourcesBreakdown(User $user, $agentIds): array
    {
        $files = DB::table('source_files')
            ->join('agent_sources', function ($join) {
                $join->on('source_files.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', SourceFile::class);
            })
            ->whereIn('agent_sources.agent_id', $agentIds)
            ->distinct('source_files.id')
            ->count('source_files.id');

        $websites = DB::table('source_websites')
            ->join('agent_sources', function ($join) {
                $join->on('source_websites.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', SourceWebsite::class);
            })
            ->whereIn('agent_sources.agent_id', $agentIds)
            ->distinct('source_websites.id')
            ->count('source_websites.id');

        $crawledPages = DB::table('source_website_urls')
            ->join('source_websites', 'source_websites.id', '=', 'source_website_urls.source_website_id')
            ->join('agent_sources', function ($join) {
                $join->on('source_websites.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', SourceWebsite::class);
            })
            ->whereIn('agent_sources.agent_id', $agentIds)
            ->whereNotNull('source_website_urls.crawled_at')
            ->distinct('source_website_urls.id')
            ->count('source_website_urls.id');

        return [
            'files' => $files,
            'websites' => $websites,
            'crawled_pages' => $crawledPages,
            'total' => $files + $websites,
        ];
    }

    private function getMessagesTrend(?Team $team): array
    {
        if (! $team) {
            return ['this_month' => 0, 'last_month' => 0, 'percent_change' => 0];
        }

        $agentIds = $team->agents()->pluck('id');

        // This month's messages
        $thisMonth = DB::table('chat_messages')
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->whereIn('chat_sessions.agent_id', $agentIds)
            ->where('chat_conversations.visible', true)
            ->where('chat_messages.role', 'assistant')
            ->where('chat_messages.created_at', '>=', now()->startOfMonth())
            ->count();

        // Last month's messages
        $lastMonth = DB::table('chat_messages')
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->whereIn('chat_sessions.agent_id', $agentIds)
            ->where('chat_conversations.visible', true)
            ->where('chat_messages.role', 'assistant')
            ->where('chat_messages.created_at', '>=', now()->subMonth()->startOfMonth())
            ->where('chat_messages.created_at', '<', now()->startOfMonth())
            ->count();

        $percentChange = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100)
            : ($thisMonth > 0 ? 100 : 0);

        return [
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'percent_change' => $percentChange,
        ];
    }

    private function getWeeklyMessagesVolume(?Team $team): array
    {
        $weeklyData = [];

        if (! $team) {
            for ($i = 6; $i >= 0; $i--) {
                $weeklyData[] = ['label' => now()->subDays($i)->format('D'), 'value' => 0];
            }

            return $weeklyData;
        }

        $startDate = now()->subDays(6)->startOfDay();
        $endDate = now()->endOfDay();

        // Get daily message counts from team's agents using the date index
        $dailyMessages = DB::table('chat_messages')
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('agents', 'chat_sessions.agent_id', '=', 'agents.id')
            ->where('agents.team_id', $team->id)
            ->where('chat_conversations.visible', true)
            ->where('chat_messages.role', 'assistant')
            ->whereRaw('date(chat_messages.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('date(chat_messages.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->selectRaw('DATE(chat_messages.created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Generate last 7 days with day names
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateKey = $date->format('Y-m-d');
            $dayName = $date->format('D'); // Mon, Tue, Wed, etc.

            $weeklyData[] = [
                'label' => $dayName,
                'value' => $dailyMessages[$dateKey] ?? 0,
            ];
        }

        return $weeklyData;
    }

    private function getMonthlyMessagesVolume(?Team $team): array
    {
        $monthlyData = [];

        if (! $team) {
            for ($i = 29; $i >= 0; $i--) {
                $monthlyData[] = ['label' => now()->subDays($i)->format('j'), 'value' => 0];
            }

            return $monthlyData;
        }

        $startDate = now()->subDays(29)->startOfDay();
        $endDate = now()->endOfDay();

        // Get daily message counts from team's agents
        $dailyMessages = DB::table('chat_messages')
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('agents', 'chat_sessions.agent_id', '=', 'agents.id')
            ->where('agents.team_id', $team->id)
            ->where('chat_conversations.visible', true)
            ->where('chat_messages.role', 'assistant')
            ->whereRaw('date(chat_messages.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('date(chat_messages.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->selectRaw('DATE(chat_messages.created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Generate last 30 days with day numbers
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateKey = $date->format('Y-m-d');
            $dayNumber = $date->format('j'); // 1, 2, 3, etc.

            $monthlyData[] = [
                'label' => $dayNumber,
                'value' => $dailyMessages[$dateKey] ?? 0,
            ];
        }

        return $monthlyData;
    }
}
