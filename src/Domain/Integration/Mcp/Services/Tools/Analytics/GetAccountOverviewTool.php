<?php

namespace Domain\Integration\Mcp\Services\Tools\Analytics;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description(<<<'TXT'
A one-shot snapshot of the whole account for a period: total messages (incoming
+ AI replies), conversations, unique visitors, leads captured, average response
time, thumbs up/down, tokens, and — for the current cycle — messages used vs the
plan limit. Use this first when the user asks "how's my account doing?" or wants
an overview, then drill in with the more specific tools.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetAccountOverviewTool extends AnalyticsTool
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

        // Count from the granular event log (exact occurred_at), not the
        // hour-bucketed rollup, so a cycle that starts mid-hour doesn't drop its
        // first partial hour (see GetMessageStatsTool). Conversations come from
        // the per-conversation metrics table (exact started_at) for the same reason.
        $msgs = $this->analytics()->table('analytics_events')
            ->where('workspace_id', $workspace->id)
            ->where('event_type', 'message_sent')
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->selectRaw("COUNT(*) FILTER (WHERE role = 'user') user_messages, COUNT(*) FILTER (WHERE role = 'assistant') assistant_messages, COALESCE(SUM(total_tokens),0) tokens, CASE WHEN COUNT(*) FILTER (WHERE response_time_ms IS NOT NULL) > 0 THEN AVG(response_time_ms) END avg_response_ms")
            ->first();

        $conversations = $this->analytics()->table('analytics_conversation_metrics')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('started_at', [$period->start, $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->count();

        $feedback = $this->analytics()->table('analytics_events')
            ->where('workspace_id', $workspace->id)
            ->where('event_type', 'feedback_given')
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->selectRaw("COUNT(*) FILTER (WHERE feedback = 'positive') positive, COUNT(*) FILTER (WHERE feedback = 'negative') negative")
            ->first();

        $visitors = $this->analytics()->table('analytics_session_firsts')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('first_seen_at', [$period->start, $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->count();

        $leads = DB::table('leads')
            ->whereIn('agent_id', $scope['ids'])
            ->whereBetween('created_at', [$period->start, $period->end])
            ->count();

        $userMessages = (int) $msgs->user_messages;
        $assistantMessages = (int) $msgs->assistant_messages;
        $positive = (int) $feedback->positive;
        $negative = (int) $feedback->negative;
        $avgResponse = $this->round($msgs->avg_response_ms, 0);

        $credit = $workspace->credit;
        $messagesUsed = $credit?->usage?->messages;
        $messagesLimit = $credit?->messages;

        $summary = sprintf(
            '%s: %s messages (%s in, %s AI) across %s conversations from %s visitors, %s leads captured.%s%s',
            ucfirst($period->label),
            number_format($userMessages + $assistantMessages),
            number_format($userMessages),
            number_format($assistantMessages),
            number_format($conversations),
            number_format($visitors),
            number_format($leads),
            $avgResponse !== null ? ' Avg response '.number_format((float) $avgResponse).'ms.' : '',
            ($positive + $negative) > 0 ? ' Feedback: '.$positive.'👍 / '.$negative.'👎.' : '',
        );

        return Response::make(Response::text($summary))->withStructuredContent([
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'incoming_messages' => $userMessages,
            'ai_messages' => $assistantMessages,
            'total_messages' => $userMessages + $assistantMessages,
            'conversations' => $conversations,
            'unique_visitors' => $visitors,
            'leads' => $leads,
            'avg_response_ms' => $avgResponse,
            'positive_feedback' => $positive,
            'negative_feedback' => $negative,
            'tokens' => (int) $msgs->tokens,
            'messages_used_this_cycle' => $messagesUsed !== null ? (int) $messagesUsed : null,
            'messages_limit_this_cycle' => $messagesLimit !== null ? (int) $messagesLimit : null,
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
