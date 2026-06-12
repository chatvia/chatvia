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
The topics visitors talk about most, for a period — each topic ranked by the
number of conversations it appears in. Answers "what are people asking about?",
"top topics this cycle?", "what's trending?". Great input for a bar chart of
demand by topic.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetTopTopicsTool extends AnalyticsTool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate([
            ...$this->commonRules(),
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $workspace = $this->workspace();
        if (! $workspace) {
            return Response::error('No workspace context for this analytics request.');
        }

        $period = $this->resolvePeriod($workspace, $v);
        $scope = $this->agentScope($workspace, $v);
        $limit = (int) ($v['limit'] ?? 10);

        $rows = $this->analytics()->table('analytics_agent_topic_daily')
            ->where('workspace_id', $workspace->id)
            ->whereRaw('bucket_day BETWEEN ?::date AND ?::date', [$period->start->toDateString(), $period->end->toDateString()])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->groupBy('topic_id')
            ->selectRaw('topic_id, SUM(conversation_count) conversations')
            ->orderByDesc('conversations')
            ->limit($limit)
            ->get();

        // Topic labels live on the main DB; resolve in PHP so this works even
        // when analytics runs on a separate connection.
        $names = DB::table('topics')->whereIn('id', $rows->pluck('topic_id')->all())->pluck('topic', 'id');

        $topics = $rows->map(fn ($r): array => [
            'topic' => $names[$r->topic_id] ?? 'Topic #'.$r->topic_id,
            'conversations' => (int) $r->conversations,
        ])->all();

        $summary = empty($topics)
            ? sprintf('No topics recorded %s yet.', $period->label)
            : sprintf(
                'Top topic %s is "%s" (%s conversations).',
                $period->label,
                $topics[0]['topic'],
                number_format($topics[0]['conversations']),
            );

        $structured = [
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'topics' => $topics,
        ];

        if (count($topics) > 1) {
            $structured['echarts_option'] = AnalyticsChart::bar(
                array_column($topics, 'topic'),
                [['name' => 'Conversations', 'data' => array_column($topics, 'conversations')]],
                'Top topics — '.$period->label,
                horizontal: true,
            );
        }

        return Response::make(Response::text($summary))->withStructuredContent($structured);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->commonSchema($schema),
            'limit' => $schema->integer()
                ->description('How many top topics to return (1–50). Defaults to 10.')
                ->default(10),
        ];
    }
}
