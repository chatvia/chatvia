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
 * first. Reads the retained conversations, so deleted ones are not counted.
 */
class ChannelBreakdownQuery extends AccountAnalyticsQuery
{
    public function get(AnalyticsScopeData $scope): ChannelBreakdownData
    {
        $rows = $this->liveConversations($scope)
            ->whereBetween('c.created_at', [$scope->period->start, $scope->period->end])
            ->groupBy('c.source')
            ->selectRaw('c.source, COUNT(*) conversations')
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
