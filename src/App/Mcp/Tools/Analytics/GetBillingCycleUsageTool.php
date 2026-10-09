<?php

namespace App\Mcp\Tools\Analytics;

use App\Mcp\McpCallerContext;
use Domain\Analytics\Queries\Account\Usage\BillingCycleUsageQuery;
use Domain\Analytics\Services\Chart\AnalyticsChart;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-billing-cycle-usage: the current cycle's message allowance. Unlike the
 * other analytics tools it takes no period — it is always the current cycle —
 * so it does not share their base.
 */
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
class GetBillingCycleUsageTool extends Tool
{
    public function handle(Request $request, McpCallerContext $caller, BillingCycleUsageQuery $cycles): Response|ResponseFactory
    {
        $request->validate([]);

        if ($caller->workspace === null) {
            return Response::error('No workspace context for this analytics request.');
        }

        $cycle = $cycles->get($caller->workspace);
        if ($cycle === null) {
            return Response::make(Response::text('There is no active billing cycle on this account right now.'))
                ->withStructuredContent(['has_active_cycle' => false]);
        }

        $summary = sprintf(
            'This cycle (ends %s, %d day%s left): %s of %s messages used, %s remaining%s. On the current pace you\'ll reach about %s by cycle end.',
            $cycle->cycleEnd->toFormattedDateString(),
            $cycle->daysLeft,
            $cycle->daysLeft === 1 ? '' : 's',
            number_format($cycle->messagesUsed),
            number_format($cycle->messagesLimit),
            number_format($cycle->messagesRemaining),
            $cycle->messagesExceeded > 0 ? ' ('.number_format($cycle->messagesExceeded).' over the limit)' : '',
            number_format($cycle->projectedMessagesUsed),
        );

        $structured = [
            'has_active_cycle' => true,
            'cycle_start' => $cycle->cycleStart->toDateString(),
            'cycle_end' => $cycle->cycleEnd->toDateString(),
            'days_total' => $cycle->daysTotal,
            'days_left' => $cycle->daysLeft,
            'messages_limit' => $cycle->messagesLimit,
            'messages_used' => $cycle->messagesUsed,
            'messages_remaining' => $cycle->messagesRemaining,
            'messages_exceeded' => $cycle->messagesExceeded,
            'addon_messages' => $cycle->addonMessages,
            'tokens_used' => $cycle->tokensUsed,
            'projected_messages_used' => $cycle->projectedMessagesUsed,
        ];

        if ($cycle->messagesLimit > 0) {
            $structured['echarts_option'] = AnalyticsChart::gauge($cycle->messagesUsed, 0, max($cycle->messagesLimit, $cycle->messagesUsed), title: 'Messages used');
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
