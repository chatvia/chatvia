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
Human-handoff stats for a period: how many conversations were escalated from the
AI to a human, the handoff rate (share of conversations), and how many handoffs
are pending right now (awaiting a human, not period-bound). Answers "how many
chats went to a human?", "what's my escalation rate?", "any handoffs waiting?".
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetHandoffStatsTool extends AnalyticsTool
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

        $base = fn () => DB::table('chat_conversations as c')
            ->join('chat_sessions as s', 'c.chat_session_id', '=', 's.id')
            ->whereIn('s.agent_id', $scope['ids'])
            ->whereNull('c.deleted_at');

        $totalConversations = (int) $base()->whereBetween('c.created_at', [$period->start, $period->end])->count();
        $handoffs = (int) $base()->whereBetween('c.created_at', [$period->start, $period->end])->whereNotNull('c.handoff_at')->count();
        $pendingNow = (int) $base()->where('c.handoff_status', 'pending')->count();

        $rate = $this->pct($handoffs, $totalConversations);

        $summary = sprintf(
            '%s: %s of %s conversations went to a human (%s%% handoff rate). %s handoff%s pending right now.',
            ucfirst($period->label),
            number_format($handoffs),
            number_format($totalConversations),
            $rate,
            number_format($pendingNow),
            $pendingNow === 1 ? '' : 's',
        );

        return Response::make(Response::text($summary))->withStructuredContent([
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'conversations' => $totalConversations,
            'handoffs' => $handoffs,
            'handoff_rate_pct' => $rate,
            'pending_now' => $pendingNow,
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
