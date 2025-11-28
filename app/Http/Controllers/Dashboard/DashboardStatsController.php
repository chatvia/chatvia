<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Lead;
use App\Models\SourceFile;
use App\Models\SourceWebsite;
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
        $agentIds = $user->agents()->pluck('id');

        // Count conversations for user's agents
        $totalConversations = DB::table('chat_conversations')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->whereIn('chat_sessions.agent_id', $agentIds)
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
        $messagesTrend = $this->getMessagesTrend($user);

        // Usage stats
        $usage = $this->getUsageStats($user);

        return [
            'agents' => [
                'total' => $user->agents()->count(),
                'widget_count' => $user->agents()->where('type', 'widget')->count(),
                'knowledge_base_count' => $user->agents()->where('type', 'knowledge_base')->count(),
                'recent' => $user->agents()
                    ->select(['id', 'name', 'sub_domain', 'type', 'external_id'])
                    ->withCount(['chatSessions as conversations_count' => function ($query) {
                        $query->select(DB::raw('count(distinct chat_conversations.id)'))
                            ->join('chat_conversations', 'chat_sessions.id', '=', 'chat_conversations.chat_session_id');
                    }])
                    ->latest()
                    ->limit(5)
                    ->get(),
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
                'members' => $user->members()->count(),
            ],
            'weekly_usage' => $this->getWeeklyMessagesVolume($user),
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

    private function getMessagesTrend(User $user): array
    {
        $agentIds = $user->agents()->pluck('id');

        // This month's messages
        $thisMonth = DB::table('chat_messages')
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->whereIn('chat_sessions.agent_id', $agentIds)
            ->where('chat_messages.role', 'assistant')
            ->where('chat_messages.created_at', '>=', now()->startOfMonth())
            ->count();

        // Last month's messages
        $lastMonth = DB::table('chat_messages')
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->whereIn('chat_sessions.agent_id', $agentIds)
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

    private function getWeeklyMessagesVolume(User $user): array
    {
        $startDate = now()->subDays(6)->startOfDay();
        $endDate = now()->endOfDay();

        // Get daily message counts from user's agents using the date index
        $dailyMessages = DB::table('chat_messages')
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('agents', 'chat_sessions.agent_id', '=', 'agents.id')
            ->where('agents.user_id', $user->id)
            ->where('chat_messages.role', 'assistant')
            ->whereRaw('date(chat_messages.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('date(chat_messages.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->selectRaw('DATE(chat_messages.created_at) as date, COUNT(*) as count')
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
