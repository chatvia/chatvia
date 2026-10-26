<?php

namespace Domain\Analytics\Queries\Account\Audience;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Audience\ChannelBreakdownData;
use Domain\Analytics\Data\Account\Audience\ChannelShareData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Support\Str;

/**
 * Conversations per channel (Hosted Chat, Chat Widget, WhatsApp, …), busiest
 * first, by when they started. Read from the per-conversation metrics, like
 * the other conversation counts, so a deleted conversation still counts.
 */
class ChannelBreakdownQuery extends AccountAnalyticsQuery
{
    public function get(AnalyticsScopeData $scope): ChannelBreakdownData
    {
        $rows = $this->conversationMetrics($scope)
            ->whereNotNull('source')
            ->groupBy('source')
            ->selectRaw('source, COUNT(*) conversations')
            ->orderByDesc('conversations')
            ->get();

        $total = (int) $rows->sum('conversations');

        return new ChannelBreakdownData(
            totalConversations: $total,
            channels: $rows->map(fn (object $row): ChannelShareData => new ChannelShareData(
                source: $row->source,
                label: ConversationSource::tryFrom((string) $row->source)?->label() ?? Str::ucfirst((string) $row->source),
                conversations: (int) $row->conversations,
                pct: $this->pct((int) $row->conversations, $total),
            ))->all(),
        );
    }
}
