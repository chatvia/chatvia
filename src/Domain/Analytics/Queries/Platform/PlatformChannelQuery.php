<?php

declare(strict_types=1);

namespace Domain\Analytics\Queries\Platform;

use Domain\Analytics\Data\Account\Audience\ChannelBreakdownData;
use Domain\Analytics\Data\Account\Audience\ChannelShareData;
use Domain\Analytics\Data\Platform\PlatformScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Conversations per channel (Hosted Chat, Chat Widget, WhatsApp, …) started
 * in the window, busiest first. There is no rollup per channel, so this
 * reads the retained conversations: deleted ones are not counted, nor hidden
 * ones (opened but never used).
 */
class PlatformChannelQuery extends PlatformAnalyticsQuery
{
    public function get(PlatformScopeData $scope): ChannelBreakdownData
    {
        $rows = DB::table('chat_conversations')
            ->whereNull('deleted_at')
            ->where('is_visible', true)
            ->whereBetween('created_at', [$scope->period->start, $scope->period->end])
            ->when(! $scope->isWholePlatform(), fn (Builder $query): Builder => $query->where('workspace_id', $scope->workspaceId))
            ->groupBy('source')
            ->selectRaw('source, COUNT(*) conversations')
            ->orderByDesc('conversations')
            ->orderBy('source')
            ->get();

        $total = (int) $rows->sum('conversations');

        return new ChannelBreakdownData(
            totalConversations: $total,
            channels: $rows->map(fn (object $row): ChannelShareData => new ChannelShareData(
                source: $row->source,
                label: ConversationSource::tryFrom((string) $row->source)?->label() ?? Str::ucfirst((string) $row->source),
                conversations: (int) $row->conversations,
                pct: $this->pct((int) $row->conversations, $total),
            ))->values()->all(),
        );
    }
}
