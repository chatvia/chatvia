<?php

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Audience\ChannelShareData;
use Domain\Analytics\Queries\Account\Audience\ChannelBreakdownQuery;
use Domain\Analytics\Services\Chart\AnalyticsChart;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-channel-breakdown: conversations per channel, with a pie chart.
 */
#[Description(<<<'TXT'
Conversation volume per channel for a period — Hosted Chat, Chat Widget, Command
Bar, Telegram, Slack, WhatsApp, Messenger, MS Teams, Instagram — with each
channel's share of the total. Answers "which channel is busiest?", "how much
comes from WhatsApp vs the website?". The per-channel counts are ideal for a pie
or bar chart. (Reflects retained conversations within the period.)
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetChannelBreakdownTool extends AnalyticsTool
{
    public function __construct(private readonly ChannelBreakdownQuery $channels) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $data = $this->channels->get($scope);
        $period = $scope->period;

        $channels = array_map(static fn (ChannelShareData $channel): array => [
            'source' => $channel->source,
            'label' => $channel->label,
            'conversations' => $channel->conversations,
            'pct' => $channel->pct,
        ], $data->channels);

        $summary = empty($channels)
            ? sprintf('No conversations across any channel %s.', $period->label)
            : sprintf(
                'Busiest channel %s is %s with %s conversations (%s%% of %s total).',
                $period->label,
                $channels[0]['label'],
                number_format($channels[0]['conversations']),
                $channels[0]['pct'],
                number_format($data->totalConversations),
            );

        $structured = [
            'period' => $this->periodPayload($period),
            'total_conversations' => $data->totalConversations,
            'channels' => $channels,
        ];

        if (count($channels) > 1) {
            $structured['echarts_option'] = AnalyticsChart::pie(
                array_map(static fn (array $channel): array => ['name' => $channel['label'], 'value' => $channel['conversations']], $channels),
                'Conversations by channel — '.$period->label,
            );
        }

        return Response::make(Response::text($summary))->withStructuredContent($structured);
    }
}
