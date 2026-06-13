<?php

namespace Domain\Integration\Mcp\Services\Tools\Analytics;

use Domain\Analytics\Services\Chart\AnalyticsChart;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

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
    private const LABELS = [
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

    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate($this->commonRules());

        $workspace = $this->workspace();
        if (! $workspace) {
            return Response::error('No workspace context for this analytics request.');
        }

        $period = $this->resolvePeriod($workspace, $v);
        $scope = $this->agentScope($workspace, $v);

        $rows = DB::table('chat_conversations as c')
            ->join('chat_sessions as s', 'c.chat_session_id', '=', 's.id')
            ->whereIn('s.agent_id', $scope['ids'])
            ->whereNull('c.deleted_at')
            ->whereBetween('c.created_at', [$period->start, $period->end])
            ->groupBy('c.source')
            ->selectRaw('c.source, COUNT(*) conversations')
            ->orderByDesc('conversations')
            ->get();

        $total = (int) $rows->sum('conversations');

        $channels = $rows->map(fn ($r): array => [
            'source' => $r->source,
            'label' => self::LABELS[$r->source] ?? ucfirst((string) $r->source),
            'conversations' => (int) $r->conversations,
            'pct' => $this->pct((int) $r->conversations, $total),
        ])->all();

        $summary = empty($channels)
            ? sprintf('No conversations across any channel %s.', $period->label)
            : sprintf(
                'Busiest channel %s is %s with %s conversations (%s%% of %s total).',
                $period->label,
                $channels[0]['label'],
                number_format($channels[0]['conversations']),
                $channels[0]['pct'],
                number_format($total),
            );

        $structured = [
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'total_conversations' => $total,
            'channels' => $channels,
        ];

        if (count($channels) > 1) {
            $structured['echarts_option'] = AnalyticsChart::pie(
                array_map(static fn (array $c): array => ['name' => $c['label'], 'value' => $c['conversations']], $channels),
                'Conversations by channel — '.$period->label,
            );
        }

        return Response::make(Response::text($summary))->withStructuredContent($structured);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->commonSchema($schema);
    }
}
