<?php

namespace Domain\Integration\Mcp\Services\Tools\Analytics;

use Carbon\CarbonImmutable;
use Domain\Analytics\Services\Chart\AnalyticsChart;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description(<<<'TXT'
The current billing cycle's message allowance: when the cycle started and ends,
how many days are left, the plan's message limit, how many have been used and
how many remain, any overage, bonus/add-on messages, tokens consumed, and a
simple run-rate projection of where usage will land by the cycle end. Answers
"how many messages have I used / how many left?", "when does my cycle reset?",
"am I going to run out?". Billing is message-based; tokens are analytics only.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetBillingCycleUsageTool extends AnalyticsTool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([]);

        $workspace = $this->workspace();
        if (! $workspace) {
            return Response::error('No workspace context for this analytics request.');
        }

        $credit = $workspace->credit;
        if (! $credit) {
            return Response::make(Response::text('There is no active billing cycle on this account right now.'))
                ->withStructuredContent(['has_active_cycle' => false]);
        }

        $usage = $credit->usage;
        $now = CarbonImmutable::now();
        $start = CarbonImmutable::parse($credit->started_at);
        $end = CarbonImmutable::parse($credit->ends_at);

        $limit = (int) $credit->messages;
        $used = (int) ($usage->messages ?? 0);
        $remaining = max(0, $limit - $used);
        $exceeded = (int) ($usage->messages_exceeded ?? 0);
        $tokens = (int) ($usage->tokens ?? 0);

        $daysTotal = max(1, (int) $start->diffInDays($end));
        $daysElapsed = max(1, min($daysTotal, (int) $start->diffInDays($now)));
        $daysLeft = max(0, (int) $now->diffInDays($end, false));
        $projected = (int) round(($used / $daysElapsed) * $daysTotal);

        $addonMessages = (int) $workspace->addons()
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->sum('amount');

        $summary = sprintf(
            'This cycle (ends %s, %d day%s left): %s of %s messages used, %s remaining%s. On the current pace you\'ll reach about %s by cycle end.',
            $end->toFormattedDateString(),
            $daysLeft,
            $daysLeft === 1 ? '' : 's',
            number_format($used),
            number_format($limit),
            number_format($remaining),
            $exceeded > 0 ? ' ('.number_format($exceeded).' over the limit)' : '',
            number_format($projected),
        );

        $structured = [
            'has_active_cycle' => true,
            'cycle_start' => $start->toDateString(),
            'cycle_end' => $end->toDateString(),
            'days_total' => $daysTotal,
            'days_left' => $daysLeft,
            'messages_limit' => $limit,
            'messages_used' => $used,
            'messages_remaining' => $remaining,
            'messages_exceeded' => $exceeded,
            'addon_messages' => $addonMessages,
            'tokens_used' => $tokens,
            'projected_messages_used' => $projected,
        ];

        if ($limit > 0) {
            $structured['echarts_option'] = AnalyticsChart::gauge($used, 0, max($limit, $used), title: 'Messages used');
        }

        return Response::make(Response::text($summary))->withStructuredContent($structured);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        // Always the current cycle — no period argument.
        return [];
    }
}
