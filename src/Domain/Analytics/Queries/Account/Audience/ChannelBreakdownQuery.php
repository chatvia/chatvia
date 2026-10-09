<?php

namespace Domain\Analytics\Queries\Account\Audience;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Audience\ChannelBreakdownData;
use Domain\Analytics\Data\Account\Audience\ChannelShareData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;

/**
 * Conversations per channel (Hosted Chat, Chat Widget, WhatsApp, …), busiest
 * first. Reads the retained conversations, so deleted ones are not counted.
 */
class ChannelBreakdownQuery extends AccountAnalyticsQuery
{
    private const array LABELS = [
        'hosted_chat' => 'Hosted Chat',
        'widget' => 'Chat Widget',
        'bar' => 'Command Bar',
        'telegram' => 'Telegram',
        'slack' => 'Slack',
        'messenger' => 'Messenger',
        'whatsapp' => 'WhatsApp',
        'msteams' => 'MS Teams',
        'instagram' => 'Instagram',
    ];

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
                label: self::LABELS[$row->source] ?? ucfirst((string) $row->source),
                conversations: (int) $row->conversations,
                pct: $this->pct((int) $row->conversations, $total),
            ))->all(),
        );
    }
}
