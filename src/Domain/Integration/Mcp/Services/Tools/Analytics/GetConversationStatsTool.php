<?php

namespace Domain\Integration\Mcp\Services\Tools\Analytics;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description(<<<'TXT'
Conversation-level stats for a period: how many conversations started, the
average length (messages per conversation), the average duration in seconds, and
the single busiest day. Answers "how many conversations this cycle?", "how long
is the average chat?", "which day was busiest?".
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetConversationStatsTool extends AnalyticsTool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate($this->commonRules());

        $workspace = $this->workspace();
        if (! $workspace) {
            return Response::error('No workspace context for this analytics request.');
        }

        $period = $this->resolvePeriod($workspace, $v);
        $scope = $this->agentScope($workspace, $v);

        $conversations = (int) $this->analytics()->table('analytics_agent_hourly_rollups')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('bucket_hour', [$period->start->copy()->startOfHour(), $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->sum('conversations_started');

        $metrics = $this->analytics()->table('analytics_conversation_metrics')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('started_at', [$period->start, $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->selectRaw('AVG(message_count) avg_messages, AVG(NULLIF(duration_seconds, 0)) avg_duration')
            ->first();

        $busiest = $this->analytics()->table('analytics_agent_hourly_rollups')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('bucket_hour', [$period->start->copy()->startOfHour(), $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->selectRaw('bucket_hour::date date, SUM(conversations_started) c')
            ->groupBy('date')
            ->orderByRaw('SUM(conversations_started) DESC')
            ->limit(1)
            ->first();

        $avgMessages = $this->round($metrics->avg_messages, 1);
        $avgDuration = $this->round($metrics->avg_duration, 0);

        $summary = sprintf(
            '%s: %s conversations, averaging %s messages each%s.%s',
            ucfirst($period->label),
            number_format($conversations),
            $avgMessages !== null ? number_format((float) $avgMessages, 1) : '0',
            $avgDuration !== null ? ' and about '.number_format((float) $avgDuration).'s long' : '',
            $busiest ? ' Busiest day was '.$busiest->date.' ('.number_format((int) $busiest->c).' conversations).' : '',
        );

        return Response::make(Response::text($summary))->withStructuredContent([
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'conversations' => $conversations,
            'avg_messages_per_conversation' => $avgMessages,
            'avg_duration_seconds' => $avgDuration,
            'busiest_day' => $busiest ? ['date' => (string) $busiest->date, 'conversations' => (int) $busiest->c] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->commonSchema($schema);
    }
}
