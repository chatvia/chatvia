<?php

declare(strict_types=1);

namespace Domain\Analytics\Queries\Platform;

use Domain\Analytics\Data\Account\Audience\ChannelBreakdownData;
use Domain\Analytics\Data\Account\Audience\ChannelShareData;
use Domain\Analytics\Data\Platform\PlatformScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

/**
 * Conversations per channel (Hosted Chat, Chat Widget, WhatsApp, …) started
 * in the window, busiest first. Read from the per-conversation metrics, like
 * every other analytics count: a conversation with a message counts even
 * after it was deleted, and one opened but never used has no row.
 */
class PlatformChannelQuery extends PlatformAnalyticsQuery
{
    public function get(PlatformScopeData $scope): ChannelBreakdownData
    {
        $rows = $this->analytics()->table('analytics_conversation_metrics')
            ->whereNotNull('source')
            ->whereBetween('started_at', [$scope->period->start, $scope->period->end])
            ->when(! $scope->isWholePlatform(), fn (Builder $query): Builder => $query->where('workspace_id', $scope->workspaceId))
            ->groupBy('source')
            ->selectRaw('source, COUNT(*) conversations')
            ->orderByDesc('conversations')
            ->orderBy('source')
            ->get();

        $total = (int) $rows->sum('conversations');

        return new ChannelBreakdownData(
            totalConversations: $total,
            channels: $rows->map(fn (object $row): ChannelShareData => new ChannelShareData(
                source: $row->source,
                label: ConversationSource::tryFrom((string) $row->source)?->label() ?? Str::ucfirst((string) $row->source),
                conversations: (int) $row->conversations,
                pct: $this->pct((int) $row->conversations, $total),
            ))->values()->all(),
        );
    }
}
