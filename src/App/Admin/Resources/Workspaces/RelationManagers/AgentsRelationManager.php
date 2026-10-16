<?php

declare(strict_types=1);

namespace App\Admin\Resources\Workspaces\RelationManagers;

use App\Admin\Pages\DashboardFilters;
use App\Identity\Workspace\Models\Workspace;
use BackedEnum;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Platform\AgentActivityData;
use Domain\Analytics\Enums\AnalyticsPeriod;
use Domain\Analytics\Queries\Platform\AgentActivityQuery;
use Domain\Conversation\Enums\ConversationSource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The workspace's agents on its admin page: model, where each answers,
 * knowledge, this month's traffic and when it was last used. Read-only.
 */
class AgentsRelationManager extends RelationManager
{
    /**
     * The messaging channels, by the agent relation that holds each
     * connection. A connection switched off does not count.
     */
    private const array CHANNELS = [
        'telegramBot' => ConversationSource::Telegram,
        'slackApp' => ConversationSource::Slack,
        'messengerPage' => ConversationSource::Messenger,
        'whatsappNumber' => ConversationSource::Whatsapp,
        'teamsBot' => ConversationSource::MsTeams,
        'instagramAccount' => ConversationSource::Instagram,
    ];

    #[\Override]
    protected static string $relationship = 'agents';

    #[\Override]
    protected static ?string $title = 'Agents';

    #[\Override]
    protected static string|BackedEnum|null $icon = Heroicon::OutlinedCpuChip;

    /** The admin panel is staff-only; the dashboard's agent policy does not apply. */
    #[\Override]
    protected static bool $shouldSkipAuthorization = true;

    /**
     * This month's traffic of the agents on the current page, read once.
     *
     * @var array<int, AgentActivityData>|null
     */
    private ?array $activity = null;

    #[\Override]
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord instanceof Workspace ? (string) $ownerRecord->agents()->count() : null;
    }

    #[\Override]
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('llm')
                ->withExists(array_map(
                    fn (): \Closure => fn (Builder $channel): Builder => $channel->where('is_enabled', true),
                    self::CHANNELS,
                ))
                ->addSelect(['sources_count' => DB::table('agent_sources')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('agent_sources.agent_id', 'agents.id')]))
            ->columns([
                TextColumn::make('name')
                    ->label('Agent')
                    ->weight('medium')
                    ->description(fn (Agent $record): ?string => $record->slug)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_public')
                    ->label('Access')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Public' : 'Protected')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('llm.descriptive_name')
                    ->label('Model')
                    ->placeholder('—'),
                TextColumn::make('answers_on')
                    ->label('Answers on')
                    ->badge()
                    ->color('gray')
                    ->state(fn (Agent $record): array => $this->answersOn($record))
                    ->placeholder('Nowhere'),
                TextColumn::make('sources_count')
                    ->label('Knowledge sources')
                    ->numeric(),
                TextColumn::make('conversations_this_month')
                    ->label('Conversations')
                    ->tooltip('This month')
                    ->state(fn (Agent $record): int => $this->activityOf($record)?->conversations ?? 0)
                    ->numeric(),
                TextColumn::make('messages_this_month')
                    ->label('Messages')
                    ->tooltip('This month')
                    ->state(fn (Agent $record): int => $this->activityOf($record)?->messages ?? 0)
                    ->numeric(),
                TextColumn::make('last_active_at')
                    ->label('Last active')
                    ->state(fn (Agent $record): mixed => $this->activityOf($record)?->lastActiveAt)
                    ->since()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon(Heroicon::OutlinedCpuChip)
            ->emptyStateHeading('No agents yet');
    }

    /**
     * The web surfaces the agent has switched on, then its connected
     * messaging channels.
     *
     * @return list<string>
     */
    private function answersOn(Agent $agent): array
    {
        $surfaces = array_filter(
            [ConversationSource::HostedChat, ConversationSource::Widget, ConversationSource::Bar],
            $agent->surfaceEnabled(...),
        );

        $channels = array_filter(
            self::CHANNELS,
            fn (string $relation): bool => (bool) $agent->getAttribute(Str::snake($relation).'_exists'),
            ARRAY_FILTER_USE_KEY,
        );

        return array_values(array_map(
            fn (ConversationSource $source): string => $source->label(),
            [...$surfaces, ...array_values($channels)],
        ));
    }

    private function activityOf(Agent $agent): ?AgentActivityData
    {
        if ($this->activity === null) {
            $records = $this->getTableRecords();
            $agentIds = collect($records instanceof Collection ? $records->all() : $records->items())
                ->map(fn (Agent $record): int => $record->id)
                ->values()
                ->all();

            $this->activity = app(AgentActivityQuery::class)->get(
                DashboardFilters::scope([
                    DashboardFilters::PERIOD => AnalyticsPeriod::ThisMonth->value,
                    DashboardFilters::WORKSPACE => $agent->workspace_id,
                ]),
                $agentIds,
            );
        }

        return $this->activity[$agent->id] ?? null;
    }
}
