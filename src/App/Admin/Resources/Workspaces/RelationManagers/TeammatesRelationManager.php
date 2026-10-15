<?php

declare(strict_types=1);

namespace App\Admin\Resources\Workspaces\RelationManagers;

use App\Identity\Workspace\Enums\WorkspaceRole;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Models\WorkspaceMembership;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The workspace's staff accounts on its admin page, owner first: role,
 * teams with the team role in each, last sign-in, status and two-step
 * sign-in. Read-only.
 */
class TeammatesRelationManager extends RelationManager
{
    #[\Override]
    protected static string $relationship = 'members';

    #[\Override]
    protected static ?string $title = 'Teammates';

    #[\Override]
    protected static string|BackedEnum|null $icon = Heroicon::OutlinedUsers;

    /** The admin panel is staff-only; the dashboard's policies do not apply. */
    #[\Override]
    protected static bool $shouldSkipAuthorization = true;

    /**
     * "Team · Role" lines of the accounts on the current page, read once.
     *
     * @var array<int, list<string>>|null
     */
    private ?array $teams = null;

    #[\Override]
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord instanceof Workspace ? (string) $ownerRecord->members()->count() : null;
    }

    #[\Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['users.name', 'users.email'])
                    ->sortable(),
                TextColumn::make('workspace_role')
                    ->label('Role')
                    ->badge()
                    ->state(fn (User $record): string => $this->roleOf($record) === WorkspaceRole::Owner ? 'Owner' : 'Teammate')
                    ->color(fn (string $state): string => $state === 'Owner' ? 'primary' : 'gray'),
                TextColumn::make('teams')
                    ->label('Teams')
                    ->state(fn (User $record): array => $this->teamsOf($record))
                    ->listWithLineBreaks()
                    ->placeholder('No team'),
                TextColumn::make('last_login_at')
                    ->label('Last sign-in')
                    ->since()
                    ->sortable()
                    ->placeholder('Never'),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (User $record): string => $record->isDeactivated() ? 'Deactivated' : 'Active')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'danger')
                    ->tooltip(fn (User $record): ?string => $record->deactivation_reason),
                IconColumn::make('two_step')
                    ->label('Two-step sign-in')
                    ->state(fn (User $record): bool => $record->usesTwoFactor())
                    ->boolean(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('workspace_user.role = ? DESC', [WorkspaceRole::Owner->value])
                ->orderBy('users.name'))
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->emptyStateHeading('No staff accounts');
    }

    private function roleOf(User $user): ?WorkspaceRole
    {
        $membership = $user->relationLoaded('pivot') ? $user->getRelation('pivot') : null;

        return $membership instanceof WorkspaceMembership ? $membership->role : null;
    }

    /**
     * The owner holds every permission in every team, so it has no team
     * roles of its own.
     *
     * @return list<string>
     */
    private function teamsOf(User $user): array
    {
        if ($this->roleOf($user) === WorkspaceRole::Owner) {
            return ['Every team, as owner'];
        }

        if ($this->teams === null) {
            $records = $this->getTableRecords();
            $userIds = collect($records instanceof Collection ? $records->all() : $records->items())
                ->map(fn (User $record): int => $record->id)
                ->values()
                ->all();

            $this->teams = DB::table('team_user')
                ->join('teams', 'teams.id', '=', 'team_user.team_id')
                ->join('roles', 'roles.id', '=', 'team_user.role_id')
                ->where('teams.workspace_id', $this->getOwnerRecord()->getKey())
                ->whereIn('team_user.user_id', $userIds)
                ->orderBy('teams.name')
                ->get(['team_user.user_id', 'teams.name as team', 'roles.name as role'])
                ->groupBy('user_id')
                ->map(fn (Collection $rows): array => $rows->map(fn (object $row): string => "{$row->team} · {$row->role}")->values()->all())
                ->all();
        }

        return $this->teams[$user->id] ?? [];
    }
}
