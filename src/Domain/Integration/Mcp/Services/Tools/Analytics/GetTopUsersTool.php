<?php

namespace Domain\Integration\Mcp\Services\Tools\Analytics;

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Services\Chart\AnalyticsChart;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description(<<<'TXT'
The most active end-users (identified chatters) for a period, ranked by how many
messages they sent, plus the count of unique identified users and how many new
visitors arrived. Answers "who is my most active user?", "top 10 users by
messages", "how many unique / new visitors?". Anonymous widget visitors with no
account are not individually named but are reflected in the new-visitor count.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetTopUsersTool extends AnalyticsTool
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
        $limit = (int) ($v['limit'] ?? 5);

        $base = fn () => $this->analytics()->table('analytics_events')
            ->where('workspace_id', $workspace->id)
            ->where('event_type', 'message_sent')
            ->where('role', 'user')
            ->whereNotNull('chatter_user_id')
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id));

        $rows = $base()
            ->selectRaw('chatter_user_id, COUNT(*) messages')
            ->groupBy('chatter_user_id')
            ->orderByDesc('messages')
            ->limit($limit)
            ->get();

        $uniqueUsers = (int) $base()->distinct()->count('chatter_user_id');

        $newVisitors = (int) $this->analytics()->table('analytics_session_firsts')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('first_seen_at', [$period->start, $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->count();

        $users = User::query()
            ->whereIn('id', $rows->pluck('chatter_user_id')->all())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $top = $rows->map(fn ($r): array => [
            'name' => $users[$r->chatter_user_id]->name ?? 'Unknown user',
            'email' => $users[$r->chatter_user_id]->email ?? null,
            'messages' => (int) $r->messages,
        ])->all();

        $summary = empty($top)
            ? sprintf('No identified users sent messages %s yet.', $period->label)
            : sprintf(
                'Most active %s: %s with %s messages. %s identified users in total, %s new visitors.',
                $period->label,
                $top[0]['name'],
                number_format($top[0]['messages']),
                number_format($uniqueUsers),
                number_format($newVisitors),
            );

        $structured = [
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'top_users' => $top,
            'unique_identified_users' => $uniqueUsers,
            'new_visitors' => $newVisitors,
        ];

        if (count($top) > 1) {
            $structured['echarts_option'] = AnalyticsChart::bar(
                array_column($top, 'name'),
                [['name' => 'Messages', 'data' => array_column($top, 'messages')]],
                'Most active users — '.$period->label,
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
                ->description('How many top users to return (1–50). Defaults to 5.')
                ->default(5),
        ];
    }
}
