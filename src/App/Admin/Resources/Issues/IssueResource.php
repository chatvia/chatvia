<?php

declare(strict_types=1);

namespace App\Admin\Resources\Issues;

use App\Admin\AdminPanelProvider;
use App\Admin\Issue\Data\IssueActionData;
use App\Admin\Issue\Data\IssueServiceData;
use App\Admin\Issue\Enums\IssueResolution;
use App\Admin\Issue\Enums\IssueStatus;
use App\Admin\Issue\Models\Issue;
use App\Admin\Issue\Models\IssueOccurrence;
use App\Admin\Issue\Services\IssueServices;
use App\Admin\Issue\Services\IssueTypes;
use App\Admin\Issue\Types\IssueType;
use App\Admin\Resources\Issues\Pages\ListIssues;
use App\Admin\Resources\Issues\Pages\ViewIssue;
use App\Admin\Resources\Issues\RelationManagers\OccurrencesRelationManager;
use App\Admin\Resources\Issues\Widgets\IssueOccurrencesChart;
use App\Admin\Resources\Workspaces\WorkspaceResource;
use App\Admin\StaffToolsAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use UnitEnum;

use function Filament\Support\generate_icon_html;

/**
 * Radar: what an admin can fix, one row per service and kind of issue
 * ("fal.ai refused the API key"), with how often and when it last
 * happened. The view page says what to do and where, and lists each time it
 * happened with the person behind it. An issue closes by itself when its
 * service works again; an admin can also resolve, ignore or delete it. The
 * sidebar counts the open ones.
 *
 * Opens for the same staff as the Tools page ({@see StaffToolsAccess}).
 */
class IssueResource extends Resource
{
    /** The most fix buttons an issue type gives. */
    private const int MAX_FIX_BUTTONS = 3;

    #[\Override]
    protected static ?string $model = Issue::class;

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = AdminPanelProvider::SYSTEM_NAVIGATION_GROUP;

    // Between Status (98) and Settings (100).
    #[\Override]
    protected static ?int $navigationSort = 99;

    #[\Override]
    protected static ?string $slug = 'radar';

    #[\Override]
    protected static ?string $navigationLabel = 'Radar';

    #[\Override]
    protected static ?string $breadcrumb = 'Radar';

    #[\Override]
    protected static ?string $modelLabel = 'issue';

    #[\Override]
    protected static ?string $pluralModelLabel = 'issues';

    #[\Override]
    protected static bool $isGloballySearchable = false;

    #[\Override]
    public static function canAccess(): bool
    {
        return app(StaffToolsAccess::class)->allows();
    }

    #[\Override]
    public static function getNavigationBadge(): ?string
    {
        $open = once(fn (): int => Issue::query()->where('status', IssueStatus::Open)->count());

        return $open > 0 ? (string) $open : null;
    }

    #[\Override]
    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    #[\Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Open issues';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')
                    ->label('Issue')
                    ->html()
                    ->formatStateUsing(fn (Issue $record): HtmlString => self::titleWithLogo($record))
                    ->description(fn (Issue $record): ?string => $record->message)
                    ->wrap(),
                TextColumn::make('occurrences_count')
                    ->label('Times')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (IssueStatus $state): string => $state->label())
                    ->color(fn (IssueStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(IssueStatus::cases())->mapWithKeys(fn (IssueStatus $status): array => [$status->value => $status->label()])->all())
                    ->default(IssueStatus::Open->value),
                SelectFilter::make('service')
                    ->options(fn (): array => Issue::query()->distinct()->pluck('service')
                        ->mapWithKeys(fn (string $service): array => [$service => app(IssueServices::class)->for($service)->label])
                        ->sort()
                        ->all()),
                SelectFilter::make('kind')
                    ->options(fn (): array => app(IssueTypes::class)->all()->map(fn (IssueType $type): string => $type->label())->all()),
            ])
            ->defaultSort('last_seen_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->poll('30s')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading('No issues')
            ->emptyStateDescription('Every service the install uses is working.')
            ->recordUrl(fn (Issue $record): string => self::getUrl('view', ['record' => $record]))
            ->recordActions([
                self::resolveAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('resolve')
                        ->label('Resolve')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->action(fn (Collection $records) => Issue::query()->whereKey($records->modelKeys())->update(['status' => IssueStatus::Resolved, 'resolution' => IssueResolution::Admin, 'resolved_at' => now()]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('ignore')
                        ->label('Ignore')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->action(fn (Collection $records) => Issue::query()->whereKey($records->modelKeys())->update(['status' => IssueStatus::Ignored, 'resolution' => null, 'resolved_at' => null]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Action::make('deleteResolved')
                    ->label('Delete resolved')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Delete the resolved issues?')
                    ->modalDescription('Their occurrences go with them. An issue that happens again shows up as new.')
                    ->visible(fn (): bool => Issue::query()->where('status', IssueStatus::Resolved)->exists())
                    ->action(fn () => Issue::query()->where('status', IssueStatus::Resolved)->delete()),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('What to do')
                    ->icon(Heroicon::OutlinedLightBulb)
                    ->iconColor('warning')
                    ->extraAttributes(['class' => 'cv-settings-card cv-issue-hint'])
                    ->schema([
                        TextEntry::make('advice')
                            ->hiddenLabel()
                            ->state(fn (Issue $record): ?string => $record->advice()),
                        Actions::make(collect(range(0, self::MAX_FIX_BUTTONS - 1))->map(self::fixAction(...))->all()),
                    ]),
                Section::make('Summary')
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->extraAttributes(['class' => 'cv-settings-card'])
                    ->columns(['default' => 2, 'lg' => 4])
                    ->schema([
                        TextEntry::make('service')
                            ->html()
                            ->formatStateUsing(fn (Issue $record): HtmlString => self::serviceBadge($record->serviceData())),
                        TextEntry::make('kind')
                            ->formatStateUsing(fn (Issue $record): string => $record->type()?->label() ?? $record->kind),
                        TextEntry::make('subject')
                            ->label('About')
                            ->state(fn (Issue $record): ?string => $record->subjectLabel())
                            ->fontFamily('mono')
                            ->placeholder('The whole service'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (Issue $record): string => match (true) {
                                $record->resolution === IssueResolution::Recovered => 'Closed by itself',
                                default => $record->status->label(),
                            })
                            ->color(fn (IssueStatus $state): string => $state->color())
                            ->tooltip(fn (Issue $record): ?string => $record->resolution === IssueResolution::Recovered
                                ? "Closed when {$record->serviceData()->label} worked again."
                                : null),
                        TextEntry::make('occurrences_count')
                            ->label('Times it happened')
                            ->numeric(),
                        TextEntry::make('first_seen_at')
                            ->label('First seen')
                            ->since()
                            ->dateTimeTooltip(),
                        TextEntry::make('last_seen_at')
                            ->label('Last seen')
                            ->since()
                            ->dateTimeTooltip(),
                        TextEntry::make('resolved_at')
                            ->label('Resolved')
                            ->since()
                            ->dateTimeTooltip()
                            ->placeholder('Not yet'),
                        TextEntry::make('message')
                            ->label('What the service said last')
                            ->placeholder('Nothing')
                            ->columnSpanFull(),
                    ]),
                Livewire::make(IssueOccurrencesChart::class, fn (Issue $record): array => ['record' => $record])
                    ->key('issue-occurrences-chart'),
            ]);
    }

    /**
     * What one occurrence shows: when, who, the workspace and agent, what
     * the app was doing, the model, and what the service answered.
     *
     * @return list<Grid>
     */
    public static function occurrenceEntries(IssueOccurrence $occurrence): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextEntry::make('occurred_at')
                    ->label('When')
                    ->state($occurrence->occurred_at)
                    ->dateTime(),
                TextEntry::make('actor')
                    ->label('Who')
                    ->state($occurrence->actorLabel()),
                TextEntry::make('workspace')
                    ->label('Workspace')
                    ->state($occurrence->workspace?->name)
                    ->url($occurrence->workspace !== null ? WorkspaceResource::getUrl('view', ['record' => $occurrence->workspace]) : null)
                    ->color('primary')
                    ->placeholder('None'),
                TextEntry::make('agent')
                    ->label('Agent')
                    ->state($occurrence->agent?->name)
                    ->placeholder('None'),
                TextEntry::make('origin')
                    ->label('What the app was doing')
                    ->state($occurrence->originLabel())
                    ->tooltip($occurrence->origin_name),
                TextEntry::make('model')
                    ->label('Model')
                    ->state($occurrence->model)
                    ->fontFamily('mono')
                    ->placeholder('None'),
                TextEntry::make('http_status')
                    ->label('HTTP status')
                    ->state($occurrence->http_status)
                    ->placeholder('None'),
                TextEntry::make('endpoint')
                    ->label('Address called')
                    ->state($occurrence->endpoint)
                    ->fontFamily('mono')
                    ->copyable()
                    ->placeholder('None'),
                TextEntry::make('message')
                    ->label('What the service said')
                    ->state($occurrence->message)
                    ->placeholder('Nothing')
                    ->columnSpanFull(),
            ])->columnSpanFull(),
        ];
    }

    public static function resolveAction(): Action
    {
        return Action::make('resolve')
            ->label('Resolve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Issue $record): bool => $record->status !== IssueStatus::Resolved)
            ->action(fn (Issue $record) => $record->resolve());
    }

    public static function getRelations(): array
    {
        return [
            OccurrencesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIssues::route('/'),
            'view' => ViewIssue::route('/{record}'),
        ];
    }

    /**
     * Button $index of the issue's fix buttons ({@see IssueType::actions()}),
     * shown only when the issue has that many.
     */
    private static function fixAction(int $index): Action
    {
        $fix = fn (Issue $record): ?IssueActionData => $record->actions()[$index] ?? null;

        return Action::make("fix{$index}")
            ->label(fn (Issue $record): string => $fix($record)->label ?? '')
            ->icon(fn (Issue $record): Heroicon => $fix($record)?->external === true ? Heroicon::OutlinedArrowTopRightOnSquare : Heroicon::OutlinedCog6Tooth)
            ->color($index === 0 ? 'primary' : 'gray')
            ->url(fn (Issue $record): ?string => $fix($record)?->url, shouldOpenInNewTab: fn (Issue $record): bool => $fix($record)?->external === true)
            ->visible(fn (Issue $record): bool => $fix($record) instanceof IssueActionData);
    }

    /** The issue's title with its service's logo before it. */
    private static function titleWithLogo(Issue $issue): HtmlString
    {
        $mark = self::serviceMark($issue->serviceData());
        $title = e($issue->title());

        return new HtmlString("<span class=\"cv-issue-provider\">{$mark}<span class=\"cv-issue-provider__title\">{$title}</span></span>");
    }

    /** The service's logo and name. */
    private static function serviceBadge(IssueServiceData $service): HtmlString
    {
        $mark = self::serviceMark($service);
        $label = e($service->label);

        return new HtmlString("<span class=\"cv-issue-provider\">{$mark}<span>{$label}</span></span>");
    }

    /** The service's logo, or its icon when it has no logo of its own. */
    private static function serviceMark(IssueServiceData $service): string
    {
        if ($service->logo !== null) {
            $src = e($service->logo);

            return "<img src=\"{$src}\" alt=\"\" class=\"cv-issue-provider__logo\" />";
        }

        $icon = generate_icon_html($service->icon, attributes: new ComponentAttributeBag(['class' => 'cv-issue-provider__icon']))?->toHtml() ?? '';

        $tone = e("cv-tone-{$service->tone}");

        return "<span class=\"cv-issue-provider__mark {$tone}\">{$icon}</span>";
    }
}
