<?php

namespace Domain\Dashboard\Actions;

use App\Auth\Models\User;
use App\Billing\Models\UserQuota;
use App\Teams\Models\Team;
use Carbon\Carbon;
use Domain\Agents\Models\Lead;
use Domain\Sources\Files\Models\SourceFile;
use Domain\Sources\Web\Models\SourceWebsite;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetDashboardStatsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(User $user, ?Team $team): array
    {
        $agentIds = $team ? $team->agents()->pluck('id') : collect();

        $totalConversations = DB::table('chat_conversations')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->whereIn('chat_sessions.agent_id', $agentIds)
            ->where('chat_conversations.visible', true)
            ->count();

        $totalLeads = Lead::whereIn('agent_id', $agentIds)->count();

        $activeSessions = DB::table('chat_sessions')
            ->whereIn('agent_id', $agentIds)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'agents' => [
                'total' => $team ? $team->agents()->count() : 0,
                'widget_count' => $team ? $team->agents()->where('type', 'widget')->count() : 0,
                'knowledge_base_count' => $team ? $team->agents()->where('type', 'knowledge_base')->count() : 0,
            ],
            'messages' => [
                'total' => $user->creditSystem()->getTotalSentMessages(),
                'available' => $user->consumableMessages(),
                'trend' => $this->getMessagesTrend($team),
            ],
            'conversations' => $totalConversations,
            'leads' => $totalLeads,
            'active_sessions' => $activeSessions,
            'knowledge_sources' => $this->getKnowledgeSourcesBreakdown($agentIds),
            'team' => [
                'members' => $team ? $team->members()->count() : 0,
            ],
            'weekly_usage' => $this->getDailyMessagesVolume($team, 7, 'D'),
            'monthly_usage' => $this->getDailyMessagesVolume($team, 30, 'j'),
            'usage' => $this->getUsageStats($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param  Collection<int, int>  $agentIds
     * @return array<string, int>
     */
    private function getKnowledgeSourcesBreakdown($agentIds): array
    {
        $files = DB::table('source_files')
            ->join('agent_sources', function ($join): void {
                $join->on('source_files.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', SourceFile::class);
            })
            ->whereIn('agent_sources.agent_id', $agentIds)
            ->distinct('source_files.id')
            ->count('source_files.id');

        $websites = DB::table('source_websites')
            ->join('agent_sources', function ($join): void {
                $join->on('source_websites.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', SourceWebsite::class);
            })
            ->whereIn('agent_sources.agent_id', $agentIds)
            ->distinct('source_websites.id')
            ->count('source_websites.id');

        $crawledPages = DB::table('source_website_urls')
            ->join('source_websites', 'source_websites.id', '=', 'source_website_urls.source_website_id')
            ->join('agent_sources', function ($join): void {
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

    /**
     * @return array<string, int|float>
     */
    private function getMessagesTrend(?Team $team): array
    {
        if (! $team) {
            return ['this_month' => 0, 'last_month' => 0, 'percent_change' => 0];
        }

        $agentIds = $team->agents()->pluck('id');

        $thisMonth = $this->countAssistantMessages($agentIds, now()->startOfMonth(), null);
        $lastMonth = $this->countAssistantMessages($agentIds, now()->subMonth()->startOfMonth(), now()->startOfMonth());

        $percentChange = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100)
            : ($thisMonth > 0 ? 100 : 0);

        return [
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'percent_change' => $percentChange,
        ];
    }

    /**
     * @param  Collection<int, int>  $agentIds
     */
    private function countAssistantMessages($agentIds, Carbon $from, ?Carbon $to): int
    {
        $query = DB::table('chat_messages')
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->whereIn('chat_sessions.agent_id', $agentIds)
            ->where('chat_conversations.visible', true)
            ->where('chat_messages.role', 'assistant')
            ->where('chat_messages.created_at', '>=', $from);

        if ($to) {
            $query->where('chat_messages.created_at', '<', $to);
        }

        return $query->count();
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function getDailyMessagesVolume(?Team $team, int $days, string $labelFormat): array
    {
        $data = [];

        if (! $team) {
            for ($i = $days - 1; $i >= 0; $i--) {
                $data[] = ['label' => now()->subDays($i)->format($labelFormat), 'value' => 0];
            }

            return $data;
        }

        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

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

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateKey = $date->format('Y-m-d');

            $data[] = [
                'label' => $date->format($labelFormat),
                'value' => $dailyMessages[$dateKey] ?? 0,
            ];
        }

        return $data;
    }
}
