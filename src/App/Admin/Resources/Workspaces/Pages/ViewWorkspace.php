<?php

declare(strict_types=1);

namespace App\Admin\Resources\Workspaces\Pages;

use App\Admin\Resources\Workspaces\WorkspaceResource;
use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Models\Workspace;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * The admin panel's view of one workspace account. The only change it makes
 * is the workspace's monthly message limit.
 */
class ViewWorkspace extends ViewRecord
{
    #[\Override]
    protected static string $resource = WorkspaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('messageLimit')
                ->label('Set message limit')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->fillForm(fn (Workspace $record): array => ['monthly_limit' => $record->messageLimit?->monthly_limit])
                ->schema([
                    TextInput::make('monthly_limit')
                        ->label('Agent replies per month')
                        ->integer()
                        ->minValue(1)
                        ->helperText('Leave empty for no limit. The count of this month is kept.'),
                ])
                ->action(function (Workspace $record, array $data, SetMessageLimitAction $setLimit): void {
                    $limit = filled($data['monthly_limit'] ?? null) ? (int) $data['monthly_limit'] : null;

                    $setLimit->execute($record, $limit);
                    $record->load('messageLimit');

                    Notification::make()->success()->title($limit === null ? 'Message limit removed' : 'Message limit saved')->send();
                }),
        ];
    }
}
