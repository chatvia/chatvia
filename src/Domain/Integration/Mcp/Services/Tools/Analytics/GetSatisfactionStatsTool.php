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
Quality and satisfaction signals for a period: thumbs up vs down (and the
positive rate), the sentiment split of messages (positive / neutral / negative),
how often the AI could not find an answer, and the average answer confidence.
Answers "are people happy?", "how's sentiment?", "how often does the AI fail to
answer?". Use this to spot knowledge gaps.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetSatisfactionStatsTool extends AnalyticsTool
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

        $feedback = $this->analytics()->table('analytics_events')
            ->where('workspace_id', $workspace->id)
            ->where('event_type', 'feedback_given')
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->selectRaw("COUNT(*) FILTER (WHERE feedback = 'positive') positive, COUNT(*) FILTER (WHERE feedback = 'negative') negative")
            ->first();

        $sentimentRows = $this->analytics()->table('analytics_agent_sentiment_daily')
            ->where('workspace_id', $workspace->id)
            ->whereRaw('bucket_day BETWEEN ?::date AND ?::date', [$period->start->toDateString(), $period->end->toDateString()])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->groupBy('sentiment')
            ->selectRaw('sentiment, SUM(message_count) total')
            ->pluck('total', 'sentiment');

        $answers = DB::table('chat_messages as m')
            ->join('chat_conversations as c', 'm.chat_conversation_id', '=', 'c.id')
            ->join('chat_sessions as s', 'c.chat_session_id', '=', 's.id')
            ->whereIn('s.agent_id', $scope['ids'])
            ->whereNull('c.deleted_at')
            ->where('m.role', 'assistant')
            ->whereBetween('m.created_at', [$period->start, $period->end])
            ->selectRaw('COUNT(*) total, COUNT(*) FILTER (WHERE m.has_llm_found_answer = false) not_found, AVG(m.confidence_score) avg_confidence')
            ->first();

        $positive = (int) $feedback->positive;
        $negative = (int) $feedback->negative;
        $rated = $positive + $negative;
        $positiveRate = $this->pct($positive, $rated);

        $answeredTotal = (int) $answers->total;
        $notFound = (int) $answers->not_found;
        $unansweredRate = $this->pct($notFound, $answeredTotal);

        $summary = sprintf(
            '%s: %s👍 / %s👎 (%s%% positive). AI could not answer %s%% of replies.',
            ucfirst($period->label),
            number_format($positive),
            number_format($negative),
            $positiveRate,
            $unansweredRate,
        );

        $sentiment = [
            'positive' => (int) ($sentimentRows['positive'] ?? 0),
            'neutral' => (int) ($sentimentRows['neutral'] ?? 0),
            'negative' => (int) ($sentimentRows['negative'] ?? 0),
        ];

        $structured = [
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'positive_feedback' => $positive,
            'negative_feedback' => $negative,
            'positive_rate_pct' => $positiveRate,
            'sentiment' => $sentiment,
            'ai_replies' => $answeredTotal,
            'unanswered' => $notFound,
            'unanswered_rate_pct' => $unansweredRate,
            'avg_confidence' => $this->round($answers->avg_confidence, 2),
        ];

        if (array_sum($sentiment) > 0) {
            $structured['echarts_option'] = AnalyticsChart::pie([
                ['name' => 'Positive', 'value' => $sentiment['positive']],
                ['name' => 'Neutral', 'value' => $sentiment['neutral']],
                ['name' => 'Negative', 'value' => $sentiment['negative']],
            ], 'Sentiment — '.$period->label, donut: true);
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
